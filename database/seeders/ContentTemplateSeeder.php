<?php

namespace Database\Seeders;

use App\Enums\Content\ContentType;
use App\Models\ContentTemplate;
use Illuminate\Database\Seeder;

class ContentTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $missing = 'در منبع در دسترس، این مورد به‌صورت رسمی اعلام نشده است.';

        $templates = [
            [
                'name' => 'آزمون استخدامی جدید',
                'content_type' => ContentType::NewJobExam,
                'priority' => 100,
                'title_template' => 'راهنمای آزمون استخدامی {organization}؛ شرایط، زمان ثبت‌نام و نحوه ثبت‌نام',
                'content_template' => <<<'HTML'
<h2>معرفی آزمون استخدامی {organization}</h2>
<p>بر اساس اطلاعات ثبت‌شده از منابع معتبر در جاب‌آزمون، آگهی «{title}» مربوط به {organization} منتشر شده است. این مطلب صرفاً بر پایه داده‌های تأییدشده تهیه شده و هیچ اطلاعاتی را حدس نمی‌زند.</p>
{section_description}
<h3>زمان ثبت‌نام</h3>
<p>شروع ثبت‌نام: {registration_starts_at}</p>
<p>آخرین مهلت ثبت‌نام: {registration_deadline}</p>
<h3>زمان برگزاری آزمون</h3>
<p>{exam_date}</p>
<h3>شرایط و مدارک</h3>
<p>مدرک تحصیلی: {education}</p>
<p>رشته‌های مورد نیاز: {field_of_study}</p>
<p>سابقه کار: {experience}</p>
<p>نوع همکاری: {employment_type}</p>
{section_requirements}
<h3>محل خدمت</h3>
<p>استان: {province}</p>
<p>شهر: {city}</p>
<h3>نحوه ثبت‌نام</h3>
<p>در صورت اعلام لینک رسمی، از طریق لینک‌های مرتبط در انتهای مطلب اقدام کنید. اگر لینک ثبت‌نام در منبع موجود نباشد، باید منتظر اطلاع‌رسانی رسمی بمانید.</p>
<h3>منبع</h3>
<p>منبع گردآوری: {source_name}</p>
<p>این محتوا برای آگاهی‌رسانی است و مرجع نهایی، اطلاعیه رسمی سازمان مربوطه است.</p>
HTML
            ],
            [
                'name' => 'راهنمای ثبت‌نام',
                'content_type' => ContentType::RegistrationGuide,
                'priority' => 90,
                'title_template' => 'راهنمای ثبت‌نام استخدام {organization}؛ مهلت و مراحل اقدام',
                'content_template' => <<<'HTML'
<h2>راهنمای ثبت‌نام {organization}</h2>
<p>برای آگهی «{title}» اطلاعات ثبت‌نام زیر از داده‌های تأییدشده استخراج شده است.</p>
<h3>بازه ثبت‌نام</h3>
<p>شروع: {registration_starts_at}</p>
<p>مهلت: {registration_deadline}</p>
<h3>شرایط کلی</h3>
<p>تحصیلات: {education}</p>
<p>رشته: {field_of_study}</p>
<p>تجربه: {experience}</p>
{section_requirements}
<h3>نکات مهم</h3>
<ul>
<li>فقط از لینک‌های رسمی اعلام‌شده در آگهی استفاده کنید.</li>
<li>در صورت نبود تاریخ یا لینک در منبع، آن بخش را تکمیل‌نشده در نظر بگیرید.</li>
<li>مرجع نهایی، اطلاعیه سازمان {organization} است.</li>
</ul>
<p>منبع: {source_name}</p>
HTML
            ],
            [
                'name' => 'مهلت ثبت‌نام',
                'content_type' => ContentType::RegistrationDeadline,
                'priority' => 85,
                'title_template' => 'مهلت ثبت‌نام استخدام {organization}؛ آخرین فرصت اقدام',
                'content_template' => <<<'HTML'
<h2>مهلت ثبت‌نام {organization}</h2>
<p>طبق اطلاعات ذخیره‌شده برای آگهی «{title}»، آخرین مهلت ثبت‌نام برابر است با:</p>
<p><strong>{registration_deadline}</strong></p>
<p>شروع ثبت‌نام (در صورت اعلام): {registration_starts_at}</p>
<p>تاریخ آزمون (در صورت اعلام): {exam_date}</p>
<p>اگر مهلت در منبع رسمی تغییر کند، پس از به‌روزرسانی داده در جاب‌آزمون این مطلب نیز قابل به‌روزرسانی است.</p>
<p>منبع: {source_name}</p>
HTML
            ],
            [
                'name' => 'خلاصه آزمون',
                'content_type' => ContentType::JobExamSummary,
                'priority' => 80,
                'title_template' => 'خلاصه آزمون استخدامی {organization}؛ شرایط و زمان‌بندی',
                'content_template' => <<<'HTML'
<h2>خلاصه آگهی {organization}</h2>
<p>عنوان: {title}</p>
<p>دسته‌بندی: {job_category}</p>
<p>شروع ثبت‌نام: {registration_starts_at}</p>
<p>مهلت: {registration_deadline}</p>
<p>آزمون: {exam_date}</p>
<p>استان/شهر: {province} {city}</p>
{section_requirements}
<p>منبع: {source_name}</p>
HTML
            ],
            [
                'name' => 'استخدام سازمان',
                'content_type' => ContentType::OrganizationRecruitment,
                'priority' => 75,
                'title_template' => 'استخدام {organization}؛ جزئیات فرصت شغلی و شرایط اعلام‌شده',
                'content_template' => <<<'HTML'
<h2>استخدام در {organization}</h2>
<p>آگهی «{title}» برای {organization} در جاب‌آزمون ثبت و تأیید شده است.</p>
{section_description}
<p>تحصیلات: {education}</p>
<p>رشته: {field_of_study}</p>
<p>تجربه: {experience}</p>
<p>نوع همکاری: {employment_type}</p>
{section_requirements}
<p>منبع: {source_name}</p>
HTML
            ],
            [
                'name' => 'خلاصه استانی',
                'content_type' => ContentType::ProvinceRecruitmentSummary,
                'priority' => 70,
                'title_template' => 'استخدام در {province}؛ فرصت {organization}',
                'content_template' => <<<'HTML'
<h2>فرصت استخدامی در {province}</h2>
<p>سازمان: {organization}</p>
<p>عنوان: {title}</p>
<p>شهر: {city}</p>
<p>مهلت ثبت‌نام: {registration_deadline}</p>
<p>آزمون: {exam_date}</p>
{section_requirements}
<p>منبع: {source_name}</p>
HTML
            ],
            [
                'name' => 'رشته تحصیلی',
                'content_type' => ContentType::FieldOfStudyRecruitment,
                'priority' => 65,
                'title_template' => 'استخدام برای رشته {field_of_study} در {organization}',
                'content_template' => <<<'HTML'
<h2>فرصت شغلی مرتبط با رشته {field_of_study}</h2>
<p>سازمان: {organization}</p>
<p>عنوان آگهی: {title}</p>
<p>مدرک: {education}</p>
<p>مهلت ثبت‌نام: {registration_deadline}</p>
{section_requirements}
<p>منبع: {source_name}</p>
HTML
            ],
            [
                'name' => 'شرایط سنی',
                'content_type' => ContentType::AgeRequirements,
                'priority' => 60,
                'title_template' => 'شرایط سنی استخدام {organization} بر اساس آگهی رسمی',
                'content_template' => <<<'HTML'
<h2>شرایط سنی اعلام‌شده</h2>
<p>در آگهی «{title}» مربوط به {organization}، بخش شرایط/مدارک شامل اشاره به سن است. متن استخراج‌شده از منبع:</p>
{section_requirements}
<p>توجه: فقط همان موارد منتشرشده در منبع معتبر نقل می‌شود و هیچ شرط سنی اضافه‌ای ساخته نمی‌شود.</p>
<p>منبع: {source_name}</p>
HTML
            ],
            [
                'name' => 'مدارک مورد نیاز',
                'content_type' => ContentType::RequiredDocuments,
                'priority' => 55,
                'title_template' => 'مدارک مورد نیاز استخدام {organization}',
                'content_template' => <<<'HTML'
<h2>مدارک اعلام‌شده برای ثبت‌نام</h2>
<p>آگهی: {title}</p>
<p>سازمان: {organization}</p>
<p>بر اساس بخش شرایط/مدارک آگهی:</p>
{section_requirements}
<p>اگر فهرست مدارک در منبع کامل نباشد، تا اعلام رسمی سازمان نباید فرضی تکمیل شود.</p>
<p>منبع: {source_name}</p>
HTML
            ],
            [
                'name' => 'خلاصه هفتگی',
                'content_type' => ContentType::WeeklyRecruitmentSummary,
                'priority' => 50,
                'title_template' => 'خلاصه فرصت‌های استخدامی هفته؛ {week_count} آگهی تأییدشده',
                'content_template' => <<<'HTML'
<h2>خلاصه هفتگی استخدام‌ها</h2>
<p>در هفت روز گذشته، تعداد {week_count} آگهی تأییدشده از منابع معتبر در جاب‌آزمون ثبت شده است. فهرست زیر فقط شامل داده‌های ذخیره‌شده است:</p>
<p>{weekly_list_html}</p>
<p>برای جزئیات هر مورد، به صفحه آگهی در جاب‌آزمون مراجعه کنید. تاریخ تهیه گزارش: {published_at}</p>
HTML
            ],
        ];

        foreach ($templates as $row) {
            // Pre-process section placeholders into renderer-friendly optional blocks via metadata flag
            ContentTemplate::query()->updateOrCreate(
                ['content_type' => $row['content_type']->value, 'name' => $row['name']],
                [
                    'title_template' => $row['title_template'],
                    'content_template' => $this->expandSections($row['content_template'], $missing),
                    'enabled' => true,
                    'priority' => $row['priority'],
                    'metadata' => ['missing_fallback' => $missing],
                ]
            );
        }
    }

    protected function expandSections(string $tpl, string $missing): string
    {
        $tpl = str_replace(
            '{section_description}',
            '<h3>معرفی و توضیحات</h3><p>{description}</p>',
            $tpl
        );
        $tpl = str_replace(
            '{section_requirements}',
            '<h3>شرایط و الزامات اعلام‌شده</h3><p>{requirements}</p>',
            $tpl
        );

        return $tpl;
    }
}
