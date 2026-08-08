<?php

namespace App\Contracts\Aggregation;

/**
 * Validates a normalized job payload before persistence/promotion.
 */
interface JobValidatorInterface
{
    /**
     * @param  array<string, mixed>  $normalized
     * @return array{valid: bool, errors: array<int, string>}
     */
    public function validate(array $normalized): array;
}
