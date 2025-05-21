<?php

// namespace App\Services;

// use App\Models\Observer;
// use App\Models\Room;
// use App\Models\Schedule;
// use App\Models\User;
// use Illuminate\Support\Collection;
// use Illuminate\Support\Facades\DB;

// class ObserverDistributionService
// {
//     protected static array $usedUserIds = [];

//     public static function distributeObservers(): void
//     {
//         Observer::truncate();
//         self::resetStaticState();
//         $schedules = self::getOrderedSchedulesWithRooms();

//         foreach ($schedules as $schedule) {
//             self::processScheduleRooms($schedule);
//         }
//     }

//     private static function resetStaticState(): void
//     {
//         self::$usedUserIds = [];
//     }

//     private static function getOrderedSchedulesWithRooms(): Collection
//     {
//         return Schedule::query()
//             ->with(['rooms' => function ($q) {
//                 $q->orderByRaw("CASE WHEN room_type = 'small' THEN 0 ELSE 1 END")
//                     ->orderBy('room_id');
//             }])
//             ->orderBy('schedule_exam_date')
//             ->orderBy('schedule_time_slot')
//             ->get();
//     }

//     private static function processScheduleRooms(Schedule $schedule): void
//     {
//         $sortedRooms = $schedule->rooms->sortBy([
//             fn ($a, $b) => $a->room_type === 'small' ? -1 : 1,
//             'room_id',
//         ]);

//         foreach ($sortedRooms as $room) {
//             self::allocateRoomResources($room, $schedule);
//             if (! self::isRoomFullyStaffed($room, $schedule)) {
//                 logger()->warning("⚠️ لم يتم تعيين العدد الكافي للقاعة {$room->room_name} في الجدول {$schedule->schedule_id}");
//             }
//         }
//         self::markUsedObserversForSchedule($schedule);
//     }

//     private static function allocateRoomResources(Room $room, Schedule $schedule): void
//     {
//         DB::transaction(function () use ($room, $schedule) {
//             self::processRoomAllocation($room, $schedule);
//             self::processRoomObservers($room, $schedule);
//         });
//     }

//     private static function processRoomAllocation(Room $room, Schedule $schedule): void
//     {
//         $roomSchedule = $room->schedules()
//             ->where('room_schedules.schedule_id', $schedule->schedule_id)
//             ->first();

//         if (! $roomSchedule || ! $roomSchedule->pivot || $roomSchedule->pivot->allocated_seats === 0) {
//             $sharedCount = $room->schedules()
//                 ->where('schedule_time_slot', $schedule->schedule_time_slot)
//                 ->where('schedule_exam_date', $schedule->schedule_exam_date)
//                 ->count();

//             $allocatedSeats = $sharedCount > 0
//                 ? (int) floor($room->capacity / $sharedCount)
//                 : $room->capacity;

//             $allocatedMonitors = self::getBaseMonitors($room);

//             $room->schedules()->syncWithoutDetaching([
//                 $schedule->schedule_id => [
//                     'allocated_seats' => $allocatedSeats,
//                     'allocated_monitors' => $allocatedMonitors,
//                 ],
//             ]);
//         }
//     }

//     private static function processRoomObservers(Room $room, Schedule $schedule): void
//     {
//         $requirements = [
//             'رئيس_قاعة' => 1,
//             'امين_سر' => $room->room_type === 'big' ? 2 : 1,
//             'مراقب' => self::getBaseMonitors($room),
//         ];

//         $existingCounts = $room->observers()
//             ->where('schedule_id', $schedule->schedule_id)
//             ->get()
//             ->groupBy(fn ($o) => $o->user->getRoleNames()->first())
//             ->map->count();

//         $eligibleUsers = self::getEligibleUsersForSchedule($schedule);

//         // تعيين رئيس القاعة أولاً
//         self::assignRoleWithPriority(
//             'رئيس_قاعة',
//             $requirements['رئيس_قاعة'],
//             $eligibleUsers,
//             $room,
//             $schedule
//         );

//         // تعيين أمين السر ثانياً
//         self::assignRoleWithPriority(
//             'امين_سر',
//             $requirements['امين_سر'],
//             $eligibleUsers,
//             $room,
//             $schedule
//         );

//         // تعيين المراقبين أخيراً
//         self::assignRoleWithPriority(
//             'مراقب',
//             $requirements['مراقب'],
//             $eligibleUsers,
//             $room,
//             $schedule,
//             true // السماح باستخدام أمين السر إذا لزم الأمر
//         );
//     }

