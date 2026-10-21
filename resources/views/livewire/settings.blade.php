@php use App\Support\Format; @endphp
<div class="space-y-4">
    {{-- Account --}}
    <section class="panel flex items-center gap-4 p-5">
        <div class="grid size-14 shrink-0 place-items-center rounded-full gold-fill font-display text-[24px] font-bold text-navy-950">
            {{ mb_substr($user['name'] ?? '?', 0, 1) }}
        </div>
        <div class="min-w-0 flex-1">
            <div class="text-[12px] font-bold text-ink-soft">{{ __('app.signed_in_as') }}</div>
            <div class="truncate font-display text-[19px] font-bold text-ink">{{ $user['name'] ?? '' }}</div>
            <div class="truncate text-[13px] text-ink-soft" dir="ltr" style="text-align: {{ $locale === 'ar' ? 'right' : 'left' }}">{{ $user['email'] ?? '' }}</div>
        </div>
        <span class="chip {{ ($user['role'] ?? '') === 'admin' ? 'gold-fill text-navy-950' : 'bg-surface-2 text-ink-soft' }}">
            <x-icon name="{{ ($user['role'] ?? '') === 'admin' ? 'crown' : 'user' }}" class="size-3.5" />
            {{ ($user['role'] ?? '') === 'admin' ? __('app.role_admin') : __('app.role_staff') }}
        </span>
    </section>

    {{-- Language --}}
    <section class="panel p-5">
        <h2 class="field-label"><x-icon name="globe" class="size-4 text-accent" />{{ __('app.language') }}</h2>
        <div class="grid grid-cols-2 gap-2">
            @foreach (['ar' => 'العربية', 'en' => 'English'] as $code => $label)
                <button wire:click="setLocale('{{ $code }}')" class="rounded-2xl border px-4 py-3 text-[15px] font-bold transition {{ $locale === $code ? 'border-gold-500 bg-gold-500/10 text-accent' : 'border-line text-ink-soft' }}">{{ $label }}</button>
            @endforeach
        </div>
    </section>

    {{-- Theme --}}
    <section class="panel p-5" x-data="{ t: @js($theme) }">
        <h2 class="field-label"><x-icon name="sun" class="size-4 text-accent" />{{ __('app.theme') }}</h2>
        <div class="grid grid-cols-3 gap-2">
            @foreach (['system' => ['monitor', __('app.theme_system')], 'light' => ['sun', __('app.theme_light')], 'dark' => ['moon', __('app.theme_dark')]] as $code => [$icon, $label])
                <button @click="t = '{{ $code }}'" wire:click="setTheme('{{ $code }}')"
                        :class="t === '{{ $code }}' ? 'border-gold-500 bg-gold-500/10 text-accent' : 'border-line text-ink-soft'"
                        class="flex flex-col items-center gap-1.5 rounded-2xl border px-2 py-3 text-[13px] font-bold transition">
                    <x-icon :name="$icon" class="size-5" />{{ $label }}
                </button>
            @endforeach
        </div>
    </section>

    {{-- Sync --}}
    <section class="panel space-y-3 p-5">
        <h2 class="field-label"><x-icon name="cloud" class="size-4 text-accent" />{{ __('app.sync') }}</h2>
        <div class="flex items-center justify-between gap-3">
            <div class="text-[14px] leading-relaxed text-ink">
                <div class="font-bold">
                    @if ($status['online'] === false)
                        <span class="text-rose-600 dark:text-rose-300">{{ __('app.offline') }}</span>
                    @else
                        <span class="text-emerald-600 dark:text-emerald-400">{{ __('app.online') }}</span>
                    @endif
                </div>
                <div class="text-[13px] text-ink-soft">{{ __('app.last_sync', ['time' => Format::relative($status['last_success_at'])]) }}</div>
                @if ($status['pending'] > 0)
                    <div class="text-[13px] font-bold text-accent">{{ trans_choice('app.pending_changes', $status['pending'], ['count' => $status['pending']]) }}</div>
                @endif
                @if ($status['error'])
                    <div class="text-[12px] text-rose-600 dark:text-rose-300">{{ __('app.sync_error', ['error' => $status['error']]) }}</div>
                @endif
            </div>
            <button wire:click="syncNow" class="btn-outline shrink-0 py-2.5 text-[14px]" wire:loading.attr="disabled" wire:target="syncNow">
                <x-icon name="refresh" class="size-4" wire:loading.class="animate-spin" wire:target="syncNow" />{{ __('app.sync_now') }}
            </button>
        </div>
        <p class="text-[12px] text-ink-soft">{{ __('app.sync_every', ['seconds' => $interval]) }}</p>
    </section>

    {{-- Reminders --}}
    <section class="panel p-5">
        <div class="flex items-center justify-between gap-3">
            <div class="min-w-0">
                <h2 class="field-label mb-1"><x-icon name="alert" class="size-4 text-accent" />{{ __('app.reminders') }}</h2>
                <p class="text-[13px] leading-relaxed text-ink-soft">
                    {{ __('app.reminders_desc', ['days' => $reminderDays, 'time' => \App\Support\Format::time(sprintf('%02d:00', $reminderHour))]) }}
                    {{ ($user['role'] ?? '') === 'admin' ? __('app.reminders_admin') : __('app.reminders_staff') }}
                </p>
                @if ($remindersOn && $remindersCount > 0)
                    <p class="mt-1 text-[12px] font-bold text-accent">{{ trans_choice('app.reminders_count', $remindersCount, ['count' => $remindersCount]) }}</p>
                @endif
            </div>
            <button type="button" wire:click="toggleReminders" role="switch" aria-checked="{{ $remindersOn ? 'true' : 'false' }}"
                    class="relative h-7 w-12 shrink-0 rounded-full transition {{ $remindersOn ? 'bg-gold-500' : 'bg-ink-soft/25' }}">
                <span class="absolute top-1 size-5 rounded-full bg-white shadow transition-all {{ $remindersOn ? 'start-6' : 'start-1' }}"></span>
            </button>
        </div>
    </section>

    {{-- Device --}}
    <section class="panel p-5">
        <h2 class="field-label"><x-icon name="smartphone" class="size-4 text-accent" />{{ __('app.this_device') }}</h2>
        <div class="text-[15px] font-bold text-ink">{{ $deviceName }}</div>
        <div class="mt-0.5 break-all font-latin text-[12px] text-ink-soft" dir="ltr">{{ $deviceId }}</div>
    </section>

    {{-- Password --}}
    <section class="panel p-5">
        <button wire:click="$toggle('changingPassword')" class="flex w-full items-center justify-between text-[15px] font-bold text-ink">
            <span class="flex items-center gap-2"><x-icon name="key" class="size-4 text-accent" />{{ __('app.change_password') }}</span>
            <x-icon name="chevron-down" class="size-5 text-ink-soft transition {{ $changingPassword ? 'rotate-180' : '' }}" />
        </button>
        @if ($changingPassword)
            <form wire:submit="changePassword" class="mt-4 space-y-3">
                <input type="password" wire:model="newPassword" class="field" dir="ltr" placeholder="{{ __('app.new_password') }}" autocomplete="new-password">
                @error('newPassword') <p class="field-error">{{ $message }}</p> @enderror
                <button class="btn-navy w-full">{{ __('app.save') }}</button>
            </form>
        @endif
    </section>

    <button wire:click="$set('confirmingLogout', true)" class="btn-danger w-full py-3.5"><x-icon name="logout" class="size-5 rtl:rotate-180" />{{ __('app.logout') }}</button>

    <div class="pt-2 text-center">
        <img src="{{ asset('images/emblem.png') }}" class="mx-auto h-10 opacity-80 dark:hidden" alt="">
        <img src="{{ asset('images/emblem-dark.png') }}" class="mx-auto hidden h-10 opacity-80 dark:block" alt="">
        <div class="mt-1 text-[12px] text-ink-soft">{{ __('app.name') }} · {{ __('app.version', ['version' => config('nativephp.version', '1.0.0')]) }}</div>
    </div>

    @if ($confirmingLogout)
        <div class="fixed inset-0 z-50 flex items-end bg-navy-950/60 p-4 backdrop-blur-sm" wire:click.self="$set('confirmingLogout', false)">
            <div class="panel safe-bottom mx-auto w-full max-w-md p-5 animate-rise">
                <p class="text-center text-[16px] font-bold text-ink">{{ __('app.logout_confirm') }}</p>
                @if ($status['pending'] > 0)
                    <p class="mt-2 rounded-xl bg-rose-500/10 px-3 py-2 text-center text-[13px] font-medium text-rose-700 dark:text-rose-200">{{ __('app.logout_pending_warning', ['count' => $status['pending']]) }}</p>
                @endif
                <div class="mt-5 grid grid-cols-2 gap-3">
                    <button wire:click="$set('confirmingLogout', false)" class="btn-ghost border border-line">{{ __('app.cancel') }}</button>
                    <button wire:click="logout" class="btn bg-rose-600 text-white">{{ __('app.logout') }}</button>
                </div>
            </div>
        </div>
    @endif
</div>
