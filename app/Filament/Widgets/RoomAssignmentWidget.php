<?php

namespace App\Filament\Widgets;

use App\Models\Schedule;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\DB;

class RoomAssignmentWidget extends Widget
{
    protected static string $view = 'filament.widgets.room-assignment-widget';

    public function getViewData(): array
    {
        $assignments = [];

        // Step 1: جلب كل الجداول مع الغرف المرتبطة بها
        $schedules = Schedule::with(['rooms'])->get();

        // Step 2: جلب الكوادر حسب النوع

        $presidents = DB::table('users')
            ->join('model_has_roles', function ($join) {
                $join->on('users.id', '=', 'model_has_roles.model_id')
                    ->where('model_has_roles.model_type', '=', 'App\\Models\\User');
            })
            ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
            ->where('roles.name', 'رئيس_قاعة')
            ->select('users.*')
            ->get();

        $secretaries = DB::table('users')
            ->join('model_has_roles', function ($join) {
                $join->on('users.id', '=', 'model_has_roles.model_id')
                    ->where('model_has_roles.model_type', '=', 'App\\Models\\User');
            })
            ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
            ->where('roles.name', 'امين_سر')
            ->select('users.*')
            ->get();

        $monitors = DB::table('users')
            ->join('model_has_roles', function ($join) {
                $join->on('users.id', '=', 'model_has_roles.model_id')
                    ->where('model_has_roles.model_type', '=', 'App\\Models\\User');
            })
            ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
            ->where('roles.name', 'مراقب')
            ->select('users.*')
            ->get();

        // لتتبع الأشخاص الذين تم تعيينهم في نفس اليوم والتوقيت
        $assigned = [];

        foreach ($schedules as $schedule) {
            foreach ($schedule->rooms as $room) {
                $key = $schedule->schedule_exam_date.'_'.$schedule->schedule_time_slot;

                // Skip إذا تم تعيين نفس الشخص لنفس التوقيت
                $assignedInSlot = $assigned[$key] ?? [
                    'presidents' => [],
                    'secretaries' => [],
                    'monitors' => [],
                ];

                // توزيع الرئيس
                $president = $presidents->first(function ($u) use ($assignedInSlot) {
                    return ! in_array($u->id, $assignedInSlot['presidents']);
                });

                if ($president) {
                    $assignedInSlot['presidents'][] = $president->id;
                }

                // توزيع الأمناء
                $secretaryCount = $room->room_type === 'big' ? 2 : 1;
                $secretariesAssigned = $secretaries->whereNotIn('id', $assignedInSlot['secretaries'])->take($secretaryCount);
                $assignedInSlot['secretaries'] = array_merge($assignedInSlot['secretaries'], $secretariesAssigned->pluck('id')->toArray());

                // توزيع المراقبين
                $monitorCount = $room->room_type === 'big' ? 8 : 4;
                $monitorsAssigned = $monitors->whereNotIn('id', $assignedInSlot['monitors'])->take($monitorCount);
                $assignedInSlot['monitors'] = array_merge($assignedInSlot['monitors'], $monitorsAssigned->pluck('id')->toArray());

                // حفظ التعيين
                $assigned[$key] = $assignedInSlot;

                $assignments[] = [
                    'room_name' => $room->room_name,
                    'room_type' => $room->room_type,
                    'date' => $schedule->schedule_exam_date,
                    'time_slot' => $schedule->schedule_time_slot,
                    'president' => $president?->name ?? 'غير متوفر',
                    'secretaries' => $secretariesAssigned->pluck('name')->toArray(),
                    'monitors' => $monitorsAssigned->pluck('name')->toArray(),
                ];
            }
        }

        return [
            'assignments' => $assignments,
        ];
    }
}
