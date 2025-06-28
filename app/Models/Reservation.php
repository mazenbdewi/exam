<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Reservation extends Model
{
    use HasFactory;

    protected $table = 'reservations';

    protected $primaryKey = 'reservation_id';

    protected $fillable = [
        'schedule_id',
        'room_id',
        'capacity_mode',
        'used_capacity',
        'date',
        'time_slot',
    ];

    const CREATED_AT = 'reservation_created_at';

    const UPDATED_AT = 'reservation_updated_at';

    public function schedule()
    {
        return $this->belongsTo(Schedule::class, 'schedule_id', 'schedule_id');
    }

    public function room()
    {
        return $this->belongsTo(Room::class, 'room_id', 'room_id');
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($reservation) {
            $exists = Reservation::where('room_id', $reservation->room_id)
                ->where('date', $reservation->date)
                ->where('time_slot', $reservation->time_slot)
                ->exists();

            if ($exists) {
                throw new \Exception('القاعة محجوزة بالفعل في هذا التوقيت');
            }
        });
    }
}
