{{-- The shareable event card. Fixed 540 x 675 CSS px -> exported at 2x = 1080 x 1350 PNG (4:5).
     Every line that must stay on one line is "whitespace-nowrap"; the logo is a data: URL and the
     fonts come from /card/fonts.css, so the PNG looks exactly like the screen. --}}
@props(['event', 'style' => 'ivory', 'payments' => true])
@php
    use App\Support\CardAssets;
    use App\Support\Format;
    use App\Support\Phone;
    use App\Support\Venue;
    $royal = $style === 'royal';
    $rtl = app()->getLocale() === 'ar';
    $len = mb_strlen($event->groom_name);
    $nameSize = $len > 30 ? 'text-[26px]' : ($len > 24 ? 'text-[30px]' : ($len > 18 ? 'text-[36px]' : 'text-[42px]'));
    $gold = '#e09a15';
    $ink = $royal ? 'text-ivory-50' : 'text-navy-900';
    $soft = $royal ? 'text-ivory-200/75' : 'text-navy-700/75';
    $tile = $royal ? 'bg-white/5 border-gold-400/25' : 'bg-white/70 border-gold-600/20';
    $venue = Venue::get();
    $djPhone = Phone::display($event->dj_phone);
    $align = 'text-align:'.($rtl ? 'right' : 'left');
