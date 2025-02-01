<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ScheduleResource\Pages;
use App\Models\Department;
use App\Models\Room;
use App\Models\Schedule;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ScheduleResource extends Resource
{
    protected static ?string $model = Schedule::class;

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationGroup = 'الإعدادات';

    protected static ?string $activeNavigationIcon = 'heroicon-o-check-badge';

    protected static ?string $navigationIcon = 'heroicon-o-newspaper';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make()
                    ->schema([
                        Select::make('department_id')
                            ->relationship('department', 'department_name')
                            ->label('القسم')
                            ->required(),
                        Select::make('schedule_academic_levels')
                            ->options([
                                'first' => 'سنة أولى',
                                'second' => 'سنة ثانية',
                                'third' => 'سنة ثالثة',
                                'fourth' => 'سنة رابعة',
                                'fifth' => 'سنة خامسة',
                            ])
                            ->label('السنة الدراسية')
                            ->required(),
                        TextInput::make('schedule_subject')
                            ->required()
                            ->label('المادة')
                            ->minLength(2)
                            ->translateLabel()
                            ->translateLabel()
                            ->maxLength(255)
                            ->suffixIcon('heroicon-m-rectangle-group')
                            ->unique(ignoreRecord: true)
                            ->hintColor('primary')
                            ->autofocus()
                            ->placeholder('اكتب اسم القاعة من فضلك')
                            ->markAsRequired(),
                        DatePicker::make('schedule_exam_date')
                            ->label('تاريخ الامتحان')
                            ->required(),
                        Select::make('schedule_time_slot')
                            ->label('وقت الامتحان')
                            ->options([
                                'morning' => 'صباحية',
                                'night' => 'مسائية',
                            ])
                            ->required(),
                        // Select::make('room_id')
                        //     ->relationship('room', 'room_name')
                        //     ->label('القاعة')
                        //     ->required(),
                        Select::make('room_id') // استخدم اسم العلاقة الصحيح (rooms)
                            ->relationship('rooms', 'room_name') // العلاقة هي 'rooms' وليس 'room'
                            ->label('القاعات')
                            ->required()
                            ->multiple() // لأنك تريد اختيار عدة قاعات
                            ->preload()
                            ->searchable(),
                    ])->columns(3),

            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('department.department_name')->label('القسم')->sortable()->searchable(),
                TextColumn::make('schedule_academic_levels')
                    ->label('السنة')
                    ->sortable()
                    ->searchable()
                    ->getStateUsing(function ($record) {
                        return match ($record->schedule_academic_levels) {
                            'first' => 'سنة أولى',
                            'second' => 'سنة ثانية',
                            'third' => 'سنة ثالثة',
                            'fourth' => 'سنة رابعة',
                            'fifth' => 'سنة خامسة',
                            default => 'غير معروفة',
                        };
                    }),
                TextColumn::make('schedule_subject')->label('المادة')->sortable()->searchable(),
                TextColumn::make('schedule_exam_date')->label('تاريخ الامتحان')->dateTime('Y-m-d')->sortable()->searchable(),
                TextColumn::make('schedule_time_slot')
                    ->label('وقت الأمتحان')
                    ->sortable()
                    ->searchable()
                    ->getStateUsing(function ($record) {
                        return $record->schedule_time_slot === 'morning' ? 'صباحي' : 'مسائي';
                    }),
                // TextColumn::make('room.room_name')->label('القاعة')->sortable()->searchable(),
                TextColumn::make('rooms.room_name')
                    ->label('القاعات')
                    ->formatStateUsing(function ($state, $record) {
                        // عرض أسماء القاعات كمصفوفة
                        return $record->rooms->pluck('room_name')->implode(', ');
                    }),

            ])
            ->filters([

                SelectFilter::make('department_id')
                    ->label('القسم')
                    ->options(fn () => Department::pluck('department_name', 'department_id')->toArray()),
                SelectFilter::make('schedule_academic_levels')
                    ->label('السنة الدراسية')
                    ->options([
                        'first' => 'السنة الأولى',
                        'second' => 'السنة الثانية',
                        'third' => 'السنة الثالثة',
                        'fourth' => 'السنة الرابعة',
                        'fifth' => 'السنة الخامسة',
                    ]),
                Filter::make('schedule_exam_date')
                    ->label('تاريخ الامتحان')
                    ->form([
                        DatePicker::make('from')->label('من'),
                        DatePicker::make('to')->label('إلى'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['from'], fn ($query, $date) => $query->whereDate('schedule_exam_date', '>=', $date))
                            ->when($data['to'], fn ($query, $date) => $query->whereDate('schedule_exam_date', '<=', $date));
                    }),
                SelectFilter::make('schedule_time_slot')
                    ->label('الفترة الامتحانية')
                    ->options([
                        'morning' => 'صباحي',
                        'night' => 'مسائي',
                    ]),
                SelectFilter::make('room_id')
                    ->label('القاعة')
                    ->options(fn () => Room::pluck('room_name', 'room_id')->toArray()),

            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
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
            'index' => Pages\ListSchedules::route('/'),
            'create' => Pages\CreateSchedule::route('/create'),
            'view' => Pages\ViewSchedule::route('/{record}'),
            'edit' => Pages\EditSchedule::route('/{record}/edit'),
        ];
    }

    public static function getPluralLabel(): ?string
    {
        return 'برامج امتحانية';
    }

    public static function getLabel(): ?string
    {
        return 'برنامج امتحاني';
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }
}
