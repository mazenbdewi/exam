<?php

namespace App\Filament\Widgets;

use App\Models\RoomSchedule;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Card;

class StaffStatsWidget extends StatsOverviewWidget
{
    protected function getCards(): array
    {
        $totalRequired = [
            'رئيس' => 0,
            'أمين سر' => 0,
            'مراقب' => 0,
        ];

        $totalCurrent = [
            'رئيس' => 0,
            'أمين سر' => 0,
            'مراقب' => 0,
        ];

        $schedules = RoomSchedule::with('room')
            ->withCount([
                'roomObservers as president_count' => fn ($q) => $q->whereHas('user.roles', fn ($q) => $q->where('name', 'رئيس_قاعة')),
                'roomObservers as secretary_count' => fn ($q) => $q->whereHas('user.roles', fn ($q) => $q->where('name', 'امين_سر')),
                'roomObservers as observer_count' => fn ($q) => $q->whereHas('user.roles', fn ($q) => $q->where('name', 'مراقب')),
            ])
            ->get();

        foreach ($schedules as $record) {
            $roomType = $record->room->room_type;

            $required = [
                'رئيس' => 1,
                'أمين سر' => $roomType === 'big' ? 2 : 1,
                'مراقب' => $roomType === 'big' ? 8 : 4,
            ];

            $totalRequired['رئيس'] += $required['رئيس'];
            $totalRequired['أمين سر'] += $required['أمين سر'];
            $totalRequired['مراقب'] += $required['مراقب'];

            $totalCurrent['رئيس'] += $record->president_count;
            $totalCurrent['أمين سر'] += $record->secretary_count;
            $totalCurrent['مراقب'] += $record->observer_count;
        }

        return [
            $this->buildCard('رؤساء القاعات', $totalCurrent['رئيس'], $totalRequired['رئيس']),
            $this->buildCard('أمناء السر', $totalCurrent['أمين سر'], $totalRequired['أمين سر']),
            $this->buildCard('المراقبين', $totalCurrent['مراقب'], $totalRequired['مراقب']),
        ];
    }

    private function buildCard(string $label, int $current, int $required): Card
    {
        $diff = $current - $required;

        if ($diff > 0) {
            $suffix = ' 🔺 زيادة: '.$diff;
            $color = 'success';
            $icon = 'heroicon-o-arrow-trending-up';
        } elseif ($diff < 0) {
            $suffix = ' 🔻 نقص: '.abs($diff);
            $color = 'danger';
            $icon = 'heroicon-o-arrow-trending-down';
        } else {
            $suffix = ' ✅ مكتمل';
            $color = 'primary';
            $icon = 'heroicon-o-check-circle';
        }

        return Card::make($label, "{$current} من {$required} {$suffix}")
            ->color($color)
            ->icon($icon);
    }
}