//     private static function assignRoleWithPriority(
//         string $role,
//         int $needed,
//         Collection $eligibleUsers,
//         Room $room,
//         Schedule $schedule,
//         bool $allowSecretaryFallback = false
//     ): void {
//         $existing = $room->observers()
//             ->where('schedule_id', $schedule->schedule_id)
//             ->whereHas('user', fn ($q) => $q->whereHas('roles', fn ($q) => $q->where('name', $role)))
//             ->count();

//         $needed = max($needed - $existing, 0);
//         if ($needed === 0) {
//             return;
//         }

//         $candidates = $eligibleUsers->filter(fn (User $u) => $u->hasRole($role));

//         if ($allowSecretaryFallback && $role === 'مراقب' && $candidates->count() < $needed) {
//             $candidates = $eligibleUsers->filter(fn (User $u) => $u->hasRole('امين_سر'));
//         }

//         $available = $candidates
//             ->reject(fn (User $u) => in_array($u->id, self::$usedUserIds))
//             ->filter(fn (User $u) => ! self::hasConflict($u, $schedule, $room->id))
//             ->unique('id')
//             ->take($needed);

//         foreach ($available as $user) {
//             Observer::create([
//                 'user_id' => $user->id,
//                 'schedule_id' => $schedule->schedule_id,
//                 'room_id' => $room->room_id,
//             ]);

//             self::$usedUserIds[] = $user->id;
//             self::$usedUserIds = array_unique(self::$usedUserIds);
//         }
//     }

//     private static function getEligibleUsersForSchedule(Schedule $schedule): Collection
//     {
//         return User::whereHas('roles', fn ($q) => $q->whereIn('name', ['رئيس_قاعة', 'امين_سر', 'مراقب']))
//             ->whereNotIn('id', self::$usedUserIds)
//             ->with(['observers' => function ($q) use ($schedule) {
//                 $q->whereHas('schedule', fn ($q) => $q->where([
//                     ['schedule_exam_date', $schedule->schedule_exam_date],
//                     ['schedule_time_slot', $schedule->schedule_time_slot],
//                 ]));
//             }])
//             ->get()
//             ->sortByDesc(function (User $user) {
//                 return ($user->observers->count() === 0 ? 1000 : 0)
//                     + self::getRolePriority($user);
//             });
//     }

//     private static function markUsedObserversForSchedule(Schedule $schedule): void
//     {
//         $newUsedIds = Observer::where('schedule_id', $schedule->schedule_id)
//             ->pluck('user_id')
//             ->unique()
//             ->toArray();

//         self::$usedUserIds = array_unique(array_merge(self::$usedUserIds, $newUsedIds));
//     }

//     private static function isRoomFullyStaffed(Room $room, Schedule $schedule): bool
//     {
//         $required = [
//             'رئيس_قاعة' => 1,
//             'امين_سر' => $room->room_type === 'big' ? 2 : 1,
//             'مراقب' => self::getBaseMonitors($room),
//         ];

//         $current = $room->observers()
//             ->where('schedule_id', $schedule->schedule_id)
//             ->get()
//             ->groupBy(fn ($o) => $o->user->getRoleNames()->first())
//             ->map->count();

//         foreach ($required as $role => $count) {
//             if (($current[$role] ?? 0) < $count) {
//                 return false;
//             }
//         }

//         return true;
//     }

//     private static function hasConflict(User $user, Schedule $schedule, ?int $roomId = null): bool
//     {
//         return Observer::where('user_id', $user->id)
//             ->whereHas('schedule', fn ($q) => $q->where([
//                 ['schedule_exam_date', $schedule->schedule_exam_date],
//                 ['schedule_time_slot', $schedule->schedule_time_slot],
//             ]))
//             ->when($roomId, fn ($q) => $q->where('room_id', '!=', $roomId))
//             ->exists();
//     }

//     private static function getRolePriority(User $user): int
//     {
//         return match ($user->getRoleNames()->first()) {
//             'رئيس_قاعة' => 3,
//             'امين_سر' => 2,
//             default => 1
//         };
//     }

