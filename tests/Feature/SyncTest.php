<?php

namespace Tests\Feature;

use App\Models\AppState;
use App\Models\Event;
use App\Services\AuthSession;
use App\Services\SyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->signIn();
    }

    protected function profile(array $devices = [['id' => 'device-A']], bool $active = true): array
    {
        return $this->doc('users/u1', ['name' => 'Admin', 'role' => 'admin', 'active' => $active, 'devices' => $devices]);
    }

    public function test_pending_changes_are_pushed_and_remote_changes_pulled(): void
    {
        $local = Event::create(['groom_name' => 'محلي', 'date' => '2026-11-05', 'start_time' => '20:00', 'total_cost' => 5000, 'paid_amount' => 1000]);
        $keep = Event::create(['groom_name' => 'Edited offline', 'date' => '2026-11-06', 'start_time' => '19:00']);

        Http::fake([
            self::DOCS.'/users/u1' => Http::response($this->profile()),
            self::DOCS.':commit' => Http::response(['writeResults' => [
                ['updateTime' => '2026-10-20T10:00:00.1Z', 'transformResults' => [['timestampValue' => '2026-10-20T10:00:00.1Z']]],
                ['updateTime' => '2026-10-20T10:00:00.1Z', 'transformResults' => [['timestampValue' => '2026-10-20T10:00:00.1Z']]],
            ]]),
            self::DOCS.':runQuery' => Http::response([
                ['document' => $this->doc('events/remote-1', [
                    'groomName' => 'عن بعد', 'date' => '2026-11-07', 'startTime' => '21:00', 'endTime' => null,
                    'djName' => 'DJ X', 'djPhone' => '0598887777', 'phone' => '0599', 'totalCost' => 9000.0, 'paidAmount' => 9000.0,
                    'notes' => '', 'deleted' => false, 'updatedAt' => new \DateTimeImmutable('2026-10-20T09:00:00Z'),
                ])],
            ]),
        ]);

        $result = app(SyncService::class)->sync();

        $this->assertSame('ok', $result['status']);
        $this->assertSame(2, $result['pushed']);
        $this->assertSame(1, $result['pulled']);
        $this->assertSame(Event::SYNCED, $local->fresh()->sync_status);
        $this->assertSame('2026-10-20T10:00:00.100000000Z', $local->fresh()->remote_updated_at);

        $remote = Event::find('remote-1');
        $this->assertSame('عن بعد', $remote->groom_name);
        $this->assertTrue($remote->isPaidInFull());
        $this->assertSame('0598887777', $remote->dj_phone);

        Http::assertSent(function (Request $r) use ($local) {
            if (! str_ends_with($r->url(), ':commit')) {
                return false;
            }
            $w = $r['writes'][0];

            return str_ends_with($w['update']['name'], 'events/'.$local->id)
                && $w['update']['fields']['groomName']['stringValue'] === 'محلي'
                && $w['update']['fields']['totalCost']['doubleValue'] == 5000
                && $w['updateTransforms'][0]['setToServerValue'] === 'REQUEST_TIME';
        });

        $this->assertNotNull(AppState::get('sync.cursor'));
        $this->assertNotNull($keep);
    }

    public function test_local_unsynced_edit_is_not_overwritten_by_pull(): void
    {
        $e = Event::create(['groom_name' => 'Mine', 'date' => '2026-11-05', 'start_time' => '20:00']);
        $e->forceFill(['sync_status' => Event::PENDING])->save();

        Http::fake([
            self::DOCS.'/users/u1' => Http::response($this->profile()),
            self::DOCS.':commit' => Http::response(['error' => ['status' => 'UNAVAILABLE', 'message' => 'try later']], 503),
        ]);

        $result = app(SyncService::class)->sync();
        $this->assertSame('error', $result['status']);
        $this->assertSame(Event::PENDING, $e->fresh()->sync_status);
    }

    public function test_remote_delete_is_applied(): void
    {
        $e = Event::create(['id' => 'ev-9', 'groom_name' => 'Old', 'date' => '2026-11-05', 'start_time' => '20:00', 'sync_status' => Event::SYNCED]);

        Http::fake([
            self::DOCS.'/users/u1' => Http::response($this->profile()),
            self::DOCS.':runQuery' => Http::response([
                ['document' => $this->doc('events/ev-9', ['groomName' => 'Old', 'date' => '2026-11-05', 'startTime' => '20:00', 'deleted' => true, 'updatedAt' => new \DateTimeImmutable('2026-10-20T09:00:00Z')])],
            ]),
        ]);

        app(SyncService::class)->sync();
        $this->assertTrue($e->fresh()->deleted);
        $this->assertSame(0, Event::visible()->count());
    }

    public function test_offline_keeps_everything_local(): void
    {
        Event::create(['groom_name' => 'Offline', 'date' => '2026-11-05', 'start_time' => '20:00']);
        Http::fake(fn () => throw new ConnectionException('no network'));

        $result = app(SyncService::class)->sync();

        $this->assertSame('offline', $result['status']);
        $this->assertFalse(AppState::get('sync.online'));
        $this->assertSame(1, app(SyncService::class)->pendingCount());
        $this->assertTrue(app(AuthSession::class)->check());
    }

    public function test_after_a_connection_failure_calls_fail_fast_for_a_while(): void
    {
        $calls = 0;
        Http::fake(function () use (&$calls) {
            $calls++;
            throw new ConnectionException('no network');
        });

        $this->assertSame('offline', app(SyncService::class)->sync()['status']);
        $this->assertSame('offline', app(SyncService::class)->sync()['status']);
        $this->assertSame(1, $calls, 'second sync must not touch the network');

        \App\Services\Firebase\FirebaseClient::markOnline();
        app(SyncService::class)->sync();
        $this->assertSame(2, $calls);
    }

    public function test_offline_tick_skips_the_network(): void
    {
        Http::fake(fn () => throw new \RuntimeException('network must not be used'));

        \Livewire\Livewire::test(\App\Livewire\SyncStatus::class)->call('tick', false)->assertOk();
        $this->assertFalse(AppState::get('sync.online'));
        $this->assertTrue(\App\Services\Firebase\FirebaseClient::recentlyOffline());
    }

    public function test_admin_pages_do_not_call_firebase_while_offline(): void
    {
        Http::fake(fn () => throw new \RuntimeException('network must not be used'));

        \Livewire\Livewire::test(\App\Livewire\Admin\Users::class)->call('load', false)
            ->assertSet('loaded', true)->assertSee(__('app.requires_online'));
        \Livewire\Livewire::test(\App\Livewire\Admin\DeviceRequests::class)->call('load', false)
            ->assertSee(__('app.requires_online'));
    }

    public function test_untied_device_is_signed_out_and_local_data_wiped(): void
    {
        Event::create(['groom_name' => 'Secret', 'date' => '2026-11-05', 'start_time' => '20:00', 'sync_status' => Event::SYNCED]);
        Http::fake([self::DOCS.'/users/u1' => Http::response($this->profile([['id' => 'some-other-phone']]))]);

        $result = app(SyncService::class)->sync();

        $this->assertSame('revoked', $result['status']);
        $this->assertFalse(app(AuthSession::class)->check());
        $this->assertSame(0, Event::count());
    }

    public function test_expired_token_is_refreshed(): void
    {
        $auth = AppState::get('auth');
        $auth['expires_at'] = time() - 10;
        AppState::put('auth', $auth);

        Http::fake([
            'securetoken.googleapis.com/*' => Http::response(['id_token' => 'fresh', 'refresh_token' => 'r2', 'expires_in' => '3600', 'user_id' => 'u1']),
            self::DOCS.'/users/u1' => Http::response($this->profile()),
            self::DOCS.':runQuery' => Http::response([['readTime' => '2026-10-20T10:00:00Z']]),
        ]);

        $this->assertSame('ok', app(SyncService::class)->sync()['status']);
        Http::assertSent(fn (Request $r) => str_contains($r->url(), ':runQuery') && $r->header('Authorization')[0] === 'Bearer fresh');
    }
}
