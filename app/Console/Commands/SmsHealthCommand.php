<?php

namespace App\Console\Commands;

use App\Services\Sms\SmsManager;
use Illuminate\Console\Command;

class SmsHealthCommand extends Command
{
    protected $signature = 'sms:health';

    protected $description = 'Check SMS configuration and provider reachability (no SMS is sent)';

    public function handle(SmsManager $sms): int
    {
        $health = $sms->health();

        $rows = [
            ['SMS enabled', $health['enabled'] ? 'yes' : 'no'],
            ['Provider', (string) $health['provider']],
            ['Credentials configured', $health['configured'] ? 'yes' : 'no'],
            ['From (sender line)', ! empty($health['from_configured']) ? 'yes' : 'no'],
            ['OTP pattern bodyId', ! empty($health['pattern_configured']) ? 'yes' : 'no'],
            ['Log fallback (dev)', ! empty($health['allow_log_fallback']) ? 'yes' : 'no'],
            ['API reachable', $health['api_reachable'] === null ? 'n/a' : ($health['api_reachable'] ? 'yes' : 'no')],
            ['OTP SMS enabled', $health['otp_enabled'] ? 'yes' : 'no'],
            ['Transactional SMS enabled', $health['transactional_enabled'] ? 'yes' : 'no'],
            ['Marketing SMS enabled', $health['marketing_enabled'] ? 'yes' : 'no'],
            ['Queue enabled (non-OTP)', $health['queue_enabled'] ? 'yes' : 'no'],
        ];

        $this->table(['Check', 'Result'], $rows);

        if (! $health['enabled']) {
            $this->warn('SMS is disabled. Set SMS_ENABLED=true or enable in admin settings.');

            return self::SUCCESS;
        }

        if (! $health['configured']) {
            $this->error('SMS credentials are not configured.');
            $this->line('Set MELIPAYAMAK_USERNAME + MELIPAYAMAK_PASSWORD in .env, or Admin → Settings → SMS.');

            return self::FAILURE;
        }

        if ($health['provider'] === 'melipayamak'
            && empty($health['pattern_configured'])
            && empty($health['from_configured'])
        ) {
            $this->warn('Neither OTP pattern nor sender line (From) is set — OTP plain fallback will fail.');
        }

        if ($health['api_reachable'] === false) {
            $this->error('Provider API is not reachable or credentials are invalid (GetCredit failed).');

            return self::FAILURE;
        }

        $this->info('SMS Health: PASS');

        return self::SUCCESS;
    }
}
