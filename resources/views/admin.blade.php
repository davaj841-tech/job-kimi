<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>پنل مدیریت | JobAzmoon</title>
  <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
  <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet">
  <style id="ja-theme-boot">{!! \App\Support\ThemeBootstrap::inlineStyle() !!}</style>
  <script>
    (function () {
      try {
        if (localStorage.getItem('ja_theme') === 'dark') {
          document.documentElement.classList.add('dark');
        }
      } catch (e) {}
    })();
  </script>
  @vite(['resources/css/app.css', 'resources/js/admin/main.ts'])
</head>
<body class="bg-slate-100">
  <div id="admin-app"></div>
</body>
</html>
