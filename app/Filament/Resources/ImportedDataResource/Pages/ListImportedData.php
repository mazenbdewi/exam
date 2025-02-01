<?php

namespace App\Filament\Resources\ImportedDataResource\Pages;

use App\Exports\StudentsExport;
use App\Filament\Resources\ImportedDataResource;
use App\Models\ImportedData;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\Schedule;
use Filament\Actions;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Arr;
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
                        ->columnSpanFull(), // تأكد من أن الحقل يعمل بشكل صحيح
                    // إضافة حقل اختيار القاعات
                    // Select::make('rooms')
                    //     ->label('اختر القاعات')
                    //     ->options(Room::all()->pluck('room_name', 'room_id'))
                    //     ->multiple()
                    //     ->searchable()
                    //     ->required()
                    //     ->reactive()
                    //     ->afterStateUpdated(function ($set, $get) {
                    //         $set('total_capacity', $this->calculateTotalCapacity($get('rooms'), $get('capacity_mode')));
                    //     }),

                    // Select::make('rooms')
                    //     ->label('اختر القاعات (الترتيب مهم)')
                    //     ->options(Room::orderBy('room_name')->pluck('room_name', 'room_id')) // الترتيب حسب الاسم
                    //     ->multiple()
                    //     ->searchable()
                    //     ->required()
                    //     ->reactive()
                    //     ->afterStateUpdated(function ($set, $get) {
                    //         $set('total_capacity', $this->calculateTotalCapacity($get('rooms'), $get('capacity_mode')));
                    //     }),
                    Select::make('rooms')
                        ->label('اختر القاعات')
                        ->options(Room::all()->pluck('room_name', 'room_id')) // استخدام الـ ID كمفتاح
                        ->multiple()
                        ->searchable()
                        ->required()
                        ->reactive()
                        ->afterStateUpdated(function ($set, $get) {
                            $set('total_capacity', $this->calculateTotalCapacity($get('rooms'), $get('capacity_mode')));
                        }),
                    Select::make('capacity_mode')
                        ->label('نسبة الاستيعاب')
                        ->options([
                            'full' => 'استيعاب كامل',
                            'half' => 'نصف الاستيعاب',
                        ])
                        ->default('full')
                        ->required()
                        ->reactive()
                        ->afterStateUpdated(function ($set, $get) {
                            $set('total_capacity', $this->calculateTotalCapacity($get('rooms'), $get('capacity_mode')));
                        }),

                    Placeholder::make('total_capacity')
                        ->label('السعة الإجمالية')
                        ->content(function ($get) {
                            $total = $this->calculateTotalCapacity($get('rooms'), $get('capacity_mode'));
                            $studentsCount = ImportedData::count();
                            $status = $studentsCount > $total ? '⚠️ غير كافية' : '✅ كافية';

                            return "السعة: $total مقعد | عدد الطلاب: $studentsCount | الحالة: $status";
                        }),
                ])
                ->action(function (array $data) { // يتم استقبال البيانات هنا
                    $this->distributeStudents(
                        rooms: Room::whereIn('room_id', $data['rooms'])->get(),
                        scheduleId: $data['schedule_id'], // أرسل schedule_id هنا
                        capacityMode: $data['capacity_mode']
                    );
                }),
            // ->action(function (array $data) {
            //     $rooms = Room::whereIn('room_id', $data['rooms'])->get();
            //     $capacityMode = $data['capacity_mode'];
            //     $this->distributeStudents($rooms, $capacityMode);
            // }),

            Actions\Action::make('exportExcel')
                ->label('تصدير Excel')
                ->form([
                    Select::make('schedule_id')
                        ->label('اختر المادة')
                        ->options(Schedule::all()->pluck('schedule_subject', 'schedule_id'))
                        ->searchable() // إمكانية البحث حسب القاعة
                        ->required(),
                    Select::make('examModel')
                        ->label('اختر النموذج')
                        ->options([
                            '' => 'لايوجد نموذج',
                            'A,B' => 'A,B',
                            'A,B,C' => 'A,B,C',
                            'A,B,C,D' => 'A,B,C,D',
                            'A,B,C,D,E' => 'A,B,C,D,E',
                        ]),

                ])
                ->action(function (array $data) {
                    $schedule_id = $data['schedule_id'];
                    $schedule = Schedule::find($schedule_id);
                    $examModel = $data['examModel'];

                    return Excel::download(
                        new StudentsExport($schedule, $examModel),
                        'students-'.now()->format('Y-m-d').'.xlsx'
                    );
                }),

            Actions\Action::make('deleteAll')
                ->label('حذف جميع البيانات')
                ->requiresConfirmation() // يتطلب تأكيدًا قبل التنفيذ
                ->action(function () {
                    // حذف جميع البيانات من الجدول
                    ImportedData::truncate();

                    // إظهار رسالة نجاح
                    Notification::make()
                        ->title('تم حذف جميع البيانات بنجاح')
                        ->success()
                        ->send();
                })
                ->color('danger') // لون الزر
                ->icon('heroicon-o-trash'), // أيقونة الزر
        ];
    }

    // public function distributeStudents()
    // {
    //     $students = ImportedData::orderBy('imported_data_id')->get();
    //     $rooms = Room::orderBy('room_capacity_total', 'desc')->get();

    //     foreach ($rooms as $room) {
    //         $capacity = $room->room_capacity_total;
    //         $studentsToAssign = $students->splice(0, $capacity);

    //         foreach ($studentsToAssign as $student) {
    //             $student->room_id = $room->room_id;
    //             $student->save();
    //         }
    //     }
    // }
    // mmmm
    // private function distributeStudents($rooms, $capacityMode)
    // {
    //     $students = ImportedData::orderBy('imported_data_id')->get();
    //     $totalCapacity = $this->calculateTotalCapacity($rooms->pluck('room_id')->toArray(), $capacityMode);

    //     if ($students->count() > $totalCapacity) {
    //         Notification::make()
    //             ->title('خطأ في التوزيع')
    //             ->body("عدد الطلاب ({$students->count()}) превышает السعة ({$totalCapacity})")
    //             ->color('danger')
    //             ->send();

    //         return;
    //     }

    //     foreach ($rooms->sortByDesc('room_capacity_total') as $room) {
    //         $capacity = $this->calculateRoomCapacity($room, $capacityMode);
    //         $studentsToAssign = $students->splice(0, $capacity);

    //         foreach ($studentsToAssign as $student) {

    //             $student->room_id = $room->room_id;
    //             $student->save();

    //         }
    //     }

    //     Notification::make()
    //         ->title('تم التوزيع بنجاح')
    //         ->body("تم توزيع {$students->count()} طالب على {$rooms->count()} قاعة")
    //         ->success()
    //         ->send();
    // }

    //     private function distributeStudents($rooms, $scheduleId, $capacityMode)
    //     {
    //         // جلب المادة مع التحقق من وجودها
    //         $schedule = Schedule::find($scheduleId);

    //         if (! $schedule) {
    //             Notification::make()
    //                 ->title('خطأ في البيانات')
    //                 ->body('المادة المحددة غير موجودة')
    //                 ->color('danger')
    //                 ->send();

    //             return;
    //         }

    //         // التحقق من التعارضات
    //         $conflicts = Reservation::where('date', $schedule->schedule_exam_date)
    //             ->where('time_slot', $schedule->schedule_time_slot)
    //             ->whereIn('room_id', $rooms->pluck('room_id'))
    //             ->exists();

    //         if ($conflicts) {
    //             Notification::make()
    //                 ->title('تعارض في الحجوزات!')
    //                 ->body('القاعات المحددة محجوزة في هذا التوقيت')
    //                 ->color('danger')
    //                 ->send();

    //             return;
    //         }

    //         // إنشاء الحجوزات
    //         foreach ($rooms as $room) {
    //             Reservation::create([
    //                 'schedule_id' => $schedule->schedule_id,
    //                 'room_id' => $room->room_id,
    //                 'capacity_mode' => $capacityMode,
    //                 'date' => $schedule->schedule_exam_date,
    //                 'time_slot' => $schedule->schedule_time_slot,
    //             ]);
    //         }

    //         // توزيع الطلاب مع مراعاة السعة
    //         $students = ImportedData::orderBy('imported_data_id')->get();

    //         foreach ($rooms as $room) {
    //             $capacity = $this->calculateEffectiveCapacity($room, $capacityMode);
    //             $studentsToAssign = $students->splice(0, $capacity);

    //             foreach ($studentsToAssign as $student) {
    //                 $student->room_id = $room->room_id;
    //                 $student->save();
    //                 // $student->update([
    //                 //     'room_id' => $room->room_id,
    //                 //     'schedule_id' => $schedule->schedule_id,
    //                 //     'seat_number' => $this->generateSeatNumber($room, $student),
    //                 // ]);
    //             }
    //         }
    //     }

    //     // دالة حساب سعة القاعة
    //     private function calculateRoomCapacity(Room $room, $mode): int
    //     {
    //         $percentage = $mode === 'half' ? 50 : 100;

    //         return (int) ($room->room_capacity_total * $percentage / 100);
    //     }

    //     // دالة حساب السعة الإجمالية
    //     private function calculateTotalCapacity(array $roomIds, string $mode): int
    //     {
    //         return Room::whereIn('room_id', $roomIds)
    //             ->get()
    //             ->sum(fn ($room) => $this->calculateRoomCapacity($room, $mode));
    //     }

    //     private function calculateEffectiveCapacity(Room $room, $mode): int
    //     {
    //         $baseCapacity = $room->room_capacity_total;

    //         return $mode === 'full' ? $baseCapacity : (int) ($baseCapacity / 2);
    //     }

    // private function distributeStudents($rooms, $scheduleId, $capacityMode)
    // {
    //     $schedule = Schedule::find($scheduleId);

    //     if (! $schedule) {
    //         Notification::make()
    //             ->title('خطأ في البيانات')
    //             ->body('المادة المحددة غير موجودة')
    //             ->color('danger')
    //             ->send();

    //         return;
    //     }

    //     foreach ($rooms as $room) {
    //         // حساب السعة المطلوبة
    //         $requestedCapacity = $this->calculateEffectiveCapacity($room, $capacityMode);

    //         // حساب السعة المستخدمة الحالية
    //         $usedCapacity = Reservation::where('room_id', $room->room_id)
    //             ->where('date', $schedule->schedule_exam_date)
    //             ->where('time_slot', $schedule->schedule_time_slot)
    //             ->sum('used_capacity');

    //         // حساب السعة المتبقية
    //         $remainingCapacity = $room->room_capacity_total - $usedCapacity;

    //         // التحقق من توفر السعة
    //         if ($requestedCapacity > $remainingCapacity) {
    //             Notification::make()
    //                 ->title('سعة غير كافية')
    //                 ->body("القاعة {$room->room_name} لا تملك سعة كافية (المتبقي: {$remainingCapacity})")
    //                 ->color('danger')
    //                 ->send();

    //             continue;
    //         }

    //         // إنشاء الحجز
    //         Reservation::create([
    //             'schedule_id' => $schedule->schedule_id,
    //             'room_id' => $room->room_id,
    //             'used_capacity' => $requestedCapacity, // إضافة الحقل الجديد
    //             'date' => $schedule->schedule_exam_date,
    //             'time_slot' => $schedule->schedule_time_slot,
    //         ]);

    //         // توزيع الطلاب
    //         $students = ImportedData::whereNull('room_id')
    //             ->orderBy('imported_data_id')
    //             ->limit($requestedCapacity)
    //             ->get();

    //         foreach ($students as $student) {
    //             $student->update([
    //                 'room_id' => $room->room_id,
    //                 'schedule_id' => $schedule->schedule_id,
    //                 'seat_number' => $this->generateSeatNumber($room, $student),
    //             ]);
    //         }
    //     }

    //     Notification::make()
    //         ->title('تم التوزيع بنجاح')
    //         ->body('تم تخصيص المقاعد بنجاح')
    //         ->success()
    //         ->send();
    // }

    // private function distributeStudents($rooms, $scheduleId, $capacityMode)
    // {
    //     $schedule = Schedule::find($scheduleId);

    //     if (! $schedule) {
    //         Notification::make()
    //             ->title('خطأ في البيانات')
    //             ->body('المادة المحددة غير موجودة')
    //             ->color('danger')
    //             ->send();

    //         return;
    //     }

    //     foreach ($rooms as $room) {
    //         $requestedCapacity = $this->calculateEffectiveCapacity($room, $capacityMode);

    //         // التحقق من السعة المتبقية
    //         $totalUsedCapacity = Reservation::where('room_id', $room->room_id)
    //             ->where('date', $schedule->schedule_exam_date)
    //             ->where('time_slot', $schedule->schedule_time_slot)
    //             ->sum('used_capacity');

    //         $remainingCapacity = $room->room_capacity_total - $totalUsedCapacity;

    //         if ($requestedCapacity > $remainingCapacity) {
    //             Notification::make()
    //                 ->title('سعة غير كافية')
    //                 ->body("السعة المتبقية في القاعة {$room->room_name}: {$remainingCapacity}")
    //                 ->color('danger')
    //                 ->send();

    //             continue;
    //         }

    //         // تحديث أو إنشاء الحجز
    //         $reservation = Reservation::updateOrCreate(
    //             [
    //                 'room_id' => $room->room_id,
    //                 'date' => $schedule->schedule_exam_date,
    //                 'time_slot' => $schedule->schedule_time_slot,
    //                 'schedule_id' => $schedule->schedule_id,
    //             ],
    //             [
    //                 'used_capacity' => $requestedCapacity,
    //             ]
    //         );

    //         // توزيع الطلاب
    //         $students = ImportedData::whereNull('room_id')
    //             ->orderBy('imported_data_id')
    //             ->limit($requestedCapacity)
    //             ->get();

    //         foreach ($students as $student) {
    //             $student->update([
    //                 'room_id' => $room->room_id,
    //                 'schedule_id' => $schedule->schedule_id,
    //                 'seat_number' => $this->generateSeatNumber($room, $student),
    //             ]);
    //         }
    //     }

    //     Notification::make()
    //         ->title('تم التوزيع بنجاح')
    //         ->success()
    //         ->send();
    // }

    // private function distributeStudents($rooms, $scheduleId, $capacityMode)
    // {
    //     $schedule = Schedule::find($scheduleId);

    //     if (! $schedule) {
    //         Notification::make()
    //             ->title('خطأ في البيانات')
    //             ->body('المادة المحددة غير موجودة')
    //             ->color('danger')
    //             ->send();

    //         return;
    //     }

    //     $students = ImportedData::orderBy('imported_data_id')->get();

    //     // الحفاظ على ترتيب القاعات كما اختارها المستخدم
    //     // $orderedRooms = Room::whereIn('room_id', $rooms)
    //     //     ->orderByRaw('FIELD(room_id, '.implode(',', $rooms).')')
    //     //     ->get();
    //     $orderedRooms = Room::where(function ($query) use ($rooms) {
    //         foreach (Arr::flatten($rooms) as $roomId) {
    //             $query->orWhere('room_id', $roomId);
    //         }
    //     })
    //         ->orderByRaw('FIELD(room_id, '.implode(',', Arr::flatten($rooms)).')')
    //         ->get();

    //     foreach ($orderedRooms as $room) {
    //         $capacity = $this->calculateEffectiveCapacity($room, $capacityMode);
    //         $studentsToAssign = $students->splice(0, $capacity);

    //         // إنشاء الحجز
    //         Reservation::create([
    //             'schedule_id' => $schedule->schedule_id,
    //             'room_id' => $room->room_id,
    //             'used_capacity' => $capacity,
    //             'date' => $schedule->schedule_exam_date,
    //             'time_slot' => $schedule->schedule_time_slot,
    //         ]);

    //         foreach ($studentsToAssign as $student) {
    //             $student->update([
    //                 'room_id' => $room->room_id,
    //                 'schedule_id' => $schedule->schedule_id,
    //                 'seat_number' => $this->generateSeatNumber($room, $student),
    //             ]);
    //         }

    //         if ($students->isEmpty()) {
    //             break;
    //         }
    //     }

    //     Notification::make()
    //         ->title('تم التوزيع بنجاح')
    //         ->body('تم توزيع جميع الطلاب حسب الترتيب المحدد')
    //         ->success()
    //         ->send();
    // }

    private function distributeStudents($rooms, $scheduleId, $capacityMode)
    {
        // استخراج الـ IDs من الكائنات إذا كانت موجودة
        $roomIds = collect($rooms)
            ->map(function ($item) {
                return is_object($item) ? $item->room_id : $item;
            })
            ->flatten()
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->all();

        if (empty($roomIds)) {
            Notification::make()
                ->title('خطأ في البيانات')
                ->body('لم يتم اختيار أي قاعات')
                ->color('danger')
                ->send();

            return;
        }

        $schedule = Schedule::find($scheduleId);

        if (! $schedule) {
            Notification::make()
                ->title('خطأ في البيانات')
                ->body('المادة المحددة غير موجودة')
                ->color('danger')
                ->send();

            return;
        }

        // جلب القاعات بالترتيب المحدد
        $orderedRooms = Room::whereIn('room_id', $roomIds)
            ->orderByRaw('FIELD(room_id, '.implode(',', $roomIds).')')
            ->get();

        $students = ImportedData::orderBy('imported_data_id')->get();

        foreach ($orderedRooms as $room) {
            $capacity = $this->calculateEffectiveCapacity($room, $capacityMode);
            $studentsToAssign = $students->splice(0, $capacity);

            Reservation::updateOrCreate(
                [
                    'room_id' => $room->room_id,
                    'date' => $schedule->schedule_exam_date,
                    'time_slot' => $schedule->schedule_time_slot,
                ],
                [
                    'schedule_id' => $schedule->schedule_id,
                    'used_capacity' => $capacity,
                ]
            );

            foreach ($studentsToAssign as $student) {

                $student->room_id = $room->room_id;

                $student->save();

            }

            if ($students->isEmpty()) {
                break;
            }
        }

        Notification::make()
            ->title('تم التوزيع بنجاح')
            ->success()
            ->send();
    }
    // دالة حساب السعة الفعالة (بدون تغيير)

    // private function calculateTotalCapacity(array $roomIds, string $mode): int
    // {
    //     return Room::whereIn('room_id', $roomIds)
    //         ->get()
    //         ->sum(fn ($room) => $this->calculateRoomCapacity($room, $mode));
    // }
    private function calculateTotalCapacity(array $rooms, string $mode): int
    {
        $rooms = collect($rooms)->flatten()->map(fn ($id) => (int) $id)->filter()->all();

        return Room::whereIn('room_id', $rooms)
            ->get()
            ->sum(fn ($room) => $this->calculateEffectiveCapacity($room, $mode));
    }

    private function calculateEffectiveCapacity(Room $room, $mode): int
    {
        $baseCapacity = $room->room_capacity_total;

        return $mode === 'full' ? $baseCapacity : (int) ($baseCapacity / 2);
    }

    private function calculateRoomCapacity(Room $room, $mode): int
    {
        $percentage = $mode === 'half' ? 50 : 100;

        return (int) ($room->room_capacity_total * $percentage / 100);
    }
}
