<?php

namespace App\Services;

use App\Models\Observer;
use App\Models\Room;
use App\Models\Schedule;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ObserverDistributionService
{
    protected static array $usedUserIds = [];

    public static function distributeObservers(): void
    {
        Observer::truncate();
        self::resetStaticState();
        $schedules = self::getOrderedSchedulesWithRooms();

        foreach ($schedules as $schedule) {
            self::processSchedule($schedule);
        }
    }

    private static function resetStaticState(): void
    {
        self::$usedUserIds = [];
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
        $eligibleUsers = self::getEligibleUsers($schedule);

        foreach ($requiredRoles as $role => $required) {
            self::fillRole($role, $required, $eligibleUsers, $room, $schedule);
        }
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
        return User::whereHas('roles', fn ($q) => $q->whereIn('name', ['رئيس_قاعة', 'امين_سر', 'مراقب']))
            ->withCount(['observers' => fn ($q) => $q->where('schedule_id', $schedule->schedule_id)])
            ->whereNotIn('id', self::$usedUserIds)
            ->orderByDesc('max_observers')
            ->orderBy('observers_count')
            ->get()
            ->filter(fn ($user) => $user->canTakeMoreObservers());
    }

    private static function fillRole(
        string $role,
        int $required,
        Collection $users,
        Room $room,
        Schedule $schedule
    ): void {
        $candidates = $users->filter(fn ($u) => $u->hasRole($role));

        if ($role === 'مراقب' && $candidates->count() < $required) {
            $candidates = $users->filter(fn ($u) => $u->hasRole('امين_سر'));
        }

        $assigned = 0;
        foreach ($candidates as $user) {
            if ($assigned >= $required) {
                break;
            }
            if (self::isAvailable($user, $schedule, $room)) {
                self::createAssignment($user, $room, $schedule);
                $assigned++;
            }
        }

        if ($assigned < $required) {
            Log::warning("نقص في {$role} للقاعة {$room->room_id} المطلوب: {$required} المتاح: {$assigned}");
        }
    }

    private static function isAvailable(User $user, Schedule $schedule, Room $room): bool
    {
        return ! Observer::where('user_id', $user->id)
            ->whereHas('schedule', fn ($q) => $q->where([
                ['schedule_exam_date', $schedule->schedule_exam_date],
                ['schedule_time_slot', $schedule->schedule_time_slot],
            ]))
            ->exists();
    }

    private static function createAssignment(User $user, Room $room, Schedule $schedule): void
    {
        Observer::create([
            'user_id' => $user->id,
            'room_id' => $room->room_id,
            'schedule_id' => $schedule->schedule_id,
        ]);

        self::$usedUserIds[] = $user->id;
        self::$usedUserIds = array_unique(self::$usedUserIds);

        if (! $user->canTakeMoreObservers()) {
            Log::info("المراقب {$user->name} وصل للحد الأقصى ({$user->max_observers})");
        }
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
