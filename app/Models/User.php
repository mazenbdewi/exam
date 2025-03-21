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
        'birth_date',
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
        'birth_date' => 'datetime:Y-m-d',
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    public function getAgeAttribute()
    {
        return Carbon::parse($this->birth_date)->age;
    }

    public function getMaxObservers(): array
    {
        $age = $this->age;

        return [
            'daily' => match (true) {
                $age >= 60 => 1,  // كبار السن: حد يومي أقل
                $age >= 50 => 2,
                $age >= 40 => 3,
                default => 4      // الشباب: حد يومي أعلى
            },
            'total' => match (true) {
                $age >= 60 => 5,  // كبار السن: حد إجمالي أقل
                $age >= 50 => 8,
                $age >= 40 => 12,
                default => 15     // الشباب: حد إجمالي أعلى
            },
        ];
    }

    // // app/Models/User.php
    // public function getMaxObservers(): array
    // {
    //     $age = Carbon::parse($this->birth_date)->age;

    //     return [
    //         'daily' => match (true) {
    //             $age >= 60 => 2,
    //             $age >= 50 => 3,
    //             $age >= 40 => 4,
    //             default => 5
    //         },
    //         'total' => match (true) {
    //             $age >= 60 => 6,
    //             $age >= 50 => 10,
    //             $age >= 40 => 12,
    //             default => 18
    //         },
    //     ];
    // }

    public function getMaxObserversByAge(): int
    {
        $age = Carbon::parse($this->birth_date)->age; // حساب العمر

        if ($age >= 60) {
            return 6;
        } elseif ($age > 50) {
            return 10;
        } elseif ($age > 40) {
            return 12;
        } else {
            return 18;
        }
    }

    public function delete()
    {
        if ($this->email == 'mazen@mazen.mazen') {
            throw new \Exception('لا يمكن حذف مستخدم محمي.');
        }

        return parent::delete();
    }
    // public function getMaxObserversByAge()
    // {
    //     $age = Carbon::parse($this->birthdate)->age;

    //     return match (true) {
    //         $age >= 60 => 6,
    //         $age >= 50 => 10,
    //         $age >= 40 => 12,
    //         default => 18
    //     };
    // }
    // public function getAllowedAssignmentsAttribute()
    // {
    //     $age = $this->birth_date->age; // افتراض أن لديك `birth_date` في جدول المستخدمين

    //     if ($age > 60) {
    //         return 6;
    //     } elseif ($age > 50) {
    //         return 10;
    //     } elseif ($age > 40) {
    //         return 12;
    //     } else {
    //         return 18;
    //     }
    // }

    // public function assignments()
    // {
    //     return $this->belongsToMany(Assignment::class, 'assignments_user', 'user_id', 'assignment_id');
    // }
    // في النموذج User.php
    public function schedules()
    {
        return $this->belongsToMany(Schedule::class, 'schedule_user', 'user_id', 'schedule_id')->withTimestamps('schedule_user_created_at', 'schedule_user_updated_at');
    }

    public function roles()
    {
        return $this->belongsToMany(Role::class, 'model_has_roles', 'model_id', 'role_id');
        // ->where('model_type', User::class); // تأكد من أن model_type هو User
    }

    public function observers()
    {
        return $this->hasMany(Observer::class, 'user_id');
    }
    // public function roles()
    // {
    //     return $this->belongsToMany(Role::class, 'model_has_roles', 'model_id', 'role_id');
    // }
}
