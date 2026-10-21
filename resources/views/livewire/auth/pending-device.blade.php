<div class="animate-rise text-center" @if ($state === 'pending') x-data="netPoll('check', 6, { immediate: false, onlyOnline: true })" @endif>
    <img src="{{ asset('images/emblem.png') }}" alt="" class="mx-auto w-32 dark:hidden">
    <img src="{{ asset('images/emblem-dark.png') }}" alt="" class="mx-auto hidden w-32 dark:block">

    <div class="panel mt-6 p-6">
        @if ($state === 'denied')
            <div class="mx-auto grid size-16 place-items-center rounded-full bg-rose-500/10 text-rose-600"><x-icon name="x" class="size-8" /></div>
            <h1 class="mt-4 font-display text-[22px] font-bold text-ink">{{ __('access.denied_title') }}</h1>
            <p class="mt-2 text-[14px] leading-relaxed text-ink-soft">{{ __('access.denied_body') }}</p>
        @else
            <div class="relative mx-auto grid size-16 place-items-center">
                <span class="absolute inset-0 animate-ping rounded-full bg-gold-500/25"></span>
                <span class="relative grid size-16 place-items-center rounded-full gold-fill text-navy-950"><x-icon name="hourglass" class="size-8" /></span>
            </div>
            <h1 class="mt-4 font-display text-[22px] font-bold text-ink">{{ __('access.pending_title') }}</h1>
            <p class="mt-2 text-[14px] leading-relaxed text-ink-soft">{{ __('access.pending_body') }}</p>
        @endif

        <div class="mt-5 space-y-2 rounded-2xl bg-surface-2 p-4 text-start text-[14px]">
            <div class="flex items-center gap-2"><x-icon name="user" class="size-4 text-accent" /><span class="font-bold text-ink">{{ $user['name'] ?? '' }}</span></div>
            <div class="flex items-center gap-2"><x-icon name="smartphone" class="size-4 text-accent" /><span class="text-ink-soft">{{ __('access.pending_device') }}:</span><span class="font-bold text-ink">{{ $deviceName }}</span></div>
        </div>

        <div class="mt-5 grid gap-2">
            @if ($state !== 'denied')
                <button wire:click="check" class="btn-gold w-full" wire:loading.attr="disabled" wire:target="check">
                    <x-icon name="refresh" class="size-4" wire:loading.class="animate-spin" wire:target="check" />{{ __('access.pending_check') }}
                </button>
            @endif
            <button wire:click="useOtherAccount" class="btn-ghost w-full">{{ __('access.use_other_account') }}</button>
        </div>
    </div>
</div>
