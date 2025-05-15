<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class TotalBigSmallRoomsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        // انضمام الجداول: schedules -> room_schedules -> rooms
        $roomCounts = DB::table('room_schedules')
            ->join('rooms', 'room_schedules.room_id', '=', 'rooms.room_id')
            ->select('rooms.room_type', DB::raw('count(*) as total'))
            ->groupBy('rooms.room_type')
            ->pluck('total', 'room_type'); // ['small' => x, 'big' => y]

        return [
            Stat::make('القاعات الصغيرة', $roomCounts['small'] ?? 0)
                ->description('إجمالي مرات استخدام القاعات الصغيرة')
                ->color('info'),

            Stat::make('القاعات الكبيرة', $roomCounts['big'] ?? 0)
                ->description('إجمالي مرات استخدام القاعات الكبيرة')
                ->color('warning'),
        ];
    }
}
