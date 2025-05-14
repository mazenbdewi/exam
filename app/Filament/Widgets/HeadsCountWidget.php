<?php

namespace App\Filament\Widgets;

use App\Models\User;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class HeadsCountWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $headsCount = User::where('roles', 'رئيس_قاعة')->count();

        return [
            Stat::make('رؤساء القاعات', $headsCount)
                ->description('إجمالي عدد رؤساء القاعات')
                ->color('primary'),
        ];
    }
}
