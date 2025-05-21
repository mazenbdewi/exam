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
        $room = Room::find($this->data['room_id']);
        if ($room) {
            $currentObservers = Observer::where('room_id', $room->room_id)->count();
            $maxObservers = $room->room_type === 'big' ? 11 : 6;

            if ($currentObservers >= $maxObservers) {
                Notification::make()
                    ->title('خطأ')
                    ->body('هذه القاعة ممتلئة بالكامل')
                    ->danger()
                    ->send();
                $this->halt();
            }
        }

        $data = $this->data;

        // التحقق من التكرار
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

        $user = User::find($data['user_id']);
        if ($user) {
            // التحقق من الحد الأقصى الشخصي للموظف
            if ($user->max_observers > 0 && $user->observers()->count() >= $user->max_observers) {
                Notification::make()
                    ->title('🚫 تجاوز الحد الأقصى')
                    ->body("الحد الأقصى المسموح: {$user->max_observers} مراقبة")
                    ->danger()
                    ->send();
                $this->halt();
            }

            // التحقق من الحدود بناءً على الدور ونوع القاعة
            $userRole = $user->getRoleNames()->first();
            $room = Room::find($data['room_id']);

            if ($room) {
                $roleMax = match ($userRole) {
                    'مراقب' => $room->room_type === 'big' ? 8 : 4,
                    'امين_سر' => $room->room_type === 'big' ? 2 : 1,
                    'رئيس_قاعة' => 1,
                    default => 0,
                };

                // التحقق من الحد الخاص بالدور
                $currentRoleCount = Observer::where('room_id', $room->room_id)
                    ->whereHas('user.roles', fn ($q) => $q->where('name', $userRole))
                    ->count();

                if ($currentRoleCount >= $roleMax) {
                    $roleName = match ($userRole) {
                        'مراقب' => 'المراقبين',
                        'امين_سر' => 'أمناء السر',
                        'رئيس_قاعة' => 'رؤساء القاعات',
                        default => 'هذا الدور'
                    };

                    Notification::make()
                        ->title('🔴 خطأ في التعيين')
                        ->body("لا يمكن تعيين أكثر من $roleMax $roleName في هذه القاعة")
                        ->danger()
                        ->send();
                    $this->halt();
                }
            }
        }
    }

    protected function afterCreate(): void
    {
        Notification::make()
            ->title('✅ نجاح')
            ->body('تم إضافة المراقب بنجاح')
            ->success()
            ->send();
    }
}
