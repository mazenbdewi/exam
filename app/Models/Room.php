<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Room extends Model
{
    use HasFactory;

    protected $table = 'rooms';

    protected $primaryKey = 'room_id';

    protected $fillable = ['room_name', 'room_capacity_total', 'room_type'];

    const CREATED_AT = 'room_created_at';

    const UPDATED_AT = 'room_updated_at';

    // public function schedules()
    // {
    //     return $this->belongsToMany(Schedule::class, 'room_schedules', 'room_id', 'schedule_id');
    // }

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
}
