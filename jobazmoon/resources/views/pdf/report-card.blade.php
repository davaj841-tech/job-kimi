<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <style>
        @font-face {
            font-family: 'Vazirmatn';
            src: url('file://{{ str_replace('\\', '/', $fontPath) }}') format('truetype');
            font-weight: normal;
            font-style: normal;
        }
        * { box-sizing: border-box; }
        body {
            font-family: 'Vazirmatn', DejaVu Sans, sans-serif;
            direction: rtl;
            color: #111827;
            font-size: 11px;
            margin: 0;
            padding: 24px;
        }
        .brand {
            border-bottom: 2px solid #ef394e;
            padding-bottom: 12px;
            margin-bottom: 16px;
        }
        .brand h1 { margin: 0; font-size: 20px; color: #ef394e; }
        .brand p { margin: 4px 0 0; color: #6b7280; }
        .meta { width: 100%; margin-bottom: 16px; }
        .meta td { padding: 6px 8px; border: 1px solid #e5e7eb; }
        .meta td.label { background: #f9fafb; width: 28%; font-weight: bold; }
        .stats { width: 100%; margin: 12px 0 18px; border-collapse: collapse; }
        .stats th, .stats td { border: 1px solid #e5e7eb; padding: 8px; text-align: center; }
        .stats th { background: #fff1f2; color: #9f1239; }
        .passed { color: #15803d; font-weight: bold; }
        .failed { color: #b91c1c; font-weight: bold; }
        h2 { font-size: 14px; margin: 18px 0 8px; color: #111827; }
        .sheet { width: 100%; border-collapse: collapse; }
        .sheet th, .sheet td { border: 1px solid #e5e7eb; padding: 5px 6px; text-align: center; font-size: 10px; }
        .sheet th { background: #f3f4f6; }
        .ok { background: #dcfce7; }
        .bad { background: #fee2e2; }
        .blank { background: #f3f4f6; }
        .footer { margin-top: 20px; color: #9ca3af; font-size: 9px; text-align: center; }
        .subjects { width: 100%; border-collapse: collapse; margin-top: 8px; }
        .subjects th, .subjects td { border: 1px solid #e5e7eb; padding: 6px; text-align: center; }
        .subjects th { background: #f9fafb; }
    </style>
</head>
<body>
    <div class="brand">
        <h1>جاب‌آزمون</h1>
        <p>کارنامه آزمون</p>
    </div>

    <table class="meta">
        <tr>
            <td class="label">نام کاربر</td>
            <td>{{ $user?->name ?? '—' }}</td>
            <td class="label">عنوان آزمون</td>
            <td>{{ $exam?->title }}</td>
        </tr>
        <tr>
            <td class="label">تاریخ</td>
            <td>{{ optional($attempt->finished_at)->format('Y-m-d H:i') ?? '—' }}</td>
            <td class="label">وضعیت</td>
            <td class="{{ ($analysis['passed'] ?? false) ? 'passed' : 'failed' }}">
                {{ ($analysis['passed'] ?? false) ? 'قبول' : 'مردود' }}
            </td>
        </tr>
    </table>

    <table class="stats">
        <tr>
            <th>نمره</th>
            <th>درصد</th>
            <th>صحیح</th>
            <th>غلط</th>
            <th>رتبه</th>
            <th>نمره قبولی</th>
        </tr>
        <tr>
            <td>{{ $analysis['score'] ?? $attempt->score }}</td>
            <td>{{ $analysis['percentage'] ?? 0 }}%</td>
            <td>{{ $analysis['total_correct'] ?? $attempt->total_correct }}</td>
            <td>{{ $analysis['total_wrong'] ?? $attempt->total_wrong }}</td>
            <td>{{ $analysis['rank'] ?? '—' }}</td>
            <td>{{ $exam?->passing_score }}</td>
        </tr>
    </table>

    @if(!empty($analysis['has_negative_marking']))
        <p>این آزمون دارای نمره منفی با نسبت {{ number_format((float) ($analysis['negative_mark_ratio'] ?? 0.3333), 4) }} است.</p>
    @endif

    @if(!empty($analysis['by_subject']))
        <h2>تحلیل موضوعی</h2>
        <table class="subjects">
            <tr>
                <th>موضوع</th>
                <th>کل</th>
                <th>صحیح</th>
                <th>غلط</th>
                <th>بدون پاسخ</th>
            </tr>
            @foreach($analysis['by_subject'] as $row)
                <tr>
                    <td>{{ $row['subject'] }}</td>
                    <td>{{ $row['total'] }}</td>
                    <td>{{ $row['correct'] }}</td>
                    <td>{{ $row['wrong'] }}</td>
                    <td>{{ $row['blank'] }}</td>
                </tr>
            @endforeach
        </table>
    @endif

    <h2>پاسخبرگ</h2>
    <table class="sheet">
        <thead>
            <tr>
                <th>#</th>
                <th>پاسخ شما</th>
                <th>پاسخ صحیح</th>
                <th>نتیجه</th>
            </tr>
        </thead>
        <tbody>
            @foreach($sheet as $row)
                @php
                    $cls = $row['is_blank'] ? 'blank' : ($row['is_correct'] ? 'ok' : 'bad');
                @endphp
                <tr class="{{ $cls }}">
                    <td>{{ $row['number'] }}</td>
                    <td>{{ $row['user_answer'] ? strtoupper($row['user_answer']) : '—' }}</td>
                    <td>{{ strtoupper($row['correct_answer']) }}</td>
                    <td>
                        @if($row['is_blank']) بدون پاسخ
                        @elseif($row['is_correct']) صحیح
                        @else غلط
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        صادرشده در {{ $generatedAt->format('Y-m-d H:i') }} — جاب‌آزمون
    </div>
</body>
</html>
