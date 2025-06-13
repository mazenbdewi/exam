<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class TotalBigSmallRoomsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        // حساب عدد القاعات الفريدة من كل نوع (بدون تكرار)
        $roomCounts = DB::table('rooms')
            ->select('room_type', DB::raw('count(DISTINCT room_id) as total'))
            ->groupBy('room_type')
            ->pluck('total', 'room_type'); // ['small' => x, 'big' => y]

        return [
            Stat::make('القاعات الصغيرة', $roomCounts['small'] ?? 0)
                ->description('عدد القاعات الصغيرة الفريدة')
                ->descriptionIcon('heroicon-o-home')
                ->color('info'),

            Stat::make('القاعات الكبيرة', $roomCounts['big'] ?? 0)
                ->description('عدد القاعات الكبيرة الفريدة')
                ->descriptionIcon('heroicon-o-building-office')
                ->color('warning'),

        ];
    }
}
