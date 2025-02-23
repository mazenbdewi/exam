<?php

namespace App\Services;

use App\Models\Schedule;
use App\Models\User;
use InvalidArgumentException;

class ObserverDistributionService
{
    protected static array $usedUserIds = [];

    public static function distributeObservers($schedules, $eligibleUsers)
    {
        if (! $schedules || ! $eligibleUsers) {
            throw new InvalidArgumentException('يجب تمرير كل من الجداول والمستخدمين المؤهلين إلى الدالة.');
        }

        self::$usedUserIds = [];

        foreach ($schedules as $schedule) {
            $availableUsers = $eligibleUsers->reject(fn ($user) => self::hasConflict($user, $schedule));

            foreach ($schedule->rooms as $room) {
                if ($availableUsers->isNotEmpty()) {
                    $user = $availableUsers->shift();
                    self::assignObserver($user, $schedule, $room);
                }
            }
        }
    }

    protected static function assignObserver(User $user, Schedule $schedule, $room)
    {
        // التأكد من أن العلاقة هي belongsToMany لاستخدام attach
        if (method_exists($schedule->observers(), 'attach')) {
            $schedule->observers()->attach($user->id, ['room_id' => $room->room_id]);
            self::$usedUserIds[] = $user->id; // تسجيل المستخدم لهذا اليوم فقط
        } else {
            throw new InvalidArgumentException('العلاقة observers يجب أن تكون من نوع BelongsToMany لاستخدام attach.');
        }
    }

    protected static function hasConflict(User $user, Schedule $schedule)
    {
        return $user->schedules()->where('schedule_exam_date', $schedule->schedule_exam_date)->exists();
    }
}
