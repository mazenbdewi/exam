<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RoomSchedule extends Model
{
    use HasFactory;

    protected $table = 'room_schedules';

    protected $primaryKey = 'room_schedule_id';

    protected $fillable = [
        'room_id',
        'schedule_id',
        'student_count',
        'allocated_seats',
        'allocated_monitors',
    ];

    public $incrementing = true; // أو false إذا كنت تستخدم مفتاح مركب

    public $timestamps = false;
}
