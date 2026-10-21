<div class="animate-rise">
    <div class="text-center">
        <img src="{{ asset('images/logo.png') }}" alt="{{ __('app.venue') }}" class="mx-auto w-60 dark:hidden">
        <img src="{{ asset('images/logo-dark.png') }}" alt="{{ __('app.venue') }}" class="mx-auto hidden w-60 dark:block">
        <h1 class="mt-4 font-display text-[22px] font-bold text-ink">{{ __('access.sign_in_title') }}</h1>
        <div class="gold-hairline mx-auto mt-3 w-40"></div>
    </div>

    <form wire:submit="submit" class="panel mt-6 space-y-4 p-5">
        @if ($error)
            <div class="flex items-start gap-2 rounded-2xl border border-rose-500/30 bg-rose-500/10 px-3.5 py-3 text-[14px] font-medium text-rose-700 dark:text-rose-200">
                <x-icon name="alert" class="mt-0.5 size-4 shrink-0" />{{ $error }}
            </div>
        @endif
        <div>
            <label class="field-label" for="login"><x-icon name="user" class="size-4 text-accent" />{{ __('access.login') }}</label>
            <input id="login" type="text" wire:model="login" class="field" dir="ltr" autocomplete="username" autocapitalize="none" spellcheck="false" required>
            @error('login') <p class="field-error">{{ $message }}</p> @enderror
        </div>
        <div x-data="{ show: false }">
            <label class="field-label" for="password"><x-icon name="key" class="size-4 text-accent" />{{ __('access.password') }}</label>
            <div class="relative">
                <input id="password" :type="show ? 'text' : 'password'" wire:model="password" class="field pe-12" dir="ltr" autocomplete="current-password" required>
                <button type="button" @click="show = !show" class="absolute inset-y-0 end-2 grid w-10 place-items-center text-ink-soft"><x-icon name="eye" class="size-5" /></button>
            </div>
            @error('password') <p class="field-error">{{ $message }}</p> @enderror
        </div>
        <button type="submit" class="btn-gold w-full py-4 text-[16px]" wire:loading.attr="disabled" wire:target="submit">
            <span wire:loading.remove wire:target="submit">{{ __('access.sign_in') }}</span>
            <span wire:loading wire:target="submit">{{ __('access.signing_in') }}</span>
        </button>
    </form>
</div>
