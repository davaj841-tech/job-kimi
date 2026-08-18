@extends('install.layout')

@section('content')
    <div class="mb-6 flex items-start gap-3">
        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-brand/10 text-brand">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-6 w-6">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75Z" />
            </svg>
        </span>
        <div>
            <h2 class="text-lg font-bold text-slate-900">اجرای مهاجرت و سیدر</h2>
            <p class="mt-1 text-sm leading-7 text-slate-600">
                {{ $migrationCount }} فایل migration از پوشه database/migrations اجرا می‌شود و سپس <code dir="ltr">db:seed</code> فراخوانی می‌گردد.
            </p>
        </div>
    </div>

    <div class="mb-3 h-3 overflow-hidden rounded-full bg-slate-100">
        <div id="bar" class="h-full w-0 rounded-full bg-brand transition-all duration-300"></div>
    </div>
    <p id="bar-label" class="mb-3 text-left text-xs text-slate-500" dir="ltr">0%</p>

    <pre id="console" class="mb-4 max-h-80 overflow-auto rounded-xl bg-slate-950 p-4 text-left text-xs leading-6 text-emerald-300" dir="ltr">آماده اجرا...</pre>

    <form id="migrate-form" method="post" action="{{ route('install.migrate.run') }}">
        @csrf
        <button id="run-btn" type="button" class="w-full rounded-xl bg-brand px-4 py-3 text-sm font-bold text-white">
            شروع migrate و seed
        </button>
    </form>

    <a id="next" href="{{ route('install.admin') }}" class="mt-3 hidden w-full rounded-xl bg-emerald-600 px-4 py-3 text-center text-sm font-bold text-white">
        ادامه به ساخت مدیر
    </a>
@endsection

@section('scripts')
<script>
    const bar = document.getElementById('bar');
    const label = document.getElementById('bar-label');
    const consoleEl = document.getElementById('console');
    const next = document.getElementById('next');
    const runBtn = document.getElementById('run-btn');

    function setProgress(p) {
        bar.style.width = p + '%';
        label.textContent = p + '%';
    }

    function appendLines(text) {
        const lines = String(text || '').split(/\r?\n/);
        consoleEl.textContent = '';
        let i = 0;
        const timer = setInterval(() => {
            if (i >= lines.length) {
                clearInterval(timer);
                return;
            }
            consoleEl.textContent += (i === 0 ? '' : '\n') + lines[i];
            consoleEl.scrollTop = consoleEl.scrollHeight;
            i += 1;
            setProgress(Math.min(99, Math.round((i / Math.max(lines.length, 1)) * 100)));
        }, 40);
    }

    runBtn.addEventListener('click', async function () {
        runBtn.disabled = true;
        runBtn.textContent = 'در حال اجرا...';
        setProgress(5);
        consoleEl.textContent = 'در حال اجرای دستورات Artisan...\n';
        const body = new FormData(document.getElementById('migrate-form'));
        try {
            const res = await fetch(@json(route('install.migrate.run')), {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body
            });
            const data = await res.json();
            appendLines(data.output || data.message || 'بدون خروجی');
            if (res.ok && data.ok) {
                setTimeout(() => {
                    setProgress(100);
                    next.classList.remove('hidden');
                    next.classList.add('block');
                    runBtn.classList.add('hidden');
                }, 800);
            } else {
                runBtn.disabled = false;
                runBtn.textContent = 'تلاش دوباره';
            }
        } catch (e) {
            consoleEl.textContent += '\nERROR: ' + e.message;
            runBtn.disabled = false;
            runBtn.textContent = 'تلاش دوباره';
        }
    });
</script>
@endsection
