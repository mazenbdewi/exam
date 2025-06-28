<?php

namespace App\Filament\Resources;

use App\Exports\ObserversExport;
use App\Filament\Resources\ObserverResource\Pages;
use App\Models\Observer;
use App\Models\Reservation;
use App\Models\Schedule;
use App\Models\User;
use Carbon\Carbon;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
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

    // public static function form(Form $form): Form
    // {
    //     return $form->schema([
    //         Forms\Components\Select::make('schedule_id')
    //             ->label('المادة')
    //             ->required()
    //             ->searchable()
    //             ->options(Schedule::query()->pluck('schedule_subject', 'schedule_id'))
    //             ->live() // للتحديث الفوري
    //             ->afterStateUpdated(function ($state, Forms\Set $set) {
    //                 if ($state) {
    //                     $schedule = Schedule::find($state);
    //                     $set('schedule_exam_date', $schedule->schedule_exam_date);
    //                     $set('schedule_time_slot', $schedule->schedule_time_slot);
    //                     $set('room_id', null);
    //                 }
    //             }),

    //         Forms\Components\TextInput::make('schedule_exam_date')
    //             ->label('تاريخ الامتحان')
    //             ->formatStateUsing(fn ($state) => Carbon::parse($state)->format('Y-m-d'))
    //             ->disabled()
    //             ->dehydrated()
    //             ->visible(fn (Forms\Get $get) => $get('schedule_id')),

    //         Forms\Components\TextInput::make('schedule_time_slot')
    //             ->label('الفترة')
    //             ->formatStateUsing(fn ($state) => $state == 'morning' ? 'صباحية' : 'مسائية')
    //             ->disabled()
    //             ->dehydrated()
    //             ->visible(fn (Forms\Get $get) => $get('schedule_id')),

    //         Forms\Components\Select::make('room_id')
    //             ->label('القاعة')
    //             ->required()
    //             ->options(function (callable $get) {
    //                 $schedule = Schedule::find($get('schedule_id'));

    //                 return $schedule ? $schedule->rooms->pluck('room_name', 'room_id') : [];
    //             })
    //             ->visible(fn (callable $get) => $get('schedule_id')),

    //         Forms\Components\Select::make('user_id')
    //             ->label('المراقب')
    //             ->options(User::whereHas('roles', fn ($q) => $q->whereIn('name', ['مراقب', 'امين_سر', 'رئيس_قاعة']))->pluck('name', 'id'))
    //             ->required()
    //             ->reactive()
    //             ->searchable()
    //             ->visible(fn () => auth()->user()->hasRole('super_admin'))
    //             ->afterStateUpdated(function ($state, callable $set) {
    //                 $user = User::find($state);
    //                 if ($user && $user->max_observers > 0 && $user->observers()->count() >= $user->max_observers) {
    //                     Notification::make()
    //                         ->title('خطأ في التعيين')
    //                         ->body("تجاوز الحد الأقصى ({$user->max_observers}) للمراقبات")
    //                         ->danger()
    //                         ->send();
    //                     $set('user_id', null);
    //                 }
    //             }),

    //         Forms\Components\Hidden::make('user_id')
    //             ->default(auth()->id())
    //             ->visible(fn () => ! auth()->user()->hasRole('super_admin')),
    //     ]);
    // }
    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('schedule_id')
                ->label('المادة')
                ->required()
                ->searchable()
                ->options(Schedule::query()->pluck('schedule_subject', 'schedule_id'))
                ->live()
                ->afterStateUpdated(function ($state, Forms\Set $set) {
                    if ($state) {
                        $schedule = Schedule::find($state);
                        $set('schedule_exam_date', $schedule->schedule_exam_date);
                        $set('schedule_time_slot', $schedule->schedule_time_slot);
                        $set('room_id', null);
                    }
                }),

            Forms\Components\TextInput::make('schedule_exam_date')
                ->label('تاريخ الامتحان')
                ->formatStateUsing(fn ($state) => Carbon::parse($state)->format('Y-m-d'))
                ->disabled()
                ->dehydrated()
                ->visible(fn (Forms\Get $get) => $get('schedule_id')),

            Forms\Components\Select::make('schedule_time_slot')
                ->label('الفترة')
                ->options([
                    'morning' => 'صباحية',
                    'night' => 'مسائية',
                ])
                ->disabled()
                ->dehydrated()
                ->visible(fn (Forms\Get $get) => $get('schedule_id')),

            Forms\Components\Select::make('room_id')
                ->label('القاعة')
                ->required()
                ->options(function (callable $get) {
                    $scheduleId = $get('schedule_id');

                    if (! $scheduleId) {
                        return [];
                    }

                    return Reservation::where('schedule_id', $scheduleId)
                        ->with('room')
                        ->get()
                        ->pluck('room.room_name', 'room_id')
                        ->unique();
                })
                ->visible(fn (callable $get) => $get('schedule_id')),

            Forms\Components\Select::make('user_id')
                ->label('المراقب')
                ->options(User::whereHas('roles', fn ($q) => $q->whereIn('name', ['مراقب', 'امين_سر', 'رئيس_قاعة']))->pluck('name', 'id'))
                ->required()
                ->reactive()
                ->searchable()
                ->visible(fn () => auth()->user()->hasRole('super_admin'))
                ->afterStateUpdated(function ($state, callable $set) {
                    $user = User::find($state);
                    if ($user && $user->max_observers > 0 && $user->observers()->count() >= $user->max_observers) {
                        Notification::make()
                            ->title('خطأ في التعيين')
                            ->body("تجاوز الحد الأقصى ({$user->max_observers}) للمراقبات")
                            ->danger()
                            ->send();
                        $set('user_id', null);
                    }
                }),

            Forms\Components\Hidden::make('user_id')
                ->default(auth()->id())
                ->visible(fn () => ! auth()->user()->hasRole('super_admin')),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('schedule.schedule_subject')->label('المادة')
                    ->formatStateUsing(function ($state, $record) {
                        $examDate = Carbon::parse($record->schedule->schedule_exam_date);

                        if ($record->schedule->formatted_time_slot == 'صباحية') {
                            $examDate->setTime(10, 0);
                        } elseif ($record->schedule->formatted_time_slot == 'مسائية') {
                            $examDate->setTime(14, 0);
                        }

                        if (now()->isSameDay($examDate)) {
                            $status = 'الآن';
                            $color = 'success';
                            $icon = '✅';
                        } elseif (now()->greaterThan($examDate)) {
                            $status = 'تمت';
                            $color = 'danger';
                            $icon = '❌';
                        } else {
                            $status = 'لاحقًا';
                            $color = 'warning';
                            $icon = '⏳';
                        }

                        return "{$state} - <span class='text-{$color}-600 font-bold'>{$icon} {$status}</span>";
                    })
                    ->html(),
                Tables\Columns\TextColumn::make('user.name')->label('الاسم'),
                Tables\Columns\TextColumn::make('user.roles.name')->label('الدور'),
                Tables\Columns\TextColumn::make('schedule.formatted_academic_level')->label('السنة'),
                Tables\Columns\TextColumn::make('schedule.schedule_exam_date')->label('تاريخ الامتحان')->date('Y-m-d'),
                Tables\Columns\TextColumn::make('schedule.formatted_time_slot')->label('الفترة'),

                Tables\Columns\TextColumn::make('room.room_name')
                    ->label('القاعة')
                    ->formatStateUsing(function ($state, $record) {
                        $user = auth()->user();
                        if ($user->hasRole('super_admin')) {
                            return $state;
                        }

                        $examDate = Carbon::parse($record->schedule->schedule_exam_date);
                        if ($record->schedule->formatted_time_slot == 'صباحية') {
                            $examDate->setTime(10, 0);
                        } elseif ($record->schedule->formatted_time_slot == 'مسائية') {
                            $examDate->setTime(14, 0);
                        } else {
                            return null;
                        }

                        $showTime = $examDate->subHour();

                        return now()->greaterThanOrEqualTo($showTime) ? $state : '---';
                    }),
            ])
            ->filters([
                SelectFilter::make('user_id')
                    ->label('المراقب')
                    ->searchable()
                    ->options(User::whereHas('roles', fn ($q) => $q->whereIn('name', ['مراقب', 'امين_سر', 'رئيس_قاعة']))->pluck('name', 'id')),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()->hidden(fn () => ! auth()->user()->hasRole('super_admin')),
                Tables\Actions\EditAction::make()->hidden(fn () => ! auth()->user()->hasRole('super_admin')),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->headerActions([

                Tables\Actions\Action::make('distribute')
                    ->label('توزيع تلقائي')
                    ->icon('heroicon-o-users')
                    ->color('primary')
                    ->hidden(fn () => ! auth()->user()->hasRole('super_admin'))
                    ->action(function () {
                        if (\App\Services\ObserverDistributionService::distributeObservers()) {
                            \Filament\Notifications\Notification::make()
                                ->title('تم التوزيع بنجاح')
                                ->success()
                                ->send();
                        } else {
                            \Filament\Notifications\Notification::make()
                                ->title('لم يتم تنفيذ أي توزيع، تأكد من وجود حجوزات قاعات أو تحقق من نقص المراقبين')
                                ->danger()
                                ->send();
                        }
                    })
                    ->requiresConfirmation(),

                Tables\Actions\Action::make('export')
                    ->label('تصدير Excel')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->action(function (array $data, $livewire) {
                        $query = static::getEloquentQuery()->with(['user', 'schedule', 'room']);

                        if (! auth()->user()->hasRole('super_admin')) {
                            $query->where('user_id', auth()->id());
                        }

                        if ($livewire->tableFilters['user_id']['value'] ?? null) {
                            $query->where('user_id', $livewire->tableFilters['user_id']['value']);
                        }

                        return Excel::download(new ObserversExport($query), 'observers-'.now()->format('Y-m-d').'.xlsx');
                    }),
            ])
            ->modifyQueryUsing(fn (Builder $query) => auth()->user()->hasRole('super_admin') ? $query : $query->where('user_id', auth()->id()));
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListObservers::route('/'),
            'create' => Pages\CreateObserver::route('/create'),
            'edit' => Pages\EditObserver::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        $user = auth()->user();

        return $user->hasRole('super_admin') ? static::$model::count() : static::$model::where('user_id', $user->id)->count();
    }

    public static function getPluralLabel(): ?string
    {
        return 'المراقبات';
    }

    public static function getLabel(): ?string
    {
        return 'مراقبة';
    }

    public function update(User $user, Observer $observer)
    {
        return $user->hasRole('super_admin') || $observer->user_id === $user->id;
    }
}
