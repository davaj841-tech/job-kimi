<?php

namespace App\Enums\Aggregation;

enum JobSourceType: string
{
    case Government = 'government';
    case Ministry = 'ministry';
    case PublicInstitution = 'public_institution';
    case Bank = 'bank';
    case Company = 'company';
    case University = 'university';
    case ExamAuthority = 'exam_authority';
    case CareerPage = 'career_page';

    public function label(): string
    {
        return match ($this) {
            self::Government => 'سازمان دولتی',
            self::Ministry => 'وزارتخانه / دستگاه اجرایی',
            self::PublicInstitution => 'نهاد عمومی',
            self::Bank => 'بانک',
            self::Company => 'شرکت معتبر',
            self::University => 'دانشگاه / مؤسسه آموزشی',
            self::ExamAuthority => 'مرجع آزمون / استخدام',
            self::CareerPage => 'صفحه شغلی رسمی',
        };
    }
}
