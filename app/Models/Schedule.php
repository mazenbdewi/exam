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

    protected $fillable = ['schedule_subject', 'department_id', 'schedule_exam_date', 'schedule_academic_levels', 'schedule_time_slot', 'room_id', 'student_count'];

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
        return $this->belongsToMany(Room::class, 'room_schedules', 'schedule_id', 'room_id')
            ->withPivot(['allocated_seats', 'allocated_monitors']);
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

    public function conflictingRooms()
    {
        return $this->belongsToMany(Room::class, 'room_schedules', 'schedule_id', 'room_id')
            ->withPivot('allocated_seats')
            ->using(RoomSchedule::class);
    }

    public function getSharedRoomRequirementsAttribute()
    {
        return $this->rooms->map(function ($room) {
            $sharedMaterials = $room->schedules()
                ->where('schedule_time_slot', $this->schedule_time_slot)
                ->where('schedule_exam_date', $this->schedule_exam_date)
                ->count();

            return [
                'room_id' => $room->id,
                'shared_with' => $sharedMaterials,
                'required_monitors' => ceil(($room->room_type === 'big' ? 8 : 4) / $sharedMaterials),
            ];
        });
    }

    // في موديل Schedule
    protected $appends = ['formatted_academic_level', 'formatted_time_slot', 'unallocated_students'];

    public function getFormattedAcademicLevelAttribute()
    {
        $levels = [
            'first' => 'سنة أولى',
            'second' => 'سنة ثانية',
            'third' => 'سنة ثالثة',
            'fourth' => 'سنة رابعة',
            'fifth' => 'سنة خامسة',
        ];

        // إذا كان الحقل يحتوي على قيمة مفردة
        if (isset($levels[$this->schedule_academic_levels])) {
            return $levels[$this->schedule_academic_levels];
        }

        // إذا كان الحقل يحتوي على عدة قيم (مصفوفة أو JSON)
        if (is_array($this->schedule_academic_levels)) {
            return collect($this->schedule_academic_levels)
                ->map(function ($level) use ($levels) {
                    return $levels[$level] ?? $level;
                })
                ->implode('، ');
        }

        return $this->schedule_academic_levels; // العودة للقيمة الأصلية إذا لم تكن مطابقة
    }

    public function getFormattedTimeSlotAttribute()
    {
        $slots = [
            'morning' => 'صباحية',
            'night' => 'مسائية',
        ];

        return $slots[$this->schedule_time_slot] ?? $this->schedule_time_slot;
    }

    public function getRemainingCapacityAttribute()
    {
        return $this->student_count - $this->reservations()->sum('used_capacity');
    }

    public function allocatedRooms()
    {
        return $this->belongsToMany(Room::class, 'reservations', 'schedule_id', 'room_id')
            ->using(ReservationPivot::class)
            ->withPivot(['used_capacity', 'capacity_mode', 'date', 'time_slot']);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['allocatedRooms']);
    }

    // في app/Models/Schedule.php
    public function getRoomsDistributionAttribute()
    {
        return $this->allocatedRooms->map(function ($room) {
            return $room->room_name.' ('.$room->pivot->used_capacity.')';
        })->implode(', ');
    }

    // public function getUnallocatedStudentsAttribute()
    // {
    //     $allocated = $this->reservations()->sum('used_capacity');

    //     return max(0, $this->student_count - $allocated);
    // }

    public function getUnallocatedStudentsAttribute()
    {
        $allocated = $this->reservations_sum_used_capacity ?? 0;

        return max(0, $this->student_count - $allocated);
    }
}
