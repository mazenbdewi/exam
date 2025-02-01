<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ScheduleUser extends Model
{
    use HasFactory;

    protected $table = 'schedule_user';

    protected $primaryKey = 'schedule_user_id';

    protected $fillable = ['schedule_user_id', 'schedule_id', 'user_id', 'schedule_user_created_at', 'schedule_user_updated_at'];

    const CREATED_AT = 'schedule_user_created_at';

    const UPDATED_AT = 'schedule_user_updated_at';
}
