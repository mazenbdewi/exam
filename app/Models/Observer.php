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

            $isManual = ! app()->has('auto_context');

            // التحقق من سعة القاعة
            $room = $observer->room;
            if ($room) {
                $currentObservers = Observer::where('room_id', $room->room_id)->count();
                $maxObservers = $room->room_type === 'big' ? 11 : 6;

                if ($currentObservers >= $maxObservers) {
                    if ($isManual) {
                        Notification::make()
                            ->title('خطأ')
                            ->body('هذه القاعة ممتلئة بالكامل')
                            ->danger()
                            ->send();
                    }

                    return false;
                }
            }

            // التحقق من التكرار
            $existingObserver = Observer::where('user_id', $observer->user_id)
                ->where('schedule_id', $observer->schedule_id)
                ->exists();
            if ($existingObserver) {
                if ($isManual) {
                    Notification::make()
                        ->title('خطأ')
                        ->body('هذا المراقب معين مسبقًا لهذه المادة')
                        ->danger()
                        ->send();
                }

                return false;
            }

            $user = User::withCount('observers')->find($observer->user_id);

            if ($user) {
                $maxAllowed = $user->max_observers; // الحد الأقصى من عمود max_observers

                if ($user->observers_count >= $maxAllowed) {
                    if ($isManual) {
                        Notification::make()
                            ->title('تجاوز الحد المسموح')
                            ->body("لقد تجاوزت الحد الأقصى للمراقبات ({$maxAllowed}) للموظف {$user->name}")
                            ->danger()
                            ->send();
                    }

                    return false;
                }
            }

            return true;

            // التحقق من التعارض الزمني
            $schedule = Schedule::find($observer->schedule_id);
            if ($schedule) {
                $conflict = Observer::where('user_id', $observer->user_id)
                    ->whereHas('schedule', function ($query) use ($schedule) {
                        $query->where('schedule_exam_date', $schedule->schedule_exam_date)
                            ->where('schedule_time_slot', $schedule->schedule_time_slot);
                    })->exists();

                if ($conflict) {
                    if ($isManual) {
                        Notification::make()
                            ->title('خطأ')
                            ->body('تعارض في الجدول الزمني')
                            ->danger()
                            ->send();
                    }

                    return false;
                }
            }

        });
    }
}
