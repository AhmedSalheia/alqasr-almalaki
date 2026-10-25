<div x-data="netPoll('tick', 60)">
    @if ($info)
        @php
            $state = $status['state'] ?? 'idle';
            $progress = (int) ($status['progress'] ?? 0);
            $sizeMb = $info['size'] ? round($info['size'] / 1048576) : null;
        @endphp
        <div class="panel mb-4 overflow-hidden animate-rise"
             @if ($state === 'downloading') wire:poll.2s @endif>
            <div class="flex items-start gap-3 p-4">
                <span class="grid size-11 shrink-0 place-items-center rounded-2xl gold-fill text-navy-950"><x-icon name="download" class="size-5" stroke="2.2" /></span>
                <div class="min-w-0 flex-1">
                    <div class="font-display text-[16px] font-bold text-ink">{{ __('app.update_available', ['version' => $info['version']]) }}</div>
                    <div class="text-[12.5px] text-ink-soft">{{ __('app.update_current', ['version' => $current]) }}</div>

                    @if ($state === 'downloading')
                        <div class="mt-2.5 h-2 overflow-hidden rounded-full bg-surface-2">
                            <div class="h-full rounded-full gold-fill transition-all" style="width: {{ max(3, $progress) }}%"></div>
                        </div>
                        <div class="mt-1 text-[12px] font-bold text-accent">{{ __('app.update_downloading', ['progress' => $progress]) }}</div>
                    @elseif ($state === 'error')
                        <div class="mt-1 text-[12px] text-rose-600 dark:text-rose-300">{{ __('app.update_failed', ['error' => $status['error'] ?? '']) }}</div>
                    @endif

                    <div class="mt-3 flex flex-wrap gap-2">
                        @if (! $native)
                            <a href="{{ $info['page_url'] }}" class="btn-gold px-4 py-2 text-[13px]">{{ __('app.update_open_github') }}</a>
                        @elseif ($state === 'done')
                            <button wire:click="install" class="btn-gold px-4 py-2 text-[13px]"><x-icon name="check" class="size-4" stroke="2.6" />{{ __('app.update_install') }}</button>
                        @elseif ($state !== 'downloading')
                            <button wire:click="download" class="btn-gold px-4 py-2 text-[13px]">
                                <x-icon name="download" class="size-4" />{{ $state === 'error' ? __('app.try_again') : __('app.update_download') }}@if ($sizeMb) <span class="font-latin opacity-80">({{ $sizeMb }} MB)</span>@endif
                            </button>
                        @endif
                        @if ($state !== 'downloading')
                            <button wire:click="dismiss" class="btn-ghost px-3 py-2 text-[13px]">{{ __('app.update_later') }}</button>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
