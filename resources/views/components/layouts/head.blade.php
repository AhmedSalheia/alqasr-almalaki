@props(['title' => null])
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="theme-color" content="#0c1a2d" media="(prefers-color-scheme: dark)">
<meta name="theme-color" content="#fbf6ea" media="(prefers-color-scheme: light)">
<title>{{ $title ? $title.' · ' : '' }}{{ __('app.name') }}</title>
<link rel="icon" type="image/png" href="{{ asset('icon.png') }}">
<script>
    (function () {
        var pref = document.documentElement.dataset.theme || 'system';
        var dark = pref === 'dark' || (pref === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
        document.documentElement.classList.toggle('dark', dark);
    })();
</script>
@vite(['resources/css/app.css', 'resources/js/app.js'])
@livewireStyles
