<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;
use Throwable;

final class TransactionFailedException extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        string $message = 'Transaction failed after retries',
        ?Throwable $previous = null,
        public readonly array $context = [],
    ) {
        parent::__construct($message, 0, $previous);
    }
}
