<!DOCTYPE html>
<html lang="fa" dir="ltr">
<head>
    <meta charset="UTF-8">
    <style>
        @font-face {
            font-family: 'Vazirmatn';
            font-weight: 400;
            src: url('{{ $fontRegular ?? $fontPath }}') format('truetype');
        }
        @font-face {
            font-family: 'Vazirmatn';
            font-weight: 700;
            src: url('{{ $fontBold ?? $fontRegular ?? $fontPath }}') format('truetype');
        }
        body {
            font-family: 'Vazirmatn', sans-serif;
            direction: ltr;
            text-align: right;
            margin: 0;
            font-size: 11px;
            color: #111827;
        }
        .wrap { display: table; width: 100%; table-layout: fixed; min-height: 100%; }
        .sidebar, .main { display: table-cell; vertical-align: top; }
        .sidebar {
            width: 34%;
            background: #1f2937;
            color: #f9fafb;
            padding: 20px 16px;
        }
        .main { width: 66%; padding: 22px 20px; background: #fff; }
        .photo {
            width: 100px; height: 100px; border-radius: 6px; object-fit: cover;
            display: block; margin: 0 auto 14px;
        }
        .photo-ph {
            width: 100px; height: 100px; border-radius: 6px; background: #4b5563;
            margin: 0 auto 14px; text-align: center; line-height: 100px;
        }
        .sidebar h2 {
            font-size: 12px; border-bottom: 1px solid #4b5563;
            padding-bottom: 4px; margin: 16px 0 8px;
        }
        .main h1 { margin: 0 0 4px; font-size: 22px; color: #111827; }
        .main .job { color: #6b7280; margin-bottom: 16px; }
        .main h2 {
            font-size: 13px; color: #1f2937; border-bottom: 2px solid #1f2937;
            padding-bottom: 4px; margin: 16px 0 10px;
        }
        .item { margin-bottom: 10px; page-break-inside: avoid; }
        .muted { color: #9ca3af; }
        .main .muted { color: #6b7280; }
        ul { margin: 0; padding-right: 14px; }
        li { margin-bottom: 4px; }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="sidebar">
            @if($photoPath)
                <img class="photo" src="file://{{ str_replace('\\', '/', $photoPath) }}" alt="photo">
            @else
                <div class="photo-ph">عکس</div>
            @endif

            <h2>اطلاعات تماس</h2>
            <div class="item">
                <div>{{ $personal['mobile'] ?? '' }}</div>
                <div>{{ $personal['email'] ?? '' }}</div>
                <div class="muted">{{ $personal['address'] ?? '' }}</div>
                <div class="muted">کد ملی: {{ $personal['national_code'] ?? '' }}</div>
            </div>

            <h2>مهارت‌ها</h2>
            <ul>
                @foreach($skills as $skill)
                    <li>{{ $skill['name'] ?? '' }}@if(!empty($skill['level'])) — {{ $skill['level'] }}@endif</li>
                @endforeach
            </ul>

            @if(!empty($languages))
                <h2>زبان‌ها</h2>
                <ul>
                    @foreach($languages as $lang)
                        <li>{{ $lang['name'] ?? '' }}@if(!empty($lang['level'])) — {{ $lang['level'] }}@endif</li>
                    @endforeach
                </ul>
            @endif
        </div>
        <div class="main">
            <h1>{{ $personal['full_name'] ?? '—' }}</h1>
            <div class="job">{{ $targetJob ?? '' }}</div>

            @if($summary)
                <h2>خلاصه</h2>
                <div class="item">{{ $summary }}</div>
            @endif

            <h2>تحصیلات</h2>
            @foreach($education as $edu)
                <div class="item">
                    <strong>{{ $edu['degree'] ?? '' }} — {{ $edu['field'] ?? '' }}</strong>
                    <div class="muted">{{ $edu['university'] ?? '' }}</div>
                </div>
            @endforeach

            @if(!empty($experience))
                <h2>سوابق شغلی</h2>
                @foreach($experience as $exp)
                    <div class="item">
                        <strong>{{ $exp['title'] ?? '' }}</strong> — {{ $exp['company'] ?? '' }}
                        <div class="muted">
                            {{ $exp['start_date'] ?? '' }} -
                            {{ !empty($exp['is_current']) ? 'اکنون' : ($exp['end_date'] ?? '') }}
                        </div>
                        <div>{{ $exp['description'] ?? '' }}</div>
                    </div>
                @endforeach
            @endif
        </div>
    </div>
</body>
</html>
