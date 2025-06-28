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

    public function __construct(Schedule $schedule)
    {
        $this->schedule = $schedule;
    }

    public function collection()
    {
        $students = ImportedData::with('room')->get();

        $students = $students->map(function ($student) {
            return [
                'الرقم' => $student->number,
                'الاسم الكامل' => $student->full_name,
                'اسم الأب' => $student->father_name,
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
        $sheet->mergeCells('A1:D1');
        $sheet->setCellValue('A1', 'القسم: '.$schedule->department->department_name);

        $sheet->mergeCells('A2:D2');
        $sheet->setCellValue('A2', 'اسم المادة: '.$schedule->schedule_subject);

        $sheet->mergeCells('A3:D3');
        $sheet->setCellValue('A3', 'السنة: '.$academicLevelText);

        $sheet->mergeCells('A4:D4');
        $sheet->setCellValue('A4', 'تاريخ المادة: '.$schedule->schedule_exam_date);

        $sheet->mergeCells('A5:D5');
        $sheet->setCellValue('A5', 'فترة المادة: '.$timeSlotText);

        // ضبط ارتفاع الصفوف (زيادة الارتفاع بمقدار 40%)
        $sheet->getRowDimension(1)->setRowHeight(35);
        $sheet->getRowDimension(2)->setRowHeight(35);
        $sheet->getRowDimension(3)->setRowHeight(35);
        $sheet->getRowDimension(4)->setRowHeight(35);
        $sheet->getRowDimension(5)->setRowHeight(35);

        // زيادة ارتفاع صف العناوين
        $sheet->getRowDimension(6)->setRowHeight(30);

        // زيادة ارتفاع صفوف البيانات
        $highestRow = $sheet->getHighestRow();
        for ($row = 7; $row <= $highestRow; $row++) {
            $sheet->getRowDimension($row)->setRowHeight(25);
        }

        // ضبط عرض الأعمدة لتناسب الصفحة العمودية
        $sheet->getColumnDimension('A')->setWidth(15); // الرقم
        $sheet->getColumnDimension('B')->setWidth(40); // الاسم الكامل
        $sheet->getColumnDimension('C')->setWidth(25); // اسم الأب
        $sheet->getColumnDimension('D')->setWidth(25); // القاعة

        // تنسيق الترويسة
        $headerStyle = [
            'font' => [
                'bold' => true,
                'size' => 14,
            ],
            'alignment' => [
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
            ],
        ];

        $sheet->getStyle('A1:D5')->applyFromArray($headerStyle);

        // تنسيق عناوين الأعمدة
        $columnHeaderStyle = [
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
                'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                ],
            ],
        ];

        $sheet->getStyle('A6:D6')->applyFromArray($columnHeaderStyle);

        // تنسيق البيانات
        $dataStyle = [
            'font' => [
                'size' => 12,
            ],
            'alignment' => [
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
                'wrapText' => true, // تمكين التفاف النص
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                ],
            ],
        ];

        $sheet->getStyle('A7:D'.$highestRow)->applyFromArray($dataStyle);

        // إظهار خطوط الخلايا (Gridlines)
        $sheet->setShowGridlines(true);

        // إعداد الصفحة للطباعة بشكل عمودي على A4
        $sheet->getPageSetup()->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_PORTRAIT);
        $sheet->getPageSetup()->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_A4);
        $sheet->getPageSetup()->setFitToWidth(1);
        $sheet->getPageSetup()->setFitToHeight(0);

        // إضافة هوامش للصفحة
        $sheet->getPageMargins()->setTop(0.75);
        $sheet->getPageMargins()->setRight(0.75);
        $sheet->getPageMargins()->setLeft(0.75);
        $sheet->getPageMargins()->setBottom(0.75);
    }
}
