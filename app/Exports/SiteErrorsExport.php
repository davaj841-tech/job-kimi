<?php

namespace App\Exports;

use App\Models\SiteError;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class SiteErrorsExport implements FromCollection, WithHeadings, WithMapping
{
    public function __construct(
        protected Collection $rows
    ) {}

    public function collection(): Collection
    {
        return $this->rows;
    }

    public function headings(): array
    {
        return [
            'شناسه',
            'سطح',
            'پیام فارسی',
            'پیام اصلی',
            'کلاس خطا',
            'فایل',
            'خط',
            'آدرس',
            'متد',
            'کاربر',
            'تکرار',
            'آخرین مشاهده',
            'وضعیت',
        ];
    }

    /**
     * @param  SiteError  $row
     */
    public function map($row): array
    {
        return [
            $row->id,
            $row->level,
            $row->message_fa,
            $row->message,
            $row->exception_class,
            $row->file,
            $row->line,
            $row->url,
            $row->method,
            $row->user?->name ?: $row->user?->username,
            $row->occurrences,
            optional($row->last_seen_at)->format('Y-m-d H:i:s'),
            $row->resolved_at ? 'حل‌شده' : 'حل‌نشده',
        ];
    }
}
