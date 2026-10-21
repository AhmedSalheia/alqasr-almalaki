{{-- The shareable event card. Fixed 540 x 675 CSS px -> exported at 2x = 1080 x 1350 PNG (4:5). --}}
@props(['event', 'style' => 'ivory', 'payments' => true])
@php
    use App\Support\Format;
    $royal = $style === 'royal';
    $len = mb_strlen($event->groom_name);
    $nameSize = $len > 26 ? 'text-[30px]' : ($len > 18 ? 'text-[36px]' : 'text-[42px]');
    $gold = '#e09a15';
    $ink = $royal ? 'text-ivory-50' : 'text-navy-900';
    $soft = $royal ? 'text-ivory-200/75' : 'text-navy-700/75';
    $tile = $royal ? 'bg-white/5 border-gold-400/25' : 'bg-white/70 border-gold-600/20';
@endphp
<div {{ $attributes }} dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}" lang="{{ app()->getLocale() }}"
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

    <div class="relative flex h-full flex-col items-center px-[46px] pt-[34px] pb-[30px] text-center">
        <img src="{{ asset($royal ? 'images/emblem-dark.png' : 'images/emblem.png') }}" alt="" class="h-[78px] w-auto">
        <div class="mt-0.5 pb-1 font-display text-[22px] font-bold leading-snug gold-text">القصر الملكي</div>
        <div class="font-latin text-[10.5px] font-bold tracking-[0.42em] {{ $soft }}" dir="ltr">AL QASR AL MALAKI</div>

        <div class="mt-3 flex w-full items-center gap-3">
            <div class="h-px flex-1" style="background:linear-gradient(90deg,transparent,{{ $gold }})"></div>
            <svg class="size-3" viewBox="0 0 12 12"><path d="M6 0l2 4 4 2-4 2-2 4-2-4-4-2 4-2z" fill="{{ $gold }}"/></svg>
            <div class="h-px flex-1" style="background:linear-gradient(90deg,{{ $gold }},transparent)"></div>
        </div>

        <div class="mt-3 text-[13px] font-bold tracking-wide" style="color:{{ $gold }}">{{ __('event.card_title') }}</div>
        <div class="mt-1 w-full font-display font-bold leading-tight {{ $nameSize }} {{ $royal ? 'gold-text' : 'text-navy-900' }}" style="overflow-wrap:anywhere">{{ $event->groom_name }}</div>

        {{-- date & time --}}
        <div class="mt-4 flex w-full items-stretch justify-center gap-3">
            <div class="flex flex-1 flex-col items-center justify-center rounded-2xl border {{ $tile }} py-2.5">
                <div class="text-[12px] font-bold {{ $soft }}">{{ Format::weekday($event->date) }}</div>
                <div class="font-latin text-[40px] font-bold leading-none {{ $ink }}">{{ $event->date->day }}</div>
                <div class="text-[13px] font-bold {{ $ink }}">{{ Format::monthName($event->date->month) }} <span class="font-latin">{{ $event->date->year }}</span></div>
            </div>
            <div class="flex flex-1 flex-col items-center justify-center rounded-2xl border {{ $tile }} py-2.5">
                <div class="text-[12px] font-bold {{ $soft }}">{{ __('event.time') }}</div>
                <div class="mt-1 font-latin text-[24px] font-bold leading-none {{ $ink }}">{{ Format::time($event->start_time) }}</div>
                @if ($event->end_time)
                    <div class="mt-1 font-latin text-[14px] font-semibold {{ $soft }}">{{ __('calendar.until') }} {{ Format::time($event->end_time) }}</div>
                @endif
            </div>
        </div>

        {{-- details --}}
        <div class="mt-3 grid w-full grid-cols-2 gap-x-3 gap-y-2 text-start">
            @if ($event->dj_name)
                <div class="rounded-xl border {{ $tile }} px-3 py-2">
                    <div class="text-[11px] font-bold" style="color:{{ $gold }}">{{ __('event.dj_name') }}</div>
                    <div class="truncate text-[15px] font-bold {{ $ink }}">{{ $event->dj_name }}</div>
                </div>
            @endif
            @if ($event->phone)
                <div class="rounded-xl border {{ $tile }} px-3 py-2 {{ $event->dj_name ? '' : 'col-span-2' }}">
                    <div class="text-[11px] font-bold" style="color:{{ $gold }}">{{ __('event.phone') }}</div>
                    <div class="truncate font-latin text-[16px] font-bold {{ $ink }}" dir="ltr" style="text-align:{{ app()->getLocale() === 'ar' ? 'right' : 'left' }}">{{ $event->phone }}</div>
                </div>
            @endif
        </div>

        @if ($payments && (float) $event->total_cost > 0)
            <div class="mt-2 grid w-full grid-cols-3 overflow-hidden rounded-xl border {{ $tile }}">
                @foreach ([['event.total_cost', $event->total_cost, $ink], ['event.paid_amount', $event->paid_amount, $royal ? 'text-emerald-300' : 'text-emerald-700'], ['event.remaining', $event->remaining(), $royal ? 'text-rose-300' : 'text-rose-700']] as [$label, $amount, $color])
                    <div class="px-2 py-2 {{ ! $loop->first ? 'border-s '.($royal ? 'border-gold-400/20' : 'border-gold-600/15') : '' }}">
                        <div class="text-[10.5px] font-bold" style="color:{{ $gold }}">{{ __($label) }}</div>
                        <div class="font-latin text-[15px] font-bold {{ $color }}">{{ Format::money($amount) }}</div>
                    </div>
                @endforeach
            </div>
        @endif

        @if ($event->notes)
            <div class="mt-2 w-full rounded-xl border {{ $tile }} px-3 py-2 text-start">
                <div class="text-[11px] font-bold" style="color:{{ $gold }}">{{ __('event.notes') }}</div>
                <div class="text-[13px] leading-snug {{ $ink }}" style="display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden">{{ $event->notes }}</div>
            </div>
        @endif

        <div class="mt-auto w-full pt-2">
            <div class="font-display text-[15px] font-bold" style="color:{{ $gold }}">{{ __('event.card_subtitle') }}</div>
            <div class="mt-1 flex items-center justify-center gap-2 font-latin text-[10.5px] font-semibold {{ $soft }}" dir="ltr">
                {{-- <span><bdi>{{ __('event.card_ref') }}</bdi> <bdi>{{ strtoupper(substr(str_replace('-', '', $event->id), 0, 8)) }}</bdi></span> --}}
                {{-- <span>•</span> --}}
                <span>{{ now()->format('Y-m-d') }}</span>
            </div>
        </div>
    </div>
</div>
