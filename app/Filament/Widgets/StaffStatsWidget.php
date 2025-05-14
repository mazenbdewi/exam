<?php

namespace App\Filament\Widgets;

use App\Models\User;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StaffStatsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $roles = [
            'رئيس_قاعة',
            'امين_سر',
            'مراقب',
        ];

        $stats = [];

        foreach ($roles as $role => $color) {
            $count = User::whereHas('roles', function ($query) use ($role) {
                $query->where('name', $role);
            })->count();

            $stats[] = Stat::make(
                $role, // اسم الدور مباشرة (بدون ترجمة)
                $count
            );
        }

        return $stats;
    }

    private function getRoleIcon(string $role): string
    {
        return match ($role) {
            'رئيس_قاعة' => 'heroicon-o-user-circle',
            'امين_سر' => 'heroicon-o-clipboard-document-list',
            'مراقب' => 'heroicon-o-eye',
            default => 'heroicon-o-users'
        };
    }
}
