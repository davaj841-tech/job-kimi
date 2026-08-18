@extends('install.layout')

@section('content')
    <div class="text-center">
        <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-emerald-100 text-emerald-600">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-9 w-9">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
            </svg>
        </div>
        <h2 class="text-xl font-bold text-slate-900">نصب کامل شد</h2>
        <p class="mx-auto mt-2 max-w-md text-sm leading-7 text-slate-600">
            فایل نشانه نصب ساخته شد، کش پیکربندی پاک شد و برنامه آماده استفاده است.
        </p>
        <div class="mt-6 flex flex-col gap-2 sm:flex-row sm:justify-center">
            <a href="{{ $loginUrl }}" class="rounded-xl bg-brand px-5 py-3 text-sm font-bold text-white">ورود</a>
            <a href="{{ $homeUrl }}" class="rounded-xl border border-slate-200 px-5 py-3 text-sm font-bold text-slate-700">صفحه اصلی</a>
        </div>
    </div>
@endsection
