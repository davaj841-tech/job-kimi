<?php

namespace App\Support;

/**
 * Shared-hosting helpers for PHP functions disabled via disable_functions.
 */
final class ProcOpen
{
    public static function available(): bool
    {
        if (! function_exists('proc_open')) {
            return false;
        }

        $disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));

        return ! in_array('proc_open', $disabled, true);
    }
}
