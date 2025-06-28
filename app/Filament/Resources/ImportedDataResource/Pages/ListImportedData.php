<?php

namespace App\Filament\Resources\ImportedDataResource\Pages;

use App\Exports\StudentsExport;
use App\Filament\Resources\ImportedDataResource;
use App\Models\ImportedData;
use App\Models\Reservation;
use App\Models\Schedule;
use Filament\Actions;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Facades\Excel;

class ListImportedData extends ListRecords
{
    protected static string $resource = ImportedDataResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),

            Actions\Action::make('distributeStudents')
                ->label('توزيع الطلاب')
                ->form([
                    Select::make('schedule_id')
                        ->label('اختر المادة')
                        ->options(Schedule::all()->pluck('schedule_subject', 'schedule_id'))
                        ->searchable()
                        ->required()
                        ->reactive()
                        ->afterStateUpdated(function ($set, $get) {
                            $scheduleId = $get('schedule_id');
                            if ($scheduleId) {
                                $schedule = Schedule::find($scheduleId);
                                $reservations = $this->getReservationsForSchedule($schedule);

                                $totalUsedCapacity = $this->calculateTotalUsedCapacity($reservations);
                                $set('total_capacity', $totalUsedCapacity);

                                $roomsInfo = $this->generateRoomsInfo($reservations);
                                $set('rooms_info', $roomsInfo);
                            }
                        })
                        ->columnSpanFull(),

                    Placeholder::make('rooms_info')
                        ->label('تفاصيل القاعات')
                        ->content(function ($get) {
                            $scheduleId = $get('schedule_id');
                            if (! $scheduleId) {
                                return 'الرجاء اختيار المادة أولاً.';
                            }

                            return $get('rooms_info') ?? 'لا توجد قاعات لهذه المادة.';
                        })
                        ->columnSpanFull()
                        ->extraAttributes(['class' => 'whitespace-pre-wrap text-right']),

                    Placeholder::make('total_capacity')
                        ->label('إجمالي السعة المستخدمة')
                        ->content(function ($get) {
                            $scheduleId = $get('schedule_id');
                            if (! $scheduleId) {
                                return 'الرجاء اختيار المادة أولاً.';
                            }

                            $total = $get('total_capacity') ?? 0;
                            $studentsCount = ImportedData::count();
                            $status = $studentsCount > $total ? '⚠️ السعة غير كافية' : '✅ السعة كافية';

                            return "عدد المقاعد المتاحة: {$total} مقعد\nعدد الطلاب: {$studentsCount} طالب\nالحالة: {$status}";
                        }),
                ])
                ->action(function (array $data) {
                    $this->distributeStudents($data['schedule_id']);
                }),

            Actions\Action::make('exportExcel')
                ->label('تصدير بيانات الطلاب إلى Excel')
                ->form([
                    Select::make('schedule_id')
                        ->label('اختر المادة')
                        ->options(Schedule::all()->pluck('schedule_subject', 'schedule_id'))
                        ->searchable()
                        ->required(),
                ])
                ->action(function (array $data) {
                    $schedule_id = $data['schedule_id'];
                    $schedule = Schedule::find($schedule_id);

                    return Excel::download(
                        new StudentsExport($schedule),
                        'students-'.now()->format('Y-m-d').'.xlsx'
                    );
                }),

            Actions\Action::make('deleteAll')
                ->label('حذف جميع بيانات الطلاب')
                ->requiresConfirmation()
                ->action(function () {
                    ImportedData::truncate();
                    Notification::make()
                        ->title('تم حذف جميع بيانات الطلاب بنجاح')
                        ->success()
                        ->send();
                })
                ->color('danger')
                ->icon('heroicon-o-trash'),
        ];
    }

    private function getReservationsForSchedule(Schedule $schedule): Collection
    {
        return Reservation::with('room')
            ->where('schedule_id', $schedule->schedule_id)
            ->where('date', $schedule->schedule_exam_date)
            ->where('time_slot', $schedule->schedule_time_slot)
            ->get();
    }

    private function generateRoomsInfo(Collection $reservations): string
    {
        if ($reservations->isEmpty()) {
            return 'لا توجد قاعات مخصصة لهذه المادة.';
        }

        $info = "تفاصيل القاعات وعدد المقاعد:\n";
        $info .= "\n";

        foreach ($reservations as $reservation) {
            $roomName = $reservation->room->room_name ?? 'قاعة غير معروفة';
            $usedCapacity = $reservation->used_capacity;
            $info .= "• القاعة: {$roomName}  عدد المقاعد المتاحة: {$usedCapacity}\n";
        }

        return $info;
    }

    private function calculateTotalUsedCapacity(Collection $reservations): int
    {
        return $reservations->sum('used_capacity');
    }

    private function distributeStudents($scheduleId)
    {
        $schedule = Schedule::find($scheduleId);

        if (! $schedule) {
            Notification::make()
                ->title('خطأ في البيانات')
                ->body('المادة المحددة غير موجودة.')
                ->color('danger')
                ->send();

            return;
        }

        $reservations = $this->getReservationsForSchedule($schedule);

        if ($reservations->isEmpty()) {
            Notification::make()
                ->title('خطأ في التوزيع')
                ->body('لا توجد قاعات محجوزة لهذه المادة.')
                ->color('danger')
                ->send();

            return;
        }

        $students = ImportedData::orderBy('imported_data_id')->get();
        $totalUsedCapacity = $this->calculateTotalUsedCapacity($reservations);

        if ($students->count() > $totalUsedCapacity) {
            Notification::make()
                ->title('خطأ في التوزيع')
                ->body('عدد الطلاب أكبر من إجمالي المقاعد المتاحة.')
                ->color('danger')
                ->send();

            return;
        }

        foreach ($reservations as $reservation) {
            $usedCapacity = $reservation->used_capacity;
            $studentsToAssign = $students->splice(0, $usedCapacity);

            foreach ($studentsToAssign as $student) {
                $student->room_id = $reservation->room_id;
                $student->save();
            }

            if ($students->isEmpty()) {
                break;
            }
        }

        Notification::make()
            ->title('تم توزيع الطلاب بنجاح')
            ->success()
            ->send();
    }
}
