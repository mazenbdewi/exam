<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class TestWidget extends BaseWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('اختبار', '123')
                ->description('هذا ويدجيت اختبار')
                ->color('success'),
        ];
    }
}
