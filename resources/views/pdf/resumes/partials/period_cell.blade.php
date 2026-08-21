{{-- Compact single-line period: ۱۴۰۱/۰۷ - ۱۴۰۲/۱۲ --}}
@php
    $start = trim((string) ($start ?? ''));
    $end = trim((string) ($end ?? ''));
    $parts = array_values(array_filter([$start, $end], static fn ($x) => $x !== ''));
    $periodText = implode(' - ', $parts);
@endphp
@if($periodText !== '')
<span class="period-inline">{{ $periodText }}</span>
@endif
