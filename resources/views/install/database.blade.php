@extends('install.layout')

@section('content')
    <div class="mb-6 flex items-start gap-3">
        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-brand/10 text-brand">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-6 w-6">
                <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375" />
            </svg>
        </span>
        <div>
            <h2 class="text-lg font-bold text-slate-900">اتصال پایگاه‌داده</h2>
            <p class="mt-1 text-sm leading-7 text-slate-600">
                اطلاعات MySQL هاست را وارد کنید. اگر دیتابیس وجود نداشته باشد ساخته می‌شود.
            </p>
        </div>
    </div>

    <form id="db-form" method="post" action="{{ route('install.database.store') }}" class="space-y-4">
        @csrf
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="mb-1 block text-sm font-bold">هاست</label>
                <input name="db_host" value="{{ $old['db_host'] }}" required class="field" dir="ltr">
            </div>
            <div>
                <label class="mb-1 block text-sm font-bold">پورت</label>
                <input name="db_port" value="{{ $old['db_port'] }}" required class="field" dir="ltr">
            </div>
        </div>
        <div>
            <label class="mb-1 block text-sm font-bold">نام پایگاه‌داده</label>
            <input name="db_database" value="{{ $old['db_database'] }}" required class="field" dir="ltr" pattern="[A-Za-z0-9_]+">
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="mb-1 block text-sm font-bold">نام کاربری</label>
                <input name="db_username" value="{{ $old['db_username'] }}" required class="field" dir="ltr">
            </div>
            <div>
                <label class="mb-1 block text-sm font-bold">رمز عبور</label>
                <input type="password" name="db_password" value="{{ $old['db_password'] }}" class="field" dir="ltr" autocomplete="new-password">
            </div>
        </div>
        <div>
            <label class="mb-1 block text-sm font-bold">پیشوند جداول (اختیاری)</label>
            <input name="db_prefix" value="{{ $old['db_prefix'] }}" class="field" dir="ltr" pattern="[A-Za-z0-9_]*">
        </div>

        <p id="db-status" class="hidden rounded-xl px-3 py-2 text-sm"></p>

        <div class="flex flex-col gap-2 sm:flex-row">
            <button type="button" id="test-btn" class="flex-1 rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-bold">
                تست اتصال
            </button>
            <button type="submit" class="flex-1 rounded-xl bg-brand px-4 py-3 text-sm font-bold text-white">
                ذخیره و ادامه
            </button>
        </div>
    </form>
@endsection

@section('scripts')
<style>
    .field { width: 100%; border-radius: 0.75rem; border: 1px solid #e2e8f0; padding: 0.65rem 0.85rem; font-size: 0.875rem; }
    .field:focus { outline: none; border-color: #ef394e; }
</style>
<script>
    document.getElementById('test-btn').addEventListener('click', async function () {
        const form = document.getElementById('db-form');
        const status = document.getElementById('db-status');
        const body = new FormData(form);
        status.classList.remove('hidden', 'bg-emerald-50', 'text-emerald-800', 'bg-red-50', 'text-red-700');
        status.textContent = 'در حال تست...';
        try {
            const res = await fetch(@json(route('install.database.test')), {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body
            });
            const data = await res.json();
            status.textContent = data.message || (res.ok ? 'موفق' : 'ناموفق');
            status.classList.add(res.ok ? 'bg-emerald-50' : 'bg-red-50', res.ok ? 'text-emerald-800' : 'text-red-700');
        } catch (e) {
            status.textContent = 'خطا در ارسال درخواست.';
            status.classList.add('bg-red-50', 'text-red-700');
        }
    });
</script>
@endsection
