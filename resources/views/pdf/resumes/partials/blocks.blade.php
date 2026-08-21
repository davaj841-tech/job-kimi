{{-- DomPDF paints LTR; visual RTL = last table cell is the rightmost. --}}
@php
    $eduPeriod = $eduPeriod ?? static fn ($edu) => ['start' => '', 'end' => ''];
    $expPeriod = $expPeriod ?? static fn ($exp) => ['start' => '', 'end' => ''];
@endphp

@if($isSide)
<table width="100%" cellspacing="0" cellpadding="0">
    <tr>
        <td class="pad" valign="top">
            @if(!empty($summary))
                <div class="sec"><h2>معرفی</h2><div class="text">{{ $summary }}</div></div>
            @endif
            @if(!empty($experience))
                <h2>سوابق شغلی</h2>
                <table class="grid-table" cellspacing="0" cellpadding="0">
                    <tr>
                        <th>توضیحات</th>
                        <th>از تاریخ تا تاریخ</th>
                        <th>محل کار</th>
                        <th>عنوان</th>
                    </tr>
                    @foreach($experience as $exp)
                        @php $period = $expPeriod($exp); @endphp
                        <tr>
                            <td>{{ $exp['description'] ?? '' }}</td>
                            <td class="period-cell">
                                @include('pdf.resumes.partials.period_cell', ['start' => $period['start'] ?? '', 'end' => $period['end'] ?? ''])
                            </td>
                            <td>{{ $exp['company'] ?? '' }}</td>
                            <td>{{ $exp['title'] ?? '' }}</td>
                        </tr>
                    @endforeach
                </table>
            @endif
            @if(!empty($education))
                <h2>تحصیلات</h2>
                <table class="grid-table" cellspacing="0" cellpadding="0">
                    <tr>
                        <th>معدل</th>
                        <th>از تاریخ تا تاریخ</th>
                        <th>دانشگاه</th>
                        <th>مقطع / رشته</th>
                    </tr>
                    @foreach($education as $edu)
                        @php $period = $eduPeriod($edu); @endphp
                        <tr>
                            <td class="num">{{ $edu['gpa'] ?? '' }}</td>
                            <td class="period-cell">
                                @include('pdf.resumes.partials.period_cell', ['start' => $period['start'] ?? '', 'end' => $period['end'] ?? ''])
                            </td>
                            <td>{{ $edu['university'] ?? '' }}</td>
                            <td>{{ trim(($edu['degree'] ?? '').' '.($edu['field'] ?? '')) }}</td>
                        </tr>
                    @endforeach
                </table>
            @endif
        </td>
        <td class="side {{ $layout === 'sidebar' ? 'dark' : '' }}" valign="top">
            @if(!empty($photoSrc))
                <img class="photo" src="{{ $photoSrc }}" alt="" style="display:block;margin:0 auto 8px;">
            @endif
            <p class="name" style="text-align:center;font-size:15px;">{{ $personal['full_name'] ?? '—' }}</p>
            @if(!empty($targetJob))
                <p class="role" style="text-align:center;">{{ $targetJob }}</p>
            @endif
            @if($facts)
                <h2>اطلاعات شخصی</h2>
                @foreach($facts as $row)
                    <div style="margin-bottom:3px;">
                        @include('pdf.resumes.partials.fact_cell', ['k' => $row[0], 'v' => $row[1]])
                    </div>
                @endforeach
            @endif
            @if(!empty($skills))
                <h2>مهارت‌ها</h2>
                <table class="chip-table" width="100%" cellspacing="0" cellpadding="0">
                    @foreach(array_chunk($skills, 1) as $pair)
                        <tr>
                            @foreach($pair as $skill)
                                <td><span class="chip">{{ $skill['name'] ?? '' }}@if(!empty($skill['level'])) · {{ $skill['level'] }}@endif</span></td>
                            @endforeach
                        </tr>
                    @endforeach
                </table>
            @endif
            @if(!empty($languages))
                <h2>زبان‌ها</h2>
                @foreach($languages as $lang)
                    <div style="text-align:right;">{{ $lang['name'] ?? '' }}@if(!empty($lang['level'])) <span class="k">({{ $lang['level'] }})</span>@endif</div>
                @endforeach
            @endif
        </td>
    </tr>
