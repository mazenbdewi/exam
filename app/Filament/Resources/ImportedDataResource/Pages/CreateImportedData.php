<?php

namespace App\Filament\Resources\ImportedDataResource\Pages;

use App\Filament\Resources\ImportedDataResource;
use Filament\Resources\Pages\CreateRecord;

class CreateImportedData extends CreateRecord
{
    protected static string $resource = ImportedDataResource::class;

    protected function getFormActions(): array
    {
        return [

        ];
    }
}
