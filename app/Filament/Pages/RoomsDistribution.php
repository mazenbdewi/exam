<?php

namespace App\Filament\Pages;

use App\Models\Room;
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
            ->query(
                Room::query()
                    ->has('schedules')
                    ->with('observers.user')
                    ->with(['schedules' => function ($query) {
                        $query->select(
                            'schedules.schedule_id',
                            'schedules.schedule_subject',
                            'schedules.schedule_exam_date',
                            'schedules.schedule_time_slot'
                        );
                    }])
                    ->withCount([
                        'observers as president_count' => fn ($q) => $q->whereHas('user.roles', fn ($q) => $q->where('name', 'رئيس_قاعة')),
                        'observers as secretary_count' => fn ($q) => $q->whereHas('user.roles', fn ($q) => $q->where('name', 'امين_سر')),
                        'observers as observer_count' => fn ($q) => $q->whereHas('user.roles', fn ($q) => $q->where('name', 'مراقب')),
                    ])
            )
            ->columns([
                TextColumn::make('room_name')
                    ->label('اسم القاعة')
                    ->searchable()
                    ->sortable(),

                // العمود الجديد لنوع القاعة
                TextColumn::make('room_type')
                    ->label('نوع القاعة')
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'big' => 'كبيرة',
                        'small' => 'صغيرة',
                        default => 'غير محدد',
                    })
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'big' => 'success',
                        'small' => 'warning',
                        default => 'gray',
                    }),

                TextColumn::make('schedules.schedule_subject')
                    ->label('المواد')
                    ->formatStateUsing(function (Room $record) {
                        return $record->schedules
                            ->map(fn ($s) => $s->schedule_subject.' ('.($s->schedule_time_slot === 'morning' ? 'صباحية' : 'مسائية').')')
                            ->join(', ');
                    }),

                TextColumn::make('schedules.schedule_exam_date')
                    ->label('تاريخ الامتحان')
                    ->formatStateUsing(function (Room $record) {
                        return $record->schedules
                            ->pluck('schedule_exam_date')
                            ->unique()
                            ->map(function ($date) {
                                return \Carbon\Carbon::parse($date)->format('Y-m-d');
                            })
                            ->join(', ');
                    })
                    ->sortable(),

                TextColumn::make('current_staff')
                    ->label('الموظفون الموزعون')
                    ->getStateUsing(function (Room $record) {
                        return "رئيس: {$record->president_count} | أمين سر: {$record->secretary_count} | مراقب: {$record->observer_count}";
                    }),

                TextColumn::make('required_shortage')
                    ->label('النقص المطلوب')
                    ->getStateUsing(function (Room $record) {
                        $required = [
                            'رئيس' => 1,
                            'أمين سر' => $record->room_type === 'big' ? 2 : 1,
                            'مراقب' => $record->room_type === 'big' ? 8 : 4,
                        ];

                        $shortage = [];

                        $presidentShortage = max($required['رئيس'] - $record->president_count, 0);
                        if ($presidentShortage > 0) {
                            $shortage[] = "رئيس: {$presidentShortage}";
                        }

                        $secretaryShortage = max($required['أمين سر'] - $record->secretary_count, 0);
                        if ($secretaryShortage > 0) {
                            $shortage[] = "أمين سر: {$secretaryShortage}";
                        }

                        $observerShortage = max($required['مراقب'] - $record->observer_count, 0);
                        if ($observerShortage > 0) {
                            $shortage[] = "مراقب: {$observerShortage}";
                        }

                        return count($shortage) > 0
                            ? implode(' | ', $shortage)
                            : 'لا يوجد نقص';
                    })
                    ->color('danger'),

                TextColumn::make('status')
                    ->label('الحالة')
                    ->color(fn (Room $record) => $record->president_count >= 1 &&
                                                $record->secretary_count >= ($record->room_type === 'big' ? 2 : 1) &&
                                                $record->observer_count >= ($record->room_type === 'big' ? 8 : 4)
                                                ? 'success' : 'danger')
                    ->getStateUsing(fn (Room $record) => $record->president_count >= 1 &&
                                                       $record->secretary_count >= ($record->room_type === 'big' ? 2 : 1) &&
                                                       $record->observer_count >= ($record->room_type === 'big' ? 8 : 4)
                                                       ? 'مكتملة' : 'غير مكتملة'),

                TextColumn::make('view_staff')
                    ->label('الموظفون')
                    ->state('عرض')
                    ->action(
                        Action::make('viewStaff')
                            ->modalHeading(fn (Room $record) => "الموظفون في {$record->room_name}")
                            ->modalContent(fn (Room $record) => view('filament.tables.actions.staff-list', [
                                'groupedStaff' => $record->observers()
                                    ->with('user.roles')
                                    ->get()
                                    ->sortByDesc(fn ($observer) => $this->getRolePriority($observer->user))
                                    ->groupBy(fn ($observer) => $observer->user->getRoleNames()->first()),
                            ]))
                            ->modalWidth('2xl')
                            ->modalSubmitAction(false)
                            ->modalCancelActionLabel('إغلاق')
                    )->badge()
                    ->color(fn (string $state): string => match ($state) {
                        default => 'success',
                    }),
            ])
            ->filters([
                SelectFilter::make('room_type')
                    ->label('نوع القاعة')
                    ->options([
                        'big' => 'كبيرة',
                        'small' => 'صغيرة',
                    ])
                    ->attribute('room_type'),
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
