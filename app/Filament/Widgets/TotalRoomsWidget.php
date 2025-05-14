<?php

namespace App\Filament\Widgets;

use App\Models\Schedule;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class TotalRoomsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $totalRooms = Schedule::query()
            ->join('room_schedules', 'schedules.schedule_id', '=', 'room_schedules.schedule_id')
            ->count();

        return [
            Stat::make('إجمالي القاعات المستخدمة', $totalRooms)
                ->description('مع احتساب التكرار')
                ->color('success'),
        ];
    }
}
