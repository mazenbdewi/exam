<?php

namespace App\Services;

use App\Models\Observer;
use App\Models\Room;
use App\Models\Schedule;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class ObserverDistributionService
{
    protected static $usedUsers = [];

    protected static $currentDate;

    public static function distributeObservers()
    {
        $schedules = Schedule::with(['rooms' => function ($query) {
            $query->orderByDesc('room_capacity_total')
                ->withCount(['observers as current_observers']);
        }])
            ->orderBy('schedule_exam_date')
            ->orderBy('schedule_time_slot')
            ->get();

        foreach ($schedules as $schedule) {
            self::$currentDate = $schedule->schedule_exam_date;
            self::processSchedule($schedule);
        }

        self::sendDistributionReport();
    }

    private static function processSchedule(Schedule $schedule)
    {
        Log::channel('observer_distribution')->info("Processing schedule: {$schedule->schedule_subject} - {$schedule->schedule_exam_date}");

        foreach ($schedule->rooms as $room) {
            if (self::isRoomFull($room)) {
                Log::debug("Room {$room->room_name} is already full");

                continue;
            }

            self::fillRoom($room, $schedule);
        }
    }

    private static function isRoomFull(Room $room): bool
    {
        $maxCapacity = $room->room_type === 'big' ? 11 : 6;

        return $room->current_observers >= $maxCapacity;
    }

    private static function fillRoom(Room $room, Schedule $schedule)
    {
        $requiredRoles = self::calculateRequiredRoles($room);

        foreach ($requiredRoles as $role => $requiredCount) {
            for ($i = 0; $i < $requiredCount; $i++) {
                $user = self::findBestUserForRole($role, $schedule, $room);

                if ($user && self::assignUserToRoom($user, $schedule, $room)) {
                    self::markUserAsUsed($user, $schedule);
                    Log::info("Assigned {$user->name} ({$role}) to {$room->room_name}");
                }
            }
        }
    }

    private static function calculateRequiredRoles(Room $room): array
    {
        $existing = [
            'رئيس_قاعة' => $room->observers()->whereHas('user.roles', fn ($q) => $q->where('name', 'رئيس_قاعة'))->count(),
            'امين_سر' => $room->observers()->whereHas('user.roles', fn ($q) => $q->where('name', 'امين_سر'))->count(),
            'مراقب' => $room->observers()->whereHas('user.roles', fn ($q) => $q->where('name', 'مراقب'))->count(),
        ];

        return [
            'رئيس_قاعة' => max(1 - $existing['رئيس_قاعة'], 0),
            'امين_سر' => max(($room->room_type === 'big' ? 2 : 1) - $existing['امين_سر'], 0),
            'مراقب' => self::calculateRemainingCapacity($room, $existing),
        ];
    }

    private static function calculateRemainingCapacity(Room $room, array $existing): int
    {
        $maxCapacity = $room->room_type === 'big' ? 11 : 6;
        $occupied = $existing['رئيس_قاعة'] + $existing['امين_سر'] + $existing['مراقب'];

        return max($maxCapacity - $occupied - 1, 0);
    }

    private static function findBestUserForRole(string $role, Schedule $schedule, Room $room): ?User
    {
        return User::whereHas('roles', fn ($q) => $q->where('name', $role))
            ->whereNotIn('id', self::$usedUsers)
            ->whereDoesntHave('observers', function ($q) use ($schedule) {
                $q->whereHas('schedule', fn ($q) => $q
                    ->where('schedule_exam_date', $schedule->schedule_exam_date)
                    ->where('schedule_time_slot', $schedule->schedule_time_slot)
                );
            })
            ->withCount(['observers' => function ($q) {
                $q->whereDate('observer_created_at', self::$currentDate);
            }])
            ->orderBy('observers_count')
            ->orderByDesc('birth_date')
            ->first();
    }

    private static function assignUserToRoom(User $user, Schedule $schedule, Room $room): bool
    {
        try {
            if ($user->exceedsMaxObservers()) {
                Log::warning("User {$user->name} exceeded max observers limit");

                return false;
            }

            Observer::create([
                'user_id' => $user->id,
                'schedule_id' => $schedule->schedule_id,
                'room_id' => $room->room_id,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error("Assignment failed: {$e->getMessage()}");

            return false;
        }
    }

    private static function markUserAsUsed(User $user, Schedule $schedule)
    {
        self::$usedUsers[$schedule->schedule_exam_date][] = $user->id;
    }

    private static function sendDistributionReport()
    {
        $report = [
            'total_assigned' => Observer::whereDate('observer_created_at', today())->count(),
            'failed_assignments' => Log::channel('observer_distribution')->where('level', 'WARNING')->count(),
        ];

        // إرسال الإشعار أو البريد الإلكتروني هنا
        Log::info('Distribution completed: '.json_encode($report));
    }
}
