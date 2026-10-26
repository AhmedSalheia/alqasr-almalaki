@php $remaining = max(0, (float) (is_numeric($total_cost) ? $total_cost : 0) - (float) (is_numeric($paid_amount) ? $paid_amount : 0)); @endphp
<form wire:submit="save" class="space-y-4">
    <div class="text-center">
        <h1 class="font-display text-[26px] font-bold gold-text">{{ $heading }}</h1>
        <div class="gold-hairline mx-auto mt-2 w-40"></div>
    </div>

    <div class="panel space-y-4 p-5">
        <div>
            <label class="field-label" for="groom_name"><x-icon name="crown" class="size-4 text-accent" />{{ __('event.groom_name') }}</label>
            <input id="groom_name" type="text" wire:model="groom_name" class="field font-display text-[18px]" placeholder="{{ __('event.groom_placeholder') }}" autocomplete="off" required>
            @error('groom_name') <p class="field-error">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="field-label" for="date"><x-icon name="calendar" class="size-4 text-accent" />{{ __('event.date') }}</label>
            <input id="date" type="date" wire:model.live="date" class="field" required>
            @error('date') <p class="field-error">{{ $message }}</p> @enderror
            @if ($sameDay > 0)
                <p class="mt-2 flex items-center gap-1.5 rounded-xl bg-gold-500/10 px-3 py-2 text-[13px] font-medium text-gold-800 dark:text-gold-200">
                    <x-icon name="alert" class="size-4 shrink-0" />{{ __('event.same_day_warning', ['count' => $sameDay]) }}
                </p>
            @endif
        </div>

        <div>
            <label class="field-label" for="phone"><x-icon name="phone" class="size-4 text-accent" />{{ __('event.phone') }}</label>
            <input id="phone" type="tel" inputmode="tel" dir="ltr" wire:model="phone" class="field text-start rtl:text-end" placeholder="05X XXX XXXX" autocomplete="off">
            @error('phone') <p class="field-error">{{ $message }}</p> @enderror
        </div>

        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="field-label" for="start_time"><x-icon name="clock" class="size-4 text-accent" />{{ __('event.start_time') }}</label>
                <input id="start_time" type="time" wire:model="start_time" class="field" required>
                @error('start_time') <p class="field-error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="field-label" for="end_time">{{ __('event.end_time') }} <span class="font-normal opacity-70">({{ __('event.optional') }})</span></label>
                <input id="end_time" type="time" wire:model="end_time" class="field">
                @error('end_time') <p class="field-error">{{ $message }}</p> @enderror
            </div>
        </div>
    </div>

    <div class="panel space-y-4 p-5">
        <div class="field-label"><x-icon name="music" class="size-4 text-accent" />{{ __('event.dj') }}</div>
        <div class="grid grid-cols-2 gap-2">
            <button type="button" wire:click="useVenueDj" @class(['rounded-2xl border px-3 py-3 text-[14px] font-bold transition', 'border-gold-500 bg-gold-500/10 text-accent' => ! $dj_external, 'border-line text-ink-soft' => $dj_external])>{{ __('event.dj_venue') }}</button>
            <button type="button" wire:click="useExternalDj" @class(['rounded-2xl border px-3 py-3 text-[14px] font-bold transition', 'border-gold-500 bg-gold-500/10 text-accent' => $dj_external, 'border-line text-ink-soft' => ! $dj_external])>{{ __('event.dj_external') }}</button>
        </div>

        @if (! $dj_external)
            <div class="flex items-center justify-between gap-3 rounded-xl border border-line px-4 py-3">
                <div class="min-w-0">
                    <div class="truncate text-[15px] font-bold text-ink">{{ \App\Support\Venue::value('dj_name') }}</div>
                    <div class="font-latin text-[14px] font-semibold text-ink-soft" dir="ltr" style="text-align: {{ app()->getLocale() === 'ar' ? 'right' : 'left' }}">{{ \App\Support\Phone::display(\App\Support\Venue::value('dj_phone')) }}</div>
                </div>
                <x-icon name="check" class="size-5 shrink-0 text-accent" />
            </div>
        @else
            <div>
                <label class="field-label" for="dj_name">{{ __('event.dj_name') }}</label>
                <input id="dj_name" type="text" wire:model="dj_name" class="field" autocomplete="off" required>
                @error('dj_name') <p class="field-error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="field-label" for="dj_phone"><x-icon name="phone" class="size-4 text-accent" />{{ __('event.dj_phone') }}</label>
                <div class="flex gap-2" dir="ltr">
                    <select wire:model="dj_phone_code" class="field w-[6.5rem] shrink-0 font-latin font-bold" aria-label="{{ __('event.dj_phone_code') }}">
                        @foreach (\App\Support\Phone::CODES as $code)
                            <option value="{{ $code }}">{{ $code }}</option>
                        @endforeach
                    </select>
                    <input id="dj_phone" type="tel" inputmode="tel" dir="ltr" wire:model="dj_phone" class="field min-w-0 flex-1 text-left" placeholder="05X XXX XXXX" autocomplete="off" required>
                </div>
                @error('dj_phone') <p class="field-error">{{ $message }}</p> @enderror
            </div>
        @endif
    </div>

    <div class="panel space-y-4 p-5">
        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="field-label" for="total_cost"><x-icon name="wallet" class="size-4 text-accent" />{{ __('event.total_cost') }}</label>
                <div class="relative">
                    <input id="total_cost" type="text" inputmode="decimal" wire:model.live.debounce.400ms="total_cost" class="field pe-9 font-latin text-[18px] font-bold" placeholder="0">
                    <span class="pointer-events-none absolute inset-y-0 end-3 grid place-items-center text-ink-soft">{{ config('alqasr.currency.symbol') }}</span>
                </div>
                @error('total_cost') <p class="field-error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="field-label" for="paid_amount">{{ __('event.paid_amount') }}</label>
                <div class="relative">
                    <input id="paid_amount" type="text" inputmode="decimal" wire:model.live.debounce.400ms="paid_amount" class="field pe-9 font-latin text-[18px] font-bold" placeholder="0">
                    <span class="pointer-events-none absolute inset-y-0 end-3 grid place-items-center text-ink-soft">{{ config('alqasr.currency.symbol') }}</span>
                </div>
                @error('paid_amount') <p class="field-error">{{ $message }}</p> @enderror
            </div>
        </div>
        <div class="flex items-center justify-between rounded-2xl bg-surface-2 px-4 py-3">
            <span class="text-[13px] font-bold text-ink-soft">{{ __('event.remaining') }}</span>
            <span class="font-latin text-[20px] font-bold {{ $remaining > 0 ? 'text-rose-600 dark:text-rose-300' : 'text-emerald-600 dark:text-emerald-400' }}">{{ \App\Support\Format::money($remaining) }}</span>
        </div>
    </div>

    <div class="panel p-5">
        <label class="field-label" for="notes"><x-icon name="notes" class="size-4 text-accent" />{{ __('event.notes') }}</label>
        <textarea id="notes" rows="4" wire:model="notes" class="field resize-none leading-relaxed" placeholder="{{ __('event.notes_placeholder') }}"></textarea>
        @error('notes') <p class="field-error">{{ $message }}</p> @enderror
    </div>

    <div class="sticky bottom-24 z-30 pt-2">
        <button type="submit" class="btn-gold w-full py-4 text-[17px]" wire:loading.attr="disabled" wire:target="save">
            <span wire:loading.remove wire:target="save" class="flex items-center gap-2"><x-icon name="check" class="size-5" stroke="2.6" />{{ __('app.save') }}</span>
            <span wire:loading wire:target="save">{{ __('app.loading') }}</span>
        </button>
    </div>
</form>
