@if (! $loaded)
    <div class="space-y-3">
        @for ($i = 0; $i < 3; $i++)
            <div class="panel h-24 animate-pulse bg-surface-2/60"></div>
        @endfor
    </div>
@elseif ($loadError)
    <div class="panel flex flex-col items-center p-8 text-center">
        <div class="grid size-14 place-items-center rounded-full bg-rose-500/10 text-rose-600"><x-icon name="wifi-off" class="size-7" /></div>
        <p class="mt-3 text-[14px] font-medium text-ink-soft">{{ $loadError }}</p>
        <button x-on:click="$wire.load(navigator.onLine, true)" class="btn-outline mt-4 py-2.5 text-[14px]"><x-icon name="refresh" class="size-4" />{{ __('app.try_again') }}</button>
    </div>
@endif
