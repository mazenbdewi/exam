<?php

namespace App\Filament\Resources\ScheduleResource\Pages;

use App\Filament\Resources\ScheduleResource;
use App\Models\Schedule;
use Filament\Resources\Pages\Page;

class ViewDistribution extends Page
{
    protected static string $resource = ScheduleResource::class;

    protected static string $view = 'filament.resources.schedule-resource.pages.view-distribution';

    public Schedule $record;

    public function mount(Schedule $record): void
    {
        $this->record = $record;
    }

    public function getReservationsProperty()
    {
        return $this->record->reservations()->with('room')->get();
    }

    public function getStatsProperty()
    {
        return [
            'total_capacity' => $this->reservations->sum('used_capacity'),
            'total_rooms' => $this->reservations->count(),
            'remaining_capacity' => $this->record->estimated_students - $this->reservations->sum('used_capacity'),
        ];
    }
}
