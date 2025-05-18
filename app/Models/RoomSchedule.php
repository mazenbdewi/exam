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

    public function room()
    {
        return $this->belongsTo(
            Room::class,       // النموذج المرتبط
            'room_id',         // Foreign key في جدول room_schedules
            'room_id'          // Primary key في جدول rooms
        );
    }

    /**
     * العلاقة مع نموذج الجدول الزمني (Schedule)
     */
    public function schedule()
    {
        return $this->belongsTo(
            Schedule::class,   // النموذج المرتبط
            'schedule_id',     // Foreign key في جدول room_schedules
            'schedule_id'     // Primary key في جدول schedules
        );
    }

    public function roomObservers()
    {
        return $this->hasManyThrough(
            Observer::class,
            Room::class,
            'room_id', // Foreign key على جدول rooms
            'room_id',  // Foreign key على جدول observers
            'room_id',  // Local key على جدول room_schedules
            'room_id'   // Local key على جدول rooms
        );
    }
}
