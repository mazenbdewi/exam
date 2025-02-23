<?php

namespace App\Services;

use App\Models\Schedule;
use App\Models\User;
use Illuminate\Support\Collection;

class ObserverDistributionService
{
    protected static array $usedUserIds = [];

    public static function distributeObservers()
    {
        $schedulesGroupedByDate = Schedule::with('rooms')->get()->groupBy('schedule_exam_date');
        $originalEligibleUsers = User::whereHas('roles', function ($query) {
            $query->where('name', 'مراقب');
        })->get();

        foreach ($schedulesGroupedByDate as $examDate => $schedules) {
            $eligibleUsers = collect($originalEligibleUsers->all()); // إعادة تهيئة قائمة المستخدمين لكل يوم جديد

            foreach ($schedules as $schedule) {
                foreach ($schedule->rooms as $room) {
                    $observer = self::findEligibleObserver($eligibleUsers, $schedule);
                    if ($observer) {
                        self::assignObserver($observer, $schedule, $room);
                    }
                }
            }
        }
    }

    protected static function findEligibleObserver(Collection $eligibleUsers, Schedule $schedule)
    {
        return $eligibleUsers->first(function ($user) use ($schedule) {
            return ! in_array($user->id, self::$usedUserIds) && ! self::hasConflict($user, $schedule);
        });
    }

    protected static function assignObserver(User $user, Schedule $schedule, $room)
    {
        // تعيين المراقب للقائمة
        $schedule->observers()->attach($user->id, ['room_id' => $room->room_id]);
        self::$usedUserIds[] = $user->id; // تسجيل المستخدم لهذا اليوم فقط
    }

    protected static function hasConflict(User $user, Schedule $schedule)
    {
        return $user->schedules()->where('schedule_exam_date', $schedule->schedule_exam_date)->exists();
    }
}
