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
        // الحصول على جميع التواريخ مرتبة ترتيبًا زمنيًا
        $dates = Schedule::orderBy('schedule_exam_date')
            ->pluck('schedule_exam_date')
            ->unique()
            ->values();

        foreach ($dates as $date) {
            self::processDate($date);
        }
    }

    private static function processDate(string $date)
    {
        Log::info("======== بدء معالجة تاريخ: {$date} ========");

        // استرجاع جميع الجداول مع القاعات لهذا التاريخ
        $schedules = Schedule::with(['rooms.observers.user'])
            ->where('schedule_exam_date', $date)
            ->orderBy('schedule_time_slot')
            ->get();

        if ($schedules->isEmpty()) {
            Log::info('لا توجد جداول في هذا التاريخ.');

            return;
        }

        foreach ($schedules as $schedule) {
            Log::info("المادة: {$schedule->schedule_subject} - الفترة: {$schedule->schedule_time_slot}");
            foreach ($schedule->rooms as $room) {
                self::fillRoom($room, $schedule, $date);
            }
        }
    }

    private static function fillRoom($room, $schedule, string $date)
    {
        Log::info("القاعة: {$room->room_name} (النوع: {$room->room_type})");

        // حساب الأدوار المطلوبة
        $existing = $room->observers->groupBy(fn ($o) => $o->user->getRoleNames()->first());
        $required = [
            'رئيس_قاعة' => max(1 - $existing->get('رئيس_قاعة', collect())->count(), 0),
            'امين_سر' => max(($room->room_type === 'big' ? 2 : 1) - $existing->get('امين_سر', collect())->count(), 0),
            'مراقب' => max(($room->room_type === 'big' ? 8 : 4) - $existing->get('مراقب', collect())->count(), 0),
        ];

        Log::info('المطلوب: '.json_encode($required, JSON_UNESCAPED_UNICODE));

        foreach ($required as $role => $count) {
            if ($count <= 0) {
                continue;
            }

            Log::info("البحث عن {$role} (مطلوب: {$count})");

            // استرجاع المستخدمين المؤهلين لهذا الدور
            $users = User::whereHas('roles', fn ($q) => $q->where('name', $role))
                ->whereDoesntHave('observers', function ($q) use ($schedule) {
                    $q->whereHas('schedule', fn ($subQ) => $subQ->where('schedule_exam_date', $schedule->schedule_exam_date)
                        ->where('schedule_time_slot', $schedule->schedule_time_slot)
                    );
                })
                ->get()
                ->filter(fn ($user) => Observer::where('user_id', $user->id)
                    ->whereHas('schedule', fn ($q) => $q->where('schedule_exam_date', $date))
                    ->count() < $user->getMaxObserversByAge()
                )
                ->sortByDesc('years_experience');

            foreach ($users as $user) {
                if ($count <= 0) {
                    break;
                }

                Log::info("المستخدم المرشح: {$user->name} (الخبرة: {$user->years_experience})");

                Observer::firstOrCreate([
                    'user_id' => $user->id,
                    'schedule_id' => $schedule->schedule_id,
                    'room_id' => $room->room_id,
                ]);

                Log::info('تم التعيين بنجاح!');
                $count--;
            }

            if ($count > 0) {
                Log::warning("لم يتم تلبية الطلب بالكامل. ناقص: {$count}");
            }
        }
    }
}
