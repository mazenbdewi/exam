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
        $eligibleUsers = User::whereHas('roles', function ($query) {
            $query->whereIn('name', ['مراقب', 'امين_سر', 'رئيس_قاعة']);
        })
            ->get()
            ->filter(function ($user) {
                return Observer::where('user_id', $user->id)->count() < $user->getMaxObserversByAge();
            });

        $schedules = Schedule::with('rooms')->whereHas('rooms')->get();
        $totalObserversAssigned = 0;
        $totalRoomsAssigned = 0;

        foreach ($schedules as $schedule) {
            foreach ($schedule->rooms as $room) {

                $existingObservers = Observer::where('room_id', $room->room_id)->get();

                // احتساب الأدوار الحالية
                $currentRoles = [
                    'رئيس_قاعة' => $existingObservers->filter(fn ($obs) => $obs->user->hasRole('رئيس_قاعة'))->count(),
                    'امين_سر' => $existingObservers->filter(fn ($obs) => $obs->user->hasRole('امين_سر'))->count(),
                    'مراقب' => $existingObservers->filter(fn ($obs) => $obs->user->hasRole('مراقب'))->count(),
                ];

                // تحديد الأعداد المطلوبة مع احتساب الحاليين
                $roomType = $room->room_type;
                $maxHeads = 1 - $currentRoles['رئيس_قاعة'];
                $maxSecretaries = ($roomType === 'big' ? 2 : 1) - $currentRoles['امين_سر'];
                $maxObservers = ($roomType === 'big' ? 8 : 4) - $currentRoles['مراقب'];
                $roomType = $room->room_type;

                $maxHeads = 1;
                $maxSecretaries = ($roomType === 'big') ? 2 : 1;
                $maxObservers = ($roomType === 'big') ? 8 : 4;

                $assignedRoles = [
                    'رئيس_قاعة' => 0,
                    'امين_سر' => 0,
                    'مراقب' => 0,
                ];

                foreach ($eligibleUsers as $key => $user) {
                    if ($assignedRoles['رئيس_قاعة'] >= $maxHeads) {
                        break;
                    }

                    $role = $user->getRoleNames()->first();
                    if ($role === 'رئيس_قاعة') {
                        $hasConflict = Observer::where('user_id', $user->id)
                            ->whereHas('schedule', function ($query) use ($schedule) {
                                $query->where('schedule_exam_date', $schedule->schedule_exam_date)
                                    ->where('schedule_time_slot', $schedule->schedule_time_slot);
                            })->exists();

                        if (! $hasConflict) {
                            $assigned = self::assignObserver($user, $schedule, $room);
                            if ($assigned) {
                                $assignedRoles['رئيس_قاعة']++;
                                $totalObserversAssigned++;
                                unset($eligibleUsers[$key]);
                            }
                        }
                    }
                }

                // --- تعيين أمناء السر ---
                foreach ($eligibleUsers as $key => $user) {
                    if ($assignedRoles['امين_سر'] >= $maxSecretaries) {
                        break;
                    }

                    $role = $user->getRoleNames()->first();
                    if ($role === 'امين_سر') {
                        // التحقق من التعارض في الوقت
                        $hasConflict = Observer::where('user_id', $user->id)
                            ->whereHas('schedule', function ($query) use ($schedule) {
                                $query->where('schedule_exam_date', $schedule->schedule_exam_date)
                                    ->where('schedule_time_slot', $schedule->schedule_time_slot);
                            })->exists();

                        if (! $hasConflict) {
                            $assigned = self::assignObserver($user, $schedule, $room);
                            if ($assigned) {
                                $assignedRoles['امين_سر']++;
                                $totalObserversAssigned++;
                                unset($eligibleUsers[$key]); // إزالة المستخدم من القائمة
                            }
                        }
                    }
                }

                // --- تعيين المراقبين العاديين ---
                foreach ($eligibleUsers as $key => $user) {
                    if ($assignedRoles['مراقب'] >= $maxObservers) {

                        break;
                    }

                    $role = $user->getRoleNames()->first();
                    if ($role === 'مراقب') {
                        // التحقق من التعارض في الوقت
                        $hasConflict = Observer::where('user_id', $user->id)
                            ->whereHas('schedule', function ($query) use ($schedule) {
                                $query->where('schedule_exam_date', $schedule->schedule_exam_date)
                                    ->where('schedule_time_slot', $schedule->schedule_time_slot);
                            })->exists();

                        if (! $hasConflict) {
                            $assigned = self::assignObserver($user, $schedule, $room);
                            if ($assigned) {
                                $assignedRoles['مراقب']++;
                                $totalObserversAssigned++;
                                unset($eligibleUsers[$key]); // إزالة المستخدم من القائمة
                            }
                        }
                    }
                }

                // --- التحقق من اكتمال القاعة ---
                $totalRequired = $maxHeads + $maxSecretaries + $maxObservers;

                $totalAssigned = array_sum($assignedRoles);

                if ($totalAssigned === $totalRequired) {
                    $totalRoomsAssigned++;
                } else {
                    // إشعار في حالة عدم اكتمال القاعة
                    Notification::make()
                        ->title('تحذير')
                        ->body("القاعة {$room->room_name} لم تُعبأ بالكامل (ناقص ".($totalRequired - $totalAssigned).' مراقبين)')
                        ->warning()
                        ->send();
                }
            }
        }

        // إظهار الإشعار النهائي
        Notification::make()
            ->title('تم التوزيع')
            // ->body("تم تعيين $totalObserversAssigned مراقب في $totalRoomsAssigned قاعة مكتملة")
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
