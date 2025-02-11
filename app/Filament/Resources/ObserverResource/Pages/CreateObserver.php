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
            $maxObservers = $room->room_type === 'big' ? 11 : 6; // 11 للكبيرة، 6 للصغيرة

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
        if ($user && Observer::where('user_id', $data['user_id'])->count() >= $user->getMaxObserversByAge()) {
            Notification::make()
                ->title('🚫 تجاوز الحد الأقصى')
                ->body('تجاوز الحد الأقصى للمراقبات المسموحة')
                ->danger()
                ->send();
            $this->halt();
        }
        $user = User::find($data['user_id']);
        $room = Room::find($data['room_id']);

        if ($user && $room) {
            $userRole = $user->getRoleNames()->first();

            $maxAllowed = match ($userRole) {
                'مراقب' => ($room->room_type === 'big') ? 8 : 4,
                'امين_سر' => ($room->room_type === 'big') ? 2 : 1,
                'رئيس_قاعة' => 1,
                default => 0,
            };

            $currentCount = Observer::where('room_id', $room->room_id)
                ->whereHas('user.roles', function ($query) use ($userRole) {
                    $query->where('name', $userRole);
                })->count();

            if ($currentCount >= $maxAllowed) {
                $roleName = match ($userRole) {
                    'مراقب' => 'المراقبين',
                    'امين_سر' => 'أمناء السر',
                    'رئيس_قاعة' => 'رؤساء القاعات',
                    default => 'هذا الدور'
                };

                Notification::make()
                    ->title('🔴 خطأ في التعيين')
                    ->body("لا يمكن تعيين أكثر من $maxAllowed $roleName في هذه القاعة")
                    ->danger()
                    ->send();
                $this->halt();
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
