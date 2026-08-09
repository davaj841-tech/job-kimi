<?php

namespace App\Contracts\Aggregation;

/**
 * Future parser boundary for endpoint-specific raw payloads.
 */
interface JobParserInterface
{
    public function parserType(): string;

    /**
     * @param  mixed  $payload
     * @return array<int, array<string, mixed>>
     */
    public function parse(mixed $payload, array $context = []): array;
}
