@props(['title' => null])
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ $dir }}" data-theme="{{ $theme }}">
<head>
    <x-layouts.head :title="$title" />
</head>
<body class="royal-backdrop min-h-dvh">
    <div class="safe-top safe-bottom mx-auto flex min-h-dvh max-w-md flex-col px-6">
        <div class="flex justify-end gap-2 pt-4 text-[13px] font-bold">
            @php $back = '?back='.urlencode('/'.ltrim(request()->path(), '/')); @endphp
            <a href="{{ route('prefs.locale', app()->getLocale() === 'ar' ? 'en' : 'ar').$back }}" class="chip border border-line bg-surface text-ink-soft">
                <x-icon name="globe" class="size-4" /> {{ app()->getLocale() === 'ar' ? 'English' : 'العربية' }}
            </a>
        </div>
        <div class="flex flex-1 flex-col justify-center py-8">
            {{ $slot }}
        </div>
        <p class="pb-6 text-center text-[12px] text-ink-soft">{{ __('access.administration_only') }}</p>
    </div>
    <x-layouts.toaster />
    @livewireScripts
</body>
</html>
