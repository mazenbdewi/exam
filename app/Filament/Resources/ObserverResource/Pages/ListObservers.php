<?php

namespace App\Filament\Resources\ObserverResource\Pages;

use App\Filament\Resources\ObserverResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListObservers extends ListRecords
{
    protected static string $resource = ObserverResource::class;

    protected function getHeaderActions(): array
    {

        return [
            Actions\CreateAction::make()
                ->hidden(fn () => ! auth()->user()->hasRole('super_admin')),
        ];

    }
}
