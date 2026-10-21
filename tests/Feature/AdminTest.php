<?php

namespace Tests\Feature;

use App\Services\UserDirectory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_creates_a_user(): void
    {
        $this->signIn();
        Http::fake([
            'identitytoolkit.googleapis.com/*' => Http::response(['localId' => 'new-uid']),
            self::DOCS.'/users/new-uid' => Http::response($this->doc('users/new-uid', ['name' => 'Sara'])),
        ]);

        $this->assertSame('new-uid', app(UserDirectory::class)->create('Sara', 'sara', 'secret1', 'staff'));

        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'accounts:signUp') && $r['email'] === 'sara@alqasr-almalaky.app');
        Http::assertSent(fn (Request $r) => $r->method() === 'PATCH' && str_ends_with($r->url(), '/users/new-uid')
            && $r['fields']['role']['stringValue'] === 'staff' && $r['fields']['active']['booleanValue'] === true);
    }

    public function test_admin_approves_a_device(): void
    {
        $this->signIn();
        Http::fake([
            self::DOCS.'/deviceRequests/u2_phone-B*' => Http::sequence()
                ->push($this->doc('deviceRequests/u2_phone-B', ['uid' => 'u2', 'deviceId' => 'phone-B', 'deviceName' => 'Galaxy', 'status' => 'pending']))
                ->push($this->doc('deviceRequests/u2_phone-B', ['status' => 'approved'])),
            self::DOCS.'/users/u2*' => Http::response($this->doc('users/u2', ['name' => 'Sara', 'devices' => [['id' => 'phone-A', 'name' => 'Old']]])),
        ]);

        app(UserDirectory::class)->approve('u2_phone-B');

        Http::assertSent(function (Request $r) {
            if ($r->method() !== 'PATCH' || ! str_contains($r->url(), '/users/u2?')) {
                return false;
            }
            $ids = array_map(fn ($v) => $v['mapValue']['fields']['id']['stringValue'], $r['fields']['devices']['arrayValue']['values']);

            return $ids === ['phone-A', 'phone-B'];
        });
        Http::assertSent(fn (Request $r) => $r->method() === 'PATCH' && str_contains($r->url(), '/deviceRequests/u2_phone-B?') && $r['fields']['status']['stringValue'] === 'approved');
    }
}
