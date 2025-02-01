<?php

namespace App\Filament\Resources\ImportedDataResource\Pages;

use App\Filament\Resources\ImportedDataResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditImportedData extends EditRecord
{
    protected static string $resource = ImportedDataResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
