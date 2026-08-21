<?php

declare(strict_types=1);

namespace Tests\Unit\Update;

use App\Services\Update\ManifestValidator;
use App\Services\Update\SemVer;
use App\Services\Update\UpdatePathGuard;
use Tests\TestCase;

final class SemVerAndPathTest extends TestCase
{
    public function test_semver_compare(): void
    {
        $this->assertTrue(SemVer::greaterThan('1.0.1', '1.0.0'));
        $this->assertFalse(SemVer::greaterThan('1.0.0', '1.0.1'));
        $this->assertTrue(SemVer::greaterOrEqual('1.0.0', '1.0.0'));
        $this->assertTrue(SemVer::isValid('2.10.3'));
        $this->assertFalse(SemVer::isValid('v1.0.0'));
    }

    public function test_path_guard_blocks_traversal_and_env(): void
    {
        $g = new UpdatePathGuard;
        $this->assertFalse($g->isSafeRelative('../.env'));
        $this->assertFalse($g->isSafeRelative('app/../../.env'));
        $this->assertTrue($g->isProtected('.env'));
        $this->assertTrue($g->isProtected('storage/logs/laravel.log'));
        $this->assertTrue($g->isProtected('vendor/autoload.php'));
        $this->assertTrue($g->isAllowedRoot('app/Models/User.php'));
        $this->assertFalse($g->isAllowedRoot('evil/hack.php'));
    }

    public function test_manifest_requires_newer_version(): void
    {
        $v = new ManifestValidator;
        $manifest = [
            'application' => 'JobAzmoon',
            'version' => '1.0.0',
            'minimum_version' => '1.0.0',
            'release_date' => '2026-08-21',
            'release_type' => 'patch',
            'php' => '8.2',
            'laravel' => '11',
            'backup_required' => true,
            'migration_required' => false,
            'maintenance_mode' => false,
            'files' => [],
        ];
        $result = $v->validate($manifest, '1.0.0');
        $this->assertFalse($result['ok']);
    }
}
