<?php

namespace Tests\Unit;

use App\Enums\Aggregation\JobSourceReliability;
use App\Enums\Aggregation\JobSourceType;
use PHPUnit\Framework\TestCase;

class AggregationEnumsTest extends TestCase
{
    public function test_source_type_cases_cover_required_org_kinds(): void
    {
        $values = array_map(fn (JobSourceType $t) => $t->value, JobSourceType::cases());

        foreach (['government', 'ministry', 'bank', 'company', 'university', 'exam_authority', 'career_page'] as $expected) {
            $this->assertContains($expected, $values);
        }
    }

    public function test_reliability_levels_and_auto_publish_gate(): void
    {
        $this->assertTrue(JobSourceReliability::Official->allowsAutoPublish());
        $this->assertTrue(JobSourceReliability::HighlyTrusted->allowsAutoPublish());
        $this->assertFalse(JobSourceReliability::Trusted->allowsAutoPublish());
        $this->assertFalse(JobSourceReliability::Unverified->allowsAutoPublish());
        $this->assertLessThan(
            JobSourceReliability::Unverified->sortWeight(),
            JobSourceReliability::Official->sortWeight()
        );
    }
}
