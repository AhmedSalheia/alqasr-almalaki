<?php

namespace Tests\Feature;

use App\Models\AppState;
use App\Services\AuthFailed;
use App\Services\AuthService;
use App\Services\AuthSession;
use App\Services\Firebase\OfflineException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AuthFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        AppState::put('device_id', 'device-A');
        AppState::put('device_name', 'Test phone');
    }

    protected function fakeSignIn(array $profile): void
    {
        Http::fake([
            'identitytoolkit.googleapis.com/*' => Http::response(['idToken' => 'tok', 'refreshToken' => 'ref', 'localId' => 'u1', 'email' => 'x', 'expiresIn' => '3600']),
            self::DOCS.'/users/u1*' => Http::response($this->doc('users/u1', $profile)),
            self::DOCS.'/deviceRequests/*' => Http::response($this->doc('deviceRequests/u1_device-A', ['status' => 'pending'])),
        ]);
    }

    public function test_username_is_turned_into_an_email(): void
    {
        $this->assertSame('ahmad@alqasr-almalaky.app', app(AuthService::class)->normalizeLogin(' Ahmad '));
        $this->assertSame('a@b.com', app(AuthService::class)->normalizeLogin('A@b.com'));
    }

    public function test_first_device_is_tied_automatically(): void
    {
        $this->fakeSignIn(['name' => 'Sara', 'role' => 'staff', 'active' => true, 'devices' => []]);

        $this->assertSame('approved', app(AuthService::class)->login('sara', 'secret'));
        $this->assertTrue(app(AuthSession::class)->check());

        Http::assertSent(fn (Request $r) => $r->method() === 'PATCH'
            && str_contains($r->url(), '/users/u1?updateMask.fieldPaths=devices')
            && $r['fields']['devices']['arrayValue']['values'][0]['mapValue']['fields']['id']['stringValue'] === 'device-A');
    }

    public function test_tied_device_signs_in_directly(): void
    {
        $this->fakeSignIn(['name' => 'Sara', 'role' => 'admin', 'active' => true, 'devices' => [['id' => 'device-A', 'name' => 'x']]]);

        $this->assertSame('approved', app(AuthService::class)->login('sara', 'secret'));
        $this->assertTrue(app(AuthSession::class)->isAdmin());
        Http::assertNotSent(fn (Request $r) => $r->method() === 'PATCH');
    }

    public function test_new_device_needs_admin_approval(): void
    {
        $this->fakeSignIn(['name' => 'Sara', 'role' => 'staff', 'active' => true, 'devices' => [['id' => 'other-phone']]]);

        $this->assertSame('pending', app(AuthService::class)->login('sara', 'secret'));
        $this->assertFalse(app(AuthSession::class)->check());
        $this->assertTrue(app(AuthSession::class)->pendingDevice());

        Http::assertSent(fn (Request $r) => $r->method() === 'PATCH'
            && str_contains($r->url(), '/deviceRequests/u1_device-A')
            && $r['fields']['status']['stringValue'] === 'pending');

        $this->get('/')->assertRedirect(route('device.pending'));
    }

    public function test_disabled_account_is_rejected(): void
    {
        $this->fakeSignIn(['name' => 'Sara', 'role' => 'staff', 'active' => false, 'devices' => []]);

        try {
            app(AuthService::class)->login('sara', 'secret');
            $this->fail('expected AuthFailed');
        } catch (AuthFailed $e) {
            $this->assertSame('account_disabled', $e->reason);
        }
    }

    public function test_wrong_password(): void
    {
        Http::fake(['identitytoolkit.googleapis.com/*' => Http::response(['error' => ['code' => 400, 'message' => 'INVALID_LOGIN_CREDENTIALS']], 400)]);

        $this->expectException(AuthFailed::class);
        app(AuthService::class)->login('sara', 'nope');
    }

    public function test_offline_sign_in(): void
    {
        Http::fake(fn () => throw new ConnectionException('Could not resolve host'));

        $this->expectException(OfflineException::class);
        app(AuthService::class)->login('sara', 'secret');
    }
}
