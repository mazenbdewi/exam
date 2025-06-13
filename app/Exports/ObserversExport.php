<?php

namespace App\Exports;

use Auth;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ObserversExport implements FromQuery, WithCustomStartCell, WithHeadings, WithMapping, WithStyles
{
    protected $query;

    protected $user;

    public function __construct($query)
    {
        $this->query = $query;
        $this->user = Auth::user();
    }

    public function startCell(): string
    {
        return $this->user->hasRole('super_admin') ? 'A5' : 'B6';
    }

    public function query()
    {
        return $this->query;
    }

    public function headings(): array
    {
        $headings = [
            'المراقب',
            'الدور',
            'المادة',
            'تاريخ الامتحان',
            'الفترة',
            'القاعة',
            'التوقيع',
        ];

        if (! $this->user->hasRole('super_admin')) {
            array_shift($headings); // إزالة عمود المراقب لغير المشرف
        }

        return $headings;
    }

    // public function map($observer): array
    // {
    //     $observerName = $observer->user->name;
    //     $roleName = $observer->user->roles->first()->name ?? '';

    //     $data = [
    //         $observerName,
    //         $roleName,
    //         $observer->schedule->schedule_subject,
    //         $observer->schedule->schedule_exam_date,
    //         $this->formatTimeSlot($observer->schedule->schedule_time_slot),
    //         $observer->room->room_name,
    //         '',
    //     ];

    //     if (! $this->user->hasRole('super_admin')) {
    //         array_shift($data); // إزالة اسم المراقب لغير المشرف
    //     }

    //     return $data;
    // }

    public function map($observer): array
    {
        $observerName = $observer->user->name;
        $roleName = $observer->user->roles->first()->name ?? '';

        $data = [
            $observerName,
            $roleName,
            $observer->schedule->schedule_subject,
            $observer->schedule->schedule_exam_date,
            $this->formatTimeSlot($observer->schedule->schedule_time_slot),
            '',
            '',
        ];

        if (! $this->user->hasRole('super_admin')) {
            array_shift($data); // إزالة اسم المراقب لغير المشرف
        }

        return $data;
    }

    private function formatTimeSlot($slot): string
    {
        return match ($slot) {
            'morning' => 'صباحية',
            'night' => 'مسائية',
            default => $slot,
        };
    }

    public function styles(Worksheet $sheet)
    {

        $sheet->getPageSetup()->setPaperSize(PageSetup::PAPERSIZE_A4);
        $sheet->getPageSetup()->setOrientation(PageSetup::ORIENTATION_PORTRAIT);
        $sheet->getPageSetup()->setFitToPage(true);
        $sheet->getPageSetup()->setFitToWidth(1);
        $sheet->getPageSetup()->setFitToHeight(0);

        // هوامش الصفحة
        $sheet->getPageMargins()->setTop(0.5);
        $sheet->getPageMargins()->setRight(0.5);
        $sheet->getPageMargins()->setLeft(0.5);
        $sheet->getPageMargins()->setBottom(0.5);

        // ترويسة وتذييل الصفحة
        $sheet->getHeaderFooter()->setOddHeader('وزارة التعليم العالي – جامعة اللاذقية – كلية الهمك');
        $sheet->getHeaderFooter()->setOddFooter('&CPage &P of &N');

        // منطقة الطباعة
        $sheet->getPageSetup()->setPrintArea(
            $this->user->hasRole('super_admin') ? 'A1:G'.$sheet->getHighestRow() : 'B1:G'.$sheet->getHighestRow()
        );

        // كتابة الترويسة
        $titleCell = $this->user->hasRole('super_admin') ? 'A' : 'B';

        $sheet->setCellValue($titleCell.'1', 'وزارة التعليم العالي');
        $sheet->setCellValue($titleCell.'2', 'جامعة اللاذقية');
        $sheet->setCellValue($titleCell.'3', 'كلية هندسة الهمك');

        $sheet->getStyle($titleCell.'1:'.$titleCell.'3')->applyFromArray([
            'font' => ['size' => 16, 'bold' => true],
            'alignment' => [
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
            ],
        ]);

        if (! $this->user->hasRole('super_admin')) {
            $sheet->setCellValue('D4', 'السيد/ة: '.$this->user->name.' ('.$this->user->roles->first()->name.')');
            $sheet->getStyle('D4')->applyFromArray([
                'font' => ['size' => 14, 'bold' => true],
                'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER],
            ]);
        }

        // تحديد صف العناوين
        $headerRow = $this->user->hasRole('super_admin') ? 5 : 6;

        // تنسيق عناوين الأعمدة
        $sheet->getStyle(($this->user->hasRole('super_admin') ? 'A' : 'B').$headerRow.':G'.$headerRow)->applyFromArray([
            'font' => ['bold' => true, 'size' => 14],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'D3D3D3'],
            ],
            'borders' => [
                'allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN],
            ],
        ]);

        // إخفاء العمود A لغير المشرف
        if (! $this->user->hasRole('super_admin')) {
            $sheet->getColumnDimension('A')->setVisible(false);
        }

        // تنسيق باقي الجدول
        $sheet->getStyle(($this->user->hasRole('super_admin') ? 'A' : 'B').$headerRow.':G'.$sheet->getHighestRow())->applyFromArray([
            'borders' => [
                'allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN],
            ],
            'alignment' => [
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
        ]);

        // تعديل ارتفاع الصف ليظهر النص بشكل أوضح
        for ($row = 1; $row <= $sheet->getHighestRow(); $row++) {
            $sheet->getRowDimension($row)->setRowHeight(30);
        }

        // ضبط أبعاد الأعمدة
        $sheet->getColumnDimension('A')->setWidth(35); // اسم المراقب أوسع
        $sheet->getColumnDimension('B')->setWidth(15); // اسم المراقب أوسع
        $sheet->getColumnDimension('C')->setWidth(20); // الدور
        $sheet->getColumnDimension('D')->setWidth(25); // المادة
        $sheet->getColumnDimension('E')->setWidth(20); // التاريخ
        $sheet->getColumnDimension('F')->setWidth(20); // الفترة
        $sheet->getColumnDimension('G')->setWidth(20); // القاعة
        $sheet->getColumnDimension('H')->setWidth(30); // التوقيع
    }
}
