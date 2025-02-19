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

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($observer) {

            $room = $observer->room;
            if ($room) {
                $currentObservers = Observer::where('room_id', $room->room_id)->count();
                $maxObservers = $room->room_type === 'big' ? 11 : 6; // 11 للكبيرة، 6 للصغيرة

                if ($currentObservers >= $maxObservers) {
                    Notification::make()
                        ->title('خطأ')
                        ->body('هذه القاعة ممتلئة بالكامل')
                        ->danger()
                        ->send();

                    return false;
                }
            }
            $existingObserver = Observer::where('user_id', $observer->user_id)
                ->where('schedule_id', $observer->schedule_id)
                ->exists();
            if ($existingObserver) {
                Notification::make()
                    ->title('خطأ')
                    ->body('هذا المراقب تم تعيينه مسبقًا لهذه المادة.')
                    ->danger()
                    ->send();

                return false;
            }

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

                    return false;
                }
            }
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

                    return false;
                }
            }
        });
    }
}
