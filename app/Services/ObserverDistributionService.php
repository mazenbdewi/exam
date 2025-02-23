<?php

namespace App\Services;

use App\Models\Observer;
use App\Models\Schedule;
use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;

class ObserverDistributionService
{
    public static function distributeObservers()
    {
        $datesOrder = self::getSortedExamDates();

        foreach ($datesOrder as $date) {
            self::processDate($date);
        }
    }

    private static function getSortedExamDates(): array
    {
        return Schedule::orderBy('schedule_exam_date')
            ->pluck('schedule_exam_date')
            ->unique()
            ->toArray();
    }

    private static function processDate(string $date)
    {
        $schedules = Schedule::with(['rooms.observers.user'])
            ->where('schedule_exam_date', $date)
            ->orderBy('schedule_time_slot')
            ->get();

        $eligibleUsers = self::getEligibleUsers($date);

        foreach ($schedules as $schedule) {
            foreach ($schedule->rooms as $room) {
                self::fillRoom($room, $eligibleUsers, $schedule);
            }
        }
    }

    private static function getEligibleUsers(string $date): Collection
    {
        return User::whereHas('roles', fn ($q) => $q->whereIn('name', ['رئيس_قاعة', 'امين_سر', 'مراقب']))
            ->withCount(['observers' => fn ($q) => $q->whereHas('schedule', fn ($q) => $q->where('schedule_exam_date', $date))])
            ->get()
            ->filter(fn ($user) => $user->observers_count < $user->getMaxObserversByAge())
            ->sortByDesc(fn ($user) => [
                $user->hasRole('رئيس_قاعة') ? 2 : 0,
                $user->hasRole('امين_سر') ? 1 : 0,
                $user->years_experience,
            ]);
    }

    private static function fillRoom($room, Collection &$eligibleUsers, $schedule)
    {
        $existing = $room->observers->groupBy(fn ($o) => $o->user->getRoleNames()->first());

        $required = self::calculateRequiredRoles($room, $existing);
        $assigned = self::assignRoles($required, $eligibleUsers, $schedule, $room);

        self::notifyRoomStatus($room, $assigned, array_sum($required));
    }

    private static function calculateRequiredRoles($room, $existing): array
    {
        return [
            'رئيس_قاعة' => max(1 - $existing->get('رئيس_قاعة', collect())->count(), 0),
            'امين_سر' => max(($room->room_type === 'big' ? 2 : 1) - $existing->get('امين_سر', collect())->count(), 0),
            'مراقب' => max(($room->room_type === 'big' ? 8 : 4) - $existing->get('مراقب', collect())->count(), 0),
        ];
    }

    private static function assignRoles(array $required, Collection &$users, $schedule, $room): array
    {
        $assigned = [];

        foreach ($required as $role => $count) {
            $assigned[$role] = self::assignForRole($role, $count, $users, $schedule, $room);
        }

        return $assigned;
    }

    private static function assignForRole(string $role, int $needed, Collection &$users, $schedule, $room): int
    {
        $assigned = 0;
        $candidates = $users->filter(fn ($u) => $u->hasRole($role));

        foreach ($candidates as $key => $user) {
            if ($assigned >= $needed) {
                break;
            }

            if (! self::hasConflict($user, $schedule) && self::createAssignment($user, $schedule, $room)) {
                $users->forget($key);
                $assigned++;
            }
        }

        return $assigned;
    }

    private static function hasConflict(User $user, Schedule $schedule): bool
    {
        return Observer::where('user_id', $user->id)
            ->whereHas('schedule', fn ($q) => $q->where('schedule_exam_date', $schedule->schedule_exam_date)
                ->where('schedule_time_slot', $schedule->schedule_time_slot))
            ->exists();
    }

    private static function createAssignment(User $user, Schedule $schedule, $room): bool
    {
        try {
            Observer::create([
                'user_id' => $user->id,
                'schedule_id' => $schedule->schedule_id,
                'room_id' => $room->room_id,
            ]);

            return true;
        } catch (\Exception $e) {
            logger()->error("Assignment failed: {$e->getMessage()}");

            return false;
        }
    }

    private static function notifyRoomStatus($room, array $assigned, int $totalRequired)
    {
        $totalAssigned = array_sum($assigned);

        if ($totalAssigned < $totalRequired) {
            Notification::make()
                ->title("قاعة غير مكتملة - {$room->room_name}")
                ->body("العدد المعين: {$totalAssigned}/{$totalRequired}")
                ->warning()
                ->send();
        }
    }
}
