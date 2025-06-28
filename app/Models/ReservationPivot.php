<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class ReservationPivot extends Pivot
{
    protected $table = 'reservations';

    protected $primaryKey = 'reservation_id';

    protected $fillable = [
        'used_capacity',
        'capacity_mode',
        'date',
        'time_slot',
    ];
}
