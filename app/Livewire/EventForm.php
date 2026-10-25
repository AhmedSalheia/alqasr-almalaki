<?php

namespace App\Livewire;

use App\Models\AppState;
use App\Models\Event;
use App\Services\AuthSession;
use App\Services\SyncScheduler;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Url;
use Livewire\Component;

class EventForm extends Component
{
    public ?Event $event = null;

    #[Url(as: 'date', history: false, keep: false)]
    public string $prefillDate = '';

    public string $groom_name = '';

    public string $date = '';

    public string $start_time = '20:00';

    public string $end_time = '';

    /** false = the venue DJ (config alqasr.venue_dj), true = an external DJ typed in by hand. */
    public bool $dj_external = false;

    public string $dj_name = '';

    public string $dj_phone = '';

    public string $phone = '';

    public string $total_cost = '';

    public string $paid_amount = '';

    public string $notes = '';

    public function mount(?Event $event = null): void
    {
        if ($event && $event->exists) {
            abort_if($event->deleted, 404);
            $this->event = $event;
            $this->groom_name = $event->groom_name;
            $this->date = $event->date->toDateString();
            $this->start_time = $event->start_time;
            $this->end_time = (string) $event->end_time;
            $this->dj_external = static::isExternalDj($event->dj_name, $event->dj_phone);
            $this->dj_name = $this->dj_external ? (string) $event->dj_name : '';
            $this->dj_phone = $this->dj_external ? (string) $event->dj_phone : '';
            $this->phone = (string) $event->phone;
            $this->total_cost = $this->plain($event->total_cost);
            $this->paid_amount = $this->plain($event->paid_amount);
            $this->notes = (string) $event->notes;

            return;
        }

        $this->event = null;
        $candidate = $this->prefillDate ?: (string) AppState::get('pref.last_date', '');
        $this->date = preg_match('/^\d{4}-\d{2}-\d{2}$/', $candidate) ? $candidate : today()->toDateString();
    }

    /** An event uses an external DJ when it has a DJ saved that isn't the venue DJ. */
    public static function isExternalDj(?string $name, ?string $phone): bool
    {
        $name = trim((string) $name);
        if ($name === '') {
            return false;
        }
        $venue = config('alqasr.venue_dj');
        $digits = fn ($v) => preg_replace('/\D+/', '', (string) $v);

        return $name !== trim((string) $venue['name'])
            || ($digits($phone) !== '' && $digits($phone) !== $digits($venue['phone']));
    }

    public function useVenueDj(): void
    {
        $this->dj_external = false;
        $this->resetErrorBag(['dj_name', 'dj_phone']);
    }

    public function useExternalDj(): void
    {
        $this->dj_external = true;
    }

    protected function plain($amount): string
    {
        $f = (float) $amount;

        return $f == 0.0 ? '' : rtrim(rtrim(number_format($f, 2, '.', ''), '0'), '.');
    }

    protected function rules(): array
    {
        return [
            'groom_name' => ['required', 'string', 'max:120'],
            'date' => ['required', 'date_format:Y-m-d'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i'],
            'dj_name' => [Rule::requiredIf($this->dj_external), 'nullable', 'string', 'max:120'],
            'dj_phone' => [Rule::requiredIf($this->dj_external), 'nullable', 'string', 'max:30', 'regex:/^[0-9+\-\s()]{6,30}$/'],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+\-\s()]{6,30}$/'],
            'total_cost' => ['nullable', 'numeric', 'min:0', 'max:100000000'],
            'paid_amount' => ['nullable', 'numeric', 'min:0', Rule::when(is_numeric($this->total_cost), ['lte:total_cost'])],
            'notes' => ['nullable', 'string', 'max:3000'],
        ];
    }

    protected function messages(): array
    {
        return ['paid_amount.lte' => __('event.paid_more_than_total')];
    }

    protected function validationAttributes(): array
    {
        return collect(array_keys($this->rules()))->mapWithKeys(fn ($k) => [$k => __('event.'.$k)])->all();
    }

    /** Normalise Arabic-Indic digits typed on Arabic keyboards. */
    protected function normaliseDigits(string $v): string
    {
        return strtr($v, [
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٫' => '.', ',' => '',
        ]);
    }

    public function save(AuthSession $session, SyncScheduler $scheduler)
    {
        foreach (['phone', 'dj_phone', 'total_cost', 'paid_amount'] as $f) {
            $this->{$f} = trim($this->normaliseDigits($this->{$f}));
        }
        $this->groom_name = trim($this->groom_name);

        $data = $this->validate();
        $data['total_cost'] = (float) ($data['total_cost'] ?: 0);
        $data['paid_amount'] = (float) ($data['paid_amount'] ?: 0);
        $data['end_time'] = $data['end_time'] ?: null;
        foreach (['dj_name', 'dj_phone', 'phone', 'notes'] as $f) {
            $data[$f] = filled($data[$f]) ? trim($data[$f]) : null;
        }
        if (! $this->dj_external) {
            $data['dj_name'] = config('alqasr.venue_dj.name');
            $data['dj_phone'] = config('alqasr.venue_dj.phone');
        }
        $data['sync_status'] = Event::PENDING;
        $data['updated_by_name'] = $session->name();

        if ($this->event) {
            $this->event->fill($data)->save();
            $event = $this->event;
            $message = __('event.updated');
        } else {
            $event = Event::create($data + [
                'created_by' => $session->uid(),
                'created_by_name' => $session->name(),
            ]);
            $message = __('event.created');
        }

        $scheduler->request();

        session()->flash('toast', $message);

        return $this->redirectRoute('events.show', $event, navigate: true);
    }

    public function render()
    {
        $sameDay = 0;
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->date)) {
            $sameDay = Event::query()->visible()->where('date', $this->date)
                ->when($this->event, fn ($q) => $q->whereKeyNot($this->event->id))->count();
        }

        $title = $this->event ? __('event.edit') : __('event.new');

        return view('livewire.event-form', ['sameDay' => $sameDay, 'heading' => $title])
            ->layout('components.layouts.app', [
                'back' => $this->event ? route('events.show', $this->event) : route('calendar'),
            ])
            ->title($title);
    }
}
