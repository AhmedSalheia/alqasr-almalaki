<?php

namespace Tests\Feature;

use App\Livewire\Settings;
use App\Livewire\UpdateBanner;
use App\Models\AppState;
use App\Services\Updates\UpdateChecker;
use App\Services\Updates\UpdateGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class FakeUpdateGateway implements UpdateGateway
{
    public array $downloads = [];

    public array $statusValue = ['state' => 'idle'];

    public int $installs = 0;

    public function supported(): bool
    {
        return true;
    }

    public function download(string $url, string $version, bool $unmeteredOnly): array
    {
        $this->downloads[] = compact('url', 'version', 'unmeteredOnly');

        return ['started' => true];
    }

    public function status(): array
    {
        return $this->statusValue;
    }

    public function install(): array
    {
        $this->installs++;

        return ['success' => true];
    }
}

class UpdatesTest extends TestCase
{
    use RefreshDatabase;

    protected FakeUpdateGateway $gateway;

    protected function setUp(): void
    {
        parent::setUp();
        config(['nativephp.version' => '1.0.2', 'alqasr.updates.repo' => 'owner/alqasr-calendar', 'alqasr.updates.auto_check' => true]);
        $this->gateway = new FakeUpdateGateway;
        $this->app->instance(UpdateGateway::class, $this->gateway);
        $this->signIn();
    }

    protected function fakeRelease(string $tag, array $extra = []): void
    {
        Http::fake(['api.github.com/repos/owner/alqasr-calendar/releases/latest' => Http::response(array_merge([
            'tag_name' => $tag,
            'html_url' => "https://github.com/owner/alqasr-calendar/releases/tag/{$tag}",
            'body' => 'Fixes',
            'draft' => false,
            'prerelease' => false,
            'assets' => [
                ['name' => "alqasr-calendar-{$tag}-app-release.aab", 'size' => 1, 'browser_download_url' => "https://github.com/owner/alqasr-calendar/releases/download/{$tag}/x.aab"],
                ['name' => "alqasr-calendar-{$tag}-app-release.apk", 'size' => 83886080, 'browser_download_url' => "https://github.com/owner/alqasr-calendar/releases/download/{$tag}/x.apk"],
            ],
        ], $extra))]);
    }

    public function test_newer_release_is_found_with_its_apk(): void
    {
        $this->fakeRelease('v1.0.3');

        $result = app(UpdateChecker::class)->check();

        $this->assertSame('available', $result['status']);
        $this->assertSame('1.0.3', $result['info']['version']);
        $this->assertStringEndsWith('/x.apk', $result['info']['apk_url']);
    }

    public function test_same_or_older_release_is_up_to_date(): void
    {
        $this->fakeRelease('v1.0.2');
        $this->assertSame('up_to_date', app(UpdateChecker::class)->check()['status']);

        $this->fakeRelease('v1.0.1');
        $this->assertSame('up_to_date', app(UpdateChecker::class)->check()['status']);
        $this->assertNull(app(UpdateChecker::class)->cached());
    }

    public function test_apk_from_another_host_is_ignored(): void
    {
        $this->fakeRelease('v2.0.0', ['assets' => [['name' => 'evil.apk', 'size' => 1, 'browser_download_url' => 'https://example.com/evil.apk']]]);

        $this->assertSame('up_to_date', app(UpdateChecker::class)->check()['status']);
    }

    public function test_banner_checks_and_starts_a_wifi_only_download(): void
    {
        $this->fakeRelease('v1.0.3');

        Livewire::test(UpdateBanner::class)->call('tick', true)->assertSee('1.0.3');

        $this->assertCount(1, $this->gateway->downloads);
        $this->assertTrue($this->gateway->downloads[0]['unmeteredOnly']);

        // checked recently -> no second GitHub request
        Livewire::test(UpdateBanner::class)->call('tick', true);
        Http::assertSentCount(1);
    }

    public function test_offline_tick_does_not_touch_the_network(): void
    {
        Http::fake(fn () => throw new \RuntimeException('network must not be used'));

        Livewire::test(UpdateBanner::class)->call('tick', false)->assertOk();
        $this->assertSame([], $this->gateway->downloads);
    }

    public function test_ready_update_can_be_installed_and_dismissed(): void
    {
        $this->fakeRelease('v1.0.3');
        app(UpdateChecker::class)->check();
        $this->gateway->statusValue = ['state' => 'done', 'version' => '1.0.3', 'progress' => 100];

        Livewire::test(UpdateBanner::class)
            ->assertSee(__('app.update_install'))
            ->call('install')
            ->call('dismiss')
            ->assertDontSee('1.0.3');

        $this->assertSame(1, $this->gateway->installs);
    }

    public function test_settings_manual_check(): void
    {
        $this->fakeRelease('v1.0.3');

        Livewire::test(Settings::class)->call('checkUpdates')->assertDispatched('update-found');
        $this->assertSame('1.0.3', AppState::get(UpdateChecker::STATE)['version']);
    }
}
