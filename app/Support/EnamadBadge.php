<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Builds public ENAMAD / Samandehi badge data from Settings + env.
 * Does not invent seal IDs or codes — only uses configured values.
 */
final class EnamadBadge
{
    private const ENAMAD_HOSTS = [
        'trustseal.enamad.ir',
        'enamad.ir',
        'www.enamad.ir',
    ];

    private const SAMANDEHI_HOSTS = [
        'logo.samandehi.ir',
        'samandehi.ir',
        'www.samandehi.ir',
    ];

    /**
     * @return array{
     *   enamad_enabled: bool,
     *   enamad_id: string,
     *   enamad_code: string,
     *   enamad_url: string,
     *   enamad_logo_url: string,
     *   samandehi_url: string
     * }
     */
    public static function publicPayload(): array
    {
        $enabledDefault = filter_var(config('enamad.enabled', false), FILTER_VALIDATE_BOOLEAN);
        $enabled = Setting::getBool('enamad_enabled', $enabledDefault);

        $id = trim((string) Setting::getFilled('enamad_id', config('enamad.id', '')));
        $code = trim((string) Setting::getFilled('enamad_code', config('enamad.code', '')));
        $rawUrl = trim((string) Setting::getFilled('enamad_url', config('enamad.url', '')));

        if (($id === '' || $code === '') && $rawUrl !== '') {
            $parsed = self::parseOfficialUrl($rawUrl);
            if ($parsed !== null) {
                $id = $id !== '' ? $id : $parsed['id'];
                $code = $code !== '' ? $code : $parsed['code'];
            }
        }

        $id = self::sanitizeId($id);
        $code = self::sanitizeCode($code);

        $configured = $enabled && $id !== '' && $code !== '';

        $samandehi = self::sanitizeExternalUrl(
            (string) Setting::getFilled('samandehi_url', config('enamad.samandehi_url', '')),
            self::SAMANDEHI_HOSTS
        );

        return [
            'enamad_enabled' => $configured,
            'enamad_id' => $configured ? $id : '',
            'enamad_code' => $configured ? $code : '',
            'enamad_url' => $configured ? self::verifyUrl($id, $code) : '',
            'enamad_logo_url' => $configured ? self::logoUrl($id, $code) : '',
            'samandehi_url' => $samandehi,
        ];
    }

    public static function verifyUrl(string $id, string $code): string
    {
        return 'https://trustseal.enamad.ir/?id='.rawurlencode($id).'&Code='.rawurlencode($code);
    }

    public static function logoUrl(string $id, string $code): string
    {
        return 'https://trustseal.enamad.ir/logo.aspx?id='.rawurlencode($id).'&Code='.rawurlencode($code);
    }

    /**
     * @return array{id: string, code: string}|null
     */
    public static function parseOfficialUrl(string $url): ?array
    {
        $url = trim($url);
        if ($url === '' || ! filter_var($url, FILTER_VALIDATE_URL)) {
            return null;
        }

        $parts = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));
        if ($scheme !== 'https' || ! in_array($host, self::ENAMAD_HOSTS, true)) {
            return null;
        }

        $query = [];
        parse_str((string) ($parts['query'] ?? ''), $query);
        $id = self::sanitizeId((string) ($query['id'] ?? ''));
        $code = self::sanitizeCode((string) ($query['Code'] ?? $query['code'] ?? ''));

        if ($id === '' || $code === '') {
            return null;
        }

        return ['id' => $id, 'code' => $code];
    }

    public static function sanitizeId(string $id): string
    {
        $id = trim($id);

        return preg_match('/^\d{1,12}$/', $id) === 1 ? $id : '';
    }

    public static function sanitizeCode(string $code): string
    {
        $code = trim($code);

        return preg_match('/^[A-Za-z0-9]{8,64}$/', $code) === 1 ? $code : '';
    }

    /**
     * @param  list<string>  $allowedHosts
     */
    public static function sanitizeExternalUrl(string $url, array $allowedHosts): string
    {
        $url = trim($url);
        if ($url === '' || ! filter_var($url, FILTER_VALIDATE_URL)) {
            return '';
        }

        $parts = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));

        if ($scheme !== 'https' || ! in_array($host, $allowedHosts, true)) {
            return '';
        }

        return $url;
    }
}
