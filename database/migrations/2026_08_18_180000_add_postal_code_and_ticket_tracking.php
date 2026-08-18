<?php

use App\Models\Ticket;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'postal_code')) {
                $table->string('postal_code', 10)->nullable();
            }
        });

        $addedTracking = false;
        Schema::table('tickets', function (Blueprint $table) use (&$addedTracking) {
            if (! Schema::hasColumn('tickets', 'tracking_code')) {
                $table->string('tracking_code', 6)->nullable();
                $addedTracking = true;
            }
        });

        $used = [];
        Ticket::query()->orderBy('id')->each(function (Ticket $ticket) use (&$used) {
            if (preg_match('/^\d{6}$/', (string) $ticket->tracking_code)) {
                $used[$ticket->tracking_code] = true;

                return;
            }
            do {
                $code = (string) random_int(100000, 999999);
            } while (isset($used[$code]));
            $used[$code] = true;
            $ticket->forceFill(['tracking_code' => $code])->saveQuietly();
        });

        if ($addedTracking) {
            Schema::table('tickets', function (Blueprint $table) {
                $table->unique('tracking_code');
            });
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'postal_code')) {
                $table->dropColumn('postal_code');
            }
        });

        Schema::table('tickets', function (Blueprint $table) {
            if (Schema::hasColumn('tickets', 'tracking_code')) {
                $table->dropUnique(['tracking_code']);
                $table->dropColumn('tracking_code');
            }
        });
    }
};