@endphp
<div {{ $attributes }} dir="{{ $rtl ? 'rtl' : 'ltr' }}" lang="{{ app()->getLocale() }}"
     class="relative overflow-hidden font-sans {{ $royal ? 'bg-navy-900' : 'bg-ivory-50' }}"
     style="width:540px;height:675px;background-image:{{ $royal
        ? 'radial-gradient(120% 70% at 50% 0%, #1f3455 0%, #0c1a2d 55%, #070f1c 100%)'
        : 'radial-gradient(120% 70% at 50% 0%, #fffdf7 0%, #fbf3e0 55%, #f1e2bd 100%)' }};">

    {{-- lattice texture --}}
    <div class="absolute inset-0" style="opacity:{{ $royal ? '.07' : '.10' }};background-image:url(&quot;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='44' height='44' viewBox='0 0 44 44'%3E%3Cg fill='none' stroke='%23e09a15' stroke-width='1'%3E%3Cpath d='M22 3l5.5 13.5L41 22l-13.5 5.5L22 41l-5.5-13.5L3 22l13.5-5.5z'/%3E%3C/g%3E%3C/svg%3E&quot;);"></div>

    {{-- double gold frame --}}
    <div class="absolute inset-[14px] rounded-[26px] border-[1.5px]" style="border-color:{{ $gold }};"></div>
    <div class="absolute inset-[21px] rounded-[21px] border" style="border-color:{{ $royal ? 'rgb(233 176 60 / .35)' : 'rgb(192 124 12 / .30)' }};"></div>

    {{-- corner fleurons --}}
    @foreach (['top-[8px] left-[8px]', 'top-[8px] right-[8px] -scale-x-100', 'bottom-[8px] left-[8px] -scale-y-100', 'bottom-[8px] right-[8px] -scale-100'] as $pos)
        <svg class="absolute {{ $pos }} size-[58px]" viewBox="0 0 60 60" fill="none">
            <path d="M6 54C6 26 26 6 54 6" stroke="{{ $gold }}" stroke-width="1.6"/>
            <path d="M12 54c0-22 20-42 42-42" stroke="{{ $gold }}" stroke-opacity=".45" stroke-width="1"/>
            <path d="M8 8c9 0 14 4 15 12-8-1-13-5-15-12Z" fill="{{ $gold }}"/>
            <circle cx="27" cy="27" r="2.4" fill="{{ $gold }}"/>
        </svg>
    @endforeach

    <div class="relative flex h-full flex-col items-center px-[44px] pt-[30px] pb-[28px] text-center">
        {{-- logo (already carries both names, each on one line) --}}
        <img src="{{ CardAssets::logo($royal) }}" alt="القصر الملكي" width="139" height="118" style="width:139px;height:118px">

        <div class="mt-2 flex w-full items-center gap-3">
            <div class="h-px flex-1" style="background:linear-gradient(90deg,transparent,{{ $gold }})"></div>
            <svg class="size-3" viewBox="0 0 12 12"><path d="M6 0l2 4 4 2-4 2-2 4-2-4-4-2 4-2z" fill="{{ $gold }}"/></svg>
            <div class="h-px flex-1" style="background:linear-gradient(90deg,{{ $gold }},transparent)"></div>
        </div>

        <div class="mt-2 whitespace-nowrap text-[13px] font-bold tracking-wide" style="color:{{ $gold }}">{{ __('event.card_title') }}</div>
        <div class="w-full overflow-hidden text-ellipsis whitespace-nowrap font-display font-bold leading-[1.35] {{ $nameSize }} {{ $royal ? 'gold-text' : 'text-navy-900' }}">{{ $event->groom_name }}</div>

        {{-- date & time --}}
        <div class="mt-2 flex w-full items-stretch gap-3">
            <div class="flex flex-1 flex-col items-center justify-center rounded-2xl border {{ $tile }} py-2">
                <div class="whitespace-nowrap text-[12px] font-bold {{ $soft }}">{{ Format::weekday($event->date) }}</div>
                <div class="font-latin text-[38px] font-bold leading-none {{ $ink }}">{{ $event->date->day }}</div>
                <div class="mt-0.5 whitespace-nowrap text-[13px] font-bold {{ $ink }}">{{ Format::monthName($event->date->month) }} <span class="font-latin">{{ $event->date->year }}</span></div>
            </div>
            <div class="flex flex-1 flex-col items-center justify-center rounded-2xl border {{ $tile }} py-2">
                <div class="whitespace-nowrap text-[12px] font-bold {{ $soft }}">{{ __('event.time') }}</div>
                <div class="mt-1 whitespace-nowrap font-latin text-[26px] font-bold leading-none {{ $ink }}">{{ Format::time($event->start_time) }}</div>
                @if ($event->end_time)
                    <div class="mt-1.5 whitespace-nowrap text-[13px] font-bold {{ $soft }}">{{ __('calendar.until') }} <span class="font-latin">{{ Format::time($event->end_time) }}</span></div>
                @endif
            </div>
        </div>

        {{-- DJ details & groom phone --}}
        <div class="mt-2 grid w-full grid-cols-2 gap-3 text-start">
            <div class="min-w-0 rounded-xl border {{ $tile }} px-3 py-2">
                <div class="flex items-center gap-1 whitespace-nowrap text-[11px] font-bold" style="color:{{ $gold }}"><x-icon name="music" class="size-3" stroke="2.2" />{{ __('event.dj_details') }}</div>
                <div dir="auto" class="truncate text-start text-[15px] font-bold leading-snug {{ $ink }}">{{ $event->dj_name ?: '—' }}</div>
                @if ($djPhone)
                    <div class="truncate whitespace-nowrap font-latin text-[14px] font-bold {{ $soft }}" dir="ltr" style="{{ $align }}">{{ $djPhone }}</div>
                @endif
            </div>
            <div class="min-w-0 rounded-xl border {{ $tile }} px-3 py-2">
                <div class="flex items-center gap-1 whitespace-nowrap text-[11px] font-bold" style="color:{{ $gold }}"><x-icon name="phone" class="size-3" stroke="2.2" />{{ __('event.phone') }}</div>
                <div class="truncate whitespace-nowrap font-latin text-[16px] font-bold leading-snug {{ $ink }}" dir="ltr" style="{{ $align }}">{{ $event->phone ?: '—' }}</div>
            </div>
        </div>

        @if ($payments && (float) $event->total_cost > 0)
            <div class="mt-2 grid w-full grid-cols-3 overflow-hidden rounded-xl border {{ $tile }}">
                @foreach ([['event.total_cost', $event->total_cost, $ink], ['event.paid_amount', $event->paid_amount, $royal ? 'text-emerald-300' : 'text-emerald-700'], ['event.remaining', $event->remaining(), $royal ? 'text-rose-300' : 'text-rose-700']] as [$label, $amount, $color])
                    <div class="px-2 py-1.5 {{ ! $loop->first ? 'border-s '.($royal ? 'border-gold-400/20' : 'border-gold-600/15') : '' }}">
                        <div class="whitespace-nowrap text-[10.5px] font-bold" style="color:{{ $gold }}">{{ __($label) }}</div>
                        <div class="whitespace-nowrap font-latin text-[15px] font-bold {{ $color }}">{{ Format::money($amount) }}</div>
                    </div>
                @endforeach
            </div>
        @endif

        {{-- notes: always shown --}}
        <div class="mt-2 w-full rounded-xl border {{ $tile }} px-3 py-1.5 text-start">
            <div class="flex items-center gap-1 whitespace-nowrap text-[11px] font-bold" style="color:{{ $gold }}"><x-icon name="notes" class="size-3" stroke="2.2" />{{ __('event.notes') }}</div>
            <div dir="auto" class="text-start text-[13px] leading-snug {{ $event->notes ? $ink : $soft }}" style="display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;min-height:1.375em">{{ $event->notes ?: '—' }}</div>
        </div>

        {{-- footer: venue location & contact, then the greeting and the date --}}
        <div class="mt-auto w-full pt-2">
            <div class="flex items-center justify-center gap-4 whitespace-nowrap text-[12px] font-bold {{ $ink }}">
                @if ($venue['location'])
                    <span class="inline-flex min-w-0 items-center gap-1"><x-icon name="pin" class="size-3.5 shrink-0" stroke="2.2" style="color:{{ $gold }}" /><span class="truncate">{{ $venue['location'] }}</span></span>
                @endif
                @if ($venue['phone'])
                    <span class="inline-flex shrink-0 items-center gap-1"><x-icon name="phone" class="size-3.5" stroke="2.2" style="color:{{ $gold }}" /><span class="font-latin text-[13px]" dir="ltr">{{ Phone::display($venue['phone']) }}</span></span>
                @endif
            </div>
            <div class="mx-auto my-1.5 h-px w-40" style="background:linear-gradient(90deg,transparent,{{ $gold }},transparent)"></div>
            <div class="whitespace-nowrap font-display text-[15px] font-bold" style="color:{{ $gold }}">{{ __('event.card_subtitle') }}</div>
            <div class="mt-0.5 whitespace-nowrap font-latin text-[11px] font-semibold {{ $soft }}" dir="ltr">{{ now()->format('Y-m-d') }}</div>
        </div>
    </div>
</div>
