<?php

namespace App\Services;

use App\Models\Observer;
use App\Models\Schedule;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class ObserverDistributionService
{
    protected static array $roleGroups = [
        'رئيس_قاعة' => ['pointer' => 0, 'users' => []],
        'امين_سر' => ['pointer' => 0, 'users' => []],
        'مراقب' => ['pointer' => 0, 'users' => []],
    ];

    public static function distributeObservers()
    {
        self::initRoleGroups();

        $dates = Schedule::orderBy('schedule_exam_date')
            ->pluck('schedule_exam_date')
            ->unique();

        foreach ($dates as $date) {
            self::processDate($date);
        }
    }

    private static function initRoleGroups()
    {
        foreach (array_keys(self::$roleGroups) as $role) {
            self::$roleGroups[$role]['users'] = User::whereHas('roles', fn ($q) => $q->where('name', $role))
                ->with('observers')
                ->get()
                ->sortBy('age') // الأصغر سنًا أولاً
                ->values()
                ->toArray();
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
                $user = self::getNextAvailableUser($role, $schedule, $date);

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

    private static function getNextAvailableUser(string $role, Schedule $schedule, string $date): ?User
    {
        $users = collect(self::$roleGroups[$role]['users'])
            ->filter(function ($user) use ($schedule, $date) {
                $userModel = User::find($user['id']);

                return ! self::hasConflict($userModel, $schedule) && self::canAssign($userModel, $date);
            });

        if ($users->isEmpty()) {
            return null;
        }

        // اختيار الأقل استخدامًا في التواريخ السابقة
        $leastUsedUser = $users->sortBy(function ($user) {
            return User::find($user['id'])->observers()->count();
        })->first();

        return User::find($leastUsedUser['id']);
    }

    private static function hasConflict(User $user, Schedule $schedule): bool
    {
        return $user->schedules()
            ->where('schedule_exam_date', $schedule->schedule_exam_date)
            ->where('schedule_time_slot', $schedule->schedule_time_slot)
            ->exists();
    }

    private static function canAssign(User $user, string $date): bool
    {
        $limits = $user->getMaxObservers();

        $dailyCount = $user->observers()
            ->whereHas('schedule', fn ($q) => $q->where('schedule_exam_date', $date))
            ->count();

        $totalCount = $user->observers()->count();

        return $dailyCount < $limits['daily'] && $totalCount < $limits['total'];
    }
}
