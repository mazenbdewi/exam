<?php

namespace App\Models;

use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Observer extends Model
{
    use HasFactory;

    protected $table = 'observers';

    protected $primaryKey = 'observer_id';

    protected $fillable = [
        'user_id',
        'schedule_id',
        'room_id',
    ];

    const CREATED_AT = 'observer_created_at';

    const UPDATED_AT = 'observer_updated_at';

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function schedule()
    {
        return $this->belongsTo(Schedule::class, 'schedule_id');
    }

    public function room()
    {
        return $this->belongsTo(Room::class, 'room_id');
    }

    // protected static function boot()
    // {
    //     parent::boot();

    //     static::creating(function ($observer) {
    //         // التحقق من أن المراقب لم يتم تعيينه مسبقًا لنفس المادة
    //         $existingObserver = Observer::where('user_id', $observer->user_id)
    //             ->where('schedule_id', $observer->schedule_id)
    //             ->exists();
    //         if ($existingObserver) {
    //             Notification::make()
    //                 ->title('خطأ')
    //                 ->body('هذا المراقب تم تعيينه مسبقًا لهذه المادة.')
    //                 ->danger()
    //                 ->send();

    //             // منع العملية
    //             return false;
    //         }

    //         // التحقق من عدد المراقبات المسموح بها للمراقب
    //         $user = User::find($observer->user_id);
    //         if ($user) {
    //             $maxObservers = $user->getMaxObserversByAge();
    //             $currentObservers = Observer::where('user_id', $observer->user_id)->count();
    //             if ($currentObservers >= $maxObservers) {
    //                 Notification::make()
    //                     ->title('خطأ')
    //                     ->body('هذا المراقب قد تجاوز الحد الأقصى للمراقبات المسموح بها.')
    //                     ->danger()
    //                     ->send();

    //                 // منع العملية
    //                 return false;
    //             }
    //         }

    //         // التحقق من سعة القاعة
    //         $room = Room::find($observer->room_id);
    //         if ($room) {
    //             $currentObserversInRoom = Observer::where('room_id', $observer->room_id)->count();
    //             $maxObserversInRoom = $room->room_type === 'big' ? 8 : 4;
    //             if ($currentObserversInRoom >= $maxObserversInRoom) {
    //                 Notification::make()
    //                     ->title('خطأ')
    //                     ->body('هذه القاعة ممتلئة بالمراقبين.')
    //                     ->danger()
    //                     ->send();

    //                 // منع العملية
    //                 return false;
    //             }
    //         }
    //     });
    // }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($observer) {
            // التحقق من أن المراقب لم يتم تعيينه مسبقًا لنفس المادة
            $existingObserver = Observer::where('user_id', $observer->user_id)
                ->where('schedule_id', $observer->schedule_id)
                ->exists();
            if ($existingObserver) {
                Notification::make()
                    ->title('خطأ')
                    ->body('هذا المراقب تم تعيينه مسبقًا لهذه المادة.')
                    ->danger()
                    ->send();

                // منع العملية
                return false;
            }

            // التحقق من عدد المراقبات المسموح بها للمراقب
            $user = User::find($observer->user_id);
            if ($user) {
                $maxObservers = $user->getMaxObserversByAge();
                $currentObservers = Observer::where('user_id', $observer->user_id)->count();
                if ($currentObservers >= $maxObservers) {
                    Notification::make()
                        ->title('خطأ')
                        ->body('هذا المراقب قد تجاوز الحد الأقصى للمراقبات المسموح بها.')
                        ->danger()
                        ->send();

                    // منع العملية
                    return false;
                }
            }

            // التحقق من سعة القاعة
            $room = Room::find($observer->room_id);
            if ($room) {
                $currentObserversInRoom = Observer::where('room_id', $observer->room_id)->count();
                $maxObserversInRoom = $room->room_type === 'big' ? 8 : 4;
                if ($currentObserversInRoom >= $maxObserversInRoom) {
                    Notification::make()
                        ->title('خطأ')
                        ->body('هذه القاعة ممتلئة بالمراقبين.')
                        ->danger()
                        ->send();

                    // منع العملية
                    return false;
                }
            }

            // التحقق من تعارض الجداول الزمنية
            $schedule = Schedule::find($observer->schedule_id);
            if ($schedule) {
                $conflictingObservers = Observer::where('user_id', $observer->user_id)
                    ->whereHas('schedule', function ($query) use ($schedule) {
                        $query->where('schedule_exam_date', $schedule->schedule_exam_date)
                            ->where('schedule_time_slot', $schedule->schedule_time_slot);
                    })
                    ->exists();
                if ($conflictingObservers) {
                    Notification::make()
                        ->title('خطأ')
                        ->body('هذا المراقب معين بالفعل في جدول آخر في نفس الوقت والتاريخ.')
                        ->danger()
                        ->send();

                    // منع العملية
                    return false;
                }
            }
        });
    }
}
