<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <style>
        @font-face {
            font-family: 'Vazirmatn';
            src: url('file://{{ str_replace('\\', '/', $fontPath) }}') format('truetype');
        }
        body {
            font-family: 'Vazirmatn', DejaVu Sans, sans-serif;
            direction: rtl;
            color: #111827;
            font-size: 11px;
            margin: 28px 36px;
        }
        .header { text-align: center; margin-bottom: 22px; }
        .header h1 { margin: 0; font-size: 24px; font-weight: 700; }
        .header .job { margin-top: 6px; color: #6b7280; }
        .photo {
            width: 80px; height: 80px; border-radius: 50%; object-fit: cover;
            margin: 0 auto 10px; display: block;
        }
        .photo-ph {
            width: 80px; height: 80px; border-radius: 50%; background: #d1d5db;
            margin: 0 auto 10px; text-align: center; line-height: 80px; color: #fff;
        }
        .contact { text-align: center; color: #4b5563; margin-bottom: 18px; }
        h2 {
            font-size: 12px; letter-spacing: .5px; text-align: center;
            border-top: 1px solid #e5e7eb; border-bottom: 1px solid #e5e7eb;
            padding: 6px 0; margin: 18px 0 12px; color: #374151;
        }
        .item { margin-bottom: 12px; page-break-inside: avoid; }
        .muted { color: #6b7280; }
        .space { height: 8px; }
    </style>
</head>
<body>
    <div class="header">
        @if($photoPath)
            <img class="photo" src="file://{{ str_replace('\\', '/', $photoPath) }}" alt="photo">
        @else
            <div class="photo-ph">عکس</div>
        @endif
        <h1>{{ $personal['full_name'] ?? '—' }}</h1>
        <div class="job">{{ $targetJob ?? '' }}</div>
    </div>

    <div class="contact">
        {{ $personal['mobile'] ?? '' }}
        @if(!empty($personal['email'])) · {{ $personal['email'] }} @endif
        @if(!empty($personal['address'])) · {{ $personal['address'] }} @endif
    </div>

    @if($summary)
        <h2>خلاصه حرفه‌ای</h2>
        <div class="item" style="text-align:center">{{ $summary }}</div>
    @endif

    <h2>تحصیلات</h2>
    @foreach($education as $edu)
        <div class="item" style="text-align:center">
            <strong>{{ $edu['degree'] ?? '' }} — {{ $edu['field'] ?? '' }}</strong>
            <div class="muted">{{ $edu['university'] ?? '' }}</div>
        </div>
    @endforeach

    @if(!empty($experience))
        <h2>تجربه کاری</h2>
        @foreach($experience as $exp)
            <div class="item" style="text-align:center">
                <strong>{{ $exp['title'] ?? '' }}</strong>
                <div class="muted">{{ $exp['company'] ?? '' }}</div>
                <div>{{ $exp['description'] ?? '' }}</div>
            </div>
        @endforeach
    @endif

    <h2>مهارت‌ها</h2>
    <div class="item" style="text-align:center">
        {{ collect($skills)->pluck('name')->filter()->implode(' · ') }}
    </div>

    @if(!empty($languages))
        <h2>زبان‌ها</h2>
        <div class="item" style="text-align:center">
            @foreach($languages as $lang)
                {{ $lang['name'] ?? '' }}@if(!empty($lang['level'])) ({{ $lang['level'] }})@endif
                @if(! $loop->last) · @endif
            @endforeach
        </div>
    @endif
</body>
</html>
