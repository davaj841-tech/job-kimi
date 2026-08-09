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
            color: #1f2937;
            font-size: 11px;
            margin: 0;
            padding: 0;
        }
        .header {
            background: #1d4ed8;
            color: #fff;
            padding: 18px 22px;
        }
        .header h1 { margin: 0; font-size: 22px; }
        .header .job { margin-top: 4px; font-size: 12px; opacity: .9; }
        .wrap { display: table; width: 100%; table-layout: fixed; }
        .sidebar, .main { display: table-cell; vertical-align: top; padding: 16px; }
        .sidebar { width: 32%; background: #f8fafc; border-left: 1px solid #e2e8f0; }
        .main { width: 68%; }
        .photo {
            width: 90px; height: 90px; border-radius: 8px; object-fit: cover;
            background: #cbd5e1; display: block; margin-bottom: 12px;
        }
        .photo-ph {
            width: 90px; height: 90px; border-radius: 8px; background: #94a3b8;
            color: #fff; text-align: center; line-height: 90px; margin-bottom: 12px;
        }
        h2 {
            font-size: 13px; margin: 0 0 8px; color: #1d4ed8;
            border-bottom: 1px solid #bfdbfe; padding-bottom: 4px;
        }
        .item { margin-bottom: 10px; page-break-inside: avoid; }
        .muted { color: #64748b; }
        ul { margin: 0; padding-right: 14px; }
        li { margin-bottom: 4px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $personal['full_name'] ?? '—' }}</h1>
        <div class="job">{{ $targetJob ?? '' }}</div>
    </div>
    <div class="wrap">
        <div class="sidebar">
            @if($photoPath)
                <img class="photo" src="file://{{ str_replace('\\', '/', $photoPath) }}" alt="photo">
            @else
                <div class="photo-ph">عکس</div>
            @endif

            <h2>تماس</h2>
            <div class="item">
                <div>{{ $personal['mobile'] ?? '' }}</div>
                <div>{{ $personal['email'] ?? '' }}</div>
                <div class="muted">{{ $personal['address'] ?? '' }}</div>
                <div class="muted">کد ملی: {{ $personal['national_code'] ?? '' }}</div>
                <div class="muted">تولد: {{ $personal['birth_date'] ?? '' }}</div>
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
            @if($summary)
                <h2>خلاصه</h2>
                <div class="item">{{ $summary }}</div>
            @endif

            <h2>تحصیلات</h2>
            @foreach($education as $edu)
                <div class="item">
                    <strong>{{ $edu['degree'] ?? '' }} — {{ $edu['field'] ?? '' }}</strong>
                    <div class="muted">{{ $edu['university'] ?? '' }}
                        @if(!empty($edu['start_year']) || !empty($edu['end_year']))
                            ({{ $edu['start_year'] ?? '' }} - {{ $edu['end_year'] ?? '' }})
                        @endif
                    </div>
                    @if(isset($edu['gpa']))<div class="muted">معدل: {{ $edu['gpa'] }}</div>@endif
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
