<?php

namespace App\Filament\Resources;

use App\Exports\ObserversExport;
use App\Filament\Resources\ObserverResource\Pages;
use App\Models\Observer;
use App\Models\Schedule;
use App\Models\User;
use App\Services\ObserverDistributionService;
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

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('schedule_id')
                ->label('المادة')
                ->options(Schedule::all()->pluck('schedule_subject', 'schedule_id'))
                ->required()
                ->reactive()
                ->searchable()
                ->afterStateUpdated(function ($state, callable $set) {
                    $schedule = Schedule::find($state);
                    $set('schedule_exam_date', $schedule?->schedule_exam_date);
                    $set('schedule_time_slot', $schedule?->schedule_time_slot);
                }),

            Forms\Components\DatePicker::make('schedule_exam_date')
                ->label('تاريخ الامتحان')
                ->disabled()
                ->visible(fn (callable $get) => $get('schedule_id')),

            Forms\Components\Select::make('schedule_time_slot')
                ->label('الفترة')
                ->options(['morning' => 'صباحية', 'night' => 'مسائية'])
                ->disabled()
                ->visible(fn (callable $get) => $get('schedule_id')),

            Forms\Components\Select::make('room_id')
                ->label('القاعة')
                ->required()
                ->options(function (callable $get) {
                    $schedule = Schedule::find($get('schedule_id'));

                    return $schedule ? $schedule->rooms->pluck('room_name', 'room_id') : [];
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
                Tables\Columns\TextColumn::make('user.name')->label('الاسم'),
                Tables\Columns\TextColumn::make('user.roles.name')->label('الدور'),
                Tables\Columns\TextColumn::make('schedule.schedule_subject')->label('المادة'),
                Tables\Columns\TextColumn::make('schedule.formatted_academic_level')->label('السنة'),
                Tables\Columns\TextColumn::make('schedule.schedule_exam_date')
                    ->label('تاريخ الامتحان')
                    ->date('Y-m-d'),
                Tables\Columns\TextColumn::make('schedule.formatted_time_slot')->label('الفترة'),
                Tables\Columns\TextColumn::make('room.room_name')->label('القاعة'),
            ])
            ->filters([
                SelectFilter::make('user_id')
                    ->label('المراقب')
                    ->searchable()
                    ->options(User::whereHas('roles', fn ($q) => $q->whereIn('name', ['مراقب', 'امين_سر', 'رئيس_قاعة']))->pluck('name', 'id')),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
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
                    ->action(fn () => ObserverDistributionService::distributeObservers()),

                Tables\Actions\Action::make('export')
                    ->label('تصدير Excel')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->action(fn () => Excel::download(
                        new ObserversExport(static::getEloquentQuery()->with(['user', 'schedule', 'room'])),
                        'observers-'.now()->format('Y-m-d').'.xlsx'
                    )),
            ])
            ->modifyQueryUsing(fn (Builder $query) => auth()->user()->hasRole('super_admin') ? $query : $query->where('user_id', auth()->id())
            );
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListObservers::route('/'),
            'create' => Pages\CreateObserver::route('/create'),
            'edit' => Pages\EditObserver::route('/{record}/edit'),

        ];
    }

    // Helper methods for table columns
    public static function formattedAcademicLevel($record): string
    {
        $levelMap = [
            'first' => 'سنة أولى', 'second' => 'سنة ثانية',
            'third' => 'سنة ثالثة', 'fourth' => 'سنة رابعة',
            'fifth' => 'سنة خامسة', '1' => 'أولى', '2' => 'ثانية',
            '3' => 'ثالثة', '4' => 'رابعة', '5' => 'خامسة',
        ];

        return $levelMap[strtolower($record->schedule->schedule_academic_levels)] ?? 'غير معروف';
    }

    public static function formattedTimeSlot($state): string
    {
        return match ($state) {
            'morning' => 'صباحية',
            'night' => 'مسائية',
            default => $state
        };
    }

    public static function getPluralLabel(): ?string
    {
        return 'المراقبات';
    }

    public static function getLabel(): ?string
    {
        return 'مراقبة';
    }

    public static function getNavigationBadge(): ?string
    {
        return static::$model::count();
    }
}
