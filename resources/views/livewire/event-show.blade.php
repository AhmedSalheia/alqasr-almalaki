@php use App\Support\Format; @endphp
<div class="space-y-4">
    {{-- Hero --}}
    <section class="panel relative overflow-hidden p-5 text-center animate-rise">
        <div class="pointer-events-none absolute -top-16 start-1/2 size-56 -translate-x-1/2 rounded-full bg-gold-500/15 blur-3xl rtl:translate-x-1/2"></div>
        <div class="relative">
            <div class="text-[12px] font-bold tracking-wide text-accent">{{ __('event.card_title') }}</div>
            <h1 class="mt-1 font-display text-[30px] font-bold leading-tight text-ink">{{ $event->groom_name }}</h1>
            <div class="gold-hairline mx-auto my-3 w-32"></div>
            <div class="text-[15px] font-medium text-ink-soft">{{ Format::longDate($event->date) }}</div>
            <div class="mt-1 font-latin text-[20px] font-bold text-ink">{{ Format::timeRange($event->start_time, $event->end_time) }}</div>
            <div class="mt-3 flex flex-wrap justify-center gap-2">
                <x-payment-chip :event="$event" />
                @if ($event->sync_status === 'pending')
                    <span class="chip bg-gold-500/15 text-gold-700 dark:text-gold-300"><x-icon name="cloud-up" class="size-3.5" />{{ __('event.pending_sync') }}</span>
                @endif
            </div>
        </div>
    </section>

    {{-- Details --}}
    <section class="panel divide-y divide-line">
        @php
            // [icon, label, value, is phone number, digits to call, WhatsApp number]
            $rows = [
                ['phone', __('event.phone'), $event->phone ?: '—', true, $phoneDigits, $whatsapp],
                ['music', __('event.dj_name'), $event->dj_name ?: '—', false, null, null],
            ];
            if ($event->dj_phone) {
                $rows[] = ['phone', __('event.dj_phone'), $event->dj_phone, true, $djDigits, $djWhatsapp];
            }
        @endphp
        @foreach ($rows as [$icon, $label, $value, $ltr, $call, $wa])
            <div class="flex items-center gap-3 px-4 py-3.5">
                <span class="grid size-10 shrink-0 place-items-center rounded-2xl bg-gold-500/10 text-accent"><x-icon :name="$icon" class="size-5" /></span>
                <div class="min-w-0 flex-1">
                    <div class="text-[12px] font-bold text-ink-soft">{{ $label }}</div>
                    <div class="truncate text-[16px] font-bold text-ink" @if ($ltr) dir="ltr" style="text-align: {{ app()->getLocale() === 'ar' ? 'right' : 'left' }}" @endif>{{ $value }}</div>
                </div>
                @if ($call)
                    <a href="tel:{{ $call }}" class="grid size-10 place-items-center rounded-full bg-navy-900 text-gold-300 dark:bg-gold-500 dark:text-navy-950" aria-label="{{ __('event.call') }}"><x-icon name="phone" class="size-4.5" /></a>
                    <a href="https://wa.me/{{ $wa }}" class="grid size-10 place-items-center rounded-full bg-emerald-600 text-white" aria-label="{{ __('event.whatsapp') }}"><x-icon name="chat" class="size-4.5" /></a>
                @endif
            </div>
        @endforeach

        <div class="grid grid-cols-3 divide-x divide-line text-center rtl:divide-x-reverse">
            <div class="px-2 py-3.5">
                <div class="text-[11px] font-bold text-ink-soft">{{ __('event.total_cost') }}</div>
                <div class="mt-0.5 font-latin text-[17px] font-bold text-ink">{{ Format::money($event->total_cost) }}</div>
            </div>
            <div class="px-2 py-3.5">
                <div class="text-[11px] font-bold text-ink-soft">{{ __('event.paid_amount') }}</div>
                <div class="mt-0.5 font-latin text-[17px] font-bold text-emerald-600 dark:text-emerald-400">{{ Format::money($event->paid_amount) }}</div>
            </div>
            <div class="px-2 py-3.5">
                <div class="text-[11px] font-bold text-ink-soft">{{ __('event.remaining') }}</div>
                <div class="mt-0.5 font-latin text-[17px] font-bold {{ $event->remaining() > 0 ? 'text-rose-600 dark:text-rose-300' : 'text-emerald-600 dark:text-emerald-400' }}">{{ Format::money($event->remaining()) }}</div>
            </div>
        </div>

        @if ($event->notes)
            <div class="px-4 py-3.5">
                <div class="mb-1 flex items-center gap-1.5 text-[12px] font-bold text-ink-soft"><x-icon name="notes" class="size-4 text-accent" />{{ __('event.notes') }}</div>
                <p class="whitespace-pre-line text-[15px] leading-relaxed text-ink">{{ $event->notes }}</p>
            </div>
        @endif

        @if ($event->created_by_name)
            <div class="px-4 py-2.5 text-[12px] text-ink-soft">{{ __('event.added_by', ['name' => $event->created_by_name]) }}</div>
        @endif
    </section>

    {{-- Card --}}
    <section class="space-y-3"
             x-data="cardExporter({
                native: @js($isNative),
                url: @js(route('events.card', $event)),
                fileName: @js($fileName),
                messages: {
                    saved: @js(__('event.card_saved_gallery')),
                    shared: @js(__('event.card_shared')),
                    downloaded: @js(__('event.card_downloaded')),
                    failed: @js(__('event.card_failed')),
                },
             })">

        <div class="flex items-center justify-between gap-2">
            <h2 class="font-display text-[19px] font-bold text-ink">{{ __('event.card_style') }}</h2>
            <div class="flex rounded-2xl border border-line bg-surface p-1 text-[13px] font-bold">
                @foreach (['ivory' => __('event.card_ivory'), 'royal' => __('event.card_royal')] as $key => $label)
                    <button type="button" wire:click="$set('cardStyle', '{{ $key }}')"
                            class="rounded-xl px-3 py-1.5 transition {{ $cardStyle === $key ? 'gold-fill text-navy-950 shadow' : 'text-ink-soft' }}">{{ $label }}</button>
                @endforeach
            </div>
        </div>

        <label class="flex items-center justify-between rounded-2xl border border-line bg-surface px-4 py-3 text-[14px] font-bold text-ink">
            <span class="flex items-center gap-2"><x-icon name="wallet" class="size-4 text-accent" />{{ __('event.card_show_payments') }}</span>
            <input type="checkbox" wire:model.live="showPayments" class="peer sr-only">
            <span class="relative h-7 w-12 rounded-full bg-ink-soft/25 transition after:absolute after:top-1 after:start-1 after:size-5 after:rounded-full after:bg-white after:shadow after:transition peer-checked:bg-gold-500 peer-checked:after:translate-x-5 rtl:peer-checked:after:-translate-x-5"></span>
        </label>

        <div x-data="fitScale(540)" class="w-full" dir="ltr">
            <div class="relative mx-auto overflow-hidden rounded-[22px] shadow-[0_25px_50px_-20px_rgb(12_26_45/0.55)]"
                 :style="`width:${540 * scale}px;height:${675 * scale}px`">
                <div class="origin-top-left" :style="`transform:scale(${scale});width:540px;height:675px`">
                    <x-event-card id="event-card" :event="$event" :style="$cardStyle" :payments="$showPayments" />
                </div>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-3">
            <button type="button" @click="run('save')" :disabled="busy" class="btn-gold whitespace-nowrap px-3 py-3.5">
                <x-icon name="download" class="size-5" stroke="2.2" x-show="!busy" />
                <x-icon name="refresh" class="size-5 animate-spin" x-show="busy" x-cloak />
                {{ __('event.download_card') }}
            </button>
            <button type="button" @click="run('share')" :disabled="busy" class="btn-outline whitespace-nowrap px-3 py-3.5">
                <x-icon name="share" class="size-5" />{{ __('event.share_card') }}
            </button>
        </div>
    </section>

    {{-- Actions --}}
    <section class="grid grid-cols-2 gap-3 pt-2">
        <a href="{{ route('events.edit', $event) }}" wire:navigate class="btn-navy py-3.5"><x-icon name="edit" class="size-5" />{{ __('app.edit') }}</a>
        <button type="button" wire:click="$set('confirmingDelete', true)" class="btn-danger py-3.5"><x-icon name="trash" class="size-5" />{{ __('app.delete') }}</button>
    </section>

    @if ($confirmingDelete)
        <div class="fixed inset-0 z-50 flex items-end bg-navy-950/60 p-4 backdrop-blur-sm" wire:click.self="$set('confirmingDelete', false)">
            <div class="panel safe-bottom mx-auto w-full max-w-md p-5 animate-rise">
                <div class="mx-auto mb-3 grid size-12 place-items-center rounded-full bg-rose-500/10 text-rose-600"><x-icon name="trash" class="size-6" /></div>
                <p class="text-center text-[16px] font-bold text-ink">{{ __('event.delete_confirm') }}</p>
                <div class="mt-5 grid grid-cols-2 gap-3">
                    <button type="button" wire:click="$set('confirmingDelete', false)" class="btn-ghost border border-line">{{ __('app.cancel') }}</button>
                    <button type="button" wire:click="delete" class="btn bg-rose-600 text-white">{{ __('app.delete') }}</button>
                </div>
            </div>
        </div>
    @endif
</div>
