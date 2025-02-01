<?php

namespace App\Imports;

use App\Models\ImportedData;
use Maatwebsite\Excel\Concerns\ToModel;

class ImportedDataImport implements ToModel
{
    /**
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function model(array $row)
    {
        return new ImportedData([
            'number' => $row[0],
            'full_name' => $row[1],
            'father_name' => $row[2],
        ]);
    }
}
