<?php

namespace App\Filament\Widgets;

use App\Models\User;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class HeadsCountWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $headsCount = DB::table('users')
            ->join('model_has_roles', 'users.id', '=', 'model_has_roles.model_id')
            ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
            ->where('roles.name', 'رئيس_قاعة')
            ->where('model_has_roles.model_type', User::class)
            ->count();

        return [
            Stat::make('رؤساء القاعات', $headsCount)
                ->description('إجمالي عدد رؤساء القاعات')
                ->color('primary'),
        ];
    }
}
