<?php

namespace App\Services\Aggregation\Parsers;

use App\Contracts\Aggregation\JobParserInterface;

/**
 * Resolves endpoint parser_type to a registered parser implementation.
 * Source-specific parsers stay isolated from the generic RSS/JSON/HTML parsers.
 */
class SourceParserRegistry
{
    /** @var array<string, JobParserInterface> */
    protected array $parsers = [];

    /**
     * @param  iterable<JobParserInterface>  $parsers
     */
    public function __construct(iterable $parsers = [])
    {
        foreach ($parsers as $parser) {
            $this->register($parser);
        }
    }

    public function register(JobParserInterface $parser): void
    {
        $this->parsers[$parser->parserType()] = $parser;
    }

    public function get(?string $parserType): ?JobParserInterface
    {
        if ($parserType === null || $parserType === '') {
            return null;
        }

        return $this->parsers[$parserType] ?? null;
    }

    /**
     * @return list<string>
     */
    public function keys(): array
    {
        return array_keys($this->parsers);
    }
}
