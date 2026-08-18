<!DOCTYPE html>
<html lang="fa" dir="ltr">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    @php
        $fa = static fn (mixed $v): string => \App\Services\ReportCardPDFService::fa($v);
        $pct = static fn (mixed $v): string => \App\Services\ReportCardPDFService::faPct($v);
        $passed = (bool) ($analysis['passed'] ?? false);
        $isRetry = (bool) ($analysis['is_retry_wrong'] ?? $attempt->is_retry_wrong ?? false);
        $retryMode = $analysis['retry_mode'] ?? $attempt->retry_mode ?? null;
        $percent = (float) ($analysis['percentage'] ?? 0);
        $correct = (int) ($analysis['total_correct'] ?? $attempt->total_correct ?? 0);
        $wrong = (int) ($analysis['total_wrong'] ?? $attempt->total_wrong ?? 0);
        $blank = (int) ($analysis['total_unanswered'] ?? 0);
        $totalQ = (int) ($analysis['total_questions'] ?? count($sheet));
        $negRatio = number_format((float) ($analysis['negative_mark_ratio'] ?? 0.3333), 2);
        $site = $siteName ?? 'جاب‌آزمون';
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
            font-size: 11px;
            line-height: 1.7;
            margin: 0;
            padding: 12px 14px 16px;
            background: #ffffff;
        }
        .frame { border: 2px solid #0f2744; }
        .brand {
            width: 100%;
            border-collapse: collapse;
            background: #0f2744;
            color: #fff;
        }
        .brand td { border: 0; padding: 10px 12px; vertical-align: middle; }
        .brand-logo { width: 92px; text-align: center; }
        .logo { height: 40px; max-width: 110px; }
        .brand h1 { margin: 0; font-size: 18px; color: #fff; letter-spacing: 0; }
        .brand .exam { margin: 3px 0 0; font-size: 11px; color: #fdba74; font-weight: 700; }
        .brand .meta { margin: 2px 0 0; font-size: 9.5px; color: #94a3b8; }
        .chip {
            display: inline-block;
            margin-top: 5px;
            padding: 1px 8px;
            font-size: 9px;
            font-weight: 700;
            background: #f97316;
            color: #fff;
        }
        .pct-box {
            width: 96px;
            text-align: center;
            background: #ef394e;
            padding: 12px 8px;
        }
        .pct-box .lbl { display: block; font-size: 9px; font-weight: 700; color: #fecaca; }
        .pct-box .pct { display: block; font-size: 22px; font-weight: 700; line-height: 1.15; margin-top: 2px; }
        .stripe { height: 3px; background: #f97316; }
        .pad { padding: 12px 14px 14px; }
        .retry {
            background: #fff7ed;
            border: 1px solid #fdba74;
            color: #9a3412;
            padding: 5px 9px;
            margin-bottom: 10px;
            font-size: 10px;
            font-weight: 700;
        }
        .info { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        .info td { padding: 0 3px 7px; border: 0; vertical-align: top; }
        .cell { width: 100%; border-collapse: collapse; background: #f8fafc; border: 1px solid #e2e8f0; }
        .cell td { padding: 7px 9px; border: 0; }
        .cell .key { width: 36%; font-weight: 700; color: #0f2744; font-size: 10px; }
        .cell .val { font-size: 11.5px; font-weight: 700; }
        .badge { display: inline-block; padding: 2px 10px; font-weight: 700; font-size: 11px; }
        .badge-ok { background: #dcfce7; color: #166534; }
        .badge-no { background: #fee2e2; color: #991b1b; }
        .stats { width: 100%; border-collapse: collapse; margin: 2px 0 10px; }
        .stats th, .stats td { border: 1px solid #d6deea; padding: 8px 4px; text-align: center; }
        .stats th { background: #0f2744; color: #fff; font-size: 10px; }
        .stats td { font-size: 12px; font-weight: 700; background: #f8fafc; }
        h2 {
            font-size: 12px;
            margin: 12px 0 6px;
            color: #0f2744;
            font-weight: 700;
            padding: 0 0 4px;
            border-bottom: 2px solid #f97316;
        }
        .note { background: #fff7ed; border: 1px solid #fdba74; color: #9a3412; padding: 7px 9px; margin: 0 0 10px; font-size: 10.5px; }
        .subjects, .sheet { width: 100%; border-collapse: collapse; }
        .subjects th, .subjects td, .sheet th, .sheet td {
            border: 1px solid #d6deea;
            padding: 5px 4px;
            text-align: center;
            font-size: 10px;
        }
        .subjects th, .sheet th { background: #0f2744; color: #fff; font-weight: 700; font-size: 10px; }
        .subjects .name { text-align: right; font-weight: 700; color: #0f2744; padding-right: 8px; background: #f8fafc; }
        .barwrap { background: #e2e8f0; height: 7px; }
        .bar { height: 7px; background: #10b981; }
        .bar-mid { background: #f59e0b; }
        .bar-low { background: #ef394e; }
        .ok { background: #ecfdf5; color: #166534; }
        .bad { background: #fef2f2; color: #991b1b; }
        .blank { background: #f8fafc; color: #64748b; }
        .legend { width: 100%; margin: 6px 0 8px; border-collapse: collapse; }
        .legend td { border: 0; font-size: 10px; padding: 2px 4px; }
        .dot { display: inline-block; width: 8px; height: 8px; }
        .footer {
            margin-top: 12px;
            color: #64748b;
            font-size: 9.5px;
            text-align: center;
            border-top: 1px solid #e2e8f0;
            padding: 8px 6px 2px;
        }
    </style>
</head>
<body>
    <div class="frame">
        <table class="brand">
            <tr>
                <td class="brand-logo">
                    @if(!empty($logoDataUri))
                        <img class="logo" src="{{ $logoDataUri }}" alt=""/>
                    @endif
                </td>
                <td>
                    <h1>کارنامه آزمون</h1>
                    <p class="exam">{{ $exam?->title ?: $site }}</p>
                    <p class="meta">{{ $site }} · شماره کارنامه {{ $fa($attempt->id) }}</p>
                    @if($isRetry)
                        <span class="chip">
                            @if($retryMode === 'blank')
                                مرور سوالات بدون پاسخ
                            @else
                                مرور سوالات غلط
                            @endif
                        </span>
                    @endif
                </td>
                <td class="pct-box">
                    <span class="lbl">درصد کل</span>
                    <span class="pct">{{ $pct($percent) }}</span>
                </td>
            </tr>
        </table>
        <div class="stripe"></div>

        <div class="pad">
            @if($isRetry)
                <div class="retry">
                    @if($retryMode === 'blank')
                        این کارنامه مربوط به آزمون سوالات بدون پاسخ است و از کارنامه اصلی جداست.
                    @else
                        این کارنامه مربوط به آزمون مجدد سوالات غلط است و از کارنامه اصلی جداست.
                    @endif
                </div>
            @endif

            <table class="info">
                <tr>
                    <td width="50%">
                        <table class="cell"><tr>
                            <td class="val">{{ $exam?->title ?: '—' }}</td>
                            <td class="key">عنوان آزمون</td>
                        </tr></table>
                    </td>
                    <td width="50%">
                        <table class="cell"><tr>
                            <td class="val">{{ $user?->name ?? '—' }}</td>
                            <td class="key">نام داوطلب</td>
                        </tr></table>
                    </td>
                </tr>
                <tr>
                    <td width="50%">
                        <table class="cell"><tr>
                            <td class="val">
                                <span class="badge {{ $passed ? 'badge-ok' : 'badge-no' }}">
                                    {{ $passed ? 'قبول شدید' : 'قبول نشدید' }}
                                </span>
                            </td>
                            <td class="key">نتیجه</td>
                        </tr></table>
                    </td>
                    <td width="50%">
                        <table class="cell"><tr>
                            <td class="val">{{ $user?->mobile ?? $user?->username ?? '—' }}</td>
                            <td class="key">شناسه داوطلب</td>
                        </tr></table>
                    </td>
                </tr>
                <tr>
                    <td width="50%">
                        <table class="cell"><tr>
                            <td class="val">{{ $finishedAtFa }}</td>
                            <td class="key">پایان آزمون</td>
                        </tr></table>
                    </td>
                    <td width="50%">
                        <table class="cell"><tr>
                            <td class="val">{{ $startedAtFa }}</td>
                            <td class="key">شروع آزمون</td>
                        </tr></table>
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
                    <th>کل سوال</th>
                    <th>نمره</th>
                    <th>درصد</th>
                </tr>
                <tr>
                    <td>{{ $fa($exam?->passing_score ?? '—') }}</td>
                    <td>{{ $fa($analysis['rank'] ?? '—') }}</td>
                    <td>{{ $fa($blank) }}</td>
                    <td>{{ $fa($wrong) }}</td>
                    <td>{{ $fa($correct) }}</td>
                    <td>{{ $fa($totalQ) }}</td>
                    <td>{{ $fa($analysis['score'] ?? $attempt->score) }}</td>
                    <td>{{ $pct($percent) }}</td>
                </tr>
            </table>

            @if(!empty($analysis['has_negative_marking']))
                <div class="note">این آزمون نمره منفی دارد (نسبت {{ $fa($negRatio) }}). سوالات نزده نمره منفی ندارند.</div>
            @endif

            @if(!empty($analysis['by_subject']))
                <h2>تحلیل درس‌ها</h2>
                <table class="subjects">
                    <tr>
                        <th>نمودار</th>
                        <th>درصد</th>
                        <th>نزده</th>
                        <th>غلط</th>
                        <th>صحیح</th>
                        <th>تعداد</th>
                        <th>نام درس</th>
                    </tr>
                    @foreach($analysis['by_subject'] as $row)
                        @php
                            $p = (float) ($row['percentage'] ?? 0);
                            $barClass = $p >= 70 ? 'bar' : ($p >= 50 ? 'bar bar-mid' : 'bar bar-low');
                        @endphp
                        <tr>
                            <td style="width: 70px;">
                                <div class="barwrap"><div class="{{ $barClass }}" style="width: {{ min(100, $p) }}%;"></div></div>
                            </td>
                            <td>{{ $pct($p) }}</td>
                            <td>{{ $fa($row['blank'] ?? 0) }}</td>
                            <td>{{ $fa($row['wrong'] ?? 0) }}</td>
                            <td>{{ $fa($row['correct'] ?? 0) }}</td>
                            <td>{{ $fa($row['total'] ?? 0) }}</td>
                            <td class="name">{{ $row['subject_label'] ?? \App\Services\ExamService::subjectDisplayName($row['subject'] ?? null) }}</td>
                        </tr>
                    @endforeach
                </table>
            @endif

            <div class="footer">
                {{ $site }} · صادرشده در {{ $generatedAtFa }} · این کارنامه صرفاً برای اطلاع داوطلب است.
            </div>
        </div>
    </div>
</body>
</html>
