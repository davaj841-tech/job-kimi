<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Models\User;
use RuntimeException;

final class WalletFrozenException extends RuntimeException
{
    public function __construct(public readonly User $user)
    {
        parent::__construct('Wallet is frozen.');
    }
}
