<?php

namespace App\Services;

use App\Models\Observer;
use App\Models\Schedule;
use App\Models\User;
use Filament\Notifications\Notification;

class ObserverDistributionService
{
    public static function distributeObservers()
    {
        // استرجاع المستخدمين المؤهلين بدون حظرهم على جميع الأيام
        $originalEligibleUsers = User::whereHas('roles', function ($query) {
            $query->whereIn('name', ['مراقب', 'امين_سر', 'رئيس_قاعة']);
        })->get()->filter(function ($user) {
            return Observer::where('user_id', $user->id)->count() < $user->getMaxObserversByAge();
        });
        $originalEligibleUsers = collect($originalEligibleUsers);

        // تجميع الجداول حسب تاريخ الامتحان
        $schedulesGroupedByDate = Schedule::with('rooms')
            ->whereHas('rooms')
            ->get()
            ->groupBy('schedule_exam_date');

        $totalObserversAssigned = 0;
        $totalRoomsAssigned = 0;

        foreach ($schedulesGroupedByDate as $examDate => $schedules) {
            // إعادة تهيئة قائمة المستخدمين المؤهلين لكل يوم امتحان
            $eligibleUsers = collect($originalEligibleUsers->all());

            foreach ($schedules as $schedule) {
                foreach ($schedule->rooms as $room) {

                    // استرجاع المراقبين الحاليين في القاعة
                    $existingObservers = Observer::where('room_id', $room->room_id)->get();

                    // حساب الأدوار الحالية في القاعة
                    $currentRoles = [
                        'رئيس_قاعة' => $existingObservers->filter(fn ($obs) => $obs->user->hasRole('رئيس_قاعة'))->count(),
                        'امين_سر' => $existingObservers->filter(fn ($obs) => $obs->user->hasRole('امين_سر'))->count(),
                        'مراقب' => $existingObservers->filter(fn ($obs) => $obs->user->hasRole('مراقب'))->count(),
                    ];

                    $roomType = $room->room_type;
                    $requiredHeads = max(1 - $currentRoles['رئيس_قاعة'], 0);
                    $requiredSecretaries = max(($roomType === 'big' ? 2 : 1) - $currentRoles['امين_سر'], 0);
                    $requiredObservers = max(($roomType === 'big' ? 8 : 4) - $currentRoles['مراقب'], 0);

                    $assignedRoles = [
                        'رئيس_قاعة' => 0,
                        'امين_سر' => 0,
                        'مراقب' => 0,
                    ];

                    // تعيين "رئيس_قاعة"
                    foreach ($eligibleUsers as $user) {
                        if ($assignedRoles['رئيس_قاعة'] >= $requiredHeads) {
                            break;
                        }
                        if ($user->getRoleNames()->first() === 'رئيس_قاعة') {
                            $hasConflict = Observer::where('user_id', $user->id)
                                ->whereHas('schedule', function ($query) use ($schedule) {
                                    $query->where('schedule_exam_date', $schedule->schedule_exam_date)
                                        ->where('schedule_time_slot', $schedule->schedule_time_slot);
                                })->exists();
                            if (! $hasConflict && self::assignObserver($user, $schedule, $room)) {
                                $assignedRoles['رئيس_قاعة']++;
                                $totalObserversAssigned++;
                                // إزالة المستخدم من قائمة المؤهلين لهذا اليوم لمنع تكرار التعيين في نفس الفتحة
                                $eligibleUsers = $eligibleUsers->reject(fn ($u) => $u->id === $user->id);
                            }
                        }
                    }

                    // تعيين "امين_سر"
                    foreach ($eligibleUsers as $user) {
                        if ($assignedRoles['امين_سر'] >= $requiredSecretaries) {
                            break;
                        }
                        if ($user->getRoleNames()->first() === 'امين_سر') {
                            $hasConflict = Observer::where('user_id', $user->id)
                                ->whereHas('schedule', function ($query) use ($schedule) {
                                    $query->where('schedule_exam_date', $schedule->schedule_exam_date)
                                        ->where('schedule_time_slot', $schedule->schedule_time_slot);
                                })->exists();
                            if (! $hasConflict && self::assignObserver($user, $schedule, $room)) {
                                $assignedRoles['امين_سر']++;
                                $totalObserversAssigned++;
                                $eligibleUsers = $eligibleUsers->reject(fn ($u) => $u->id === $user->id);
                            }
                        }
                    }

                    // تعيين "مراقب"
                    foreach ($eligibleUsers as $user) {
                        if ($assignedRoles['مراقب'] >= $requiredObservers) {
                            break;
                        }
                        if ($user->getRoleNames()->first() === 'مراقب') {
                            $hasConflict = Observer::where('user_id', $user->id)
                                ->whereHas('schedule', function ($query) use ($schedule) {
                                    $query->where('schedule_exam_date', $schedule->schedule_exam_date)
                                        ->where('schedule_time_slot', $schedule->schedule_time_slot);
                                })->exists();
                            if (! $hasConflict && self::assignObserver($user, $schedule, $room)) {
                                $assignedRoles['مراقب']++;
                                $totalObserversAssigned++;
                                $eligibleUsers = $eligibleUsers->reject(fn ($u) => $u->id === $user->id);
                            }
                        }
                    }

                    $totalRequired = $requiredHeads + $requiredSecretaries + $requiredObservers;
                    $totalAssigned = array_sum($assignedRoles);

                    if ($totalAssigned === $totalRequired) {
                        $totalRoomsAssigned++;
                    } else {
                        Notification::make()
                            ->title('تحذير')
                            ->body("القاعة {$room->room_name} لم تُعبأ بالكامل (ناقص ".($totalRequired - $totalAssigned).' مراقبين)')
                            ->warning()
                            ->send();
                    }
                }
            }
        }

        Notification::make()
            ->title('تم التوزيع')
            ->body("تم تعيين $totalObserversAssigned مراقب في $totalRoomsAssigned قاعة مكتملة")
            ->success()
            ->send();
    }

    private static function assignObserver($user, $schedule, $room): bool
    {
        try {
            Observer::create([
                'user_id' => $user->id,
                'schedule_id' => $schedule->schedule_id,
                'room_id' => $room->room_id,
            ]);

            logger()->info("تم تعيين {$user->name} في قاعة {$room->room_name}");

            return true;
        } catch (\Exception $e) {
            logger()->error("خطأ في التعيين: {$e->getMessage()}");

            return false;
        }
    }
}
