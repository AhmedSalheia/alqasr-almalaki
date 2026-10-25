<div class="space-y-4" x-data="netPoll('load', 20)">
    <div>
        <h1 class="font-display text-[24px] font-bold gold-text">{{ __('admin.requests_title') }}</h1>
        <p class="text-[13px] text-ink-soft">{{ __('admin.requests_sub') }}</p>
    </div>

    @include('livewire.admin.partials.state')

    @if ($loaded && ! $loadError)
        @forelse ($requests as $r)
            <div wire:key="r-{{ $r['id'] }}" class="panel p-4 animate-rise">
                <div class="flex items-center gap-3">
                    <span class="grid size-12 shrink-0 place-items-center rounded-2xl bg-gold-500/10 text-accent"><x-icon name="smartphone" class="size-6" /></span>
                    <div class="min-w-0 flex-1">
                        <div class="truncate text-[16px] font-bold text-ink">{{ $r['name'] ?? '' }}</div>
                        <div class="truncate text-[13px] text-ink-soft">{{ $r['deviceName'] ?? '' }}</div>
                        <div class="text-[12px] text-ink-soft">{{ __('admin.requested', ['time' => \App\Support\Format::relative($r['createdAt'] ?? null)]) }}</div>
                    </div>
                </div>
                <div class="mt-3 grid grid-cols-2 gap-2">
                    <button wire:click="approve('{{ $r['id'] }}')" class="btn-gold py-2.5 text-[14px]"><x-icon name="check" class="size-4" stroke="2.6" />{{ __('admin.approve') }}</button>
                    <button wire:click="deny('{{ $r['id'] }}')" class="btn-danger py-2.5 text-[14px]"><x-icon name="x" class="size-4" />{{ __('admin.deny') }}</button>
                </div>
            </div>
        @empty
            <div class="panel flex flex-col items-center p-8 text-center">
                <div class="grid size-14 place-items-center rounded-full bg-emerald-500/10 text-emerald-600"><x-icon name="shield" class="size-7" /></div>
                <p class="mt-3 text-[14px] font-medium text-ink-soft">{{ __('admin.no_requests') }}</p>
            </div>
        @endforelse
    @endif
</div>
