<?php

namespace App\Filament\Resources;

use App\Exports\ObserversExport;
use App\Filament\Resources\ObserverResource\Pages;
use App\Models\Observer;
use App\Models\Room;
use App\Models\Schedule;
use App\Models\User;
use App\Services\ObserverDistributionService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Facades\Excel;

class ObserverResource extends Resource
{
    protected static ?string $model = Observer::class;

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationGroup = 'المراقبات';

    protected static ?string $activeNavigationIcon = 'heroicon-o-check-badge';

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {

        return $form
            ->schema([

                Forms\Components\Select::make('schedule_id')
                    ->label('المادة')
                    ->options(Schedule::all()->pluck('schedule_subject', 'schedule_id'))
                    ->required()
                    ->reactive()
                    ->searchable()
                    ->afterStateUpdated(function ($state, callable $set) {
                        $schedule = Schedule::find($state);
                        if ($schedule) {
                            $set('schedule_exam_date', $schedule->schedule_exam_date);
                            $set('schedule_time_slot', $schedule->schedule_time_slot);
                        } else {
                            $set('schedule_exam_date', null);
                            $set('schedule_time_slot', null);
                        }
                    }),

                Forms\Components\DatePicker::make('schedule_exam_date')
                    ->label('تاريخ الامتحان')
                    ->disabled()
                    ->visible(fn (callable $get) => $get('schedule_id') !== null),

                Forms\Components\Select::make('schedule_time_slot')
                    ->label('الفترة')
                    ->options([
                        'morning' => 'صباحية',
                        'night' => 'مسائية',
                    ])
                    ->disabled()
                    ->visible(fn (callable $get) => $get('schedule_id') !== null),
                Forms\Components\Select::make('room_id')
                    ->label('القاعة')
                    ->required()
                    ->options(function (callable $get) {
                        $schedule = Schedule::find($get('schedule_id'));
                        if (! $schedule) {
                            return [];
                        }

                        $roomType = $schedule->schedule_time_slot === 'morning' ? 'small' : 'big';

                        return Room::whereHas('schedules', function ($query) use ($schedule) {
                            $query->where('room_schedules.schedule_id', $schedule->schedule_id); // تحديد الجدول بوضوح
                        })
                            ->pluck('room_name', 'room_id');

                    })
                    ->reactive()
                    ->visible(fn (callable $get) => $get('schedule_id') !== null),

                Forms\Components\Select::make('user_id')
                    ->label('المراقب')
                    ->options(
                        User::whereHas('roles', function ($query) {
                            $query->whereIn('name', ['مراقب', 'امين_سر', 'رئيس_قاعة']);
                        })->pluck('name', 'id')
                    )
                    ->required()
                    ->reactive()
                    ->searchable()
                    ->visible(fn () => auth()->user()->hasRole('super_admin'))
                    ->default(fn () => auth()->user()->hasRole('super_admin') ? null : auth()->user()->id)
                    ->afterStateUpdated(function ($state, callable $set) {
                        $user = User::find($state);
                        if ($user) {
                            $max = $user->getMaxObserversByAge();
                            $current = Observer::where('user_id', $state)->count();
                            if ($current >= $max) {
                                $set('user_id', auth()->user()->id);
                            }
                        }
                    }),

                Forms\Components\Hidden::make('user_id')
                    ->default(auth()->user()->id)
                    ->visible(fn () => ! auth()->user()->hasRole('super_admin'))
                    ->hidden(fn () => auth()->user()->hasRole('super_admin'))
                    ->afterStateUpdated(function ($state, callable $set) {
                        if (auth()->user()->hasAnyRole(['مراقب', 'امين_سر', 'رئيس_قاعة'])) {
                            $user = User::find($state);
                            if ($user) {
                                $max = $user->getMaxObserversByAge();
                                $current = Observer::where('user_id', $state)->count();
                                if ($current >= $max) {
                                    $set('user_id', auth()->user()->id);
                                }
                            }
                        }
                    }),

            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.name')->label('الاسم'),
                Tables\Columns\TextColumn::make('user.roles.name')->label('نوع المراقبة'),
                Tables\Columns\TextColumn::make('schedule.schedule_subject')->label('المادة'),
                Tables\Columns\TextColumn::make('schedule.schedule_exam_date')->label('تاريخ الامتحان'),
                Tables\Columns\TextColumn::make('schedule.schedule_time_slot')
                    ->label('الفترة')
                    ->formatStateUsing(function ($state) {
                        return match ($state) {
                            'morning' => 'صباحية',
                            'night' => 'مسائية',
                            default => $state,
                        };
                    }),
                Tables\Columns\TextColumn::make('room.room_name')->label('القاعة'),
            ])->modifyQueryUsing(function (Builder $query) {
                if (auth()->user()->hasRole('super_admin')) {
                    return $query;
                }

                return $query->where('user_id', auth()->user()->id);
            })
            ->filters([
                SelectFilter::make('user_id')
                    ->label('المراقب')
                    ->searchable()
                    ->options(
                        User::whereHas('roles', function ($query) {
                            $query->whereIn('name', ['مراقب', 'امين_سر', 'رئيس_قاعة']);
                        })->pluck('name', 'id')
                    ),
            ])
            ->modifyQueryUsing(function ($query) {
                if (in_array(auth()->user()->role, ['مراقب', 'امين_سر', 'رئيس_قاعة'])) {
                    $query->where('user_id', auth()->id());
                }
            })

            ->actions([
                Tables\Actions\DeleteAction::make(),
            ])
            ->headerActions([
                Action::make('توزيع المراقبين')
                    ->action(function () {
                        $schedules = Schedule::all();
                        $eligibleUsers = User::where('role', 'observer')->get();

                        ObserverDistributionService::distributeObservers($schedules, $eligibleUsers);
                    }),
                // Tables\Actions\Action::make('distributeObservers')
                //     ->label('توزيع المراقبين')
                //     ->icon('heroicon-o-users')
                //     ->color('primary')
                //     ->hidden(fn () => ! auth()->user()->hasRole('super_admin'))
                //     ->action(function () {
                //         // static::distributeObservers();
                //         ObserverDistributionService::distributeObservers();

                //     }),
                Tables\Actions\Action::make('export')
                    ->label('تصدير إلى Excel')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->action(function ($livewire) {
                        $baseQuery = static::getEloquentQuery()
                            ->with(['user', 'schedule', 'room'])
                            ->when(
                                ! auth()->user()->hasRole('super_admin'),
                                fn ($query) => $query->where('user_id', auth()->id())
                            );
                        foreach ($livewire->tableFilters as $filterName => $value) {
                            if (! empty($value)) {
                                $filter = $livewire->getTable()->getFilter($filterName);
                                if ($filter) {
                                    $filter->apply(
                                        $baseQuery, // التصحيح هنا: استخدام Eloquent Builder بدلاً من Query Builder
                                        $value,
                                        $livewire->getTable()->getColumn($filterName)
                                    );
                                }
                            }
                        }

                        return Excel::download(
                            new ObserversExport($baseQuery),
                            'observers-'.now()->format('Y-m-d').'.xlsx'
                        );
                    }),

            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListObservers::route('/'),
            'create' => Pages\CreateObserver::route('/create'),
        ];
    }

    public function columnFormats(): array
    {
        return [
            'C' => NumberFormat::FORMAT_DATE_DDMMYYYY,
        ];
    }

    public function map($observer): array
    {
        return [
            // ...
            Date::dateTimeToExcel($observer->schedule->schedule_exam_date),
            // ...
        ];
    }

    // // دالة مساعدة لتعيين المراقب (كما هي)
    // private static function assignObserver($user, $schedule, $room): bool
    // {
    //     try {
    //         Observer::create([
    //             'user_id' => $user->id,
    //             'schedule_id' => $schedule->schedule_id,
    //             'room_id' => $room->room_id,
    //         ]);

    //         return true;
    //     } catch (\Exception $e) {
    //         logger()->error("فشل تعيين المراقب: {$e->getMessage()}");

    //         return false;
    //     }
    // }

    protected static $usedUserIds = [];

    public static function distributeByDates(array $datesOrder)
    {
        foreach ($datesOrder as $date) {
            self::distributeForDate($date);
            self::markUsedObservers($date);
        }
    }

    private static function distributeForDate($targetDate)
    {
        $schedules = Schedule::with(['rooms.observers'])
            ->where('schedule_exam_date', $targetDate)
            ->orderBy('schedule_time_slot')
            ->get();

        $eligibleUsers = User::whereHas('roles', fn ($q) => $q->whereIn('name', ['مراقب', 'امين_سر', 'رئيس_قاعة']))
            ->whereNotIn('id', self::$usedUserIds)
            ->withCount(['observers' => fn ($q) => $q->whereIn('schedule_id', $schedules->pluck('schedule_id'))])
            ->get()
            ->filter(fn ($user) => $user->observers_count < $user->getMaxObserversByAge())
            ->sortByDesc(fn ($user) => $user->hasRole('رئيس_قاعة') ? 2 : ($user->hasRole('امين_سر') ? 1 : 0));

        foreach ($schedules as $schedule) {
            foreach ($schedule->rooms as $room) {
                self::processRoom($room, $eligibleUsers, $schedule);
            }
        }
    }

    private static function processRoom($room, &$eligibleUsers, $schedule)
    {
        $existingObservers = $room->observers;

        $currentRoles = [
            'رئيس_قاعة' => $existingObservers->filter(fn ($o) => $o->user->hasRole('رئيس_قاعة'))->count(),
            'امين_سر' => $existingObservers->filter(fn ($o) => $o->user->hasRole('امين_سر'))->count(),
            'مراقب' => $existingObservers->filter(fn ($o) => $o->user->hasRole('مراقب'))->count(),
        ];

        $roomType = $room->room_type;
        $required = [
            'رئيس_قاعة' => max(1 - $currentRoles['رئيس_قاعة'], 0),
            'امين_سر' => max(($roomType === 'big' ? 2 : 1) - $currentRoles['امين_سر'], 0),
            'مراقب' => max(($roomType === 'big' ? 8 : 4) - $currentRoles['مراقب'], 0),
        ];

        foreach (['رئيس_قاعة', 'امين_سر', 'مراقب'] as $role) {
            $needed = $required[$role];
            $candidates = $eligibleUsers->filter(fn ($u) => $u->hasRole($role));

            foreach ($candidates as $user) {
                if ($needed <= 0) {
                    break;
                }

                if (! self::hasConflict($user, $schedule) && self::assignObserver($user, $schedule, $room)) {
                    self::$usedUserIds[] = $user->id;
                    $eligibleUsers = $eligibleUsers->reject(fn ($u) => $u->id === $user->id);
                    $needed--;
                }
            }
        }
    }

    private static function markUsedObservers($date)
    {
        $newUsedIds = Observer::whereHas('schedule', fn ($q) => $q->where('schedule_exam_date', $date))
            ->pluck('user_id')
            ->unique()
            ->toArray();

        self::$usedUserIds = array_unique(array_merge(self::$usedUserIds, $newUsedIds));
    }

    private static function hasConflict($user, $schedule)
    {
        return Observer::where('user_id', $user->id)
            ->whereHas('schedule', fn ($q) => $q->where('schedule_exam_date', $schedule->schedule_exam_date)
                ->where('schedule_time_slot', $schedule->schedule_time_slot))
            ->exists();
    }

    public static function getPluralLabel(): ?string
    {
        return 'جدول المراقبة';
    }

    public static function getLabel(): ?string
    {
        return 'جدول مراقبة';
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }
}
