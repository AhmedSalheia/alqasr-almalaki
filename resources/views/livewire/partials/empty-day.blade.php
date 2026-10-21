<div class="panel flex flex-col items-center px-6 py-8 text-center">
    <div class="mb-3 grid size-14 place-items-center rounded-full bg-gold-500/10 text-accent">
        <x-icon name="crown" class="size-7" />
    </div>
    <p class="text-[14px] font-medium text-ink-soft">{{ __('calendar.no_events_day') }}</p>
    <a href="{{ route('events.create', ['date' => $date->toDateString()]) }}" wire:navigate class="btn-outline mt-4 py-2.5 text-[14px]">
        <x-icon name="plus" class="size-4" />{{ __('calendar.add_on_day') }}
    </a>
</div>
