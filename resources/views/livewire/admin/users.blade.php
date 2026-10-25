<div class="space-y-4" x-data="netPoll('load', 0)">
    <div class="flex items-end justify-between gap-3">
        <div>
            <h1 class="font-display text-[24px] font-bold gold-text">{{ __('admin.users_title') }}</h1>
            <p class="text-[13px] text-ink-soft">{{ __('admin.users_sub') }}</p>
        </div>
        <button wire:click="$toggle('creating')" class="btn-gold shrink-0 px-4 py-2.5 text-[14px]">
            <x-icon name="{{ $creating ? 'x' : 'plus' }}" class="size-4" stroke="2.4" />{{ $creating ? __('app.cancel') : __('admin.add_user') }}
        </button>
    </div>

    @if ($creating)
        <form wire:submit="create" class="panel space-y-4 p-5 animate-rise">
            <div>
                <label class="field-label">{{ __('admin.name') }}</label>
                <input type="text" wire:model="name" class="field" required>
                @error('name') <p class="field-error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="field-label">{{ __('admin.login') }}</label>
                <input type="text" wire:model="login" class="field" dir="ltr" autocapitalize="none" spellcheck="false" required>
                <p class="mt-1 text-[12px] text-ink-soft">{{ __('admin.login_hint') }}</p>
                @error('login') <p class="field-error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="field-label">{{ __('admin.password') }}</label>
                <input type="text" wire:model="password" class="field" dir="ltr" autocomplete="off" required>
                <p class="mt-1 text-[12px] text-ink-soft">{{ __('admin.password_hint') }}</p>
                @error('password') <p class="field-error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="field-label">{{ __('admin.role') }}</label>
                <div class="grid gap-2">
                    @foreach (['staff', 'admin'] as $r)
                        <label class="flex cursor-pointer items-center gap-3 rounded-2xl border px-4 py-3 text-[14px] font-bold transition {{ $role === $r ? 'border-gold-500 bg-gold-500/10 text-accent' : 'border-line text-ink-soft' }}">
                            <input type="radio" wire:model.live="role" value="{{ $r }}" class="sr-only">
                            <x-icon name="{{ $r === 'admin' ? 'crown' : 'user' }}" class="size-5" />{{ __('admin.role_'.$r) }}
                        </label>
                    @endforeach
                </div>
            </div>
            <button class="btn-gold w-full" wire:loading.attr="disabled" wire:target="create">{{ __('admin.create') }}</button>
        </form>
    @endif

    @include('livewire.admin.partials.state')

    @if ($loaded && ! $loadError)
        <div class="space-y-3">
            @foreach ($users as $u)
                @php
                    $isMe = $u['id'] === $me;
                    $active = (bool) ($u['active'] ?? false);
                    $devices = $u['devices'] ?? [];
                    $open = $expanded === $u['id'];
                @endphp
                <div wire:key="u-{{ $u['id'] }}" class="panel overflow-hidden {{ $active ? '' : 'opacity-70' }}">
                    <button type="button" wire:click="$set('expanded', {{ $open ? 'null' : "'".$u['id']."'" }})" class="flex w-full items-center gap-3 p-4 text-start">
                        <div class="grid size-12 shrink-0 place-items-center rounded-full font-display text-[20px] font-bold {{ ($u['role'] ?? '') === 'admin' ? 'gold-fill text-navy-950' : 'bg-surface-2 text-accent' }}">
                            {{ mb_substr($u['name'] ?? '?', 0, 1) }}
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2">
                                <span class="truncate text-[16px] font-bold text-ink">{{ $u['name'] ?? '—' }}</span>
                                @if ($isMe)<span class="chip bg-navy-900 text-gold-300 dark:bg-gold-500 dark:text-navy-950">{{ __('admin.you') }}</span>@endif
                            </div>
                            <div class="truncate text-[12.5px] text-ink-soft" dir="ltr" style="text-align: {{ app()->getLocale() === 'ar' ? 'right' : 'left' }}">{{ $u['email'] ?? '' }}</div>
                            <div class="mt-1.5 flex flex-wrap gap-1.5">
                                <span class="chip {{ ($u['role'] ?? '') === 'admin' ? 'bg-gold-500/15 text-gold-700 dark:text-gold-300' : 'bg-surface-2 text-ink-soft' }}">{{ ($u['role'] ?? '') === 'admin' ? __('app.role_admin') : __('app.role_staff') }}</span>
                                <span class="chip {{ $active ? 'bg-emerald-500/12 text-emerald-700 dark:text-emerald-300' : 'bg-rose-500/10 text-rose-700 dark:text-rose-300' }}">{{ $active ? __('admin.active') : __('admin.disabled') }}</span>
                                <span class="chip bg-surface-2 text-ink-soft"><x-icon name="smartphone" class="size-3" />{{ trans_choice('admin.devices_count', count($devices), ['count' => count($devices)]) }}</span>
                            </div>
                        </div>
                        <x-icon name="chevron-down" class="size-5 shrink-0 text-ink-soft transition {{ $open ? 'rotate-180' : '' }}" />
                    </button>

                    @if ($open)
                        <div class="space-y-3 border-t border-line p-4 animate-rise">
                            @foreach ($devices as $d)
                                @php $did = is_array($d) ? ($d['id'] ?? '') : $d; @endphp
                                <div class="flex items-center gap-3 rounded-2xl bg-surface-2 px-3 py-2.5" wire:key="d-{{ $u['id'] }}-{{ $did }}">
                                    <x-icon name="smartphone" class="size-5 shrink-0 text-accent" />
                                    <div class="min-w-0 flex-1">
                                        <div class="truncate text-[14px] font-bold text-ink">{{ is_array($d) ? ($d['name'] ?? $did) : $did }}</div>
                                        <div class="truncate font-latin text-[11px] text-ink-soft" dir="ltr">{{ $did }}</div>
                                    </div>
                                    <button type="button" wire:click="removeDevice('{{ $u['id'] }}', '{{ $did }}')" wire:confirm="{{ __('admin.remove_device_confirm') }}" class="chip border border-rose-500/30 text-rose-700 dark:text-rose-300">{{ __('admin.remove_device') }}</button>
                                </div>
                            @endforeach

                            @unless ($isMe)
                                <div class="grid grid-cols-2 gap-2">
                                    <button type="button" wire:click="toggleActive('{{ $u['id'] }}')" class="{{ $active ? 'btn-danger' : 'btn-outline' }} py-2.5 text-[14px]">{{ $active ? __('admin.disable') : __('admin.enable') }}</button>
                                    <button type="button" wire:click="toggleRole('{{ $u['id'] }}')" class="btn-ghost border border-line py-2.5 text-[14px]">{{ ($u['role'] ?? '') === 'admin' ? __('admin.make_staff') : __('admin.make_admin') }}</button>
                                </div>
                            @endunless
                            @if (! str_ends_with($u['email'] ?? '', '@'.config('alqasr.username_domain')))
                                <button type="button" wire:click="sendReset('{{ $u['email'] ?? '' }}')" class="btn-ghost w-full border border-line py-2.5 text-[14px]"><x-icon name="mail" class="size-4" />{{ __('admin.send_reset') }}</button>
                            @endif
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
</div>
