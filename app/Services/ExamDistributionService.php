<?php

namespace App\Services;

use App\Models\Reservation;
use App\Models\Room;
use App\Models\Schedule;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;

class ExamDistributionService
{
    public function distributeExam(Schedule $schedule): bool
    {
        try {
            if (! $schedule->student_count) {
                Notification::make()
                    ->title('خطأ في التوزيع')
                    ->body('يجب تحديد عدد الطلاب أولاً')
                    ->danger()
                    ->send();

                return false;
            }

            $remainingCapacity = $schedule->student_count;
            $allocatedRooms = [];

            DB::transaction(function () use ($schedule, &$remainingCapacity, &$allocatedRooms) {
                $reservedCapacities = $this->getReservedCapacities($schedule);
                $rooms = Room::orderBy('room_priority')->get();

                foreach ($rooms as $room) {
                    if ($remainingCapacity <= 0) {
                        break;
                    }

                    $usedCapacity = $reservedCapacities[$room->room_id] ?? 0;
                    $availableCapacity = $room->room_capacity_total - $usedCapacity;

                    if ($availableCapacity <= 0) {
                        continue;
                    }

                    $maxAllocation = min(
                        floor($room->room_capacity_total / 2),
                        $availableCapacity,
                        $remainingCapacity
                    );

                    if ($maxAllocation <= 0) {
                        continue;
                    }

                    // التحقق من عدم وجود حجز مسبق لنفس القاعة والتاريخ والفترة
                    $existingReservations = Reservation::where('room_id', $room->room_id)
                        ->where('date', $schedule->schedule_exam_date)
                        ->where('time_slot', $schedule->schedule_time_slot)
                        ->with('schedule')
                        ->get();

                    $isCompatible = true;

                    // التحقق من التوافق مع الحجوزات الموجودة
                    foreach ($existingReservations as $existingReservation) {
                        $existingSchedule = $existingReservation->schedule;

                        // إذا كانت نفس القسم أو نفس السنة الدراسية
                        if ($existingSchedule->department_id == $schedule->department_id ||
                            $existingSchedule->schedule_academic_levels == $schedule->schedule_academic_levels) {
                            $isCompatible = false;
                            break;
                        }
                    }

                    // إذا لم تكن متوافقة، تخطى هذه القاعة
                    if (! $isCompatible) {
                        continue;
                    }

                    Reservation::create([
                        'schedule_id' => $schedule->schedule_id,
                        'room_id' => $room->room_id,
                        'used_capacity' => $maxAllocation,
                        'capacity_mode' => 'half',
                        'date' => $schedule->schedule_exam_date,
                        'time_slot' => $schedule->schedule_time_slot,
                    ]);

                    $allocatedRooms[] = $room->room_name;
                    $remainingCapacity -= $maxAllocation;
                }
            });

            if (empty($allocatedRooms)) {
                Notification::make()
                    ->title('تعذر التوزيع')
                    ->body('جميع القاعات المتاحة غير متوافقة مع شروط تجنب الغش')
                    ->warning()
                    ->send();

                return false;
            }

            if ($remainingCapacity > 0) {
                Notification::make()
                    ->title('تم التوزيع جزئياً')
                    ->body('تم توزيع '.($schedule->student_count - $remainingCapacity)." طالب. تبقى $remainingCapacity مقاعد.")
                    ->body('قد يكون بسبب عدم توافق بعض القاعات مع شروط تجنب الغش')
                    ->warning()
                    ->send();

                return false;
            }

            Notification::make()
                ->title('تم التوزيع بنجاح')
                ->body('تم توزيع جميع الطلاب على القاعات التالية: '.implode('، ', $allocatedRooms))
                ->success()
                ->send();

            return true;

        } catch (\Exception $e) {
            if (str_contains($e->getMessage(), 'Duplicate entry')) {
                Notification::make()
                    ->title('تعارض في الحجوزات')
                    ->body('يوجد حجز مسبق لنفس القاعة والتاريخ والفترة الزمنية')
                    ->danger()
                    ->send();
            } else {
                Notification::make()
                    ->title('حدث خطأ غير متوقع')
                    ->body($e->getMessage())
                    ->danger()
                    ->send();
            }

            return false;
        }
    }

    private function getReservedCapacities(Schedule $schedule): array
    {
        $reservations = Reservation::where('date', $schedule->schedule_exam_date)
            ->where('time_slot', $schedule->schedule_time_slot)
            ->get();

        $capacities = [];

        foreach ($reservations as $reservation) {
            $capacities[$reservation->room_id] = ($capacities[$reservation->room_id] ?? 0) + $reservation->used_capacity;
        }

        return $capacities;
    }

    public function redistribute(Schedule $schedule): array
    {
        DB::transaction(function () use ($schedule) {
            // حذف التوزيع القديم
            $schedule->reservations()->delete();

            // إعادة التوزيع
            $this->distributeExam($schedule);
        });

        // إعادة الحسابات
        $schedule->refresh();

        return [
            'allocated' => $schedule->student_count - $schedule->unallocated_students,
            'unallocated' => $schedule->unallocated_students,
        ];
    }

    /**
     * التحقق من توافق المادة مع القاعة
     * بحيث لا تكون هناك مادة في نفس القاعة في نفس الوقت لها نفس القسم أو نفس السنة الدراسية
     */
    private function isRoomCompatible(Schedule $schedule, Room $room): bool
    {
        // الحصول على جميع الحجوزات في هذه القاعة في نفس التاريخ والفترة
        $reservations = Reservation::where('room_id', $room->room_id)
            ->where('date', $schedule->schedule_exam_date)
            ->where('time_slot', $schedule->schedule_time_slot)
            ->with('schedule') // تحميل الجدول المرتبط
            ->get();

        foreach ($reservations as $reservation) {
            $existingSchedule = $reservation->schedule;

            // إذا كانت المادة الموجودة في الحجز تنتمي لنفس القسم أو نفس السنة الدراسية
            if ($existingSchedule->department_id == $schedule->department_id) {
                return false;
            }

            if ($existingSchedule->schedule_academic_levels == $schedule->schedule_academic_levels) {
                return false;
            }
        }

        return true;
    }
}
