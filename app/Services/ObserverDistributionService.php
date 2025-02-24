<?php

namespace App\Services;

use App\Models\Observer;
use App\Models\Schedule;
use App\Models\User;
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
        Log::info("====== بدء التوزيع لتاريخ: {$date} ======");

        $schedules = Schedule::with(['rooms.observers.user'])
            ->where('schedule_exam_date', $date)
            ->orderBy('schedule_time_slot')
            ->get();

        foreach ($schedules as $schedule) {
            foreach ($schedule->rooms as $room) {
                self::fillRoom($room, $schedule, $date);
            }
        }
    }

    private static function fillRoom($room, $schedule, string $date)
    {
        $existing = $room->observers->groupBy(fn ($o) => $o->user->getRoleNames()->first());

        $required = [
            'رئيس_قاعة' => max(1 - $existing->get('رئيس_قاعة', collect())->count(), 0),
            'امين_سر' => max(($room->room_type === 'big' ? 2 : 1) - $existing->get('امين_سر', collect())->count(), 0),
            'مراقب' => max(($room->room_type === 'big' ? 8 : 4) - $existing->get('مراقب', collect())->count(), 0),
        ];

        foreach ($required as $role => $count) {
            for ($i = 0; $i < $count; $i++) {
                $user = self::findAvailableUser($role, $schedule, $date);
                if ($user) {
                    Observer::firstOrCreate([
                        'user_id' => $user->id,
                        'schedule_id' => $schedule->schedule_id,
                        'room_id' => $room->room_id,
                    ]);
                    Log::info("تم تعيين {$user->name} كـ {$role}");
                } else {
                    Log::warning("لا يوجد مراقبون متاحون للدور: {$role}");
                }
            }
        }
    }

    private static function findAvailableUser(string $role, Schedule $schedule, string $date): ?User
    {
        return User::whereHas('roles', fn ($q) => $q->where('name', $role))
            ->whereDoesntHave('observers', function ($q) use ($schedule) {
                $q->whereHas('schedule', fn ($q) => $q->where('schedule_exam_date', $schedule->schedule_exam_date)
                    ->where('schedule_time_slot', $schedule->schedule_time_slot)
                );
            })
            ->get()
            ->filter(fn ($user) => $user->observers()->count() < $user->getMaxObservers()['total'] &&
                $user->observers()->whereHas('schedule', fn ($q) => $q->where('schedule_exam_date', $date)
                )->count() < $user->getMaxObservers()['daily']
            )
            ->sortBy([
                fn ($a, $b) => $a->observers()->count() - $b->observers()->count(),
                fn ($a, $b) => $b->years_experience - $a->years_experience,
            ])
            ->first();
    }
}