//     private static function getBaseMonitors(Room $room): int
//     {
//         return $room->room_type === 'big' ? 8 : 4;
//     }
// }

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
                $q->orderByRaw("CASE WHEN room_type = 'small' THEN 0 ELSE 1 END")
                    ->orderBy('room_id');
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
                Log::warning("⚠️ لم يتم تعيين العدد الكافي للقاعة {$room->room_name} في الجدول {$schedule->schedule_id}");
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

        self::assignRoleWithPriority(
            'رئيس_قاعة',
            $requirements['رئيس_قاعة'],
            $eligibleUsers,
            $room,
            $schedule
        );

        self::assignRoleWithPriority(
            'امين_سر',
            $requirements['امين_سر'],
            $eligibleUsers,
            $room,
            $schedule
        );

        self::assignRoleWithPriority(
            'مراقب',
            $requirements['مراقب'],
            $eligibleUsers,
            $room,
            $schedule,
            true
        );
    }

    private static function assignRoleWithPriority(
        string $role,
        int $needed,
        Collection $eligibleUsers,
        Room $room,
        Schedule $schedule,
        bool $allowSecretaryFallback = false
    ): void {
        $existing = $room->observers()
            ->where('schedule_id', $schedule->schedule_id)
            ->whereHas('user', fn ($q) => $q->whereHas('roles', fn ($q) => $q->where('name', $role)))
            ->count();

        $needed = max($needed - $existing, 0);
        if ($needed === 0) {
            return;
        }

        $candidates = $eligibleUsers->filter(fn (User $u) => $u->hasRole($role));

        if ($allowSecretaryFallback && $role === 'مراقب' && $candidates->count() < $needed) {
            $candidates = $eligibleUsers->filter(fn (User $u) => $u->hasRole('امين_سر'));
        }

        $available = $candidates
            ->reject(fn (User $u) => in_array($u->id, self::$usedUserIds))
            ->filter(fn (User $u) => $u->canTakeMoreObservers())
            ->filter(fn (User $u) => ! self::hasConflict($u, $schedule, $room->id))
            ->unique('id')
            ->take($needed);

        foreach ($available as $user) {
            if ($user->canTakeMoreObservers()) {
                Observer::create([
                    'user_id' => $user->id,
                    'schedule_id' => $schedule->schedule_id,
                    'room_id' => $room->room_id,
                ]);

                self::$usedUserIds[] = $user->id;
                self::$usedUserIds = array_unique(self::$usedUserIds);

                if (! $user->canTakeMoreObservers()) {
                    Log::info("User {$user->id} has reached maximum observers limit: {$user->max_observers}");
                }
            }
        }

        if ($available->count() < $needed) {
            Log::warning("Insufficient {$role} for Room {$room->room_id}, Needed: {$needed}, Found: {$available->count()}");
        }
    }

    private static function getEligibleUsersForSchedule(Schedule $schedule): Collection
    {
        return User::query()
            ->whereHas('roles', function ($q) {
                $q->whereIn('name', ['رئيس_قاعة', 'امين_سر', 'مراقب']);
            })
            ->withCount('observers')
            ->groupBy('users.id') // إضافة groupBy
            ->havingRaw('users.max_observers > observers_count OR users.max_observers = 0')
            ->orderByRaw('(users.max_observers - observers_count) DESC')
            ->orderByDesc('created_at')
            ->get()
            ->sortByDesc(function (User $user) {
                return ($user->observers->count() === 0 ? 1000 : 0)
                    + self::getRolePriority($user);
            });
    }

    private static function markUsedObserversForSchedule(Schedule $schedule): void
    {
        $newUsedIds = Observer::where('schedule_id', $schedule->schedule_id)
            ->with(['user' => function ($q) {
                $q->withCount('observers');
            }])
            ->get()
            ->filter(fn ($observer) => $observer->user->max_observers > 0 &&
                $observer->user->observers_count >= $observer->user->max_observers)
            ->pluck('user_id')
            ->toArray();

        self::$usedUserIds = array_unique(array_merge(self::$usedUserIds, $newUsedIds));
    }

    private static function isRoomFullyStaffed(Room $room, Schedule $schedule): bool
    {
        $required = [
            'رئيس_قاعة' => 1,
            'امين_سر' => $room->room_type === 'big' ? 2 : 1,
            'مراقب' => self::getBaseMonitors($room),
        ];

        $current = $room->observers()
            ->where('schedule_id', $schedule->schedule_id)
            ->get()
            ->groupBy(fn ($o) => $o->user->getRoleNames()->first())
            ->map->count();

        foreach ($required as $role => $count) {
            if (($current[$role] ?? 0) < $count) {
                return false;
            }
        }

        return true;
    }

    private static function hasConflict(User $user, Schedule $schedule, ?int $roomId = null): bool
    {
        return Observer::where('user_id', $user->id)
            ->whereHas('schedule', fn ($q) => $q->where([
                ['schedule_exam_date', $schedule->schedule_exam_date],
                ['schedule_time_slot', $schedule->schedule_time_slot],
            ]))
            ->when($roomId, fn ($q) => $q->where('room_id', '!=', $roomId))
            ->exists();
    }

    private static function getRolePriority(User $user): int
    {
        return match ($user->getRoleNames()->first()) {
            'رئيس_قاعة' => 3,
            'امين_سر' => 2,
            default => 1
        };
    }

    private static function getBaseMonitors(Room $room): int
    {
        return $room->room_type === 'big' ? 8 : 4;
    }
}
