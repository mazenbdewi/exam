<?php

namespace App\Services;

use App\Models\Observer;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\Schedule;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ObserverDistributionService
{
    // public static function distributeObservers(): void
    // {
    //     Log::info('===== بدء توزيع المراقبين =====');

    //     try {
    //         $schedules = self::getSchedulesWithReservations();

    //         if ($schedules->isEmpty()) {
    //             Log::warning('لا توجد جداول امتحانية لها حجوزات قاعات');

    //             return;
    //         }

    //         $scheduleIds = $schedules->pluck('schedule_id');
    //         $deletedCount = Observer::whereIn('schedule_id', $scheduleIds)->delete();
    //         Log::info("تم حذف {$deletedCount} من المراقبين السابقين");

    //         foreach ($schedules as $schedule) {
    //             self::processSchedule($schedule);
    //         }

    //         Log::info('===== انتهاء توزيع المراقبين بنجاح =====');
    //     } catch (\Exception $e) {
    //         Log::error('فشل توزيع المراقبين: '.$e->getMessage());
    //     }
    // }
    public static function distributeObservers(): bool
    {
        Log::info('===== بدء توزيع المراقبين =====');
        app()->instance('auto_context', true);

        try {
            $schedules = self::getSchedulesWithReservations();

            if ($schedules->isEmpty()) {
                Log::warning('لا توجد جداول امتحانية لها حجوزات قاعات');

                return false;
            }

            $scheduleIds = $schedules->pluck('schedule_id');
            $deletedCount = Observer::whereIn('schedule_id', $scheduleIds)->delete();
            Log::info("تم حذف {$deletedCount} من المراقبين السابقين");

            $distributed = false;

            foreach ($schedules as $schedule) {
                $result = self::processSchedule($schedule);
                if ($result) {
                    $distributed = true;
                }
            }

            Log::info('===== انتهاء توزيع المراقبين =====');

            return $distributed;

        } catch (\Exception $e) {
            Log::error('فشل توزيع المراقبين: '.$e->getMessage());

            return false;

        } finally {
            app()->forgetInstance('auto_context');
        }
    }

    private static function getSchedulesWithReservations()
    {
        return Schedule::has('reservations')->with(['reservations.room'])->orderBy('schedule_exam_date')->orderBy('schedule_time_slot')->get();
    }

    // private static function processSchedule(Schedule $schedule): void
    // {
    //     $reservations = $schedule->reservations()->with('room')->get();

    //     if ($reservations->isEmpty()) {
    //         return;
    //     }

    //     foreach ($reservations as $reservation) {
    //         $eligibleUsers = self::getEligibleUsers($schedule, $reservation);

    //         DB::transaction(function () use ($reservation, $schedule, &$eligibleUsers) {
    //             self::assignObservers($reservation->room, $schedule, $reservation, $eligibleUsers);
    //         });
    //     }
    // }

    private static function processSchedule(Schedule $schedule): bool
    {
        $reservations = $schedule->reservations()->with('room')->get();

        if ($reservations->isEmpty()) {
            return false;
        }

        $distributed = false;

        foreach ($reservations as $reservation) {
            $eligibleUsers = self::getEligibleUsers($schedule, $reservation);

            DB::transaction(function () use ($reservation, $schedule, &$eligibleUsers, &$distributed) {
                $result = self::assignObservers($reservation->room, $schedule, $reservation, $eligibleUsers);
                if ($result) {
                    $distributed = true;
                }
            });
        }

        return $distributed;
    }

    private static function getEligibleUsers(Schedule $schedule, Reservation $reservation): array
    {
        $scheduleHalf = self::getHalfFromDate($schedule->schedule_exam_date);

        $users = User::whereHas('roles', function ($q) {
            $q->whereIn('name', ['رئيس_قاعة', 'امين_سر', 'مراقب']);
        })
            ->where('monitoring_level', '>', 0)
            ->withCount(['observers as total_assigned'])
            ->get();

        $result = [];
        foreach ($users as $user) {
            $result[$user->id] = [
                'user' => $user,
                'assigned_now' => 0,
                'available' => self::isUserAvailable($user, $schedule, $reservation, $scheduleHalf),
            ];
        }

        return $result;
    }

    private static function isUserAvailable(User $user, Schedule $schedule, Reservation $reservation, string $scheduleHalf): bool
    {
        $timeConflict = Observer::where('user_id', $user->id)
            ->whereHas('schedule', function ($q) use ($schedule) {
                $q->where('schedule_exam_date', $schedule->schedule_exam_date)
                    ->where('schedule_time_slot', $schedule->schedule_time_slot);
            })->exists();

        $reservationConflict = Observer::where('user_id', $user->id)
            ->whereHas('reservation', function ($q) use ($reservation) {
                $q->where('date', $reservation->date)
                    ->where('time_slot', $reservation->time_slot);
            })->exists();

        $canTakeMore = $user->canTakeMoreObservers();

        $halfAvailable = $user->month_part === 'any' || $user->month_part === $scheduleHalf;

        return ! $timeConflict && ! $reservationConflict && $canTakeMore && $halfAvailable;
    }

    // private static function assignObservers(Room $room, Schedule $schedule, Reservation $reservation, array &$users): void
    // {
    //     $requirements = self::getRoomRequirements($room);

    //     self::assignRole('رئيس_قاعة', $requirements['head'], $users, $schedule, $room, $reservation);
    //     self::assignRole('امين_سر', $requirements['secretary'], $users, $schedule, $room, $reservation);

    //     foreach ($requirements['observers'] as $type => $required) {
    //         self::assignObserverType($type, $required, $users, $schedule, $room, $reservation);
    //     }
    // }
    private static function assignObservers(Room $room, Schedule $schedule, Reservation $reservation, array &$users): bool
    {
        $requirements = self::getRoomRequirements($room);
        $assignedBefore = Observer::where('schedule_id', $schedule->schedule_id)->count();

        self::assignRole('رئيس_قاعة', $requirements['head'], $users, $schedule, $room, $reservation);
        self::assignRole('امين_سر', $requirements['secretary'], $users, $schedule, $room, $reservation);

        foreach ($requirements['observers'] as $type => $required) {
            self::assignObserverType($type, $required, $users, $schedule, $room, $reservation);
        }

        $assignedAfter = Observer::where('schedule_id', $schedule->schedule_id)->count();

        return $assignedAfter > $assignedBefore;
    }

    private static function assignRole(string $role, int $required, array &$users, Schedule $schedule, Room $room, Reservation $reservation): void
    {
        $candidates = collect($users)
            ->filter(fn ($u) => $u['available'] && $u['user']->hasRole($role))
            ->sortBy([['assigned_now', 'asc'], ['user.total_assigned', 'asc']]);

        $assigned = 0;

        foreach ($candidates as $u) {
            if ($assigned >= $required) {
                break;
            }

            if (self::createAssignment($u['user'], $room, $schedule, $reservation, $role)) {
                $users[$u['user']->id]['assigned_now']++;
                $assigned++;
            }
        }
    }

    private static function assignObserverType(string $type, int $required, array &$users, Schedule $schedule, Room $room, Reservation $reservation): void
    {
        $priorityLevels = $type === 'primary' ? [1, 2] : [1, 2, 3];

        $candidates = collect($users)
            ->filter(fn ($u) => $u['available'] && $u['user']->hasRole('مراقب') && in_array($u['user']->monitoring_level, $priorityLevels))
            ->sortBy([['user.monitoring_level', 'asc'], ['assigned_now', 'asc'], ['user.total_assigned', 'asc']]);

        $assigned = 0;

        foreach ($candidates as $u) {
            if ($assigned >= $required) {
                break;
            }

            if (self::createAssignment($u['user'], $room, $schedule, $reservation, $type)) {
                $users[$u['user']->id]['assigned_now']++;
                $assigned++;
            }
        }
    }

    private static function getRoomRequirements(Room $room): array
    {
        return match ($room->room_type) {
            'amphitheater' => ['head' => 1, 'secretary' => 2, 'observers' => ['primary' => 2, 'secondary' => 1, 'reserve' => 1]],
            'large' => ['head' => 1, 'secretary' => 1, 'observers' => ['primary' => 1, 'secondary' => 1, 'reserve' => 1]],
            'small' => ['head' => 1, 'secretary' => 1, 'observers' => ['primary' => 1, 'reserve' => 1]],
            default => ['head' => 1, 'secretary' => 1, 'observers' => ['primary' => 1, 'reserve' => 1]],
        };
    }

    private static function getHalfFromDate(string $date): string
    {
        $day = (int) Carbon::parse($date)->format('d');

        return $day <= 15 ? 'first_half' : 'second_half';
    }

    private static function createAssignment(User $user, Room $room, Schedule $schedule, Reservation $reservation, string $type): bool
    {
        try {
            Observer::create([
                'user_id' => $user->id,
                'room_id' => $room->room_id,
                'schedule_id' => $schedule->schedule_id,
                'reservation_id' => $reservation->reservation_id,
                'observer_type' => $type,
                'monitoring_level' => $user->monitoring_level,
            ]);

            Log::info("تم تعيين {$user->name} ك{$type} في القاعة {$room->room_name}");

            return true;

        } catch (\Exception $e) {
            Log::error("فشل في تعيين {$user->name}: ".$e->getMessage());

            return false;
        }
    }
}
