<?php

namespace Tests\Feature;

use App\Livewire\EventForm;
use App\Livewire\Settings;
use App\Models\AppState;
use App\Models\Event;
use App\Services\SyncService;
use App\Support\CardAssets;
use App\Support\Phone;
use App\Support\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class VenueCardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app()->setLocale('ar');
        $this->signIn();
    }

    public function test_phone_helper(): void
    {
        $this->assertSame('+970599123456', Phone::join('+970', '٠٥٩٩ 123 456'));
        $this->assertSame(['+970', '0599123456'], Phone::split('+970599123456'));
        $this->assertSame(['+972', '0599123456'], Phone::split('0599123456'));
        $this->assertSame('+972 59 275 8021', Phone::display('+972592758021'));
        $this->assertSame('0592475403', Phone::display('0592475403'));
        $this->assertSame(Phone::national('0592758021'), Phone::national('+972592758021'));
    }

    public function test_venue_defaults(): void
    {
        $v = Venue::get();
        $this->assertSame('خانيونس - جنوب أبراج طيبة', $v['location']);
        $this->assertSame('0592475403', $v['phone']);
        $this->assertSame('أحمد النجار', $v['dj_name']);
        $this->assertSame('+972592758021', $v['dj_phone']);
    }

    public function test_external_dj_saved_with_country_code(): void
    {
        Livewire::test(EventForm::class)
            ->set('groom_name', 'سامر')
            ->set('date', '2026-12-01')
            ->call('useExternalDj')
            ->set('dj_name', 'DJ Nour')
            ->set('dj_phone_code', '+970')
            ->set('dj_phone', '0599111222')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('+970599111222', Event::firstOrFail()->dj_phone);
    }

    public function test_old_venue_dj_number_without_code_is_still_the_venue_dj(): void
    {
        $this->assertFalse(EventForm::isExternalDj('أحمد النجار', '0592758021'));
        $this->assertTrue(EventForm::isExternalDj('DJ Nour', '0592758021'));
    }

    public function test_admin_edits_venue_and_sync_pushes_it(): void
    {
        Livewire::test(Settings::class)
            ->call('editVenue')
            ->set('venueLocation', 'خانيونس - شارع جلال')
            ->set('venuePhone', '٠٥٩٩٠٠٠١١١')
            ->set('venueDjName', 'DJ Sami')
            ->set('venueDjPhoneCode', '+970')
            ->set('venueDjPhone', '0599222333')
            ->call('saveVenue')
            ->assertHasNoErrors();

        $this->assertSame('0599000111', Venue::value('phone'));
        $this->assertSame('+970599222333', Venue::value('dj_phone'));
        $this->assertTrue(Venue::dirty());

        Http::fake(['*' => Http::response(['name' => 'projects/p/databases/(default)/documents/settings/venue', 'fields' => []])]);
        app(SyncService::class)->syncVenue('token');

        Http::assertSent(fn ($r) => $r->method() === 'PATCH' && str_contains($r->url(), '/settings/venue')
            && $r['fields']['djPhone']['stringValue'] === '+970599222333');
        $this->assertFalse(Venue::dirty());
    }

    public function test_staff_cannot_edit_venue(): void
    {
        $this->signIn('staff');
        Livewire::test(Settings::class)->call('editVenue')->assertForbidden();
    }

    public function test_remote_venue_is_applied(): void
    {
        $this->assertTrue(Venue::applyRemote(['location' => 'رفح', 'phone' => '0590000000', 'djName' => 'X', 'djPhone' => '+970590000001', 'updatedAt' => '2026-10-25T10:00:00+00:00']));
        $this->assertSame('رفح', Venue::value('location'));
    }

    public function test_card_shows_logo_dj_details_notes_and_venue(): void
    {
        $e = Event::create(['groom_name' => 'سامح محمد قديح', 'date' => '2026-10-22', 'start_time' => '20:00', 'end_time' => '23:24',
            'dj_name' => 'أحمد النجار', 'dj_phone' => '+972592758021', 'phone' => '0599930082', 'total_cost' => 4000, 'paid_amount' => 500]);

        $html = view('components.event-card', ['event' => $e])->render();

        $this->assertStringContainsString('data:image/png;base64,', $html);
        $this->assertStringContainsString('تفاصيل الدي جي', $html);
        $this->assertStringContainsString('+972 59 275 8021', $html);
        $this->assertStringContainsString('ملاحظات', $html);
        $this->assertStringContainsString('خانيونس - جنوب أبراج طيبة', $html);
        $this->assertStringContainsString('0592475403', $html);
    }

    public function test_card_fonts_are_served_inline(): void
    {
        $css = $this->get(route('card.fonts'))->assertOk()->assertHeader('Content-Type', 'text/css; charset=UTF-8')->getContent();

        $this->assertStringContainsString("font-family:'Tajawal'", $css);
        $this->assertStringContainsString("font-family:'El Messiri'", $css);
        $this->assertStringNotContainsString('.woff2)', $css);
        $this->assertLessThan(260_000, strlen(CardAssets::fontCss()));
    }
}
