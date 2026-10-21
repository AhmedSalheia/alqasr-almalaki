<div x-data="toaster" @toast.window="push($event.detail)"
     class="pointer-events-none fixed inset-x-0 bottom-28 z-[60] flex flex-col items-center gap-2 px-4">
    <template x-for="t in items" :key="t.id">
        <div x-transition.opacity.duration.300ms
             class="pointer-events-auto flex max-w-sm items-center gap-3 rounded-2xl border px-4 py-3 text-[14px] font-medium shadow-xl animate-rise"
             :class="t.type === 'error'
                ? 'border-rose-400/40 bg-rose-50 text-rose-800 dark:bg-rose-950 dark:text-rose-100'
                : 'border-gold-500/40 bg-navy-900 text-ivory-50 dark:bg-ivory-100 dark:text-navy-900'">
            <span class="grid size-6 shrink-0 place-items-center rounded-full"
                  :class="t.type === 'error' ? 'bg-rose-500/20' : 'gold-fill text-navy-950'">
                <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                    <path x-show="t.type !== 'error'" d="M20 6 9 17l-5-5"/>
                    <path x-show="t.type === 'error'" d="M12 8v4M12 16h.01"/>
                </svg>
            </span>
            <span x-text="t.message"></span>
        </div>
    </template>
</div>
@if (session('toast'))
    <div x-data x-init="$nextTick(() => toast(@js(session('toast')), @js(session('toast_type', 'success'))))"></div>
@endif
