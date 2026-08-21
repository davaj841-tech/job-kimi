<?php

namespace App\Exports;

use App\Models\SiteError;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * @implements WithMapping<SiteError>
 */
class SiteErrorsExport implements FromCollection, WithHeadings, WithMapping
{
    /**
     * @param  Collection<int, SiteError>  $rows
     */
    public function __construct(
        protected Collection $rows
    ) {}

    /**
     * @return Collection<int, SiteError>
     */
    public function collection(): Collection
    {
        return $this->rows;
    }

    /**
     * @return list<string>
     */
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
     * @return list<mixed>
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
