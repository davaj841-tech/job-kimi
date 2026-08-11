<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\ExamService;
use PHPUnit\Framework\TestCase;

final class ExamSubjectDisplayNameTest extends TestCase
{
    public function test_maps_english_slugs_to_persian_names(): void
    {
        $this->assertSame('معارف', ExamService::subjectDisplayName('islamic'));
        $this->assertSame('عمومی', ExamService::subjectDisplayName('general'));
        $this->assertSame('ریاضی', ExamService::subjectDisplayName('math'));
        $this->assertSame('ادبیات', ExamService::subjectDisplayName('literature'));
    }

    public function test_keeps_persian_database_label(): void
    {
        $this->assertSame('معارف اسلامی', ExamService::subjectDisplayName('islamic', 'معارف اسلامی'));
    }

    public function test_does_not_show_ascii_slug_as_label(): void
    {
        $this->assertSame('معارف', ExamService::subjectDisplayName('islamic', 'islamic'));
    }
}
