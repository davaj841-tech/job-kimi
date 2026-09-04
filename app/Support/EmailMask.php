<?php

namespace App\Support;

final class EmailMask
{
    public static function mask(?string $email): string
    {
        $email = strtolower(trim((string) $email));
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return '***';
        }

        [$local, $domain] = explode('@', $email, 2);
        $keep = min(2, max(1, strlen($local)));

        return substr($local, 0, $keep).str_repeat('*', max(3, strlen($local) - $keep)).'@'.$domain;
    }
}
