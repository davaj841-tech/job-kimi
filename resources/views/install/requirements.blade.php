<div class="mb-4 rounded-xl bg-slate-50 px-4 py-3 text-sm">
    <span class="font-bold text-slate-800">تعداد migrationهای پیدا شده:</span>
    <span class="text-brand">{{ $migrationCount }}</span>
    <span class="text-slate-500">فایل در مسیر database/migrations</span>
</div>

<ul class="divide-y divide-slate-100 rounded-xl border border-slate-100">
    @foreach ($requirements as $item)
        <li class="flex items-center justify-between gap-3 px-4 py-2.5 text-sm">
            <span class="text-slate-700">{{ $item['label'] }}</span>
            <span class="flex items-center gap-2">
                <span class="text-xs text-slate-500">{{ $item['detail'] }}</span>
                @if ($item['ok'])
                    <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-emerald-100 text-emerald-700">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-4 w-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                        </svg>
                    </span>
                @else
                    <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-red-100 text-red-600">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-4 w-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                        </svg>
                    </span>
                @endif
            </span>
        </li>
    @endforeach
</ul>
