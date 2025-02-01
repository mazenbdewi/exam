<?php

namespace App\Filament\Resources\ObserverResource\Pages;

use App\Filament\Resources\ObserverResource;
use App\Models\Observer;
use App\Models\Room;
use App\Models\User;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateObserver extends CreateRecord
{
    protected static string $resource = ObserverResource::class;

    protected function beforeCreate(): void
    {
        $data = $this->data;

        // 1. التحقق من التكرار
        if (Observer::where('user_id', $data['user_id'])
            ->where('schedule_id', $data['schedule_id'])
            ->exists()) {
            Notification::make()
                ->title('⚠️ خطأ')
                ->body('هذا المراقب مسجل بالفعل لهذه المادة')
                ->danger()
                ->send();
            $this->halt();
        }

        // 2. التحقق من الحد الأقصى للمراقبات
        $user = User::find($data['user_id']);
        if ($user && Observer::where('user_id', $data['user_id'])->count() >= $user->getMaxObserversByAge()) {
            Notification::make()
                ->title('🚫 تجاوز الحد الأقصى')
                ->body('تجاوز الحد الأقصى للمراقبات المسموحة')
                ->danger()
                ->send();
            $this->halt();
        }

        // 3. التحقق من سعة القاعة
        $room = Room::find($data['room_id']);
        if ($room) {
            $current = Observer::where('room_id', $data['room_id'])->count();
            $max = ($room->room_type === 'big') ? 8 : 4;
            if ($current >= $max) {
                Notification::make()
                    ->title('🔴 خطأ في القاعة')
                    ->body('القاعة ممتلئة ولا يوجد أماكن شاغرة')
                    ->danger()
                    ->send();
                $this->halt();
            }
        }
    }

    // protected function afterCreate(): void
    // {
    //     Notification::make()
    //         ->title('✅ نجاح')
    //         ->body('تم إضافة المراقب بنجاح')
    //         ->success()
    //         ->send();
    // }
}
