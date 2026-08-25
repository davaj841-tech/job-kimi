<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $uuid
 * @property string $version
 * @property string|null $previous_version
 * @property string $status
 * @property string|null $release_type
 * @property string|null $description
 * @property array<string, mixed>|null $manifest
 * @property array<string, mixed>|null $preflight
 * @property list<array{at: string, level: string, message: string}>|null $log
 * @property string|null $pack_path
 * @property string|null $backup_id
 * @property string|null $full_backup_path
 * @property string|null $files_backup_path
 * @property string|null $database_backup_path
 * @property bool $migrations_ran
 * @property bool|null $migrations_reversible
 * @property bool|null $rollback_complete
 * @property string|null $error
 * @property int|null $user_id
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 * @property int|null $duration_ms
 */
class SystemUpdate extends Model
{
    public const PENDING = 'PENDING';

    public const VALIDATING = 'VALIDATING';

    public const BACKING_UP = 'BACKING_UP';

    public const INSTALLING = 'INSTALLING';

    public const RUNNING_MIGRATIONS = 'RUNNING_MIGRATIONS';

    public const CLEARING_CACHE = 'CLEARING_CACHE';

    public const VERIFYING = 'VERIFYING';

    public const COMPLETED = 'COMPLETED';

    public const FAILED = 'FAILED';

    public const ROLLING_BACK = 'ROLLING_BACK';

    public const ROLLED_BACK = 'ROLLED_BACK';

    protected $fillable = [
        'uuid',
        'version',
        'previous_version',
        'status',
        'release_type',
        'description',
        'manifest',
        'preflight',
        'log',
        'pack_path',
        'backup_id',
        'full_backup_path',
        'files_backup_path',
        'database_backup_path',
        'migrations_ran',
        'migrations_reversible',
        'rollback_complete',
        'error',
        'user_id',
        'started_at',
        'finished_at',
        'duration_ms',
    ];

    protected function casts(): array
    {
        return [
            'manifest' => 'array',
            'preflight' => 'array',
            'log' => 'array',
            'migrations_ran' => 'boolean',
            'migrations_reversible' => 'boolean',
            'rollback_complete' => 'boolean',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function appendLog(string $message, string $level = 'info'): void
    {
        $log = $this->log ?? [];
        $log[] = [
            'at' => now()->toIso8601String(),
            'level' => $level,
            'message' => $message,
        ];
        $this->log = $log;
        $this->save();
    }
}
