<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Imports\UsersImport;
use Filament\Actions;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\HtmlString;
use Maatwebsite\Excel\Facades\Excel;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
            Actions\Action::make('import')
                ->label('استيراد من Excel')
                ->icon('heroicon-o-document-arrow-up')
                ->form([
                    FileUpload::make('file')
                        ->label('ملف Excel')
                        ->required()
                        ->disk('local')
                        ->directory('imports')
                        ->rules([
                            'required',
                            'file',
                            'mimes:xlsx,xls,csv',
                        ])
                        ->helperText(
                            new HtmlString('
                                <div class="bg-gray-50 p-4 rounded-lg mt-2">
                                    <h4 class="font-bold mb-2">تنسيق الملف المطلوب:</h4>
                                    <ul class="list-disc pl-5 space-y-1">
                                        <li><strong>name</strong>: الاسم الكامل للمستخدم</li>
                                        <li><strong>email</strong>: الرقم الوطني</li>
                                        <li><strong>role</strong>: الدور الوظيفي 
                                            <ul class="list-disc pl-5">
                                                <li>رئيس_قاعة</li>
                                                <li>امين_سر</li>
                                                <li>مراقب</li>
                                            </ul>
                                        </li>
                                        <li><strong>month_part</strong>: جزء الشهر الذي يتوفر فيه الموظف
                                            <ul class="list-disc pl-5">
                                                <li>first_half: النصف الأول من الشهر</li>
                                                <li>second_half: النصف الثاني من الشهر</li>
                                                <li>any: متاح طوال الشهر</li>
                                            </ul>
                                        </li>
                                        <li><strong>observer_type</strong>: نوع المراقب
                                            <ul class="list-disc pl-5">
                                                <li>primary: مراقب أساسي</li>
                                                <li>secondary: مراقب ثانوي</li>
                                                <li>reserve: مراقب احتياط</li>
                                            </ul>
                                        </li>
                                        <li><strong>monitoring_level</strong>: مستوى المراقبة
                                            <ul class="list-disc pl-5">
                                                <li>0: لا يراقب</li>
                                                <li>1: مراقبة كاملة</li>
                                                <li>2: نصف مراقبة</li>
                                                <li>3: ربع مراقبة</li>
                                            </ul>
                                        </li>
                                    </ul>
                                </div>
                            ')
                        )

                        ->hint('يجب أن يكون الملف بصيغة XLSX, XLS أو CSV')
                        ->hintIcon('heroicon-o-information-circle'),
                ])
                ->action(function (array $data) {
                    try {
                        // استيراد البيانات من الملف
                        $test = Excel::import(new UsersImport, $data['file']);

                        // إرسال إشعار النجاح
                        Notification::make()
                            ->title('تم الاستيراد بنجاح')
                            ->body('تم استيراد البيانات من الملف بنجاح.')
                            ->success()
                            ->send();
                    } catch (\Exception $e) {
                        // إرسال إشعار الخطأ
                        Notification::make()
                            ->title('خطأ في الاستيراد')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }
}
