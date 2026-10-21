<?php

namespace Tests\Feature;

use App\Models\AppState;
use App\Models\Event;
use App\Services\Reminders\ReminderGateway;
use App\Services\Reminders\ReminderService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FakeReminderGateway implements ReminderGateway
{
    public array $scheduled = [];

    public array $cancelled = [];

    public bool $cancelledAll = false;

    public function schedule(string $id, string $title, string $body, int $at): bool
    {
        $this->scheduled[$id] = compact('title', 'body', 'at');

        return true;
    }

    public function cancel(string $id): bool
    {
        $this->cancelled[] = $id;

        return true;
    }

    public function cancelAll(): bool
    {
        $this->cancelledAll = true;

        return true;
    }

    public function requestPermission(): bool
    {
        return true;
    }
}

class RemindersTest extends TestCase
{
    use RefreshDatabase;

    protected FakeReminderGateway $gateway;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-21 09:00:00');
        $this->gateway = new FakeReminderGateway;
        $this->app->instance(ReminderGateway::class, $this->gateway);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    protected function event(string $date, string $by, array $extra = []): Event
    {
        return Event::create(array_merge([
            'groom_name' => 'Groom '.$date, 'date' => $date, 'start_time' => '20:00',
            'dj_name' => 'DJ Sami', 'dj_phone' => '0591112222', 'created_by' => $by,
        ], $extra));
    }

    public function test_admin_is_reminded_of_every_reservation_three_days_before_at_ten(): void
    {
        $this->signIn('admin', 'admin-1');
        $mine = $this->event('2026-11-10', 'admin-1');
        $other = $this->event('2026-11-12', 'staff-7');

        $result = app(ReminderService::class)->refresh();

        $this->assertSame(2, $result['scheduled']);
        $this->assertSame(Carbon::parse('2026-11-07 10:00:00')->getTimestamp(), $this->gateway->scheduled[$mine->id]['at']);
        $this->assertArrayHasKey($other->id, $this->gateway->scheduled);
        $this->assertStringContainsString('Groom 2026-11-10', $this->gateway->scheduled[$mine->id]['title']);
        $this->assertStringContainsString('0591112222', $this->gateway->scheduled[$mine->id]['body']);
    }

    public function test_staff_is_only_reminded_of_their_own_reservations(): void
    {
        $this->signIn('staff', 'staff-7');
        $mine = $this->event('2026-11-12', 'staff-7');
        $this->event('2026-11-10', 'admin-1');

        app(ReminderService::class)->refresh();

        $this->assertSame([$mine->id], array_keys($this->gateway->scheduled));
    }

    public function test_too_close_past_and_deleted_reservations_are_skipped(): void
    {
        $this->signIn('admin', 'admin-1');
        $this->event('2026-10-23', 'admin-1');                   // in 2 days: reminder time already passed
        $this->event('2026-10-01', 'admin-1');                   // past
        $this->event('2026-11-20', 'admin-1', ['deleted' => true]);

        app(ReminderService::class)->refresh();

        $this->assertSame([], $this->gateway->scheduled);
    }

    public function test_only_changes_are_sent_and_removed_reservations_are_cancelled(): void
    {
        $this->signIn('admin', 'admin-1');
        $a = $this->event('2026-11-10', 'admin-1');
        $b = $this->event('2026-11-12', 'admin-1');
        $service = app(ReminderService::class);

        $service->refresh();
        $this->gateway->scheduled = [];

        // nothing changed -> nothing re-sent
        $this->assertSame(0, $service->refresh()['scheduled']);

        // date moved -> rescheduled; deleted -> cancelled
        $a->update(['date' => '2026-11-15']);
        $b->update(['deleted' => true]);
        $service->refresh();

        $this->assertSame([$a->id], array_keys($this->gateway->scheduled));
        $this->assertSame([$b->id], $this->gateway->cancelled);
    }

    public function test_everything_is_cancelled_on_sign_out_or_when_disabled(): void
    {
        $this->signIn('admin', 'admin-1');
        $this->event('2026-11-10', 'admin-1');
        $service = app(ReminderService::class);
        $service->refresh();

        ReminderService::setEnabled(false);
        $service->refresh();
        $this->assertTrue($this->gateway->cancelledAll);
        $this->assertSame([], AppState::get(ReminderService::STATE));
    }
}
