<?php

namespace App\Filament\Pages;

use App\Models\RoomSchedule;
use App\Models\User;
use Filament\Pages\Page;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class RoomsDistribution extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string $view = 'filament.pages.rooms-distribution';

    protected static ?string $navigationIcon = 'heroicon-o-building-office';

    protected static ?string $navigationGroup = 'التقارير';

    protected static ?string $navigationLabel = 'توزيع القاعات';

    protected static ?string $title = 'توزيع المراقبين على القاعات';

    public function table(Table $table): Table
    {
        return $table
            ->query(function () {
                return RoomSchedule::query() // <-- إزالة الدالة query() الإضافية
                    ->with([
                        'room.observers.user.roles',
                        'schedule',
                    ])
                    ->withCount([
                        'roomObservers as president_count' => function ($q) {
                            $q->whereHas('user.roles', fn ($q) => $q->where('name', 'رئيس_قاعة'));
                        },
                        'roomObservers as secretary_count' => function ($q) {
                            $q->whereHas('user.roles', fn ($q) => $q->where('name', 'امين_سر'));
                        },
                        'roomObservers as observer_count' => function ($q) {
                            $q->whereHas('user.roles', fn ($q) => $q->where('name', 'مراقب'));
                        },
                    ]);
            })
            ->columns([
                // اسم القاعة
                TextColumn::make('room.room_name')
                    ->label('اسم القاعة')
                    ->searchable()
                    ->sortable(),

                // نوع القاعة
                TextColumn::make('room.room_type')
                    ->label('نوع القاعة')
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'big' => 'كبيرة',
                        'small' => 'صغيرة',
                        default => 'غير محدد'
                    })
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'big' => 'success',
                        'small' => 'warning',
                        default => 'gray'
                    }),

                // المادة
                TextColumn::make('schedule.schedule_subject')
                    ->label('المادة')
                    ->searchable()
                    ->sortable(),

                // الفترة
                TextColumn::make('schedule.schedule_time_slot')
                    ->label('الفترة')
                    ->formatStateUsing(fn ($state) => $state === 'morning' ? 'صباحية' : 'مسائية')
                    ->badge()
                    ->color(fn ($state) => $state === 'morning' ? 'info' : 'primary'),

                // تاريخ الامتحان
                TextColumn::make('schedule.schedule_exam_date')
                    ->label('تاريخ الامتحان')
                    ->date('Y-m-d')
                    ->sortable(),

                // الموظفون الموزعون
                TextColumn::make('current_staff')
                    ->label('الموظفون الموزعون')
                    ->getStateUsing(function (RoomSchedule $record) {
                        return sprintf(
                            'رئيس: %d | أمين سر: %d | مراقب: %d',
                            $record->president_count,
                            $record->secretary_count,
                            $record->observer_count
                        );
                    }),

                // النقص المطلوب
                TextColumn::make('required_shortage')
                    ->label('النقص المطلوب')
                    ->getStateUsing(function (RoomSchedule $record) {
                        $roomType = $record->room->room_type;
                        $required = [
                            'رئيس' => 1,
                            'أمين سر' => $roomType === 'big' ? 2 : 1,
                            'مراقب' => $roomType === 'big' ? 8 : 4,
                        ];

                        $shortage = [];
                        foreach ($required as $role => $req) {
                            $current = match ($role) {
                                'رئيس' => $record->president_count,
                                'أمين سر' => $record->secretary_count,
                                'مراقب' => $record->observer_count,
                            };

                            if ($current < $req) {
                                $shortage[] = "$role: ".($req - $current);
                            }
                        }

                        return empty($shortage)
                            ? 'لا يوجد نقص'
                            : implode(' | ', $shortage);
                    })
                    ->color('danger'),

                // الحالة
                TextColumn::make('status')
                    ->label('الحالة')
                    ->getStateUsing(function (RoomSchedule $record) {
                        $roomType = $record->room->room_type;

                        return ($record->president_count >= 1 &&
                                $record->secretary_count >= ($roomType === 'big' ? 2 : 1) &&
                                $record->observer_count >= ($roomType === 'big' ? 8 : 4))
                            ? 'مكتملة'
                            : 'غير مكتملة';
                    })
                    ->color(fn ($state) => $state === 'مكتملة' ? 'success' : 'danger'),

                // عرض الموظفين
                TextColumn::make('view_staff')
                    ->label('الموظفون')
                    ->state('عرض')
                    ->action(
                        Action::make('viewStaff')
                            ->modalHeading(fn ($record) => "الموظفون في {$record->room->room_name}")
                            ->modalContent(fn ($record) => view('filament.tables.actions.staff-list', [
                                'groupedStaff' => $record->room->observers()
                                    ->with('user.roles')
                                    ->get()
                                    ->sortByDesc(fn ($observer) => $this->getRolePriority($observer->user))
                                    ->groupBy(fn ($observer) => $observer->user->getRoleNames()->first()),
                            ]))
                            ->modalWidth('2xl')
                            ->modalSubmitAction(false)
                            ->modalCancelActionLabel('إغلاق')
                    )
                    ->badge()
                    ->color('success'),
            ])
            ->filters([
                SelectFilter::make('room_type')
                    ->label('نوع القاعة')
                    ->options([
                        'big' => 'كبيرة',
                        'small' => 'صغيرة',
                    ])
                    ->relationship('room', 'room_type'),
            ]);
    }

    private function getRolePriority(User $user): int
    {
        return match ($user->getRoleNames()->first()) {
            'رئيس_قاعة' => 3,
            'امين_سر' => 2,
            'مراقب' => 1,
            default => 0
        };
    }
}
