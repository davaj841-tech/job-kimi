<?php

namespace App\Services\Sms;

/**
 * Production Melipayamak provider (alias of {@see MeliPayamakSmsGateway}).
 */
class MelipayamakProvider extends MeliPayamakSmsGateway
{
    public function name(): string
    {
        return 'melipayamak';
    }
}
