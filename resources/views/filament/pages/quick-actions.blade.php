<x-filament-panels::page>
    <div class="grid gap-4 md:grid-cols-3">
        <a href="{{ route('filament.admin.resources.exams.create') }}"
           class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm transition hover:border-rose-300 dark:border-gray-700 dark:bg-gray-900">
            <p class="text-lg font-bold">آزمون جدید</p>
            <p class="mt-1 text-sm text-gray-500">ایجاد و انتشار آزمون</p>
        </a>
        <a href="{{ route('filament.admin.resources.users.create') }}"
           class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm transition hover:border-rose-300 dark:border-gray-700 dark:bg-gray-900">
            <p class="text-lg font-bold">کاربر جدید</p>
            <p class="mt-1 text-sm text-gray-500">ثبت ادمین / اپراتور / کاربر</p>
        </a>
        <a href="/admin"
           class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm transition hover:border-rose-300 dark:border-gray-700 dark:bg-gray-900">
            <p class="text-lg font-bold">پنل عملیاتی Vue</p>
            <p class="mt-1 text-sm text-gray-500">داشبورد کامل، تجمیع، تیکت</p>
        </a>
    </div>
</x-filament-panels::page>
