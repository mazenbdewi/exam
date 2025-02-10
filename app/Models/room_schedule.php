<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class room_schedule extends Model
{
    use HasFactory;

    protected $table = 'room_schedules';

    protected $primaryKey = 'room_schedule_id';

    public $timestamps = false;
}
