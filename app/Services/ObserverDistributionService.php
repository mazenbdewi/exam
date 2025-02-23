<?php

namespace App\Services;

use App\Models\Observer;
use App\Models\Schedule;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class ObserverDistributionService
{
    // تغيير المصفوفة الثابتة إلى مصفوفة ديناميكية لتخزين التعيينات لكل تاريخ
    protected static array $dateWiseAssignments = [];

    public static function distributeObservers()
    {
        self::$dateWiseAssignments = []; // إعادة التهيئة

        $dates = Schedule::orderBy('schedule_exam_date')
            ->pluck('schedule_exam_date')
            ->unique()
            ->toArray();

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
            Log::info("لا توجد جداول في تاريخ: {$date}");

            return;
        }

        // الحصول على المستخدمين المؤهلين لهذا التاريخ فقط
        $eligibleUsers = self::getEligibleUsers($date);

        foreach ($schedules as $schedule) {
            foreach ($schedule->rooms as $room) {
                self::fillRoom($room, $eligibleUsers, $schedule, $date);
            }
        }

        // تحديث التعيينات لهذا التاريخ
        self::updateDateAssignments($date);
    }

    private static function getEligibleUsers(string $date): Collection
    {
        return User::whereHas('roles', fn ($q) => $q->whereIn('name', ['رئيس_قاعة', 'امين_سر', 'مراقب']))
            ->with(['observers' => fn ($q) => $q->whereHas('schedule', fn ($q) => $q->where('schedule_exam_date', $date))])
            ->get()
            ->filter(function ($user) {
                // التحقق من الحد الأقصى للمراقبات لهذا التاريخ فقط
                $currentCount = $user->observers->count();

                return $currentCount < $user->getMaxObserversByAge();
            })
            ->sortByDesc(fn ($user) => [
                $user->hasRole('رئيس_قاعة') ? 3 : 0,
                $user->hasRole('امين_سر') ? 2 : 0,
                $user->hasRole('مراقب') ? 1 : 0,
                $user->years_experience,
            ]);
    }

    private static function fillRoom($room, Collection &$eligibleUsers, $schedule, string $date)
    {
        $existingObservers = $room->observers->groupBy(fn ($o) => $o->user->getRoleNames()->first());

        $required = [
            'رئيس_قاعة' => max(1 - $existingObservers->get('رئيس_قاعة', collect())->count(), 0),
            'امين_سر' => max(($room->room_type === 'big' ? 2 : 1) - $existingObservers->get('امين_سر', collect())->count(), 0),
            'مراقب' => max(($room->room_type === 'big' ? 8 : 4) - $existingObservers->get('مراقب', collect())->count(), 0),
        ];

        foreach ($required as $role => $count) {
            if ($count <= 0) {
                continue;
            }

            $candidates = $eligibleUsers->filter(fn ($u) => $u->hasRole($role));

            foreach ($candidates as $key => $user) {
                if ($count <= 0) {
                    break;
                }

                // التحقق من التعارض الزمني فقط في نفس التاريخ والفترة
                if (! self::hasTimeConflict($user, $schedule)) {
                    if (self::assignObserver($user, $schedule, $room)) {
                        $eligibleUsers->forget($key);
                        self::trackAssignment($date, $user->id);
                        $count--;
                        Log::info("تم تعيين {$user->name} في {$date}");
                    }
                }
            }
        }
    }

    private static function hasTimeConflict(User $user, Schedule $schedule): bool
    {
        return $user->schedules()
            ->where('schedule_exam_date', $schedule->schedule_exam_date)
            ->where('schedule_time_slot', $schedule->schedule_time_slot)
            ->exists();
    }

    private static function trackAssignment(string $date, int $userId)
    {
        if (! isset(self::$dateWiseAssignments[$date])) {
            self::$dateWiseAssignments[$date] = [];
        }
        self::$dateWiseAssignments[$date][] = $userId;
    }

    private static function updateDateAssignments(string $date)
    {
        // لا حاجة للقيام بأي شيء إضافي هنا
    }

    private static function assignObserver(User $user, Schedule $schedule, $room): bool
    {
        try {
            Observer::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'schedule_id' => $schedule->schedule_id,
                    'room_id' => $room->room_id,
                ],
                []
            );

            return true;
        } catch (\Exception $e) {
            Log::error("فشل التعيين: {$e->getMessage()}");

            return false;
        }
    }
}
