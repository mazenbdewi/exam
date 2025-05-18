<?php

namespace App\Services;

use App\Models\Observer;
use App\Models\Room;
use App\Models\Schedule;
use App\Models\User;
use Illuminate\Support\Collection;

class ObserverDistributionService
{
    protected static array $usedUserIds = [];

    protected static array $scheduleQueue = [];

    public static function distributeObservers(): void
    {
        Observer::truncate();
        self::resetStaticState();
        self::prepareScheduleQueue();
        self::processSchedulesInOrder();
    }

    private static function resetStaticState(): void
    {
        self::$usedUserIds = [];
        self::$scheduleQueue = [];
    }

    // private static function prepareScheduleQueue(): void
    // {
    //     self::$scheduleQueue = Schedule::with([
    //         'rooms' => function ($q) {
    //             $q->with(['observers']); // فقط جلب المراقبين بدون soft delete
    //         },
    //     ])
    //         ->orderBy('schedule_exam_date')
    //         ->orderBy('schedule_time_slot')
    //         ->get()
    //         ->all();
    // }

    private static function processSchedulesInOrder(): void
    {
        foreach (self::$scheduleQueue as $schedule) {
            self::processSchedule($schedule);
            self::markUsedObservers($schedule);
        }
    }

    // private static function processSchedule(Schedule $schedule): void
    // {
    //     $schedule->load([
    //         'rooms.observers.user.roles',
    //         'rooms.schedules' => fn ($q) => $q->orderBy('schedule_exam_date'),
    //     ]);

    //     $schedule->rooms->each(function (Room $room) use ($schedule) {
    //         self::processRoomAllocation($room, $schedule);
    //         self::processRoomObservers($room, $schedule);
    //     });
    // }

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

            $allocatedMonitors = $sharedCount > 0
                ? (int) ceil(self::getBaseMonitors($room) / $sharedCount)
                : self::getBaseMonitors($room);

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
        $roomSchedule = $room->schedules()
            ->where('room_schedules.schedule_id', $schedule->schedule_id)
            ->first();

        $requirements = [
            'رئيس_قاعة' => 1,
            'امين_سر' => $room->room_type === 'big' ? 2 : 1,
            'مراقب' => $roomSchedule->pivot->allocated_monitors ?? 0,
        ];

        $existingCounts = $room->observers()
            ->where('schedule_id', $schedule->schedule_id)
            ->get()
            ->groupBy(fn ($o) => $o->user->getRoleNames()->first())
            ->map->count();

        $eligibleUsers = self::getEligibleUsers($schedule);

        foreach ($requirements as $role => $required) {
            $needed = max($required - ($existingCounts[$role] ?? 0), 0);
            self::assignObservers($role, $needed, $eligibleUsers, $room, $schedule);
        }
    }

    // private static function getEligibleUsers(Schedule $schedule): Collection
    // {
    //     return User::whereHas('roles', fn ($q) => $q->whereIn('name', ['مراقب', 'امين_سر', 'رئيس_قاعة']))
    //         ->whereNotIn('id', self::$usedUserIds)
    //         ->with(['observers' => fn ($q) => $q->whereHas('schedule', fn ($q) => $q->where([
    //             ['schedule_exam_date', $schedule->schedule_exam_date],
    //             ['schedule_time_slot', $schedule->schedule_time_slot],
    //         ]))])
    //         ->get()
    //         ->filter(fn (User $user) => $user->observers->count() < $user->getMaxObserversByAge())
    //         ->sortByDesc(fn (User $user) => self::getRolePriority($user));
    // }

    private static function prepareScheduleQueue(): void
    {
        self::$scheduleQueue = Schedule::query()
            ->with(['rooms' => function ($q) {
                $q->orderBy('room_id'); // إضافة ترتيب القاعات
            }])
            ->orderBy('schedule_exam_date')
            ->orderBy('schedule_time_slot')
            ->get()
            ->all();
    }

    private static function processSchedule(Schedule $schedule): void
    {
        $schedule->load([
            'rooms' => function ($q) {
                $q->orderBy('room_id'); // ترتيب القاعات داخل الجدول الزمني
            },
            'rooms.observers.user.roles',
        ]);

        // معالجة القاعات بالترتيب بعد الفرز
        $schedule->rooms
            ->sortBy('room_id')
            ->each(function (Room $room) use ($schedule) {
                self::processRoomAllocation($room, $schedule);
                self::processRoomObservers($room, $schedule);
            });
    }

    private static function getEligibleUsers(Schedule $schedule): Collection
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

    private static function assignObservers(
        string $role,
        int $needed,
        Collection $eligibleUsers,
        Room $room,
        Schedule $schedule
    ): void {
        $eligibleUsers->filter(fn (User $u) => $u->hasRole($role))
            ->take($needed)
            ->each(function (User $user) use ($room, $schedule) {
                // التحقق من التعارضات بشكل أكثر دقة
                if (! self::hasConflict($user, $schedule) && ! in_array($user->id, self::$usedUserIds)) {
                    Observer::firstOrCreate([
                        'user_id' => $user->id,
                        'schedule_id' => $schedule->schedule_id,
                        'room_id' => $room->room_id,
                    ]);
                    self::$usedUserIds[] = $user->id;
                }
            });
    }

    private static function markUsedObservers(Schedule $schedule): void
    {
        $newUsedIds = Observer::whereHas('schedule', fn ($q) => $q->where([
            ['schedule_exam_date', $schedule->schedule_exam_date],
            ['schedule_time_slot', $schedule->schedule_time_slot],
        ]))->pluck('user_id')->unique();

        self::$usedUserIds = array_unique(
            array_merge(self::$usedUserIds, $newUsedIds->toArray())
        );
    }

    private static function hasConflict(User $user, Schedule $schedule): bool
    {
        return Observer::where('user_id', $user->id)
            ->whereHas('schedule', fn ($q) => $q->where([
                ['schedule_exam_date', $schedule->schedule_exam_date],
                ['schedule_time_slot', $schedule->schedule_time_slot],
            ]))->exists();
    }

    // الدوال المساعدة
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
