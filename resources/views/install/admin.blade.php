@extends('install.layout')

@section('content')
    <div class="mb-6 flex items-start gap-3">
        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-brand/10 text-brand">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-6 w-6">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
            </svg>
        </span>
        <div>
            <h2 class="text-lg font-bold text-slate-900">حساب مدیر و نام سایت</h2>
            <p class="mt-1 text-sm leading-7 text-slate-600">
                نام سایت در فایل محیط ذخیره می‌شود و کاربر مدیر با رمز رمزنگاری‌شده ساخته می‌شود.
            </p>
        </div>
    </div>

    <form method="post" action="{{ route('install.admin.store') }}" class="space-y-4">
        @csrf
        <div>
            <label class="mb-1 block text-sm font-bold">نام سایت</label>
            <input name="site_name" value="{{ $old['site_name'] }}" required maxlength="100" class="field">
        </div>
        <div>
            <label class="mb-1 block text-sm font-bold">نام مدیر</label>
            <input name="name" value="{{ $old['name'] }}" required maxlength="100" class="field">
        </div>
        <div>
            <label class="mb-1 block text-sm font-bold">ایمیل</label>
            <input type="email" name="email" value="{{ $old['email'] }}" required class="field" dir="ltr">
        </div>
        <div>
            <label class="mb-1 block text-sm font-bold">رمز عبور</label>
            <input type="password" name="password" required minlength="8" class="field" dir="ltr" autocomplete="new-password">
        </div>
        <div>
            <label class="mb-1 block text-sm font-bold">تکرار رمز عبور</label>
            <input type="password" name="password_confirmation" required minlength="8" class="field" dir="ltr" autocomplete="new-password">
        </div>
        <button type="submit" class="w-full rounded-xl bg-brand px-4 py-3 text-sm font-bold text-white">
            ساخت مدیر و ادامه
        </button>
    </form>
@endsection

@section('scripts')
<style>
    .field { width: 100%; border-radius: 0.75rem; border: 1px solid #e2e8f0; padding: 0.65rem 0.85rem; font-size: 0.875rem; }
    .field:focus { outline: none; border-color: #ef394e; }
</style>
@endsection
