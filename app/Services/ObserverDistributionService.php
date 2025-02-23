<?php

namespace App\Services;

use App\Models\Observer;
use App\Models\Schedule;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class ObserverDistributionService
{
    public static function distributeObservers()
    {
        $dates = Schedule::orderBy('schedule_exam_date')
            ->pluck('schedule_exam_date')
            ->unique();

        foreach ($dates as $date) {
            self::processDate($date);
        }
    }

    private static function processDate(string $date)
    {
        Log::info("معالجة تاريخ: {$date}");

        $schedules = Schedule::with(['rooms.observers.user'])
            ->where('schedule_exam_date', $date)
            ->orderBy('schedule_time_slot')
            ->get();

        if ($schedules->isEmpty()) {
            return;
        }

        $eligibleUsers = self::getEligibleUsers($date);

        foreach ($schedules as $schedule) {
            foreach ($schedule->rooms as $room) {
                self::fillRoom($room, $eligibleUsers, $schedule, $date);
            }
        }
    }

    private static function getEligibleUsers(string $date): Collection
    {
        return User::whereHas('roles', fn ($q) => $q->whereIn('name', ['رئيس_قاعة', 'امين_سر', 'مراقب']))
            ->with(['observers' => fn ($q) => $q->whereHas('schedule', fn ($q) => $q->where('schedule_exam_date', $date))])
            ->get()
            ->filter(fn ($user) => $user->observers->count() < $user->getMaxObserversByAge())
            ->sortByDesc(fn ($user) => [
                $user->hasRole('رئيس_قاعة') ? 3 : 0,
                $user->hasRole('امين_سر') ? 2 : 0,
                $user->years_experience,
            ]);
    }

    private static function fillRoom($room, Collection &$eligibleUsers, $schedule, string $date)
    {
        $existing = $room->observers->groupBy(fn ($o) => $o->user->getRoleNames()->first());

        $required = [
            'رئيس_قاعة' => max(1 - $existing->get('رئيس_قاعة', collect())->count(), 0),
            'امين_سر' => max(($room->room_type === 'big' ? 2 : 1) - $existing->get('امين_سر', collect())->count(), 0),
            'مراقب' => max(($room->room_type === 'big' ? 8 : 4) - $existing->get('مراقب', collect())->count(), 0),
        ];

        foreach ($required as $role => $count) {
            $candidates = $eligibleUsers->filter(fn ($u) => $u->hasRole($role));

            foreach ($candidates as $key => $user) {
                if ($count <= 0) {
                    break;
                }

                if (! self::hasConflict($user, $schedule)) {
                    Observer::firstOrCreate([
                        'user_id' => $user->id,
                        'schedule_id' => $schedule->schedule_id,
                        'room_id' => $room->room_id,
                    ]);

                    $eligibleUsers->forget($key);
                    $count--;
                    Log::info("تم تعيين {$user->name} في {$date}");
                }
            }
        }
    }

    private static function hasConflict(User $user, Schedule $schedule): bool
    {
        return $user->schedules()
            ->where('schedule_exam_date', $schedule->schedule_exam_date)
            ->where('schedule_time_slot', $schedule->schedule_time_slot)
            ->exists();
    }
}
