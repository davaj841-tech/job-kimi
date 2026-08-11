<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">اعلان‌های اخیر</x-slot>

        <ul class="space-y-3">
            @foreach ($notifications as $item)
                <li class="flex items-start gap-3 rounded-lg border border-gray-100 p-3 dark:border-gray-800">
                    <span class="mt-0.5 inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-rose-50 text-rose-600 dark:bg-rose-950/40 dark:text-rose-300">
                        @svg('heroicon-o-' . $item['icon'], 'h-4 w-4')
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $item['text'] }}</p>
                        <p class="mt-0.5 text-xs text-gray-500">{{ $item['time'] }}</p>
                    </div>
                </li>
            @endforeach
        </ul>
    </x-filament::section>
</x-filament-widgets::widget>
