<?php

namespace App\Imports;

use App\Models\ImportedData;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithStartRow;

class ImportedDataImport implements ToModel, WithStartRow
{
    public function startRow(): int
    {
        return 2;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function model(array $row)
    {

        return new ImportedData([
            'number' => $row[0],
            'full_name' => $row[1] ?? null,
            'father_name' => $row[2] ?? null,
        ]);
    }
}
