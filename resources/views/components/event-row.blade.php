@props(['event', 'compact' => false])
@php use App\Support\Format; @endphp
<a href="{{ route('events.show', $event) }}" wire:navigate wire:key="ev-{{ $event->id }}"
   class="panel group relative flex items-stretch gap-3 overflow-hidden p-3.5 active:scale-[.99] transition animate-rise">
    <span class="absolute inset-y-3 start-0 w-1 rounded-e-full {{ $event->isMorning() ? 'bg-morning' : 'gold-fill' }}"></span>
    @unless ($compact)
    <div class="flex w-16 shrink-0 flex-col items-center justify-center rounded-2xl py-2 text-center {{ $event->isMorning() ? 'bg-morning/12' : 'bg-surface-2' }}">
        <x-icon :name="$event->isMorning() ? 'sun' : 'clock'" class="mb-1 size-4 {{ $event->isMorning() ? 'text-morning' : 'text-accent' }}" />
        <div class="font-latin text-[17px] font-bold leading-none text-ink" dir="ltr">{{ $event->start_time }}</div>
        @if ($event->end_time)
            <div class="mt-0.5 font-latin text-[12px] font-semibold text-ink-soft" dir="ltr">{{ $event->end_time }}</div>
        @endif
    </div>
    @endunless
    <div class="min-w-0 flex-1 py-0.5 {{ $compact ? 'ps-2' : '' }}">
        <div class="flex items-start justify-between gap-2">
            <div class="truncate font-display text-[18px] font-bold leading-snug text-ink">{{ $event->groom_name }}</div>
            @if ($event->sync_status === 'pending')
                <x-icon name="cloud-up" class="mt-1 size-4 shrink-0 text-gold-500" />
            @endif
        </div>
        <div class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-[13px] text-ink-soft">
            @if ($compact)
                <span class="inline-flex items-center gap-1"><x-icon name="clock" class="size-3.5 text-accent" />{{ Format::timeRange($event->start_time, $event->end_time) }}</span>
            @endif
            @if ($event->dj_name)
                <span class="inline-flex items-center gap-1"><x-icon name="music" class="size-3.5 text-accent" />{{ $event->dj_name }}</span>
            @endif
            <span class="inline-flex items-center gap-1"><x-icon name="wallet" class="size-3.5 text-accent" />{{ Format::money($event->netCost()) }}</span>
        </div>
        <div class="mt-2 flex flex-wrap gap-1.5">
            @if ($event->isMorning())
                <span class="chip bg-morning/12 text-morning"><x-icon name="sun" class="size-3" stroke="2.4" />{{ __('event.morning') }}</span>
            @endif
            <x-payment-chip :event="$event" />
        </div>
    </div>
    <x-icon name="chevron-right" class="my-auto size-5 shrink-0 text-ink-soft/50 rtl:rotate-180" />
</a>
