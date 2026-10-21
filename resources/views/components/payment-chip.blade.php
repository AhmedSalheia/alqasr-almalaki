@props(['event'])
@if ($event->isPaidInFull())
    <span {{ $attributes->merge(['class' => 'chip bg-emerald-500/12 text-emerald-700 dark:text-emerald-300']) }}>
        <x-icon name="check" class="size-3" stroke="3" />{{ __('event.paid_in_full') }}
    </span>
@elseif ((float) $event->paid_amount > 0)
    <span {{ $attributes->merge(['class' => 'chip bg-gold-500/15 text-gold-700 dark:text-gold-300']) }}>
        {{ __('event.partially_paid') }}
    </span>
@else
    <span {{ $attributes->merge(['class' => 'chip bg-rose-500/10 text-rose-700 dark:text-rose-300']) }}>
        {{ __('event.unpaid') }}
    </span>
@endif
