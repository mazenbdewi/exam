<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ImportedData extends Model
{
    use HasFactory;

    protected $table = 'imported_data';

    protected $primaryKey = 'imported_data_id';

    protected $fillable = [
        'number',
        'full_name',
        'father_name',
        'room_id',
    ];

    const CREATED_AT = 'imported_data_created_at';

    const UPDATED_AT = 'imported_data_updated_at';

    public function room()
    {
        return $this->belongsTo(Room::class, 'room_id');
    }
}
