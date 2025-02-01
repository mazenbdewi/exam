<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DepartmentResource\Pages;
use App\Models\Department;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DepartmentResource extends Resource
{
    protected static ?string $model = Department::class;

    protected static ?int $navigationSort = 6;

    protected static ?string $navigationGroup = 'الإعدادات';

    protected static ?string $activeNavigationIcon = 'heroicon-o-check-badge';

    protected static ?string $navigationIcon = 'heroicon-o-academic-cap';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make()
                    ->schema([
                        TextInput::make('department_name')
                            ->required()
                            ->label('القسم')
                            ->minLength(2)
                            ->translateLabel()
                            ->translateLabel()
                            ->maxLength(255)
                            ->suffixIcon('heroicon-m-academic-cap')
                            ->hintColor('primary')
                            ->autofocus()
                            ->placeholder('اكتب اسم القسم من فضلك')
                            ->markAsRequired(),
                    ])->columns(1),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('department_name')->label('القسم')->sortable()->searchable(),
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
            'index' => Pages\ListDepartments::route('/'),
            'create' => Pages\CreateDepartment::route('/create'),
            'view' => Pages\ViewDepartment::route('/{record}'),
            'edit' => Pages\EditDepartment::route('/{record}/edit'),
        ];
    }

    public static function getPluralLabel(): ?string
    {
        return 'أقسام';
    }

    public static function getLabel(): ?string
    {
        return 'قسم';
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }
}
