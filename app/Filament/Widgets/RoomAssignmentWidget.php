<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class RoomAssignmentWidget extends BaseWidget
{
    protected function getStats(): array
    {
        // 1. عدد القاعات الكبيرة والصغيرة المستخدمة
        $rooms = DB::table('room_schedules')
            ->join('rooms', 'room_schedules.room_id', '=', 'rooms.room_id')
            ->select('rooms.room_type', DB::raw('count(*) as total'))
            ->groupBy('rooms.room_type')
            ->pluck('total', 'room_type');

        $bigRooms = $rooms['big'] ?? 0;
        $smallRooms = $rooms['small'] ?? 0;

        // 2. تحديد العدد المطلوب حسب كل نوع مراقب
        $required = [
            'رئيس_قاعة' => $bigRooms + $smallRooms,
            'امين_سر' => ($bigRooms * 2) + ($smallRooms * 1),
            'مراقب' => ($bigRooms * 8) + ($smallRooms * 4),
        ];

        // 3. عدد المتاح من المستخدمين المرتبطين بكل دور
        $available = [];
        foreach (['رئيس_قاعة', 'امين_سر', 'مراقب'] as $role) {
            $available[$role] = DB::table('users')
                ->join('model_has_roles', function ($join) {
                    $join->on('users.id', '=', 'model_has_roles.model_id')
                        ->where('model_has_roles.model_type', '=', 'App\\Models\\User');
                })
                ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
                ->where('roles.name', $role)
                ->count();
        }

        // 4. حساب النقص الإجمالي + تحديد إن كان النقص في الكبيرة أم الصغيرة
        $shortage = [];
        $details = [];

        // توزيع النقص على نوع القاعات
        $calcShortage = function ($totalNeeded, $availableNow, $bigUnit, $smallUnit) use ($bigRooms, $smallRooms) {
            $bigNeed = $bigRooms * $bigUnit;
            $smallNeed = $smallRooms * $smallUnit;
            $shortage = max(0, $bigNeed + $smallNeed - $availableNow);

            $bigShortage = max(0, $bigNeed - $availableNow);
            $remaining = max(0, $availableNow - $bigNeed);
            $smallShortage = max(0, $smallNeed - $remaining);

            $where = [];
            if ($bigShortage > 0) {
                $where[] = 'في القاعات الكبيرة';
            }
            if ($smallShortage > 0) {
                $where[] = 'في القاعات الصغيرة';
            }

            return [$shortage, implode(' و ', $where) ?: 'لا يوجد نقص'];
        };

        [$presidentShortage, $presidentWhere] = $calcShortage($required['رئيس_قاعة'], $available['رئيس_قاعة'], 1, 1);
        [$secretaryShortage, $secretaryWhere] = $calcShortage($required['امين_سر'], $available['امين_سر'], 2, 1);
        [$monitorShortage, $monitorWhere] = $calcShortage($required['مراقب'], $available['مراقب'], 8, 4);

        return [
            Stat::make('رئيس القاعة', "{$available['رئيس_قاعة']} / {$required['رئيس_قاعة']}")
                ->description("النقص: $presidentShortage - $presidentWhere")
                ->color($presidentShortage > 0 ? 'danger' : 'success'),

            Stat::make('أمين السر', "{$available['امين_سر']} / {$required['امين_سر']}")
                ->description("النقص: $secretaryShortage - $secretaryWhere")
                ->color($secretaryShortage > 0 ? 'warning' : 'success'),

            Stat::make('المراقبون', "{$available['مراقب']} / {$required['مراقب']}")
                ->description("النقص: $monitorShortage - $monitorWhere")
                ->color($monitorShortage > 0 ? 'danger' : 'success'),
        ];
    }
}
