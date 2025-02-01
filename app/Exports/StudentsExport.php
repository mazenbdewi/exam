<?php

namespace App\Exports;

use App\Models\ImportedData;
use App\Models\Schedule;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class StudentsExport implements FromCollection, WithCustomStartCell, WithHeadings, WithStyles, WithTitle
{
    protected $schedule;

    protected $examModel;

    public function __construct(Schedule $schedule, ?string $examModel = null)
    {
        $this->schedule = $schedule;
        $this->examModel = $examModel;
    }

    public function collection()
    {
        $students = ImportedData::with('room')->get();

        $letters = [];
        if ($this->examModel) {
            $letters = explode(',', $this->examModel);
        }

        $students = $students->map(function ($student, $index) use ($letters) {
            if (empty($letters)) {
                $student->exam_sheet = '';
            } else {
                $student->exam_sheet = $letters[$index % count($letters)];
            }

            return [
                'الرقم' => $student->number,
                'الاسم الكامل' => $student->full_name,
                'اسم الأب' => $student->father_name,
                'ورقة الامتحان' => $student->exam_sheet,
                'القاعة' => $student->room ? $student->room->room_name : 'غير معين',
            ];
        });

        return $students;
    }

    public function headings(): array
    {
        return [
            'الرقم',
            'الاسم الكامل',
            'اسم الأب',
            'ورقة الامتحان',
            'القاعة',
        ];
    }

    public function title(): string
    {
        return 'الطلاب';
    }

    public function startCell(): string
    {
        return 'A6';
    }

    public function styles(Worksheet $sheet)
    {
        $schedule = $this->schedule;

        $timeSlotText = '';
        if ($schedule->schedule_time_slot == 'morning') {
            $timeSlotText = 'صباحية';
        } elseif ($schedule->schedule_time_slot == 'night') {
            $timeSlotText = 'مسائية';
        }

        $academicLevelText = '';
        switch ($schedule->schedule_academic_levels) {
            case 'first':
                $academicLevelText = 'سنة أولى';
                break;
            case 'second':
                $academicLevelText = 'سنة ثانية';
                break;
            case 'third':
                $academicLevelText = 'سنة ثالثة';
                break;
            case 'fourth':
                $academicLevelText = 'سنة رابعة';
                break;
            case 'fifth':
                $academicLevelText = 'سنة خامسة';
                break;
            default:
                $academicLevelText = 'غير محدد';
                break;
        }

        // إضافة ترويسة الجدول
        $sheet->mergeCells('A1:E1');
        $sheet->setCellValue('A1', 'القسم: '.$schedule->department->department_name);

        $sheet->mergeCells('A2:E2');
        $sheet->setCellValue('A2', 'اسم المادة: '.$schedule->schedule_subject);

        $sheet->mergeCells('A3:E3');
        $sheet->setCellValue('A3', 'السنة: '.$academicLevelText);

        $sheet->mergeCells('A4:E4');
        $sheet->setCellValue('A4', 'تاريخ المادة: '.$schedule->schedule_exam_date);

        $sheet->mergeCells('A5:E5');
        $sheet->setCellValue('A5', 'فترة المادة: '.$timeSlotText);

        // ضبط ارتفاع الصفوف
        $sheet->getRowDimension(1)->setRowHeight(25);
        $sheet->getRowDimension(2)->setRowHeight(25);
        $sheet->getRowDimension(3)->setRowHeight(25);
        $sheet->getRowDimension(4)->setRowHeight(25);
        $sheet->getRowDimension(5)->setRowHeight(25);

        // ضبط عرض الأعمدة لتناسب الصفحة العمودية
        $sheet->getColumnDimension('A')->setWidth(10); // الرقم
        $sheet->getColumnDimension('B')->setWidth(30); // الاسم الكامل
        $sheet->getColumnDimension('C')->setWidth(20); // اسم الأب
        $sheet->getColumnDimension('D')->setWidth(15); // ورقة الامتحان
        $sheet->getColumnDimension('E')->setWidth(20); // القاعة

        // تنسيق الترويسة
        $sheet->getStyle('A1:E5')->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 12,
            ],
            'alignment' => [
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
            ],
        ]);

        // تنسيق عناوين الأعمدة
        $sheet->getStyle('A6:E6')->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 12,
            ],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => [
                    'rgb' => 'D3D3D3',
                ],
            ],
            'alignment' => [
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN, // إضافة حدود رقيقة
                ],
            ],
        ]);

        // تنسيق البيانات
        $sheet->getStyle('A6:E'.$sheet->getHighestRow())->applyFromArray([
            'font' => [
                'size' => 11,
            ],
            'alignment' => [
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN, // إضافة حدود رقيقة
                ],
            ],
        ]);

        // إظهار خطوط الخلايا (Gridlines)
        $sheet->setShowGridlines(true);

        // إعداد الصفحة للطباعة بشكل عمودي على A4
        $sheet->getPageSetup()->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_PORTRAIT);
        $sheet->getPageSetup()->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_A4);
        $sheet->getPageSetup()->setFitToWidth(1); // ضبط العرض ليتناسب مع صفحة واحدة
        $sheet->getPageSetup()->setFitToHeight(0); // عدم ضبط الارتفاع

        // إضافة هوامش للصفحة
        $sheet->getPageMargins()->setTop(0.75);
        $sheet->getPageMargins()->setRight(0.75);
        $sheet->getPageMargins()->setLeft(0.75);
        $sheet->getPageMargins()->setBottom(0.75);

        // إعداد رأس الصفحة وتذييلها (اختياري)
        $sheet->getHeaderFooter()
            ->setOddHeader('&C&Hالترويسة'); // رأس الصفحة
        $sheet->getHeaderFooter()
            ->setOddFooter('&C&Hالتذييل'); // تذييل الصفحة
    }
}
