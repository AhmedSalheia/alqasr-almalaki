<?php

namespace App\Livewire;

use App\Models\Event;
use App\Support\Format;
use App\Support\Prefs;
use Carbon\CarbonImmutable;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

class Calendar extends Component
{
    #[Url(as: 'd')]
    public string $date = '';

    #[Url(as: 'v')]
    public string $view = '';

    public function mount(): void
    {
        $this->date = $this->validDate($this->date)?->toDateString() ?? today()->toDateString();
        $this->view = in_array($this->view, ['month', 'day'], true) ? $this->view : Prefs::calendarView();
    }

    public function setView(string $view): void
    {
        $this->view = $view === 'day' ? 'day' : 'month';
        Prefs::setCalendarView($this->view);
    }

    public function select(string $date): void
    {
        if ($d = $this->validDate($date)) {
            $this->date = $d->toDateString();
        }
    }

    public function openDay(string $date): void
    {
        $this->select($date);
        $this->setView('day');
    }

    public function previous(): void
    {
        $d = $this->current();
        $this->date = ($this->view === 'month' ? $d->subMonthNoOverflow()->startOfMonth() : $d->subDay())->toDateString();
    }

    public function next(): void
    {
        $d = $this->current();
        $this->date = ($this->view === 'month' ? $d->addMonthNoOverflow()->startOfMonth() : $d->addDay())->toDateString();
    }

    public function today(): void
    {
        $this->date = today()->toDateString();
    }

    public function updatedDate(): void
    {
        $this->rememberDate();
    }

    public function dehydrate(): void
    {
        $this->rememberDate();
    }

    protected function rememberDate(): void
    {
        \App\Models\AppState::put('pref.last_date', $this->date);
    }

    #[On('synced')]
    public function refreshFromSync(): void
    {
        // re-render with fresh data
    }

    protected function current(): CarbonImmutable
    {
        return $this->validDate($this->date) ?? CarbonImmutable::today();
    }

    protected function validDate(?string $value): ?CarbonImmutable
    {
        if (! $value || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        try {
            return CarbonImmutable::createFromFormat('!Y-m-d', $value);
        } catch (\Throwable) {
            return null;
        }
    }

    public function render(\App\Services\AuthSession $session)
    {
        $isAdmin = $session->isAdmin();
        $selected = $this->current();
        $weekStart = (int) config('alqasr.week_starts_on', 6);

        $monthStart = $selected->startOfMonth();
        $gridStart = $monthStart->startOfWeek($weekStart);
        $gridEnd = $selected->endOfMonth()->endOfWeek(($weekStart + 6) % 7);

        $events = Event::query()->visible()
            ->whereBetween('date', [$gridStart->toDateString(), $gridEnd->toDateString()])
            ->ordered()->get()
            ->groupBy(fn (Event $e) => $e->date->toDateString());

        $weeks = [];
        for ($day = $gridStart; $day->lte($gridEnd); $day = $day->addDay()) {
            $weeks[intdiv($gridStart->diffInDays($day), 7)][] = $day;
        }

        $weekdayHeaders = [];
        for ($i = 0; $i < 7; $i++) {
            $weekdayHeaders[] = Format::weekday($gridStart->addDays($i), short: true);
        }

        $monthEvents = $events->flatten()->filter(fn (Event $e) => $e->date->month === $selected->month);

        // A one-week strip around the selected day for the day view
        $strip = [];
        $stripStart = $selected->startOfWeek($weekStart);
        for ($i = 0; $i < 7; $i++) {
            $strip[] = $stripStart->addDays($i);
        }
        $stripCounts = Event::query()->visible()
            ->whereBetween('date', [$strip[0]->toDateString(), $strip[6]->toDateString()])
            ->selectRaw('date, count(*) as c')->groupBy('date')->pluck('c', 'date')
            ->mapWithKeys(fn ($c, $d) => [substr($d, 0, 10) => $c]);

        return view('livewire.calendar', [
            'selected' => $selected,
            'weeks' => $weeks,
            'weekdayHeaders' => $weekdayHeaders,
            'events' => $events,
            'dayEvents' => $events->get($selected->toDateString(), collect()),
            'monthCount' => $monthEvents->count(),
            // Financial totals are only shown to admins.
            'showFinance' => $isAdmin,
            'monthCollected' => $isAdmin ? $monthEvents->sum(fn (Event $e) => (float) $e->paid_amount) : 0,
            'monthRemaining' => $isAdmin ? $monthEvents->sum(fn (Event $e) => $e->remaining()) : 0,
            'strip' => $strip,
            'stripCounts' => $stripCounts,
            'todayStr' => today()->toDateString(),
        ])->title(__('app.calendar'));
    }
}
