<!DOCTYPE html>
<html lang="fa" dir="ltr">
<head>
    <meta charset="UTF-8">
    <style>
        * { box-sizing: border-box; }
        @page { size: A4; margin: 10mm 11mm 12mm; }
        body {
            font-family: vazirmatn, sans-serif;
            direction: ltr;
            text-align: right;
            color: #1e293b;
            font-size: 10px;
            margin: 0;
            padding: 0;
            background: #fff;
            line-height: 1.7;
        }
        .accent { color: {{ $accent ?? '#1a365d' }}; }
        .name { font-size: 20px; font-weight: 700; margin: 0; color: #0f172a; }
        .role { margin: 3px 0 0; font-size: 11px; font-weight: 700; color: {{ $accent ?? '#1a365d' }}; }
        .photo {
            width: 78px;
            height: 104px;
            object-fit: cover;
            border: 1px solid #cbd5e1;
            background: #f1f5f9;
        }
        h2 {
            margin: 0 0 6px;
            font-size: 11px;
            font-weight: 700;
            color: {{ $accent ?? '#1a365d' }};
            border-bottom: 1.5px solid {{ $accent ?? '#1a365d' }};
            padding: 0 0 3px 0;
        }
        .sec { margin: 0 0 9px; }
        .facts { width: 100%; border-collapse: collapse; }
        .facts > tbody > tr > td { width: 50%; padding: 2px 4px 3px; vertical-align: top; }
        .fact-pair { width: 100%; border-collapse: collapse; }
        .fact-k {
            width: 1%;
            white-space: nowrap;
            font-size: 9.5px;
            color: #64748b;
            text-align: right;
            padding: 1px 0 1px 4px;
            vertical-align: top;
        }
        .fact-v {
            font-size: 9.5px;
            text-align: left;
            unicode-bidi: isolate;
            padding: 1px 6px 1px 0;
            vertical-align: top;
            font-weight: 700;
            color: #0f172a;
        }
        .grid-table { width: 100%; border-collapse: collapse; margin: 2px 0 8px; }
        .grid-table th {
            font-size: 8.5px;
            color: #fff;
            background: {{ $accent ?? '#1a365d' }};
            padding: 4px 5px;
            text-align: right;
            font-weight: 700;
        }
        .grid-table td {
            font-size: 9px;
            padding: 5px 5px;
            border-bottom: 1px solid #e2e8f0;
            text-align: right;
            vertical-align: top;
        }
        .grid-table .num { text-align: left; unicode-bidi: isolate; }
        .k { color: #64748b; }
        .item { margin: 0 0 7px; padding: 0 8px 0 0; border-right: 2px solid #e2e8f0; }
        .title { font-weight: 700; font-size: 10.5px; margin: 0; }
        .sub { color: #64748b; font-size: 9px; margin: 1px 0 0; }
        .date { color: {{ $accent ?? '#1a365d' }}; font-size: 9px; font-weight: 700; }
        .text { font-size: 9.5px; line-height: 1.75; color: #334155; margin-top: 2px; text-align: right; }
        .chip-table td { padding: 0 0 4px 6px; font-size: 9px; text-align: right; }
        .chip {
            border: 1px solid #e2e8f0;
            background: #f8fafc;
            padding: 2px 7px;
        }
        .banner {
            background: {{ $header ?? '#1a365d' }};
            color: #fff;
            padding: 10px 12px;
            margin: -10mm -11mm 10px;
        }
        .banner .name, .banner .role { color: #fff; }
        .banner .role { opacity: 0.92; }
        .banner .photo { border-color: rgba(255,255,255,.4); }
        .side {
            background: {{ $sidebar ?? '#f8fafc' }};
            padding: 10px 9px;
            width: 34%;
        }
        .side.dark { background: {{ $header ?? '#0f172a' }}; color: #fff; }
        .side.dark h2, .side.dark .name, .side.dark .role, .side.dark .k, .side.dark .sub { color: #fff; border-color: rgba(255,255,255,.35); }
        .side.dark .chip { background: rgba(255,255,255,.08); border-color: rgba(255,255,255,.25); color: #fff; }
        .side.dark .fact-k { color: rgba(255,255,255,.75); }
        .side.dark .fact-v { color: #fff; }
        .pad { padding: 2px 10px 0 4px; }
        .bar { height: 7px; background: {{ $header ?? '#1a365d' }}; margin: -10mm -11mm 10px; }
        .head-name { padding-right: 12px; vertical-align: middle; text-align: right; }
        .head-photo { width: 88px; vertical-align: top; text-align: left; }
        .col-gap-l { padding-left: 8px; }
        .col-gap-r { padding-right: 8px; }
        .muted { color: #64748b; }
    </style>
</head>
<body>
@php
    $layout = $layout ?? 'classic';
    $isBanner = in_array($layout, ['banner', 'magazine', 'bold'], true);
    $isSide = in_array($layout, ['sidebar', 'split'], true);
    $birthPlace = trim(implode(' / ', array_filter([
        $personal['birth_province'] ?? null,
        $personal['birth_city'] ?? null,
    ])));
    $maritalMap = ['single' => 'مجرد', 'married' => 'متاهل', 'divorced' => 'مطلقه / متعلقه'];
    $marital = $maritalMap[$personal['marital_status'] ?? ''] ?? ($personal['marital_status'] ?? '');
    $val = static fn ($v) => trim((string) $v);
    $facts = array_values(array_filter([
        ['موبایل', $val($personal['mobile'] ?? '')],
        ['تلفن منزل', $val($personal['home_phone'] ?? '')],
        ['ایمیل', $val($personal['email'] ?? '')],
        ['کد ملی', $val($personal['national_code'] ?? '')],
        ['تاریخ تولد', $val($personal['birth_date'] ?? '')],
        ['محل تولد', $birthPlace],
        ['وضعیت تاهل', $val($marital)],
        ['وضعیت سربازی', $val($personal['military_status'] ?? '')],
        ['سابقه بیمه', $val($personal['insurance_history'] ?? '')],
        ['رشته تحصیلی', $val($personal['field_of_study'] ?? '')],
        ['آدرس', $val($personal['address'] ?? '')],
        ['کد پستی', $val($personal['postal_code'] ?? '')],
    ], fn ($row) => $row[1] !== ''));
    $fmtYm = static function ($date) {
        $date = trim((string) $date);
        if ($date === '') {
            return '';
        }
        if (preg_match('/^(\d{4})-(\d{1,2})(?:-\d{1,2})?$/', $date, $m)) {
            return $m[1].'/'.str_pad($m[2], 2, '0', STR_PAD_LEFT);
        }
        if (preg_match('/^\d{4}$/', $date)) {
            return $date;
        }

        return $date;
    };
    $eduPeriod = static function ($edu) use ($fmtYm) {
        $start = $fmtYm($edu['start_date'] ?? '') ?: (string) ($edu['start_year'] ?? '');
        $end = $fmtYm($edu['end_date'] ?? '') ?: (string) ($edu['end_year'] ?? '');
        if ($start === '' && $end === '') {
            return '';
        }

        return $end === '' ? $start : $start.' تا '.$end;
    };
    $expPeriod = static function ($exp) use ($fmtYm) {
        $start = $fmtYm($exp['start_date'] ?? '');
        $end = ! empty($exp['is_current']) ? 'اکنون' : $fmtYm($exp['end_date'] ?? '');
        if ($start === '' && $end === '') {
            return '';
        }

        return $end === '' ? $start : $start.' تا '.$end;
    };
@endphp

@include('pdf.resumes.partials.blocks', [
    'layout' => $layout,
    'isBanner' => $isBanner,
    'isSide' => $isSide,
    'facts' => $facts,
    'eduPeriod' => $eduPeriod,
    'expPeriod' => $expPeriod,
])
</body>
</html>
