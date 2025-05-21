<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?int $navigationSort = 7;

    protected static ?string $navigationGroup = 'إدارة الوصول';

    protected static ?string $activeNavigationIcon = 'heroicon-o-check-badge';

    protected static ?string $navigationIcon = 'heroicon-o-key';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make()
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->label('الاسم الكامل')
                            ->minLength(2)
                            ->translateLabel()
                            ->translateLabel()
                            ->maxLength(255)
                            ->suffixIcon('heroicon-m-user')
                            ->hintColor('primary')
                            ->autofocus()
                            ->placeholder('اكت الاسم من فضلك')
                            ->markAsRequired(),

                        TextInput::make('email')
                            ->label('الجوال')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->placeholder('09********'),
                        TextInput::make('max_observers')
                            ->label('عدد المراقبات')
                            ->numeric()
                            ->default(18)
                            ->required()
                            ->minValue(6)
                            ->maxValue(24)
                            ->rules([
                                'required',
                                'integer',
                                'min:6',
                                'max:24',
                            ])
                            ->validationMessages([
                                'min' => 'يجب أن يكون عدد المراقبات على الأقل 6',
                                'max' => 'يجب ألا يتجاوز عدد المراقبات 24',
                            ]),
                        Select::make('roles')
                            ->relationship('roles', 'name')
                            ->label('الصلاحيات')
                            ->required()
                            ->preload()
                            ->searchable(),
                        TextInput::make('password')
                            ->label('كلمة السر')
                            ->required()
                            ->password()
                            ->revealable()
                            ->minLength(8)
                            ->maxLength(255)
                            ->visible(fn ($livewire): bool => $livewire instanceof Pages\CreateUser)
                            ->columnSpanFull(),
                    ])->columns(2),

                Section::make('كلمة السر')->description('إعادة تعيين كلمة السر')
                    ->schema([
                        TextInput::make('new_password')
                            ->label('كلمة السر')
                            ->nullable()
                            ->password()
                            ->minLength(8)
                            ->maxLength(255)
                            ->columnSpanFull()
                            ->visible(fn ($livewire): bool => $livewire instanceof Pages\EditUser),
                        TextInput::make('new_password_confirmation')
                            ->label('تأكيد كلمة السر')
                            ->password()
                            ->same('new_password')
                            ->requiredWith('new_password')
                            ->columnSpanFull()
                            ->visible(fn ($livewire): bool => $livewire instanceof Pages\EditUser),
                    ])->columns(2)->visible(fn ($livewire): bool => $livewire instanceof Pages\EditUser),
            ]);

    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('الاسم الكامل')->sortable()->searchable(),
                TextColumn::make('email')->label('الجوال')->sortable()->searchable(),
                TextColumn::make('max_observers')
                    ->label('عدد المراقبات')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('roles.name')->label('الصلاحية')->sortable()->searchable(),
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
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'view' => Pages\ViewUser::route('/{record}'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }

    public static function getPluralLabel(): ?string
    {
        return 'مستخدمون';
    }

    public static function getLabel(): ?string
    {
        return 'مستخدم';
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }
}
