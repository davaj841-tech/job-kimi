<?php

namespace App\Support;

final class HtmlSanitizer
{
    /** @var list<string> */
    private const ALLOWED_TAGS = [
        'p', 'br', 'strong', 'b', 'em', 'i', 'u', 's', 'ul', 'ol', 'li',
        'a', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'blockquote', 'code', 'pre',
        'span', 'div', 'table', 'thead', 'tbody', 'tr', 'th', 'td',
        'img', 'sub', 'sup', 'hr', 'figure', 'figcaption',
    ];

    public static function clean(?string $html): string
    {
        if ($html === null || trim($html) === '') {
            return '';
        }

        $html = preg_replace('#<(script|iframe|object|embed|link|meta|form|input|textarea|button|style)[^>]*>.*?</\1>#is', '', $html) ?? $html;
        $html = preg_replace('#<(script|iframe|object|embed|link|meta|form|input|textarea|button|style)[^>]*/?>#is', '', $html) ?? $html;
        $html = preg_replace('/on\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html) ?? $html;
        $html = preg_replace('/javascript\s*:/i', '', $html) ?? $html;
        $html = preg_replace('/data\s*:/i', '', $html) ?? $html;

        $allowed = '<'.implode('><', self::ALLOWED_TAGS).'>';
        $html = strip_tags($html, $allowed);

        return self::sanitizeAttributes($html);
    }

    public static function safeUrl(?string $url): ?string
    {
        if ($url === null) {
            return null;
        }
        $url = trim($url);
        if ($url === '') {
            return null;
        }
        if (preg_match('#^(javascript|vbscript|data)\s*:#i', $url)) {
            return null;
        }
        if (preg_match('#^(https?:)?//#i', $url) || str_starts_with($url, '/')) {
            return $url;
        }

        return null;
    }

    private static function sanitizeAttributes(string $html): string
    {
        return preg_replace_callback('/<([a-z0-9]+)([^>]*)>/i', function (array $m) {
            $tag = strtolower($m[1]);
            $attrs = $m[2];
            $keep = [];

            if (preg_match_all('/([a-z0-9:-]+)\s*=\s*("([^"]*)"|\'([^\']*)\'|([^\s>]+))/i', $attrs, $parts, PREG_SET_ORDER)) {
                foreach ($parts as $part) {
                    $name = strtolower($part[1]);
                    $value = $part[3] !== '' ? $part[3] : ($part[4] !== '' ? $part[4] : ($part[5] ?? ''));
                    if (str_starts_with($name, 'on') || $name === 'srcdoc' || $name === 'formaction') {
                        continue;
                    }
                    if (in_array($name, ['href', 'src', 'xlink:href'], true)) {
                        $trimmed = trim($value);
                        if (! preg_match('#^(https?:)?//#i', $trimmed) && ! str_starts_with($trimmed, '/') && ! str_starts_with($trimmed, '#')) {
                            continue;
                        }
                        if (preg_match('#^(javascript|vbscript|data)\s*:#i', $trimmed)) {
                            continue;
                        }
                    }
                    if ($tag === 'a' && $name === 'target') {
                        $keep[] = 'target="_blank" rel="noopener noreferrer"';

                        continue;
                    }
                    if (in_array($name, ['href', 'src', 'alt', 'title', 'class', 'width', 'height', 'colspan', 'rowspan', 'rel'], true)) {
                        $keep[] = $name.'="'.htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8').'"';
                    }
                }
            }

            if ($tag === 'a' && ! str_contains(implode(' ', $keep), 'rel=')) {
                $keep[] = 'rel="noopener noreferrer"';
            }

            return '<'.$tag.(count($keep) ? ' '.implode(' ', $keep) : '').'>';
        }, $html) ?? $html;
    }
}
