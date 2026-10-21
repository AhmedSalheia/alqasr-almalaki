<?php

namespace App\Services\Reminders;

use App\Models\AppState;
use App\Models\Event;
use App\Services\AuthSession;
use App\Support\Format;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * Keeps this phone's scheduled reminder notifications in line with the local reservations.
 *
 * Who is reminded: the person who added the reservation, and every admin.
 * When: `days_before` days before the reservation, at `hour` o'clock (venue time).
 *
 * Each phone schedules its own notifications from its synced copy of the calendar,
 * so reminders also fire when the phone is offline. Only changes are sent to Android.
 */
class ReminderService
{
    public const STATE = 'reminders.scheduled';

    public function __construct(
        protected ReminderGateway $gateway,
        protected AuthSession $session,
    ) {}

    public static function enabled(): bool
    {
        return (bool) AppState::get('pref.reminders', true);
    }

    public static function setEnabled(bool $on): void
    {
        AppState::put('pref.reminders', $on);
    }

    /** Ask once for Android's notification permission. */
    public function askPermissionOnce(): void
    {
        if (AppState::get('reminders.permission_asked') || ! static::enabled()) {
            return;
        }
        AppState::put('reminders.permission_asked', true);

        try {
            $this->gateway->requestPermission();
        } catch (Throwable $e) {
            report($e);
        }
    }

    public function requestPermission(): bool
    {
        try {
            return $this->gateway->requestPermission();
        } catch (Throwable $e) {
            report($e);

            return false;
        }
    }

    /**
     * @return array{scheduled:int, cancelled:int, total:int}
     */
    public function refresh(): array
    {
        $current = AppState::get(self::STATE, []);
        $wanted = ($this->session->check() && static::enabled()) ? $this->wanted() : [];

        $scheduled = $cancelled = 0;
        $kept = [];

        try {
            if ($wanted === [] && $current !== []) {
                $this->gateway->cancelAll();
                $cancelled = count($current);
            } else {
                foreach ($current as $id => $sig) {
                    if (! isset($wanted[$id])) {
                        $this->gateway->cancel($id);
                        $cancelled++;
                    }
                }
                foreach ($wanted as $id => $r) {
                    if (($current[$id] ?? null) === $r['sig']) {
                        $kept[$id] = $r['sig'];

                        continue;
                    }
                    if ($this->gateway->schedule($id, $r['title'], $r['body'], $r['at'])) {
                        $kept[$id] = $r['sig'];
                        $scheduled++;
                    }
                }
            }
        } catch (Throwable $e) {
            report($e);
        }

        AppState::put(self::STATE, $kept);

        return ['scheduled' => $scheduled, 'cancelled' => $cancelled, 'total' => count($kept)];
    }

    public function clear(): void
    {
        try {
            $this->gateway->cancelAll();
        } catch (Throwable $e) {
            report($e);
        }
        AppState::forget(self::STATE);
    }

    /**
     * Reminders this phone should currently have.
     *
     * @return array<string, array{at:int, title:string, body:string, sig:string}>
     */
    public function wanted(): array
    {
        $uid = $this->session->uid();
        $isAdmin = $this->session->isAdmin();
        $days = max(0, (int) config('alqasr.reminders.days_before', 3));
        $hour = min(23, max(0, (int) config('alqasr.reminders.hour', 10)));
        $now = CarbonImmutable::now();

        $events = Event::query()->visible()
            ->where('date', '>', $now->toDateString())
            ->where('date', '<=', $now->addDays((int) config('alqasr.reminders.horizon_days', 120))->toDateString())
            ->when(! $isAdmin, fn ($q) => $q->where('created_by', $uid))
            ->ordered()->get();

        $out = [];
        foreach ($events as $event) {
            $at = CarbonImmutable::createFromFormat('!Y-m-d', $event->date->toDateString())
                ->subDays($days)->setTime($hour, 0);

            if ($at->lte($now)) {
                continue; // booked less than N days ahead: nothing to remind early
            }

            $title = __('event.reminder_title', ['days' => $days, 'name' => $event->groom_name]);
            $parts = [Format::longDate($event->date).' · '.strip_tags(Format::timeRange($event->start_time, $event->end_time))];
            if ($event->dj_name) {
                $parts[] = __('event.dj_name').': '.$event->dj_name.($event->dj_phone ? ' ('.$event->dj_phone.')' : '');
            }
            if ($event->phone) {
                $parts[] = __('event.phone').': '.$event->phone;
            }
            $parts[] = __('event.reminder_hint');
            $body = implode("\n", $parts);

            $out[$event->id] = [
                'at' => $at->getTimestamp(),
                'title' => $title,
                'body' => $body,
                'sig' => md5($at->getTimestamp().'|'.$title.'|'.$body),
            ];
        }

        return $out;
    }
}
