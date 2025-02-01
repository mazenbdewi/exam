<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ImportedDataResource\Pages;
use App\Imports\ImportedDataImport;
use App\Models\ImportedData;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Maatwebsite\Excel\Facades\Excel;

class ImportedDataResource extends Resource
{
    protected static ?string $model = ImportedData::class;

    protected static ?string $navigationGroup = 'توزيع الطلاب';

    protected static ?int $navigationSort = 1;

    protected static ?string $activeNavigationIcon = 'heroicon-o-check-badge';

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                FileUpload::make('excel_file')
                    ->label('رفع ملف Excel')
                    ->required()
                    ->acceptedFileTypes(['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])
                    ->directory('imported-excel-files')
                    ->afterStateUpdated(function ($state, $set) {
                        // معالجة ملف Excel
                        $rows = Excel::toArray(new ImportedDataImport, $state);

                        // حفظ البيانات في قاعدة البيانات
                        foreach ($rows[0] as $row) {
                            ImportedData::create([
                                'number' => $row[0],
                                'full_name' => $row[1],
                                'father_name' => $row[2],
                            ]);
                        }

                        // إظهار رسالة نجاح
                        Notification::make()
                            ->title('تم رفع الملف بنجاح')
                            ->success()
                            ->send();
                    }),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('index') // الرقم التسلسلي
                    ->label('#')
                    ->rowIndex(), // يعرض رقمًا تسلسليًا تلقائيًا
                TextColumn::make('number')->label('الرقم'),
                TextColumn::make('full_name')->label('الاسم الكامل'),
                TextColumn::make('father_name')->label('اسم الأب'),
                TextColumn::make('room.room_name') // اسم القاعة من العلاقة
                    ->label('القاعة')
                    ->sortable() // إمكانية الفرز حسب القاعة
                    ->searchable(), // إمكانية البحث حسب القاعة
            ])
            ->filters([
                //
            ])
            ->actions([
                // Tables\Actions\EditAction::make(),
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
            'index' => Pages\ListImportedData::route('/'),
            'create' => Pages\CreateImportedData::route('/create'),
            'edit' => Pages\EditImportedData::route('/{record}/edit'),
        ];
    }

    public static function getPluralLabel(): ?string
    {
        return 'توزيع الطلاب';
    }

    public static function getLabel(): ?string
    {
        return 'توزيع الطلاب';
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }
}
