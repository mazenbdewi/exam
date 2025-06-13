<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
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
    ];

    protected $attributes = [
        'month_part' => 'any',
        'max_observers' => 18,
    ];

    // public function getAgeAttribute()
    // {
    //     return Carbon::parse($this->birth_date)->age;
    // }

    // public function getMaxObservers(): array
    // {
    //     $age = $this->age;

    //     return [
    //         'daily' => match (true) {
    //             $age >= 60 => 1,  // كبار السن: حد يومي أقل
    //             $age >= 50 => 2,
    //             $age >= 40 => 3,
    //             default => 4      // الشباب: حد يومي أعلى
    //         },
    //         'total' => match (true) {
    //             $age >= 60 => 5,  // كبار السن: حد إجمالي أقل
    //             $age >= 50 => 8,
    //             $age >= 40 => 12,
    //             default => 15     // الشباب: حد إجمالي أعلى
    //         },
    //     ];
    // }

    // public function getMaxObserversByAge(): int
    // {
    //     $age = Carbon::parse($this->birth_date)->age; // حساب العمر

    //     if ($age >= 60) {
    //         return 6;
    //     } elseif ($age > 50) {
    //         return 10;
    //     } elseif ($age > 40) {
    //         return 12;
    //     } else {
    //         return 18;
    //     }
    // }

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

    public function canTakeMoreObservers(): bool
    {
        if ($this->max_observers === 0) {
            return true; // 0 يعني غير محدود
        }

        return $this->observers()->count() < $this->max_observers;
    }
    // public function exceedsMaxObservers(): bool
    // {

    //     $currentObserversCount = $this->observers()->count();

    //     $maxAllowed = $this->max_observers ?? 18;

    //     return $currentObserversCount >= $maxAllowed;

    //     // return $this->observers()->count() >= $this->max_observers;

    // }
    // public function exceedsMaxObservers(): bool
    // {
    //     $max = $this->getMaxObserversByAge();

    //     return $this->observers()->count() >= $max;
    // }
}
