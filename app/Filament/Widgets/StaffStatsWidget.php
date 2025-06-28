<?php

namespace App\Filament\Widgets;

use App\Models\RoomSchedule;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Card;

class StaffStatsWidget extends StatsOverviewWidget
{
    protected function getColumns(): int
    {
        return 3;
    }

    protected function getCards(): array
    {
        $totalRequired = [
            'رئيس' => 0,
            'أمين سر' => 0,
            'مراقب' => 0,
        ];

        $totalCurrent = [
            'رئيس' => 0,
            'أمين سر' => 0,
            'مراقب' => 0,
        ];

        $schedules = RoomSchedule::with('room')
            ->withCount([
                'roomObservers as president_count' => fn ($q) => $q->whereHas('user.roles', fn ($q) => $q->where('name', 'رئيس_قاعة')),
                'roomObservers as secretary_count' => fn ($q) => $q->whereHas('user.roles', fn ($q) => $q->where('name', 'امين_سر')),
                'roomObservers as observer_count' => fn ($q) => $q->whereHas('user.roles', fn ($q) => $q->where('name', 'مراقب')),
            ])
            ->get();

        foreach ($schedules as $record) {
            $roomType = $record->room->room_type;

            $required = [
                'رئيس' => 1,
                'أمين سر' => $roomType === 'big' ? 2 : 1,
                'مراقب' => $roomType === 'big' ? 8 : 4,
            ];

            $totalRequired['رئيس'] += $required['رئيس'];
            $totalRequired['أمين سر'] += $required['أمين سر'];
            $totalRequired['مراقب'] += $required['مراقب'];

            $totalCurrent['رئيس'] += $record->president_count;
            $totalCurrent['أمين سر'] += $record->secretary_count;
            $totalCurrent['مراقب'] += $record->observer_count;
        }

        $totalStaff = [
            'رئيس_قاعة' => User::role('رئيس_قاعة')->count(),
            'امين_سر' => User::role('امين_سر')->count(),
            'مراقب' => User::role('مراقب')->count(),
        ];

        $primaryObservers = User::whereHas('roles', fn ($q) => $q->where('name', 'مراقب'))
            ->where('observer_type', 'primary')->count();

        $secondaryObservers = User::whereHas('roles', fn ($q) => $q->where('name', 'مراقب'))
            ->where('observer_type', 'secondary')->count();

        $reserveObservers = User::whereHas('roles', fn ($q) => $q->where('name', 'مراقب'))
            ->where('observer_type', 'reserve')->count();

        $monitoringLevels = [
            'لا يراقب' => User::where('monitoring_level', 0)->count(),
            'مراقبة كاملة' => User::where('monitoring_level', 1)->count(),
            'نصف مراقبة' => User::where('monitoring_level', 2)->count(),
            'ربع مراقبة' => User::where('monitoring_level', 3)->count(),
        ];

        $monthPartStats = [
            'first_half' => User::where('month_part', 'first_half')->count(),
            'second_half' => User::where('month_part', 'second_half')->count(),
            'any' => User::where('month_part', 'any')->count(),
        ];

        return [
            Card::make('عدد رؤساء القاعات الكلي', $totalStaff['رئيس_قاعة'])->color('primary')->icon('heroicon-o-user-group'),
            Card::make('عدد أمناء السر الكلي', $totalStaff['امين_سر'])->color('primary')->icon('heroicon-o-user-group'),
            Card::make('عدد المراقبين الكلي', $totalStaff['مراقب'])->color('primary')->icon('heroicon-o-user-group'),

            Card::make('المراقبون الأساسيون', $primaryObservers)->color('success')->icon('heroicon-o-star'),
            Card::make('المراقبون الثانويون', $secondaryObservers)->color('warning')->icon('heroicon-o-adjustments-horizontal'),
            Card::make('المراقبون الاحتياط', $reserveObservers)->color('danger')->icon('heroicon-o-clock'),

            Card::make('متاحون في النصف الأول', $monthPartStats['first_half'])
                ->description('من 1 إلى 15 من الشهر')
                ->color('sky')
                ->icon('heroicon-o-calendar-days'),

            Card::make('متاحون في النصف الثاني', $monthPartStats['second_half'])
                ->description('من 16 إلى نهاية الشهر')
                ->color('indigo')
                ->icon('heroicon-o-calendar-days'),

            Card::make('متاحون في أي وقت', $monthPartStats['any'])
                ->description('طوال الشهر')
                ->color('violet')
                ->icon('heroicon-o-calendar-days'),

            Card::make('لا يراقب', $monitoringLevels['لا يراقب'])->color('gray')->icon('heroicon-o-x-circle'),
            Card::make('مراقبة كاملة', $monitoringLevels['مراقبة كاملة'])->color('success')->icon('heroicon-o-eye'),
            Card::make('نصف مراقبة', $monitoringLevels['نصف مراقبة'])->color('warning')->icon('heroicon-o-eye'),
            Card::make('ربع مراقبة', $monitoringLevels['ربع مراقبة'])
                ->color('danger')
                ->icon('heroicon-o-eye'),
        ];
    }
}
