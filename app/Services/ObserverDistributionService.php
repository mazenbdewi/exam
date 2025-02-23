<?php

namespace App\Services;

use App\Models\Observer;
use App\Models\Schedule;
use App\Models\User;

class ObserverDistributionService
{
    protected static array $usedUserIds = [];

    public static function distributeObservers($schedules, $eligibleUsers)
    {
        self::$usedUserIds = [];

        foreach ($schedules as $schedule) {
            $availableUsers = $eligibleUsers->reject(fn ($user) => self::hasConflict($user, $schedule));

            foreach ($schedule->rooms as $room) {
                if ($availableUsers->isNotEmpty()) {
                    $user = $availableUsers->shift(); // اختيار أول مستخدم متاح
                    self::assignObserver($user, $schedule, $room);
                }
            }
        }
    }

    protected static function assignObserver(User $user, Schedule $schedule, $room)
    {
        Observer::create([
            'user_id' => $user->id,
            'schedule_id' => $schedule->schedule_id,
            'room_id' => $room->room_id,
        ]);

        self::$usedUserIds[] = $user->id;
    }

    protected static function hasConflict(User $user, Schedule $schedule): bool
    {
        return $user->schedules()->where('schedule_exam_date', $schedule->schedule_exam_date)->exists();
    }
}
