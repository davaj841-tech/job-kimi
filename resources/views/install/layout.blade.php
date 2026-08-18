<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'نصب برنامه' }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Vazirmatn', 'sans-serif'] },
                    colors: {
                        brand: { DEFAULT: '#ef394e', dark: '#0f2744' }
                    }
                }
            }
        }
    </script>
    <style>
        body { font-family: Vazirmatn, sans-serif; }
    </style>
</head>
<body class="min-h-screen bg-slate-100 text-slate-800">
    @php
        $step = (int) ($step ?? 1);
        $steps = [
            1 => 'پیش‌نیاز',
            2 => 'پایگاه‌داده',
            3 => 'مهاجرت',
            4 => 'مدیر',
            5 => 'پایان',
        ];
    @endphp
    <div class="mx-auto max-w-3xl px-4 py-8 sm:py-12">
        <div class="mb-8 text-center">
            <h1 class="text-2xl font-bold text-brand-dark">نصب برنامه</h1>
            <p class="mt-1 text-sm text-slate-500">۵ مرحله تا راه‌اندازی روی هاست</p>
        </div>

        <ol class="mb-8 grid grid-cols-5 gap-1 sm:gap-2" dir="rtl">
            @foreach($steps as $num => $label)
                <li class="text-center">
                    <div
                        class="mx-auto flex h-9 w-9 items-center justify-center rounded-full text-sm font-bold
                        {{ $step > $num ? 'bg-emerald-500 text-white' : ($step === $num ? 'bg-brand text-white' : 'bg-white text-slate-400 ring-1 ring-slate-200') }}"
                    >
                        @if($step > $num)
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-5 w-5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                            </svg>
                        @else
                            {{ $num }}
                        @endif
                    </div>
                    <p class="mt-1 hidden text-[11px] font-medium sm:block {{ $step === $num ? 'text-brand' : 'text-slate-500' }}">{{ $label }}</p>
                </li>
            @endforeach
        </ol>

        <div class="mb-6 h-2 overflow-hidden rounded-full bg-slate-200">
            <div class="h-full rounded-full bg-brand transition-all" style="width: {{ ($step / 5) * 100 }}%"></div>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-8">
            @if ($errors->any())
                <div class="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    @foreach ($errors->all() as $error)
                        <p>{{ $error }}</p>
                    @endforeach
                </div>
            @endif

            @yield('content')
        </div>
    </div>
    @yield('scripts')
</body>
</html>
