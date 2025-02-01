<?php

namespace App\Filament\Resources\ObserverResource\Pages;

use App\Filament\Resources\ObserverResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditObserver extends EditRecord
{
    protected static string $resource = ObserverResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
