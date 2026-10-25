@php use App\Support\Format; @endphp
<div class="space-y-4">
    {{-- Controls --}}
    <div class="flex items-center justify-between gap-3">
        <div class="flex rounded-2xl border border-line bg-surface p-1 text-[13px] font-bold">
            <button wire:click="setView('month')" class="flex items-center gap-1.5 rounded-xl px-3.5 py-2 transition {{ $view === 'month' ? 'gold-fill text-navy-950 shadow' : 'text-ink-soft' }}">
                <x-icon name="grid" class="size-4" />{{ __('calendar.month') }}
            </button>
            <button wire:click="setView('day')" class="flex items-center gap-1.5 rounded-xl px-3.5 py-2 transition {{ $view === 'day' ? 'gold-fill text-navy-950 shadow' : 'text-ink-soft' }}">
                <x-icon name="list" class="size-4" />{{ __('calendar.day') }}
            </button>
        </div>
        <button wire:click="today" class="chip border border-gold-500/40 px-3.5 py-2 text-[13px] text-accent {{ $selected->toDateString() === $todayStr ? 'opacity-50' : '' }}">
            {{ __('calendar.today') }}
        </button>
    </div>

    <div class="flex items-center justify-between">
        <button wire:click="previous" class="grid size-11 place-items-center rounded-full border border-line bg-surface text-ink-soft active:scale-95" aria-label="{{ __('calendar.previous') }}">
            <x-icon name="chevron-left" class="size-5 rtl:rotate-180" />
        </button>
        <div class="text-center">
            @if ($view === 'month')
                <h1 class="font-display text-[26px] font-bold leading-tight gold-text">{{ Format::monthName($selected->month, withNumber: true) }}</h1>
                <div class="font-latin text-[15px] font-semibold tracking-[0.3em] text-ink-soft">{{ $selected->year }}</div>
            @else
                <h1 class="font-display text-[24px] font-bold leading-tight gold-text">{{ Format::weekday($selected) }}</h1>
                <div class="text-[14px] font-medium text-ink-soft">{{ $selected->day }} {{ Format::monthName($selected->month) }} {{ $selected->year }}</div>
            @endif
        </div>
        <button wire:click="next" class="grid size-11 place-items-center rounded-full border border-line bg-surface text-ink-soft active:scale-95" aria-label="{{ __('calendar.next') }}">
            <x-icon name="chevron-right" class="size-5 rtl:rotate-180" />
        </button>
    </div>

    @if ($view === 'month')
        {{-- Month grid --}}
        <div class="panel overflow-hidden p-2.5"
             x-data="swipe(() => $wire.previous(), () => $wire.next())" @touchstart.passive="start($event)" @touchend.passive="end($event)">
            <div class="grid grid-cols-7 pb-1.5">
                @foreach ($weekdayHeaders as $h)
                    <div class="py-1.5 text-center text-[11px] font-bold text-accent">{{ $h }}</div>
                @endforeach
            </div>
            <div class="gold-hairline mb-1.5 opacity-60"></div>
            <div class="grid grid-cols-7 gap-1" wire:loading.class="opacity-60" wire:target="previous,next">
                @foreach ($weeks as $week)
                    @foreach ($week as $day)
                        @php
                            $key = $day->toDateString();
                            $list = $events->get($key, collect());
                            $inMonth = $day->month === $selected->month;
                            $isSel = $key === $selected->toDateString();
                            $isToday = $key === $todayStr;
                        @endphp
                        <button wire:key="cell-{{ $key }}" wire:click="select('{{ $key }}')" wire:dblclick="openDay('{{ $key }}')"
                                class="relative flex h-[66px] flex-col items-center rounded-2xl px-0.5 pt-1.5 transition
                                       {{ $inMonth ? '' : 'opacity-35' }}
                                       {{ $list->isNotEmpty() ? 'bg-gold-500/10 dark:bg-gold-400/10' : '' }}
                                       {{ $isSel ? 'ring-2 ring-gold-500 bg-gold-500/15' : '' }}">
                            <span class="grid size-7 place-items-center rounded-full font-latin text-[15px] font-bold
                                         {{ $isSel ? 'gold-fill text-navy-950' : ($isToday ? 'border border-navy-900/70 text-ink dark:border-ivory-100/70' : 'text-ink') }}">
                                {{ $day->day }}
                            </span>
                            <span class="mt-1 flex w-full flex-col items-center gap-0.5">
                                @foreach ($list->take(2) as $ev)
                                    <span class="w-[92%] truncate rounded-md bg-navy-900 px-1 py-px text-center font-latin text-[9.5px] font-bold leading-tight text-gold-300 dark:bg-gold-500 dark:text-navy-950" dir="ltr">{{ $ev->start_time }}</span>
                                @endforeach
                                @if ($list->count() > 2)
                                    <span class="text-[9.5px] font-bold text-accent">{{ __('calendar.more', ['count' => $list->count() - 2]) }}</span>
                                @endif
                            </span>
                        </button>
                    @endforeach
                @endforeach
            </div>
        </div>

        {{-- Month summary (money totals: admins only) --}}
        <div class="grid {{ $showFinance ? 'grid-cols-3' : 'grid-cols-1' }} gap-2 text-center">
            <div class="panel px-2 py-3 {{ $showFinance ? '' : 'flex items-center justify-center gap-3' }}">
                <div class="font-latin text-[22px] font-bold leading-none gold-text">{{ $monthCount }}</div>
                <div class="{{ $showFinance ? 'mt-1' : '' }} text-[11px] font-bold text-ink-soft">{{ __('calendar.reservations') }}</div>
            </div>
            @if ($showFinance)
                <div class="panel px-2 py-3">
                    <div class="truncate font-latin text-[16px] font-bold leading-tight text-emerald-600 dark:text-emerald-400">{{ Format::money($monthCollected) }}</div>
                    <div class="mt-1 text-[11px] font-bold text-ink-soft">{{ __('event.paid_amount') }}</div>
                </div>
                <div class="panel px-2 py-3">
                    <div class="truncate font-latin text-[16px] font-bold leading-tight text-rose-600 dark:text-rose-300">{{ Format::money($monthRemaining) }}</div>
                    <div class="mt-1 text-[11px] font-bold text-ink-soft">{{ __('event.remaining') }}</div>
                </div>
            @endif
        </div>

        {{-- Agenda of the selected day --}}
        <section class="space-y-3 pt-1">
            <div class="flex items-center justify-between">
                <h2 class="font-display text-[19px] font-bold text-ink">{{ Format::longDate($selected) }}</h2>
                <button wire:click="openDay('{{ $selected->toDateString() }}')" class="chip bg-surface-2 text-ink-soft">
                    {{ trans_choice('calendar.events_count', $dayEvents->count(), ['count' => $dayEvents->count()]) }}
                </button>
            </div>
            @forelse ($dayEvents as $event)
                <x-event-row :event="$event" />
            @empty
                @include('livewire.partials.empty-day', ['date' => $selected])
            @endforelse
        </section>
    @else
        {{-- Day view --}}
        <div class="panel grid grid-cols-7 gap-1 p-2"
             x-data="swipe(() => $wire.previous(), () => $wire.next())" @touchstart.passive="start($event)" @touchend.passive="end($event)">
            @foreach ($strip as $d)
                @php $k = $d->toDateString(); $sel = $k === $selected->toDateString(); $n = $stripCounts[$k] ?? 0; @endphp
                <button wire:key="strip-{{ $k }}" wire:click="select('{{ $k }}')"
                        class="flex flex-col items-center gap-1 rounded-2xl py-2 transition {{ $sel ? 'gold-fill text-navy-950 shadow' : 'text-ink' }}">
                    <span class="text-[10.5px] font-bold {{ $sel ? '' : 'text-ink-soft' }}">{{ Format::weekday($d, short: true) }}</span>
                    <span class="font-latin text-[18px] font-bold leading-none">{{ $d->day }}</span>
                    <span class="size-1.5 rounded-full {{ $n ? ($sel ? 'bg-navy-950' : 'bg-gold-500') : 'bg-transparent' }}"></span>
                </button>
            @endforeach
        </div>

        <section class="relative space-y-3" wire:loading.class="opacity-60" wire:target="previous,next,select">
            @if ($dayEvents->isNotEmpty())
                <div class="absolute inset-y-2 start-8 w-px bg-gradient-to-b from-gold-500/0 via-gold-500/50 to-gold-500/0"></div>
            @endif
            @forelse ($dayEvents as $event)
                <div class="relative flex items-start gap-3" wire:key="day-{{ $event->id }}">
                    <div class="z-10 mt-4 grid size-16 shrink-0 place-items-center rounded-full border border-gold-500/40 bg-surface text-center shadow-sm">
                        <div>
                            <div class="font-latin text-[16px] font-bold leading-none text-ink" dir="ltr">{{ $event->start_time }}</div>
                            <div class="text-[10px] font-bold text-accent">{{ (int) substr($event->start_time, 0, 2) >= 12 ? __('calendar.pm') : __('calendar.am') }}</div>
                        </div>
                    </div>
                    <div class="min-w-0 flex-1"><x-event-row :event="$event" compact /></div>
                </div>
            @empty
                @include('livewire.partials.empty-day', ['date' => $selected])
            @endforelse
        </section>
    @endif
</div>
