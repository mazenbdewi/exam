<?php

namespace App\Services;

use App\Models\Observer;
use App\Models\Schedule;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

class ObserverDistributionService
{
    protected static array $usedUserIds = [];

    public static function distributeObservers()
    {
        self::$usedUserIds = []; // إعادة تهيئة القائمة عند كل تنفيذ

        // الحصول على جميع التواريخ المرتبة
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

        // الحصول على جميع الجداول لهذا التاريخ مع القاعات
        $schedules = Schedule::with(['rooms.observers.user'])
            ->where('schedule_exam_date', $date)
            ->orderBy('schedule_time_slot')
            ->get();

        // الحصول على المراقبين المؤهلين مع استبعاد المستخدمين المعينين مسبقًا
        $eligibleUsers = User::whereHas('roles', fn ($q) => $q->whereIn('name', ['رئيس_قاعة', 'امين_سر', 'مراقب']))
            ->whereNotIn('id', self::$usedUserIds)
            ->withCount(['observers' => fn ($q) => $q->whereHas('schedule', fn ($q) => $q->where('schedule_exam_date', $date))])
            ->get()
            ->filter(fn ($user) => $user->observers_count < $user->getMaxObserversByAge())
            ->sortByDesc(fn ($user) => [
                $user->hasRole('رئيس_قاعة') ? 3 : 0,
                $user->hasRole('امين_سر') ? 2 : 0,
                $user->hasRole('مراقب') ? 1 : 0,
                $user->years_experience,
            ]);

        Log::info('عدد المرشحين المؤهلين: '.$eligibleUsers->count());

        foreach ($schedules as $schedule) {
            foreach ($schedule->rooms as $room) {
                self::fillRoom($room, $eligibleUsers, $schedule);
            }
        }

        // تحديث قائمة المستخدمين المعينين بعد كل تاريخ
        self::updateUsedUsers($date);
    }

    private static function fillRoom($room, Collection &$eligibleUsers, $schedule)
    {
        $existingObservers = $room->observers->groupBy(fn ($o) => $o->user->getRoleNames()->first());

        $required = [
            'رئيس_قاعة' => max(1 - $existingObservers->get('رئيس_قاعة', collect())->count(), 0),
            'امين_سر' => max(($room->room_type === 'big' ? 2 : 1) - $existingObservers->get('امين_سر', collect())->count(), 0),
            'مراقب' => max(($room->room_type === 'big' ? 8 : 4) - $existingObservers->get('مراقب', collect())->count(), 0),
        ];

        Log::info("القاعة {$room->room_name} تتطلب: ".json_encode($required));

        foreach ($required as $role => $count) {
            if ($count <= 0) {
                continue;
            }

            $candidates = $eligibleUsers->filter(fn ($u) => $u->hasRole($role));

            foreach ($candidates as $key => $user) {
                if ($count <= 0) {
                    break;
                }

                if (! self::hasConflict($user, $schedule)) {
                    if (self::assignObserver($user, $schedule, $room)) {
                        $eligibleUsers->forget($key);
                        self::$usedUserIds[] = $user->id;
                        $count--;
                        Log::info("تم تعيين {$user->name} كـ {$role} في {$room->room_name}");
                    }
                }
            }
        }
    }

    private static function hasConflict(User $user, Schedule $schedule): bool
    {
        return Observer::where('user_id', $user->id)
            ->whereHas('schedule', fn ($q) => $q->where('schedule_exam_date', $schedule->schedule_exam_date)
                ->where('schedule_time_slot', $schedule->schedule_time_slot))
            ->exists();
    }

    private static function assignObserver(User $user, Schedule $schedule, $room): bool
    {
        try {
            Observer::create([
                'user_id' => $user->id,
                'schedule_id' => $schedule->schedule_id,
                'room_id' => $room->room_id,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error("فشل التعيين: {$e->getMessage()}");

            return false;
        }
    }

    private static function updateUsedUsers(string $date)
    {
        $newUsed = Observer::whereHas('schedule', fn ($q) => $q->where('schedule_exam_date', $date))
            ->pluck('user_id')
            ->unique()
            ->toArray();

        self::$usedUserIds = array_unique(array_merge(self::$usedUserIds, $newUsed));
    }
}
