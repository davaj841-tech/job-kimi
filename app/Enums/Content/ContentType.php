<?php

namespace App\Enums\Content;

enum ContentType: string
{
    case NewJobExam = 'NEW_JOB_EXAM';
    case RegistrationGuide = 'REGISTRATION_GUIDE';
    case RegistrationDeadline = 'REGISTRATION_DEADLINE';
    case JobExamSummary = 'JOB_EXAM_SUMMARY';
    case OrganizationRecruitment = 'ORGANIZATION_RECRUITMENT';
    case ProvinceRecruitmentSummary = 'PROVINCE_RECRUITMENT_SUMMARY';
    case FieldOfStudyRecruitment = 'FIELD_OF_STUDY_RECRUITMENT';
    case AgeRequirements = 'AGE_REQUIREMENTS';
    case RequiredDocuments = 'REQUIRED_DOCUMENTS';
    case WeeklyRecruitmentSummary = 'WEEKLY_RECRUITMENT_SUMMARY';

    public function label(): string
    {
        return match ($this) {
            self::NewJobExam => 'آزمون استخدامی جدید',
            self::RegistrationGuide => 'راهنمای ثبت‌نام',
            self::RegistrationDeadline => 'مهلت ثبت‌نام',
            self::JobExamSummary => 'خلاصه آزمون',
            self::OrganizationRecruitment => 'استخدام سازمان',
            self::ProvinceRecruitmentSummary => 'خلاصه استانی',
            self::FieldOfStudyRecruitment => 'رشته تحصیلی',
            self::AgeRequirements => 'شرایط سنی',
            self::RequiredDocuments => 'مدارک مورد نیاز',
            self::WeeklyRecruitmentSummary => 'خلاصه هفتگی',
        };
    }
}
