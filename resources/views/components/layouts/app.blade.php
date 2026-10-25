@props(['title' => null, 'back' => null])
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ $dir }}" data-theme="{{ $theme }}">
<head>
    <x-layouts.head :title="$title" />
</head>
<body class="royal-backdrop min-h-dvh">
    <header class="safe-top sticky top-0 z-40 border-b border-line bg-bg/85 backdrop-blur-xl">
        <div class="mx-auto flex h-16 max-w-xl items-center gap-3 px-4">
            @if ($back)
                <a href="{{ $back }}" wire:navigate class="-ms-2 grid size-10 place-items-center rounded-full text-ink-soft active:bg-surface-2" aria-label="{{ __('app.back') }}">
                    <x-icon name="chevron-left" class="size-6 rtl:rotate-180" />
                </a>
            @else
                <a href="{{ route('calendar') }}" wire:navigate class="shrink-0">
                    <img src="{{ asset('images/emblem.png') }}" alt="" class="h-9 w-auto dark:hidden">
                    <img src="{{ asset('images/emblem-dark.png') }}" alt="" class="hidden h-9 w-auto dark:block">
                </a>
            @endif
            <div class="min-w-0 flex-1 leading-tight">
                <div class="truncate font-display text-[19px] font-bold gold-text">{{ __('app.venue') }}</div>
                <div class="truncate text-[12px] font-medium tracking-wide text-ink-soft">{{ $title ?? __('app.tagline') }}</div>
            </div>
            @persist('sync-status')
                <livewire:sync-status />
            @endpersist
        </div>
    </header>

    <main class="mx-auto max-w-xl px-4 pt-4 pb-36">
        <livewire:update-banner />
        {{ $slot }}
    </main>

    <nav class="safe-bottom fixed inset-x-0 bottom-0 z-40 border-t border-line bg-surface/90 backdrop-blur-xl">
        <div class="mx-auto grid h-[68px] max-w-xl grid-cols-5 items-center px-2">
            @php
                $tab = fn ($route) => request()->routeIs($route) ? 'text-accent' : 'text-ink-soft';
            @endphp
            <a href="{{ route('calendar') }}" wire:navigate class="flex flex-col items-center gap-1 text-[11px] font-bold {{ $tab('calendar') }}">
                <x-icon name="calendar" class="size-6" />{{ __('app.calendar') }}
            </a>
            @if ($isAdmin)
                <a href="{{ route('admin.devices') }}" wire:navigate class="flex flex-col items-center gap-1 text-[11px] font-bold {{ $tab('admin.devices') }}">
                    <x-icon name="smartphone" class="size-6" />{{ __('app.devices') }}
                </a>
            @else
                <span></span>
            @endif
            <a href="{{ route('events.create') }}" wire:navigate
               class="mx-auto -mt-9 grid size-16 place-items-center rounded-full gold-fill text-navy-950 shadow-[0_10px_25px_-6px_rgb(224_154_21/0.75)] ring-4 ring-bg"
               aria-label="{{ __('app.add_event') }}">
                <x-icon name="plus" class="size-8" stroke="2.4" />
            </a>
            @if ($isAdmin)
                <a href="{{ route('admin.users') }}" wire:navigate class="flex flex-col items-center gap-1 text-[11px] font-bold {{ $tab('admin.users') }}">
                    <x-icon name="users" class="size-6" />{{ __('app.users') }}
                </a>
            @else
                <span></span>
            @endif
            <a href="{{ route('settings') }}" wire:navigate class="flex flex-col items-center gap-1 text-[11px] font-bold {{ $tab('settings') }}">
                <x-icon name="settings" class="size-6" />{{ __('app.settings') }}
            </a>
        </div>
    </nav>

    <x-layouts.toaster />
    @livewireScripts
</body>
</html>
