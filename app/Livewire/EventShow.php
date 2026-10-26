<?php

namespace App\Livewire;

use App\Models\Event;
use App\Services\AuthSession;
use App\Services\SyncScheduler;
use App\Support\Native;
use Livewire\Attributes\On;
use Livewire\Component;

class EventShow extends Component
{
    public Event $event;

    public string $cardStyle = 'ivory';

    public bool $showPayments = true;

    public bool $confirmingDelete = false;

    public function mount(Event $event): void
    {
        abort_if($event->deleted, 404);
        $this->event = $event;
        $this->cardStyle = (string) \App\Models\AppState::get('pref.card_style', 'ivory');
        $this->showPayments = (bool) \App\Models\AppState::get('pref.card_payments', true);
    }

    public function updatedCardStyle(string $value): void
    {
        $this->cardStyle = $value === 'royal' ? 'royal' : 'ivory';
        \App\Models\AppState::put('pref.card_style', $this->cardStyle);
    }

    public function updatedShowPayments(bool $value): void
    {
        \App\Models\AppState::put('pref.card_payments', $value);
    }

    #[On('synced')]
    public function reload()
    {
        $fresh = Event::query()->find($this->event->id);
        if (! $fresh || $fresh->deleted) {
            session()->flash('toast', __('event.deleted'));

            return $this->redirectRoute('calendar', navigate: true);
        }
        $this->event = $fresh;
    }

    public function delete(AuthSession $session, SyncScheduler $scheduler)
    {
        $this->event->fill([
            'deleted' => true,
            'sync_status' => Event::PENDING,
            'updated_by_name' => $session->name(),
        ])->save();

        $scheduler->request();
        session()->flash('toast', __('event.deleted'));

        return $this->redirectRoute('calendar', ['d' => $this->event->date->toDateString()], navigate: true);
    }

    public function render()
    {
        $digits = preg_replace('/\D+/', '', (string) $this->event->phone);

        return view('livewire.event-show', [
            'isNative' => Native::isNative(),
            'phoneDigits' => $digits,
            'whatsapp' => Event::whatsappNumber($this->event->phone),
            'djDigits' => (str_starts_with(trim((string) $this->event->dj_phone), '+') ? '+' : '').preg_replace('/\D+/', '', (string) $this->event->dj_phone),
            'djWhatsapp' => Event::whatsappNumber($this->event->dj_phone),
            'fileName' => 'alqasr-'.$this->event->date->format('Y-m-d').'-'.(\Illuminate\Support\Str::slug($this->event->groom_name) ?: 'event').'.png',
        ])->layout('components.layouts.app', [
            'back' => route('calendar', ['d' => $this->event->date->toDateString()]),
        ])->title(__('event.details'));
    }
}
