<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ReservationResource\Pages;
use App\Models\Reservation;
use App\Models\Room;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ReservationResource extends Resource
{
    protected static ?string $model = Reservation::class;

    protected static ?string $navigationGroup = 'توزيع الطلاب';

    protected static ?int $navigationSort = 2;

    protected static ?string $activeNavigationIcon = 'heroicon-o-check-badge';

    protected static ?string $navigationIcon = 'heroicon-o-calendar';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                //
            ]);
    }

    public static function table(Table $table): Table
    {

        return $table
            ->columns([
                Tables\Columns\TextColumn::make('room.room_name')
                    ->label('اسم القاعة')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('schedule.schedule_subject')
                    ->label('المادة')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('date')
                    ->label('التاريخ')
                    ->date(),

                Tables\Columns\BadgeColumn::make('time_slot')
                    ->label('الفترة')
                    ->formatStateUsing(function ($state) {
                        return match ($state) {
                            'morning' => 'صباحية',
                            'night' => 'مسائية',
                            default => $state,
                        };
                    })
                    ->colors([
                        'success' => 'morning',
                        'danger' => 'night',
                    ]),
                Tables\Columns\TextColumn::make('used_capacity')
                    ->label('المقاعد المحجوزة'),

            ])
            ->filters([
                Tables\Filters\SelectFilter::make('room_id')
                    ->label('القاعة')
                    ->options(fn () => Room::pluck('room_name', 'room_id')->toArray()),

                Tables\Filters\Filter::make('reservations.date')
                    ->label('تاريخ الامتحان')
                    ->form([
                        DatePicker::make('from')->label('من'),
                        DatePicker::make('to')->label('إلى'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['from'], fn ($query, $date) => $query->whereDate('reservations.date', '>=', $date))
                            ->when($data['to'], fn ($query, $date) => $query->whereDate('reservations.date', '<=', $date));
                    }),

                Tables\Filters\SelectFilter::make('time_slot')
                    ->label('الفترة')
                    ->options([
                        'morning' => 'صباحية',
                        'night' => 'مسائية',
                    ]),

                Tables\Filters\SelectFilter::make('capacity_mode')
                    ->label('نوع السعة')
                    ->options([
                        'full' => 'كاملة',
                        'half' => 'نصف',
                    ]),
            ])

            ->actions([
                // Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
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
            'index' => Pages\ListReservations::route('/'),
            'create' => Pages\CreateReservation::route('/create'),
            'edit' => Pages\EditReservation::route('/{record}/edit'),
        ];
    }

    public static function getPluralLabel(): ?string
    {
        return 'الحجوزات';
    }

    public static function getLabel(): ?string
    {
        return 'حجوزات';
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }
}
