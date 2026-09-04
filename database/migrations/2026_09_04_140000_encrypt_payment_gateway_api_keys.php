<?php

use App\Models\PaymentGateway;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schema;

/**
 * Encrypt payment_gateways.api_key at rest (credentials only — not merchant_id identifiers).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('payment_gateways') || ! Schema::hasColumn('payment_gateways', 'api_key')) {
            return;
        }

        PaymentGateway::query()->orderBy('id')->each(function (PaymentGateway $row): void {
            $raw = $row->getAttributes()['api_key'] ?? null;
            if (! is_string($raw) || trim($raw) === '') {
                return;
            }

            try {
                Crypt::decryptString($raw);

                return; // already encrypted
            } catch (\Throwable) {
                // plaintext — encrypt below
            }

            // Bypass cast: write ciphertext directly.
            PaymentGateway::query()->whereKey($row->id)->update([
                'api_key' => Crypt::encryptString($raw),
            ]);
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('payment_gateways') || ! Schema::hasColumn('payment_gateways', 'api_key')) {
            return;
        }

        PaymentGateway::query()->orderBy('id')->each(function (PaymentGateway $row): void {
            $raw = $row->getAttributes()['api_key'] ?? null;
            if (! is_string($raw) || trim($raw) === '') {
                return;
            }

            try {
                $plain = Crypt::decryptString($raw);
            } catch (\Throwable) {
                return;
            }

            PaymentGateway::query()->whereKey($row->id)->update([
                'api_key' => $plain,
            ]);
        });
    }
};
