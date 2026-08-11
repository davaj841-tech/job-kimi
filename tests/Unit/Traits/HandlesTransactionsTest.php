<?php

declare(strict_types=1);

namespace Tests\Unit\Traits;

use App\Exceptions\TransactionFailedException;
use App\Traits\HandlesTransactions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

final class HandlesTransactionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_retries_on_deadlock(): void
    {
        $attempts = 0;
        $runner = new class
        {
            use HandlesTransactions;
        };

        $value = $runner->transaction(function () use (&$attempts) {
            $attempts++;
            if ($attempts === 1) {
                throw new RuntimeException('SQLSTATE[40001]: Serialization failure: 1213 Deadlock found');
            }

            return 42;
        }, 3);

        $this->assertSame(42, $value);
        $this->assertSame(2, $attempts);
    }

    public function test_throws_after_max_retries(): void
    {
        $runner = new class
        {
            use HandlesTransactions;
        };

        $this->expectException(TransactionFailedException::class);

        $runner->transaction(function (): void {
            throw new RuntimeException('Deadlock found when trying to get lock');
        }, 2);
    }

    public function test_sets_isolation_level(): void
    {
        $runner = new class
        {
            use HandlesTransactions;
        };

        $result = $runner->transaction(function () {
            return DB::table('users')->count();
        }, 1, 'SERIALIZABLE');

        $this->assertIsInt($result);
    }
}
