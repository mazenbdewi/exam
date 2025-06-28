<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class TotalBigSmallRoomsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        // حساب عدد القاعات حسب النوع
        $roomCounts = DB::table('rooms')
            ->select('room_type', DB::raw('count(DISTINCT room_id) as total'))
            ->groupBy('room_type')
            ->pluck('total', 'room_type');

        // حساب عدد القاعات حسب الأولوية
        $priorityCounts = DB::table('rooms')
            ->select('room_priority', DB::raw('count(DISTINCT room_id) as total'))
            ->groupBy('room_priority')
            ->pluck('total', 'room_priority');

        return [
            // إحصائيات أنواع القاعات
            Stat::make('القاعات الصغيرة', $roomCounts['small'] ?? 0)
                ->description('عدد القاعات الصغيرة الفريدة')
                ->descriptionIcon('heroicon-o-home')
                ->color('info'),

            Stat::make('القاعات الكبيرة', $roomCounts['big'] ?? 0)
                ->description('عدد القاعات الكبيرة الفريدة')
                ->descriptionIcon('heroicon-o-building-office')
                ->color('warning'),

            Stat::make('المدرجات', $roomCounts['amphitheater'] ?? 0)
                ->description('عدد المدرجات الفريدة')
                ->descriptionIcon('heroicon-o-academic-cap')
                ->color('primary'),

            // إحصائيات الأولوية
            Stat::make('قاعات أولوية عالية', $priorityCounts[1] ?? 0)
                ->description('عدد القاعات ذات الأهمية العالية')
                ->descriptionIcon('heroicon-o-fire')
                ->color('danger'),

            Stat::make('قاعات أولوية متوسطة', $priorityCounts[2] ?? 0)
                ->description('عدد القاعات ذات الأهمية المتوسطة')
                ->descriptionIcon('heroicon-o-adjustments-horizontal')
                ->color('warning'),

            Stat::make('قاعات أولوية منخفضة', $priorityCounts[3] ?? 0)
                ->description('عدد القاعات ذات الأهمية المنخفضة')
                ->descriptionIcon('heroicon-o-chevron-double-down')
                ->color('gray'),
        ];
    }
}
