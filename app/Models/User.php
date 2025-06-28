<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use BezhanSalleh\FilamentShield\Traits\HasPanelShield;
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
        if ($this->email == 'admin@admin.com') {
            throw new \Exception('لا يمكن حذف مستخدم محمي.');
        }

        return parent::delete();
    }

    public function schedules()
    {
        return $this->belongsToMany(Schedule::class, 'schedule_user', 'user_id', 'schedule_id')->withTimestamps('schedule_user_created_at', 'schedule_user_updated_at');
    }

    public function roles()
    {
        return $this->belongsToMany(Role::class, 'model_has_roles', 'model_id', 'role_id');
    }

    public function observers()
    {
        return $this->hasMany(Observer::class);
    }

    // public function canTakeMoreObservers(): bool
    // {
    //     // حساب العبء بناءً على مستوى المراقبة
    //     $monitoringLoad = $this->observers->sum(function ($observer) {
    //         return match ($observer->monitoring_level) {
    //             1 => 1.0,
    //             2 => 0.5,
    //             3 => 0.25,
    //             default => 0
    //         };
    //     });

    //     // الحد الأقصى للعبء (مثال: 2.0 يعني 2 مهمة كاملة)
    //     $maxLoad = $this->max_observers ?? 2.0;

    //     return $monitoringLoad < $maxLoad;
    // }
    // في app/Models/User.php
    // public function canTakeMoreObservers(): bool
    // {
    //     // حساب العبء الحالي
    //     $currentLoad = $this->observers->sum(function ($observer) {
    //         return match ($observer->monitoring_level) {
    //             1 => 1.0,
    //             2 => 0.5,
    //             3 => 0.25,
    //             default => 0
    //         };
    //     });

    //     // الحد الأقصى (مثال: 2.0 يعني مهمتين كاملتين)
    //     $maxLoad = $this->max_observers ?? 2.0;

    //     return $currentLoad < $maxLoad;
    // }

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
