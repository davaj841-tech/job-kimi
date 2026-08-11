<!DOCTYPE html>
<html lang="fa" dir="ltr">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    @php
        $fa = static fn (mixed $v): string => \App\Services\ReportCardPDFService::fa($v);
        $opt = static fn (?string $v): string => \App\Services\ReportCardPDFService::optionLetter($v);
        $passed = (bool) ($analysis['passed'] ?? false);
        $pct = (float) ($analysis['percentage'] ?? 0);
        $negRatio = number_format((float) ($analysis['negative_mark_ratio'] ?? 0.3333), 2);
    @endphp
    <style>
        @font-face {
            font-family: 'Vazirmatn';
            font-style: normal;
            font-weight: 400;
            src: url('{{ $fontRegular }}') format('truetype');
        }
        @font-face {
            font-family: 'Vazirmatn';
            font-style: normal;
            font-weight: 700;
            src: url('{{ $fontBold }}') format('truetype');
        }
        * { box-sizing: border-box; }
        body {
            font-family: 'Vazirmatn', sans-serif;
            direction: ltr;
            text-align: right;
            color: #0f172a;
            font-size: 11.5px;
            line-height: 1.75;
            margin: 0;
            padding: 18px 20px 22px;
            background: #ffffff;
        }
        .frame {
            border: 1.5px solid #d6deea;
            padding: 0;
        }
        .header {
            width: 100%;
            border-collapse: collapse;
            background: #0f2744;
            color: #ffffff;
        }
        .header td { padding: 16px 18px 14px; vertical-align: middle; border: 0; }
        .header .mark {
            width: 92px;
            text-align: center;
            background: #f97316;
            color: #ffffff;
            font-size: 11px;
            font-weight: 700;
        }
        .header .mark .pct {
            display: block;
            font-size: 26px;
            font-weight: 700;
            line-height: 1.15;
            margin-top: 2px;
        }
        .header h1 {
            margin: 0;
            font-size: 20px;
            font-weight: 700;
            color: #ffffff;
        }
        .header .sub {
            margin: 4px 0 0;
            font-size: 12px;
            color: #dbe7f5;
        }
        .accent { height: 5px; background: #f97316; }
        .pad { padding: 14px 16px 16px; }
        .info { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        .info td { padding: 0 4px 8px; vertical-align: top; border: 0; }
        .cell {
            width: 100%;
            border-collapse: collapse;
            background: #f6f8fb;
            border: 1px solid #e2e8f0;
        }
        .cell td { padding: 8px 10px; border: 0; vertical-align: middle; }
        .cell .key {
            width: 38%;
            font-weight: 700;
            color: #0f2744;
            font-size: 11px;
            text-align: right;
        }
        .cell .val {
            text-align: right;
            color: #0f172a;
            font-size: 12px;
        }
        .badge {
            display: inline-block;
            padding: 3px 12px;
            font-weight: 700;
            font-size: 12px;
        }
        .badge-ok { background: #dcfce7; color: #166534; }
        .badge-no { background: #fee2e2; color: #991b1b; }
        .stats { width: 100%; border-collapse: collapse; margin: 4px 0 12px; }
        .stats th, .stats td {
            border: 1px solid #d6deea;
            padding: 9px 6px;
            text-align: center;
        }
        .stats th {
            background: #0f2744;
            color: #ffffff;
            font-weight: 700;
            font-size: 10.5px;
        }
        .stats td {
            font-size: 13px;
            font-weight: 700;
            background: #f8fafc;
        }
        h2 {
            font-size: 13px;
            margin: 16px 0 8px;
            color: #0f2744;
            font-weight: 700;
            padding: 0 0 5px;
            border-bottom: 2px solid #f97316;
        }
        .note {
            background: #fff7ed;
            border: 1px solid #fdba74;
            color: #9a3412;
            padding: 8px 10px;
            margin: 0 0 12px;
            font-size: 11px;
        }
        .subjects, .sheet { width: 100%; border-collapse: collapse; }
        .subjects th, .subjects td,
        .sheet th, .sheet td {
            border: 1px solid #d6deea;
            padding: 7px 6px;
            text-align: center;
            font-size: 11px;
        }
        .subjects th, .sheet th {
            background: #0f2744;
            color: #ffffff;
            font-weight: 700;
            font-size: 10.5px;
        }
        .subjects .name {
            text-align: right;
            font-weight: 700;
            color: #0f2744;
            padding-right: 10px;
            background: #f8fafc;
        }
        .ok { background: #ecfdf5; color: #166534; }
        .bad { background: #fef2f2; color: #991b1b; }
        .blank { background: #f8fafc; color: #64748b; }
        .footer {
            margin-top: 14px;
            color: #64748b;
            font-size: 10px;
            text-align: center;
            border-top: 1px solid #e2e8f0;
            padding: 10px 8px 4px;
        }
    </style>
</head>
<body>
    <div class="frame">
        <table class="header">
            <tr>
                <td class="mark">
                    درصد
                    <span class="pct">{{ $fa(rtrim(rtrim(number_format($pct, 1), '0'), '.')) }}٪</span>
                </td>
                <td>
                    <h1>کارنامه رسمی آزمون</h1>
                    <p class="sub">جاب‌آزمون · سامانه آزمون‌های استخدامی</p>
                </td>
            </tr>
        </table>
        <div class="accent"></div>

        <div class="pad">
            <table class="info">
                <tr>
                    <td width="50%">
                        <table class="cell">
                            <tr>
                                <td class="val">{{ $exam?->title ?: '—' }}</td>
                                <td class="key">عنوان آزمون</td>
                            </tr>
                        </table>
                    </td>
                    <td width="50%">
                        <table class="cell">
                            <tr>
                                <td class="val">{{ $user?->name ?? '—' }}</td>
                                <td class="key">نام داوطلب</td>
                            </tr>
                        </table>
                    </td>
                </tr>
                <tr>
                    <td width="50%">
                        <table class="cell">
                            <tr>
                                <td class="val">
                                    <span class="badge {{ $passed ? 'badge-ok' : 'badge-no' }}">
                                        {{ $passed ? 'قبول شدید' : 'قبول نشدید' }}
                                    </span>
                                </td>
                                <td class="key">نتیجه</td>
                            </tr>
                        </table>
                    </td>
                    <td width="50%">
                        <table class="cell">
                            <tr>
                                <td class="val">{{ $finishedAtFa }}</td>
                                <td class="key">تاریخ برگزاری</td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>

            <table class="stats">
                <tr>
                    <th>نمره قبولی</th>
                    <th>رتبه</th>
                    <th>بدون پاسخ</th>
                    <th>غلط</th>
                    <th>صحیح</th>
                    <th>نمره</th>
                </tr>
                <tr>
                    <td>{{ $fa($exam?->passing_score ?? '—') }}</td>
                    <td>{{ $fa($analysis['rank'] ?? '—') }}</td>
                    <td>{{ $fa(max(0, (int) ($analysis['total_questions'] ?? count($sheet)) - (int) ($analysis['total_correct'] ?? 0) - (int) ($analysis['total_wrong'] ?? 0))) }}</td>
                    <td>{{ $fa($analysis['total_wrong'] ?? $attempt->total_wrong) }}</td>
                    <td>{{ $fa($analysis['total_correct'] ?? $attempt->total_correct) }}</td>
                    <td>{{ $fa($analysis['score'] ?? $attempt->score) }}</td>
                </tr>
            </table>

            @if(!empty($analysis['has_negative_marking']))
                <div class="note">
                    این آزمون نمره منفی دارد (نسبت {{ $fa($negRatio) }}).
                </div>
            @endif

            @if(!empty($analysis['by_subject']))
                <h2>تحلیل درس‌ها</h2>
                <table class="subjects">
                    <tr>
                        <th>درصد</th>
                        <th>بدون پاسخ</th>
                        <th>غلط</th>
                        <th>صحیح</th>
                        <th>تعداد سوال</th>
                        <th>نام درس</th>
                    </tr>
                    @foreach($analysis['by_subject'] as $row)
                        <tr>
                            <td>{{ $fa($row['percentage'] ?? 0) }}٪</td>
                            <td>{{ $fa($row['blank']) }}</td>
                            <td>{{ $fa($row['wrong']) }}</td>
                            <td>{{ $fa($row['correct']) }}</td>
                            <td>{{ $fa($row['total']) }}</td>
                            <td class="name">{{ $row['subject_label'] ?? \App\Services\ExamService::subjectDisplayName($row['subject'] ?? null) }}</td>
                        </tr>
                    @endforeach
                </table>
            @endif

            <h2>پاسخبرگ</h2>
            <table class="sheet">
                <thead>
                    <tr>
                        <th>نتیجه</th>
                        <th>پاسخ صحیح</th>
                        <th>پاسخ شما</th>
                        <th>شماره</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($sheet as $row)
                        @php
                            $cls = $row['is_blank'] ? 'blank' : ($row['is_correct'] ? 'ok' : 'bad');
                        @endphp
                        <tr class="{{ $cls }}">
                            <td>
                                @if($row['is_blank']) بدون پاسخ
                                @elseif($row['is_correct']) صحیح
                                @else غلط
                                @endif
                            </td>
                            <td>{{ $opt($row['correct_answer'] ?? null) }}</td>
                            <td>{{ $opt($row['user_answer'] ?? null) }}</td>
                            <td>{{ $fa($row['number']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <div class="footer">
                جاب‌آزمون · صادرشده در {{ $generatedAtFa }}
            </div>
        </div>
    </div>
</body>
</html>
