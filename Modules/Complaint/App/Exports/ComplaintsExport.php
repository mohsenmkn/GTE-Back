<?php

namespace Modules\Complaint\App\Exports;

use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Modules\Complaint\App\Models\Complaint;
use Morilog\Jalali\Jalalian;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ComplaintsExport implements
    FromQuery,
    WithHeadings,
    WithMapping,
    WithStyles,
    WithTitle,
    ShouldAutoSize,
    WithChunkReading
{
    protected array $filters;

    public function __construct(array $filters = [])
    {
        $this->filters = $filters;
    }

    /**
     * Query برای export با اعمال فیلترها
     */
    public function query()
    {
        return Complaint::query()
            ->with(['category.parent.parent', 'user'])
            ->filter($this->filters)
            ->latest();
    }

    /**
     * خواندن داده‌ها به صورت chunk برای جلوگیری از مشکل حافظه
     */
    public function chunkSize(): int
    {
        return 500;
    }

    /**
     * عنوان sheet
     */
    public function title(): string
    {
        return 'شکایات';
    }

    /**
     * سربرگ ستون‌ها
     */
    public function headings(): array
    {
        return [
            'ردیف',
            'کد پیگیری',
            'موضوع',
            'شرح شکایت',
            'نام شاکی',
            'کد پرسنلی',
            'موبایل',
            'حوزه',
            'زیرمجموعه',
            'آیتم شکایت',
            'وضعیت',
            'اولویت',
            'تاریخ ثبت',
            'تاریخ پاسخ',
        ];
    }

    /**
     * Mapping برای هر ردیف
     */
    public function map($complaint): array
    {
        static $row = 0;
        $row++;

        return [
            $row,
            $complaint->tracking_code,
            $complaint->subject,
            $complaint->description,
            $complaint->user?->name ?? '',
            $complaint->user?->personnel_code ?? '',
            $complaint->user?->mobile ?? '',
            $complaint->category?->parent?->parent?->title ?? '',
            $complaint->category?->parent?->title ?? '',
            $complaint->category?->title ?? '',
            $complaint->status?->label() ?? '',
            $complaint->priority?->label() ?? '',
            $complaint->jalali_date ?? '',
            $complaint->answered_at
                ? Jalalian::fromCarbon($complaint->answered_at)->format('Y-m-d H:i')
                : '',
        ];
    }

    /**
     * استایل‌دهی به sheet
     */
    public function styles(Worksheet $sheet): array
    {
        // راست‌چین کردن کل sheet برای زبان فارسی
        $sheet->setRightToLeft(true);

        return [
            // Bold کردن ردیف سربرگ
            1 => [
                'font' => ['bold' => true],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'E8F0FE'],
                ],
            ],
        ];
    }
}
