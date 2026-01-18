<?php

namespace App\Models;

use BezhanSalleh\FilamentShield\Traits\HasPanelShield;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, HasPanelShield, HasRoles, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'max_observers',
        'month_part',
        'observer_type',
        'monitoring_level',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'monitoring_level' => 'integer',
    ];

    protected $attributes = [
        'month_part' => 'any',
        'max_observers' => 18,
        'observer_type' => 'primary',
        'monitoring_level' => 1,
    ];

    public function delete()
    {
        if ($this->email === 'admin@admin.com') {
            throw new \Exception('لا يمكن حذف مستخدم محمي.');
        }

        return parent::delete();
    }

    public function schedules()
    {
        return $this->belongsToMany(
            Schedule::class,
            'schedule_user',
            'user_id',
            'schedule_id'
        )->withTimestamps();
    }

    /**
     * مهم جداً:
     * لا تضع هنا دالة roles() يدوياً.
     * HasRoles من Spatie يعرّفها كـ morphToMany وتقوم بتعبئة model_type تلقائياً.
     */
    public function observers()
    {
        return $this->hasMany(Observer::class);
    }

    public function canTakeMoreObservers(): bool
    {
        return $this->observers()->count() < $this->max_observers;
    }

    public function getAvailabilityForSchedule(Schedule $schedule): bool
    {
        $half = (Carbon::parse($schedule->schedule_exam_date)->day <= 15)
            ? 'first_half'
            : 'second_half';

        return $this->month_part === 'any' || $this->month_part === $half;
    }
}
