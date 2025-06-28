<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Room extends Model
{
    use HasFactory;

    protected $table = 'rooms';

    protected $primaryKey = 'room_id';

    protected $fillable = ['room_name', 'room_capacity_total', 'room_type', 'room_priority'];

    const CREATED_AT = 'room_created_at';

    const UPDATED_AT = 'room_updated_at';

    public function getMaxObservers(): int
    {
        return $this->room_type === 'big' ? 8 : 4;
    }

    public function getCurrentObservers(): int
    {
        return $this->observers()->count();
    }

    public function observers()
    {
        return $this->hasMany(Observer::class, 'room_id');
    }

    public function getEffectiveCapacity($capacityMode): int
    {
        $percentage = $capacityMode === 'half' ? 50 : 100;

        return (int) ($this->room_capacity_total * $percentage / 100);
    }

    public function reservations()
    {
        return $this->hasMany(Reservation::class, 'room_id', 'room_id');
    }

    // في موديل Room (App\Models\Room)
    public function schedules()
    {
        return $this->belongsToMany(Schedule::class, 'room_schedules', 'room_id', 'schedule_id')
            ->withPivot(['allocated_seats', 'allocated_monitors']);
    }

    // public function isSmall(): bool
    // {
    //     return $this->room_type === 'small';
    // }

    // public function isBig(): bool
    // {
    //     return $this->room_type === 'big';
    // }

    // // في نموذج User
    // public function scopeRole($query, $role)
    // {
    //     return $query->whereHas('roles', fn ($q) => $q->where('name', $role));
    // }

    // 1. العلاقات مع العدادات
    public function presidents()
    {
        return $this->hasManyThrough(
            User::class,
            Observer::class,
            'room_id',
            'id',
            'room_id',
            'user_id'
        )->whereHas('roles', fn ($q) => $q->where('name', 'رئيس_قاعة'));
    }

    public function secretaries()
    {
        return $this->hasManyThrough(
            User::class,
            Observer::class,
            'room_id',
            'id',
            'room_id',
            'user_id'
        )->whereHas('roles', fn ($q) => $q->where('name', 'امين_سر'));
    }

    public function monitors()
    {
        return $this->hasManyThrough(
            User::class,
            Observer::class,
            'room_id',
            'id',
            'room_id',
            'user_id'
        )->whereHas('roles', fn ($q) => $q->where('name', 'مراقب'));
    }

    public function scopeCompleted($query)
    {
        return $query->where(function ($q) {
            $q->whereHas('presidents', fn ($subQ) => $subQ, '>=', 1) // إضافة Closure حتى لو فارغ
                ->where(function ($subQ) {
                    $subQ->where('room_type', 'big')
                        ->whereHas('secretaries', fn ($q) => $q, '>=', 2)
                        ->whereHas('monitors', fn ($q) => $q, '>=', 8);
                })
                ->orWhere(function ($subQ) {
                    $subQ->where('room_type', 'small')
                        ->whereHas('secretaries', fn ($q) => $q, '>=', 1)
                        ->whereHas('monitors', fn ($q) => $q, '>=', 4);
                });
        });
    }

    public function scopeIncomplete($query)
    {
        return $query->where(function ($q) {
            $q->has('presidents', '<', 1) // تغيير هنا
                ->orWhere(function ($subQ) {
                    $subQ->where('room_type', 'big')
                        ->where(function ($q) {
                            $q->has('secretaries', '<', 2) // تغيير هنا
                                ->orHas('monitors', '<', 8); // تغيير هنا
                        });
                })
                ->orWhere(function ($subQ) {
                    $subQ->where('room_type', 'small')
                        ->where(function ($q) {
                            $q->has('secretaries', '<', 1) // تغيير هنا
                                ->orHas('monitors', '<', 4); // تغيير هنا
                        });
                });
        });
    }

    public function scheduledExams()
    {
        return $this->belongsToMany(Schedule::class, 'reservations', 'room_id', 'schedule_id')
            ->using(ReservationPivot::class)
            ->withPivot(['used_capacity', 'capacity_mode', 'date', 'time_slot']);
    }
}
