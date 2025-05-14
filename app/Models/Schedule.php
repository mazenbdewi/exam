<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Schedule extends Model
{
    use HasFactory;

    protected $table = 'schedules';

    protected $primaryKey = 'schedule_id';

    protected $fillable = ['schedule_subject', 'department_id', 'schedule_exam_date', 'schedule_academic_levels', 'schedule_time_slot', 'room_id'];

    const CREATED_AT = 'schedule_created_at';

    const UPDATED_AT = 'schedule_updated_at';

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id', 'department_id');
    }

    public function observers()
    {
        return $this->hasMany(Observer::class, 'schedule_id', 'schedule_id');
    }

    public function rooms()
    {
        return $this->belongsToMany(Room::class, 'room_schedules', 'schedule_id', 'room_id');
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'schedule_user', 'schedule_id', 'user_id')->withTimestamps('schedule_user_created_at', 'schedule_user_updated_at');
    }

    public function reservations()
    {
        return $this->hasMany(Reservation::class, 'schedule_id', 'schedule_id');
    }

    public function roomsCount()
    {
        return $this->rooms()->count();
    }
}
