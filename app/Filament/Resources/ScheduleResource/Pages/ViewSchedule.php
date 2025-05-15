<?php

namespace App\Filament\Resources\ScheduleResource\Pages;

use App\Filament\Resources\ScheduleResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewSchedule extends ViewRecord
{
    protected static string $resource = ScheduleResource::class;

    // protected function getHeaderActions(): array
    // {
    //     return [
    //         Actions\EditAction::make(),
    //     ];
    // }

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('assign_observers')
                ->label('توزيع المراقبين')
                ->icon('heroicon-o-user-group')
                ->color('primary')
                ->action(function () {
                    $service = new \App\Services\ObserverAssignmentService;
                    $service->assignObservers(collect([$this->record]));

                    \Filament\Notifications\Notification::make()
                        ->title('تم توزيع المراقبين بنجاح')
                        ->success()
                        ->send();
                })
                ->visible(fn () => auth()->user()->can('assign_observers')),
        ];
    }
}
