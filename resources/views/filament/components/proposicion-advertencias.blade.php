<div class="rounded-lg border border-warning-300 bg-warning-50 dark:bg-warning-900/20 dark:border-warning-700 p-3 space-y-1">
    @foreach($advertencias as $advertencia)
        <div class="flex items-start gap-2 text-sm text-warning-700 dark:text-warning-400">
            <x-heroicon-m-exclamation-triangle class="w-4 h-4 mt-0.5 shrink-0" />
            <span>{{ $advertencia }}</span>
        </div>
    @endforeach
</div>
