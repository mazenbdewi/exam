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
            'المادة',
            'تاريخ الامتحان',
            'الفترة',
            'القاعة',
            'التوقيع', // إضافة عمود التوقيع
        ];

        if (! $this->user->hasRole('super_admin')) {
            array_shift($headings); // إزالة عمود المراقب
        }

        return $headings;
    }

    public function map($observer): array
    {
        $data = [
            $observer->user->name,
            $observer->schedule->schedule_subject,
            $observer->schedule->schedule_exam_date,
            $this->formatTimeSlot($observer->schedule->schedule_time_slot),
            $observer->room->room_name,
            '', // خلية فارغة للتوقيع
        ];

        if (! $this->user->hasRole('super_admin')) {
            array_shift($data); // إزالة بيانات المراقب
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
        // إعدادات الطباعة
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

        // ترويسة الصفحة
        $sheet->getHeaderFooter()
            ->setOddHeader('وزارة التعليم العالي – جامعة اللاذقية – كلية الهمك');
        // تذييل الصفحة
        $sheet->getHeaderFooter()
            ->setOddFooter('&CPage &P of &N');

        // منطقة الطباعة
        $sheet->getPageSetup()->setPrintArea(
            $this->user->hasRole('super_admin')
                ? 'A1:F'.$sheet->getHighestRow()
                : 'B1:F'.$sheet->getHighestRow()
        );

        // كتابة الترويسة في الخلايا المنفصلة
        if ($this->user->hasRole('super_admin')) {
            $sheet->setCellValue('A1', 'وزارة التعليم العالي');
            $sheet->setCellValue('A2', 'جامعة اللاذقية');
            $sheet->setCellValue('A3', 'كلية هندسة الهمك');

            // توسيط النص في الخلايا
            $sheet->getStyle('A1:A3')->applyFromArray([
                'alignment' => [
                    'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                    'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
                ],
            ]);
        } else {
            $sheet->setCellValue('B1', 'وزارة التعليم العالي');
            $sheet->setCellValue('B2', 'جامعة اللاذقية');
            $sheet->setCellValue('B3', 'كلية هندسة الهمك');

            // توسيط النص في الخلايا
            $sheet->getStyle('B1:B3')->applyFromArray([
                'alignment' => [
                    'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                    'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
                ],
            ]);
        }

        // تنسيق الترويسة
        $sheet->getStyle($this->user->hasRole('super_admin') ? 'A1:A3' : 'B1:B3')->applyFromArray([
            'font' => [
                'size' => 16,
                'bold' => true,
            ],
            'alignment' => [
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
            ],
        ]);

        // إضافة اسم المستخدم ورووله
        if (! $this->user->hasRole('super_admin')) {
            $sheet->setCellValue('D4', 'السيد/ة: '.$this->user->name.' ('.$this->user->roles->first()->name.')');
            $sheet->getStyle('D4')->applyFromArray([
                'font' => [
                    'size' => 14,
                    'bold' => true,
                ],
                'alignment' => [
                    'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                ],
            ]);
        }

        // تنسيق عناوين الأعمدة
        $headerRow = $this->user->hasRole('super_admin') ? 5 : 6;

        $sheet->getStyle($this->user->hasRole('super_admin') ? 'A'.$headerRow.':F'.$headerRow : 'B'.$headerRow.':F'.$headerRow)->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 14,
            ],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'D3D3D3'],
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                ],
            ],
        ]);

        // إخفاء العمود A إذا لم يكن المستخدم super_admin
        if (! $this->user->hasRole('super_admin')) {
            $sheet->getColumnDimension('A')->setVisible(false);
        }

        // تنسيق عام للخلايا
        $sheet->getStyle($this->user->hasRole('super_admin') ? 'A'.$headerRow.':F'.$sheet->getHighestRow() : 'B'.$headerRow.':F'.$sheet->getHighestRow())->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                ],
            ],
            'alignment' => [
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
            ],
        ]);

        // ضبط أبعاد الأعمدة
        $sheet->getColumnDimension('B')->setWidth(30);
        $sheet->getColumnDimension('C')->setWidth(20);
        $sheet->getColumnDimension('D')->setWidth(20);
        $sheet->getColumnDimension('E')->setWidth(20);
        $sheet->getColumnDimension('F')->setWidth(30); // عرض عمود التوقيع

    }
}
