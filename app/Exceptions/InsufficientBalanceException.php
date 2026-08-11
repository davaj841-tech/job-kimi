<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Models\User;
use RuntimeException;

final class InsufficientBalanceException extends RuntimeException
{
    public function __construct(
        public readonly User $user,
        public readonly int $requested,
        public readonly int $available,
    ) {
        parent::__construct(
            sprintf('Insufficient balance: requested %d, available %d', $requested, $available)
        );
    }
}
