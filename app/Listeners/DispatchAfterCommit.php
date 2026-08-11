<?php

declare(strict_types=1);

namespace App\Listeners;

use Illuminate\Support\Facades\DB;

/**
 * Ensures side effects run only after the surrounding DB transaction commits.
 */
final class DispatchAfterCommit
{
    public static function handle(object $event, callable $dispatcher): void
    {
        DB::afterCommit(static function () use ($event, $dispatcher): void {
            $dispatcher($event);
        });
    }
}
