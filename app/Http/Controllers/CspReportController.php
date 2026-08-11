<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

final class CspReportController extends Controller
{
    public function store(Request $request): Response
    {
        /** @var mixed $decoded */
        $decoded = json_decode($request->getContent(), true);

        if (! is_array($decoded)) {
            return response()->noContent();
        }

        /** @var array<string, mixed> $report */
        $report = $this->extractReport($decoded);

        if ($report === []) {
            return response()->noContent();
        }

        $summary = [
            'document_uri' => $this->stringValue($report, ['document-uri', 'documentURI', 'documentUrl']),
            'violated_directive' => $this->stringValue($report, ['violated-directive', 'effectiveDirective', 'effective-directive']),
            'blocked_uri' => $this->stringValue($report, ['blocked-uri', 'blockedURL', 'blockedUrl']),
            'disposition' => $this->stringValue($report, ['disposition']),
        ];

        if (app()->environment('production')) {
            Log::channel('csp')->warning('CSP violation', $summary);
        } else {
            Log::channel('csp')->warning('CSP violation', [
                'summary' => $summary,
                'report' => $report,
            ]);
        }

        return response()->noContent();
    }

    /**
     * @param  array<mixed>  $payload
     * @return array<string, mixed>
     */
    private function extractReport(array $payload): array
    {
        if (isset($payload['csp-report']) && is_array($payload['csp-report'])) {
            /** @var array<string, mixed> $report */
            $report = $payload['csp-report'];

            return $report;
        }

        // Reporting API batch: [{ "type": "csp-violation", "body": {...} }]
        if (array_is_list($payload) && isset($payload[0]) && is_array($payload[0])) {
            $first = $payload[0];
            if (isset($first['body']) && is_array($first['body'])) {
                /** @var array<string, mixed> $body */
                $body = $first['body'];

                return $body;
            }
        }

        /** @var array<string, mixed> $payload */
        return $payload;
    }

    /**
     * @param  array<string, mixed>  $report
     * @param  list<string>  $keys
     */
    private function stringValue(array $report, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = $report[$key] ?? null;

            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return null;
    }
}