</table>
@else
    @if($isBanner)
        <table class="banner" width="100%" cellspacing="0" cellpadding="0">
            <tr>
                <td class="head-name">
                    <p class="name">{{ $personal['full_name'] ?? '—' }}</p>
                    @if(!empty($targetJob))<p class="role">{{ $targetJob }}</p>@endif
                </td>
                @if(!empty($photoSrc))
                    <td class="head-photo"><img class="photo" src="{{ $photoSrc }}" alt=""></td>
                @endif
            </tr>
        </table>
    @else
        <div class="bar"></div>
        <table width="100%" cellspacing="0" cellpadding="0" style="margin-bottom:8px;">
            <tr>
                <td class="head-name">
                    <p class="name">{{ $personal['full_name'] ?? '—' }}</p>
                    @if(!empty($targetJob))<p class="role">{{ $targetJob }}</p>@endif
                </td>
                @if(!empty($photoSrc))
                    <td class="head-photo"><img class="photo" src="{{ $photoSrc }}" alt=""></td>
                @endif
            </tr>
        </table>
    @endif

    @if($facts)
        <div class="sec">
            <h2>اطلاعات شخصی</h2>
            <table class="facts" cellspacing="0" cellpadding="0">
                @foreach(array_chunk($facts, 2) as $pair)
                    <tr>
                        @if(count($pair) === 2)
                            <td>@include('pdf.resumes.partials.fact_cell', ['k' => $pair[1][0], 'v' => $pair[1][1]])</td>
                            <td>@include('pdf.resumes.partials.fact_cell', ['k' => $pair[0][0], 'v' => $pair[0][1]])</td>
                        @else
                            <td></td>
                            <td>@include('pdf.resumes.partials.fact_cell', ['k' => $pair[0][0], 'v' => $pair[0][1]])</td>
                        @endif
                    </tr>
                @endforeach
            </table>
        </div>
    @endif

    @if(!empty($summary))
        <div class="sec"><h2>معرفی</h2><div class="text">{{ $summary }}</div></div>
    @endif

    @if(!empty($experience) || !empty($education))
        <table class="two-col" cellspacing="0" cellpadding="0">
            <tr>
                {{-- DomPDF LTR: سلول دوم راست‌ترین است — اول (راست) تحصیلات، بعد سوابق شغلی --}}
                <td class="col-gap-l">
                    @if(!empty($experience))
                        <div class="sec" style="margin:0;">
                            <h2>سوابق شغلی</h2>
                            <table class="grid-table" cellspacing="0" cellpadding="0">
                                <tr>
                                    <th>توضیحات</th>
                                    <th>از تاریخ تا تاریخ</th>
                                    <th>محل کار</th>
                                    <th>عنوان</th>
                                </tr>
                                @foreach($experience as $exp)
                                    @php $period = $expPeriod($exp); @endphp
                                    <tr>
                                        <td>{{ $exp['description'] ?? '' }}</td>
                                        <td class="period-cell">
                                            @include('pdf.resumes.partials.period_cell', ['start' => $period['start'] ?? '', 'end' => $period['end'] ?? ''])
                                        </td>
                                        <td>{{ $exp['company'] ?? '' }}</td>
                                        <td>{{ $exp['title'] ?? '' }}</td>
                                    </tr>
                                @endforeach
                            </table>
                        </div>
                    @endif
                </td>
                <td class="col-gap-r">
                    @if(!empty($education))
                        <div class="sec" style="margin:0;">
                            <h2>تحصیلات</h2>
                            <table class="grid-table" cellspacing="0" cellpadding="0">
                                <tr>
                                    <th>معدل</th>
                                    <th>از تاریخ تا تاریخ</th>
                                    <th>دانشگاه</th>
                                    <th>مقطع / رشته</th>
                                </tr>
                                @foreach($education as $edu)
                                    @php $period = $eduPeriod($edu); @endphp
                                    <tr>
                                        <td class="num">{{ $edu['gpa'] ?? '' }}</td>
                                        <td class="period-cell">
                                            @include('pdf.resumes.partials.period_cell', ['start' => $period['start'] ?? '', 'end' => $period['end'] ?? ''])
                                        </td>
                                        <td>{{ $edu['university'] ?? '' }}</td>
                                        <td>{{ trim(($edu['degree'] ?? '').' '.($edu['field'] ?? '')) }}</td>
                                    </tr>
                                @endforeach
                            </table>
                        </div>
                    @endif
                </td>
            </tr>
        </table>
    @endif

    @if(!empty($skills))
        <div class="sec" style="margin-top:9px;">
            <h2>مهارت‌ها</h2>
            <table class="chip-table" width="100%" cellspacing="0" cellpadding="0">
                @foreach(array_chunk($skills, 4) as $row)
                    <tr>
                        @foreach(array_reverse($row) as $skill)
                            <td><span class="chip">{{ $skill['name'] ?? '' }}@if(!empty($skill['level'])) · {{ $skill['level'] }}@endif</span></td>
                        @endforeach
                    </tr>
                @endforeach
            </table>
        </div>
    @endif
    @if(!empty($languages))
        <div class="sec">
            <h2>زبان‌ها</h2>
            <table width="100%" cellspacing="0" cellpadding="0">
                @foreach(array_chunk($languages, 2) as $pair)
                    <tr>
                        @if(count($pair) === 2)
                            <td style="padding:2px 0;width:50%;text-align:right;">{{ $pair[1]['name'] ?? '' }}@if(!empty($pair[1]['level'])) <span class="k">({{ $pair[1]['level'] }})</span>@endif</td>
                            <td style="padding:2px 0;width:50%;text-align:right;">{{ $pair[0]['name'] ?? '' }}@if(!empty($pair[0]['level'])) <span class="k">({{ $pair[0]['level'] }})</span>@endif</td>
                        @else
                            <td></td>
                            <td style="padding:2px 0;width:50%;text-align:right;">{{ $pair[0]['name'] ?? '' }}@if(!empty($pair[0]['level'])) <span class="k">({{ $pair[0]['level'] }})</span>@endif</td>
                        @endif
                    </tr>
                @endforeach
            </table>
        </div>
    @endif
@endif
