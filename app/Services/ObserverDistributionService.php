<?php

namespace App\Services;

use App\Models\Observer;
use App\Models\Room;
use App\Models\Schedule;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ObserverDistributionService
{
    protected static array $usedUserIds = [];

    public static function distributeObservers(): void
    {
        Observer::truncate();
        self::resetStaticState();
        $schedules = self::getOrderedSchedulesWithRooms();

        foreach ($schedules as $schedule) {
            self::processScheduleRooms($schedule);
        }
    }

    private static function resetStaticState(): void
    {
        self::$usedUserIds = [];
    }

    private static function getOrderedSchedulesWithRooms(): Collection
    {
        return Schedule::query()
            ->with(['rooms' => function ($q) {
                $q->orderBy('room_type')->orderBy('room_id');
            }])
            ->orderBy('schedule_exam_date')
            ->orderBy('schedule_time_slot')
            ->get();
    }

    private static function processScheduleRooms(Schedule $schedule): void
    {
        $sortedRooms = $schedule->rooms->sortBy([
            fn ($a, $b) => $a->room_type === 'small' ? -1 : 1,
            'room_id',
        ]);

        foreach ($sortedRooms as $room) {
            self::allocateRoomResources($room, $schedule);
            if (! self::isRoomFullyStaffed($room, $schedule)) {
                logger()->warning("⚠️ لم يتم تعيين العدد الكافي من الموظفين للقاعة {$room->room_name} في الجدول رقم {$schedule->schedule_id}");
            }
        }
        self::markUsedObserversForSchedule($schedule);
    }

    private static function allocateRoomResources(Room $room, Schedule $schedule): void
    {
        DB::transaction(function () use ($room, $schedule) {
            self::processRoomAllocation($room, $schedule);
            self::processRoomObservers($room, $schedule);
        });
    }

    private static function processRoomAllocation(Room $room, Schedule $schedule): void
    {
        $roomSchedule = $room->schedules()
            ->where('room_schedules.schedule_id', $schedule->schedule_id)
            ->first();

        if (! $roomSchedule || ! $roomSchedule->pivot || $roomSchedule->pivot->allocated_seats === 0) {
            $sharedCount = $room->schedules()
                ->where('schedule_time_slot', $schedule->schedule_time_slot)
                ->where('schedule_exam_date', $schedule->schedule_exam_date)
                ->count();

            $allocatedSeats = $sharedCount > 0
                ? (int) floor($room->capacity / $sharedCount)
                : $room->capacity;

            $allocatedMonitors = self::getBaseMonitors($room);

            $room->schedules()->syncWithoutDetaching([
                $schedule->schedule_id => [
                    'allocated_seats' => $allocatedSeats,
                    'allocated_monitors' => $allocatedMonitors,
                ],
            ]);
        }
    }

    private static function processRoomObservers(Room $room, Schedule $schedule): void
    {
        $requirements = [
            'رئيس_قاعة' => 1,
            'امين_سر' => $room->room_type === 'big' ? 2 : 1,
            'مراقب' => self::getBaseMonitors($room),
        ];

        $existingCounts = $room->observers()
            ->where('schedule_id', $schedule->schedule_id)
            ->get()
            ->groupBy(fn ($o) => $o->user->getRoleNames()->first())
            ->map->count();

        $eligibleUsers = self::getEligibleUsersForSchedule($schedule);

        foreach ($requirements as $role => $required) {
            $needed = max($required - ($existingCounts[$role] ?? 0), 0);
            if ($needed > 0) {
                self::assignObserversToRoom($role, $needed, $eligibleUsers, $room, $schedule);
                self::markUsedObserversForSchedule($schedule);
            }
        }
    }

    private static function assignObserversToRoom(
        string $role,
        int $needed,
        Collection $eligibleUsers,
        Room $room,
        Schedule $schedule
    ): void {
        $candidates = $eligibleUsers->filter(fn (User $u) => $u->hasRole($role));

        if ($candidates->count() < $needed && $role === 'مراقب') {
            $candidates = $eligibleUsers->filter(fn (User $u) => $u->hasRole('امين_سر'));
        }

        $availableCandidates = $candidates->filter(fn (User $user) => ! self::hasConflict($user, $schedule, $room->id))
            ->unique('id');

        $selected = $availableCandidates->take($needed);

        foreach ($selected as $user) {
            Observer::create([
                'user_id' => $user->id,
                'schedule_id' => $schedule->schedule_id,
                'room_id' => $room->room_id,
            ]);
        }
    }

    private static function getEligibleUsersForSchedule(Schedule $schedule): Collection
    {
        return User::whereHas('roles', fn ($q) => $q->whereIn('name', ['مراقب', 'امين_سر', 'رئيس_قاعة']))
            ->whereNotIn('id', self::$usedUserIds)
            ->with(['observers' => function ($q) use ($schedule) {
                $q->whereHas('schedule', fn ($q) => $q->where([
                    ['schedule_exam_date', $schedule->schedule_exam_date],
                    ['schedule_time_slot', $schedule->schedule_time_slot],
                ]));
            }])
            ->get()
            ->sortByDesc(function (User $user) {
                return ($user->observers->count() === 0 ? 1000 : 0)
                    + self::getRolePriority($user);
            });
    }

    private static function markUsedObserversForSchedule(Schedule $schedule): void
    {
        $newUsedIds = Observer::where('schedule_id', $schedule->schedule_id)
            ->pluck('user_id')
            ->unique()
            ->toArray();

        self::$usedUserIds = array_unique(array_merge(self::$usedUserIds, $newUsedIds));
    }

    private static function isRoomFullyStaffed(Room $room, Schedule $schedule): bool
    {
        $requirements = [
            'رئيس_قاعة' => 1,
            'امين_سر' => $room->room_type === 'big' ? 2 : 1,
            'مراقب' => self::getBaseMonitors($room),
        ];

        $existingCounts = $room->observers()
            ->where('schedule_id', $schedule->schedule_id)
            ->get()
            ->groupBy(fn ($o) => $o->user->getRoleNames()->first())
            ->map->count();

        foreach ($requirements as $role => $count) {
            if (($existingCounts[$role] ?? 0) < $count) {
                return false;
            }
        }

        return true;
    }

    private static function hasConflict(User $user, Schedule $schedule, ?int $currentRoomId = null): bool
    {
        return Observer::query()
            ->where('user_id', $user->id)
            ->whereHas('schedule', function ($q) use ($schedule) {
                $q->where('schedule_exam_date', $schedule->schedule_exam_date)
                    ->where('schedule_time_slot', $schedule->schedule_time_slot);
            })
            ->when($currentRoomId !== null, function ($q) use ($currentRoomId) {
                $q->where('room_id', '!=', $currentRoomId);
            })
            ->exists();
    }

    private static function getRolePriority(User $user): int
    {
        return match (true) {
            $user->hasRole('رئيس_قاعة') => 3,
            $user->hasRole('امين_سر') => 2,
            default => 1,
        };
    }

    private static function getBaseMonitors(Room $room): int
    {
        return $room->room_type === 'big' ? 8 : 4;
    }
}
