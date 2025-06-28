<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RoomResource\Pages;
use App\Models\Room;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RoomResource extends Resource
{
    protected static ?string $model = Room::class;

    protected static ?int $navigationSort = 5;

    protected static ?string $navigationGroup = 'الإعدادات';

    protected static ?string $activeNavigationIcon = 'heroicon-o-check-badge';

    protected static ?string $navigationIcon = 'heroicon-o-building-office';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make()
                    ->schema([
                        TextInput::make('room_name')
                            ->required()
                            ->label('اسم القاعة')
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

                        TextInput::make('room_capacity_total')
                            ->label('استيعاب القاعة')
                            ->numeric()
                            ->required()
                            ->suffixIcon('heroicon-m-user-group')
                            ->placeholder('استيعاب القاعة من الطلاب'),
                        Select::make('room_type')
                            ->required()
                            ->label('نوع القاعة')
                            ->suffixIcon('heroicon-m-cube-transparent')
                            ->options([
                                'amphitheater' => 'مدرج',
                                'big' => 'كبيرة',
                                'small' => 'صغيرة',
                            ])
                            ->required()
                            ->preload()
                            ->searchable(),
                        Select::make('room_priority')
                            ->required()
                            ->label('أهمية القاعة')
                            ->suffixIcon('heroicon-m-cube-transparent')
                            ->options([
                                1 => 'عالية',
                                2 => 'وسط',
                                3 => 'منخفضة',
                            ])
                            ->required()
                            ->preload()
                            ->searchable(),

                    ])->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('room_name')->label('القاعة')->sortable()->searchable(),
                TextColumn::make('room_capacity_total')->label('الاستيعاب')->sortable()->searchable(),
                TextColumn::make('room_type')
                    ->label('نوع القاعة')
                    ->sortable()
                    ->searchable()
                    ->getStateUsing(function ($record) {
                        return match ($record->room_type) {
                            'amphitheater' => 'مدرج',
                            'big' => 'كبيرة',
                            'small' => 'صغيرة',
                            default => $record->room_type
                        };
                    }),
                TextColumn::make('room_priority')
                    ->label('أهمية القاعة')
                    ->sortable()
                    ->searchable()
                    ->getStateUsing(function ($record) {
                        return match ($record->room_priority) {
                            1 => 'عالية',
                            2 => 'وسط',
                            3 => 'منخفضة',
                            default => $record->room_priority
                        };
                    }),
            ])
            ->filters([
                //
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
            'index' => Pages\ListRooms::route('/'),
            'create' => Pages\CreateRoom::route('/create'),
            'view' => Pages\ViewRoom::route('/{record}'),
            'edit' => Pages\EditRoom::route('/{record}/edit'),
        ];
    }

    public static function getPluralLabel(): ?string
    {
        return 'قاعات';
    }

    public static function getLabel(): ?string
    {
        return 'قاعة';
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }
}
