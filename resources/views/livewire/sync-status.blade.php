<div x-data="netPoll('tick', {{ $interval }})" @offline.window="$wire.tick(false)" class="shrink-0">
    @php
        $online = $status['online'];
        $pending = $status['pending'];
    @endphp
    <button type="button" x-on:click="$wire.syncNow(navigator.onLine)" wire:loading.attr="disabled" wire:target="syncNow"
            class="flex items-center gap-1.5 rounded-full border border-line bg-surface px-3 py-1.5 text-[12px] font-bold text-ink-soft active:scale-95"
            title="{{ $status['last_success_at'] ? __('app.last_sync', ['time' => \App\Support\Format::relative($status['last_success_at'])]) : '' }}">
        <span wire:loading.remove wire:target="syncNow" class="flex items-center gap-1.5">
            @if ($online === false)
                <x-icon name="wifi-off" class="size-4 text-rose-500" />
                <span>{{ __('app.offline') }}</span>
            @elseif ($pending > 0)
                <x-icon name="cloud-up" class="size-4 text-gold-500" />
                <span>{{ $pending }}</span>
            @else
                <span class="relative flex size-2.5">
                    <span class="absolute inline-flex size-full animate-ping rounded-full bg-emerald-400 opacity-60"></span>
                    <span class="relative inline-flex size-2.5 rounded-full bg-emerald-500"></span>
                </span>
                <span>{{ __('app.online') }}</span>
            @endif
        </span>
        <span wire:loading.flex wire:target="syncNow" class="items-center gap-1.5">
            <x-icon name="refresh" class="size-4 animate-spin text-gold-500" />
            <span>{{ __('app.syncing') }}</span>
        </span>
    </button>
</div>
