<?php

namespace App\Services;

use App\Models\Observer;
use App\Models\Room;
use App\Models\Schedule;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ObserverDistributionService
{
    public static function distributeObservers(): void
    {
        self::startAutoContext();
        Observer::truncate();

        $schedules = self::getOrderedSchedulesWithRooms();

        foreach ($schedules as $schedule) {
            self::processSchedule($schedule);
        }

        self::endAutoContext();
    }

    private static function startAutoContext(): void
    {
        app()->instance('auto_context', true);
    }

    private static function endAutoContext(): void
    {
        app()->forgetInstance('auto_context');
    }

    private static function getOrderedSchedulesWithRooms(): Collection
    {
        return Schedule::with(['rooms' => function ($query) {
            $query->orderBy('room_type', 'desc')->orderBy('room_id');
        }])
            ->orderBy('schedule_exam_date')
            ->orderBy('schedule_time_slot')
            ->get();
    }

    private static function processSchedule(Schedule $schedule): void
    {
        $rooms = $schedule->rooms->sortBy([
            fn ($a, $b) => $a->room_type === 'small' ? -1 : 1,
            'room_id',
        ]);

        foreach ($rooms as $room) {
            DB::transaction(function () use ($room, $schedule) {
                self::allocateResources($room, $schedule);
                self::assignObservers($room, $schedule);
            });

            if (! self::validateRoomAssignment($room, $schedule)) {
                Log::error("فشل تعيين مراقبين للقاعة {$room->room_name}");
            }
        }
    }

    private static function allocateResources(Room $room, Schedule $schedule): void
    {
        $pivot = $room->schedules()->find($schedule->schedule_id)?->pivot;

        if (! $pivot || $pivot->allocated_seats === 0) {
            $sharedCount = $room->schedules()
                ->where('schedule_exam_date', $schedule->schedule_exam_date)
                ->where('schedule_time_slot', $schedule->schedule_time_slot)
                ->count();

            $allocatedSeats = $sharedCount > 0
                ? (int) floor($room->capacity / $sharedCount)
                : $room->capacity;

            $room->schedules()->syncWithoutDetaching([
                $schedule->schedule_id => [
                    'allocated_seats' => $allocatedSeats,
                    'allocated_monitors' => self::calculateMonitors($room),
                ],
            ]);
        }
    }

    private static function assignObservers(Room $room, Schedule $schedule): void
    {
        $requiredRoles = self::getRequiredRoles($room);
        $allEligibleUsers = self::getEligibleUsers($schedule);

        foreach ($requiredRoles as $role => $required) {
            $assigned = 0;

            // تحديد النصف من تاريخ الجدول الزمني
            $scheduleHalf = self::getHalfFromDate($schedule->schedule_exam_date);

            $candidates = $allEligibleUsers->filter(function ($user) use ($role, $schedule, $scheduleHalf) {
                return $user->hasRole($role) &&
                       self::isAvailable($user, $schedule) &&
                       self::isUserAvailableForHalf($user, $scheduleHalf); // التصفية حسب النصف
            });

            if ($role === 'مراقب' && $candidates->count() < $required) {
                // استخدم الأمناء كمراقبين احتياط (مع مراعاة النصف)
                $candidates = $allEligibleUsers->filter(function ($user) use ($schedule, $scheduleHalf) {
                    return ($user->hasRole('امين_سر') || $user->hasRole('مراقب')) &&
                           self::isAvailable($user, $schedule) &&
                           self::isUserAvailableForHalf($user, $scheduleHalf); // التصفية حسب النصف
                });
            }

            foreach ($candidates as $user) {
                if ($assigned >= $required) {
                    break;
                }

                if ($user->canTakeMoreObservers()) {
                    self::createAssignment($user, $room, $schedule);
                    $assigned++;
                }
            }

            if ($assigned < $required) {
                Log::warning("نقص في {$role} للقاعة {$room->room_id} المطلوب: {$required} المتاح: {$assigned}");
            }
        }
    }

    // دالة جديدة: تحديد النصف من التاريخ
    private static function getHalfFromDate(string $date): string
    {
        $day = (int) Carbon::parse($date)->format('d');

        return ($day >= 1 && $day <= 15) ? 'first_half' : 'second_half';
    }

    // دالة جديدة: التحقق من توفر المستخدم للنصف المطلوب
    private static function isUserAvailableForHalf(User $user, string $scheduleHalf): bool
    {
        // إذا كان المستخدم غير محدد أو متوافق مع النصف
        return $user->month_part === 'any' || $user->month_part === $scheduleHalf;
    }

    private static function getRequiredRoles(Room $room): array
    {
        return [
            'رئيس_قاعة' => 1,
            'امين_سر' => $room->room_type === 'big' ? 2 : 1,
            'مراقب' => $room->room_type === 'big' ? 8 : 4,
        ];
    }

    private static function getEligibleUsers(Schedule $schedule): Collection
    {
        // تحديد النصف من تاريخ الجدول الزمني
        $scheduleHalf = self::getHalfFromDate($schedule->schedule_exam_date);

        return User::whereHas('roles', fn ($q) => $q->whereIn('name', ['رئيس_قاعة', 'امين_سر', 'مراقب']))
            ->where(function ($query) use ($scheduleHalf) {
                $query->where('month_part', $scheduleHalf)
                    ->orWhere('month_part', 'any');
            })
            ->withCount(['observers as total_assigned' => fn ($q) => $q])
            ->get()
            ->filter(fn ($user) => $user->canTakeMoreObservers() &&
                ! Observer::where('user_id', $user->id)
                    ->whereHas('schedule', fn ($q) => $q->where('schedule_exam_date', $schedule->schedule_exam_date)
                        ->where('schedule_time_slot', $schedule->schedule_time_slot))
                    ->exists()
            )
            ->sortBy(fn ($u) => $u->total_assigned);
    }

    private static function isAvailable(User $user, Schedule $schedule): bool
    {
        return ! Observer::where('user_id', $user->id)
            ->whereHas('schedule', fn ($q) => $q
                ->where('schedule_exam_date', $schedule->schedule_exam_date)
                ->where('schedule_time_slot', $schedule->schedule_time_slot))
            ->exists();
    }

    private static function createAssignment(User $user, Room $room, Schedule $schedule): void
    {
        Observer::create([
            'user_id' => $user->id,
            'room_id' => $room->room_id,
            'schedule_id' => $schedule->schedule_id,
        ]);

        Log::info("تم تعيين {$user->name} كمراقب في القاعة {$room->room_id}");
    }

    private static function validateRoomAssignment(Room $room, Schedule $schedule): bool
    {
        $counts = $room->observers()
            ->where('schedule_id', $schedule->schedule_id)
            ->with('user.roles')
            ->get()
            ->groupBy(fn ($o) => $o->user->getRoleNames()->first())
            ->map->count();

        return collect(self::getRequiredRoles($room))
            ->every(fn ($req, $role) => ($counts[$role] ?? 0) >= $req);
    }

    private static function calculateMonitors(Room $room): int
    {
        return $room->room_type === 'big' ? 8 : 4;
    }
}
