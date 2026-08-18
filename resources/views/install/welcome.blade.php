@extends('install.layout')

@section('content')
    <div class="mb-6 flex items-start gap-3">
        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-brand/10 text-brand">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-6 w-6">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
            </svg>
        </span>
        <div>
            <h2 class="text-lg font-bold text-slate-900">خوش آمدید</h2>
            <p class="mt-1 text-sm leading-7 text-slate-600">
                این راهنما برنامه را روی هاست نصب می‌کند. ابتدا پیش‌نیازهای سرور و تعداد فایل‌های migration بررسی می‌شود.
            </p>
        </div>
    </div>

    @include('install.requirements')

    <form method="post" action="{{ route('install.requirements') }}" class="mt-6">
        @csrf
        <button
            type="submit"
            @disabled(! $canContinue)
            class="w-full rounded-xl bg-brand px-4 py-3 text-sm font-bold text-white disabled:cursor-not-allowed disabled:opacity-50"
        >
            ادامه به تنظیم پایگاه‌داده
        </button>
    </form>
@endsection
