<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, minimum-scale=1, maximum-scale=5, viewport-fit=cover">
    <meta name="theme-color" content="{{ \App\Support\ThemeBootstrap::payload()['primary'] }}">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="جاب‌آزمون">
    {!! $seoHead ?? '' !!}
    @if(empty($seoHead))
    <title>جاب‌آزمون</title>
    @endif
    @include('partials.analytics')
    <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
    <link rel="preconnect" href="https://fonts.googleapis.com" crossorigin>
    <link rel="dns-prefetch" href="https://cdn.jsdelivr.net">
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet">
    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="icon" href="{{ \App\Support\PublicAsset::url((string) \App\Models\Setting::get('site_favicon', '')) ?: '/icons/icon-192.png' }}">
    <link rel="apple-touch-icon" href="/icons/maskable-icon-152.png">
    <style id="ja-theme-boot">{!! \App\Support\ThemeBootstrap::inlineStyle() !!}</style>
    <script>
      (function () {
        try {
          var theme = localStorage.getItem('ja_theme');
          var dark = theme === 'dark' || (!theme && window.matchMedia('(prefers-color-scheme: dark)').matches);
          if (dark) document.documentElement.classList.add('dark');
        } catch (e) {}
      })();
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.ts'])
</head>
<body class="bg-surface-page">
@php $gtmBody = \App\Models\Setting::getFilled('google_tag_manager', ''); @endphp
@if($gtmBody !== '')
<noscript><iframe src="https://www.googletagmanager.com/ns.html?id={{ $gtmBody }}" height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
@endif
    <div id="app"></div>
</body>
</html>
