<?php

namespace App\Filament\Widgets;

use App\Models\User;
use DB;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StaffStatsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $roles = ['رئيس_قاعة', 'امين_سر', 'مراقب'];
        $stats = [];

        foreach ($roles as $role) {
            $count = DB::table('users')
                ->join('model_has_roles', 'users.id', '=', 'model_has_roles.model_id')
                ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
                ->where('roles.name', $role)
                ->where('model_has_roles.model_type', User::class)
                ->count();

            $stats[] = Stat::make(
                trans("roles.$role"), // استخدام ترجمة للأدوار
                $count
            )->color($this->getRoleColor($role));
        }

        return $stats;
    }

    private function getRoleColor(string $role): string
    {
        return match ($role) {
            'رئيس_قاعة' => 'primary',
            'امين_سر' => 'success',
            'مراقب' => 'warning',
            default => 'gray'
        };
    }
}
