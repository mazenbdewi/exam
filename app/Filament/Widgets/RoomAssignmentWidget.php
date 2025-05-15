<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;
use Illuminate\Support\Facades\DB;

class RoomAssignmentWidget extends Widget
{
    protected static string $view = 'filament.widgets.room-assignment-widget';

    public function getViewData(): array
    {
        // عدد القاعات المستخدمة حسب النوع
        $rooms = DB::table('room_schedules')
            ->join('rooms', 'room_schedules.room_id', '=', 'rooms.room_id')
            ->select('rooms.room_type', DB::raw('count(*) as total'))
            ->groupBy('rooms.room_type')
            ->get()
            ->pluck('total', 'room_type');

        $bigRooms = $rooms['big'] ?? 0;
        $smallRooms = $rooms['small'] ?? 0;

        // إجمالي الاحتياجات بناءً على عدد القاعات
        $required = [
            'رئيس_قاعة' => $bigRooms + $smallRooms,
            'امين_سر' => ($bigRooms * 2) + ($smallRooms * 1),
            'مراقب' => ($bigRooms * 8) + ($smallRooms * 4),
        ];

        // العدد المتاح فعليًا من قاعدة البيانات باستخدام Query Builder
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

        // تحديد النقص لكل دور
        $shortage = [];
        foreach ($required as $role => $count) {
            $shortage[$role] = max(0, $count - ($available[$role] ?? 0));
        }

        return [
            'bigRooms' => $bigRooms,
            'smallRooms' => $smallRooms,
            'required' => $required,
            'available' => $available,
            'shortage' => $shortage,
        ];
    }
}
