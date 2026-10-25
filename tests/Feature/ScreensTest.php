<?php

namespace Tests\Feature;

use App\Livewire\Calendar;
use App\Livewire\EventForm;
use App\Livewire\EventShow;
use App\Models\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class ScreensTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('offline in tests'));
    }

    public function test_guests_are_sent_to_login(): void
    {
        $this->get('/')->assertRedirect(route('login'));
        $this->get('/login')->assertOk()->assertSee('تسجيل الدخول');
    }

    public function test_calendar_month_and_day_views(): void
    {
        $this->signIn();
        Event::create(['groom_name' => 'محمد الجعبري', 'date' => today()->toDateString(), 'start_time' => '20:30', 'total_cost' => 8000, 'paid_amount' => 2000]);

        $this->get('/')->assertOk()->assertSee('محمد الجعبري')->assertSee('20:30');

        Livewire::test(Calendar::class)
            ->call('setView', 'day')->assertSee('محمد الجعبري')
            ->call('next')->assertDontSee('محمد الجعبري')
            ->call('today')->assertSee('محمد الجعبري');
    }

    public function test_english_locale(): void
    {
        $this->signIn();
        $this->get('/prefs/locale/en?back=/')->assertRedirect('/');
        $this->get('/')->assertSee('Calendar')->assertSee('dir="ltr"', false);
    }

    public function test_add_event(): void
    {
        $this->signIn();

        Livewire::test(EventForm::class)
            ->set('groom_name', 'يوسف التميمي')
            ->set('date', '2026-12-24')
            ->set('start_time', '19:00')
            ->call('useExternalDj')
            ->set('dj_name', 'DJ Sami')
            ->set('dj_phone', '٠٥٢٢٣٣٣٤٤٤')
            ->set('phone', '٠٥٩٩١٢٣٤٥٦')
            ->set('total_cost', '9000')
            ->set('paid_amount', '٣٠٠٠')
            ->set('notes', 'زفة')
            ->call('save')
            ->assertHasNoErrors();

        $e = Event::firstOrFail();
        $this->assertSame('0599123456', $e->phone);
        $this->assertSame('0522333444', $e->dj_phone);
        $this->assertEquals(3000, $e->paid_amount);
        $this->assertEquals(6000, $e->remaining());
        $this->assertSame(Event::PENDING, $e->sync_status);
        $this->assertSame('u1', $e->created_by);
    }

    public function test_paid_cannot_exceed_total(): void
    {
        $this->signIn();

        Livewire::test(EventForm::class)
            ->set('groom_name', 'X')->set('date', '2026-12-24')->set('start_time', '19:00')
            ->set('total_cost', '1000')->set('paid_amount', '2000')
            ->call('save')
            ->assertHasErrors(['paid_amount']);
    }

    public function test_show_edit_and_delete(): void
    {
        $this->signIn();
        $e = Event::create(['groom_name' => 'Omar Haddad', 'date' => '2026-12-24', 'start_time' => '19:00', 'phone' => '0591234567', 'dj_name' => 'DJ Nour', 'dj_phone' => '0521234567', 'total_cost' => 5000, 'paid_amount' => 5000, 'sync_status' => Event::SYNCED]);

        $this->get(route('events.show', $e))->assertOk()->assertSee('Omar Haddad')->assertSee('event-card')->assertSee('wa.me/970591234567')->assertSee('wa.me/972521234567')->assertSee('0521234567');
        $this->get(route('events.edit', $e))->assertOk();

        Livewire::test(EventShow::class, ['event' => $e])->call('delete');
        $this->assertTrue($e->fresh()->deleted);
        $this->assertSame(Event::PENDING, $e->fresh()->sync_status);
        $this->get(route('events.show', $e))->assertNotFound();
    }

    public function test_card_upload_in_browser_mode(): void
    {
        $this->signIn();
        $e = Event::create(['groom_name' => 'Omar', 'date' => '2026-12-24', 'start_time' => '19:00']);

        $png = UploadedFile::fake()->createWithContent('card.png', "\x89PNG\r\n\x1a\n".str_repeat("\0", 64));
        $this->postJson(route('events.card', $e), ['card' => $png, 'action' => 'save'])
            ->assertOk()->assertJson(['ok' => true, 'mode' => 'browser']);
    }

    public function test_card_upload_as_base64_fallback(): void
    {
        $this->signIn();
        $e = Event::create(['groom_name' => 'Omar', 'date' => '2026-12-24', 'start_time' => '19:00']);
        $png = base64_encode("\x89PNG\r\n\x1a\n".str_repeat("\0", 64));

        $this->postJson(route('events.card', $e), ['card_base64' => 'data:image/png;base64,'.$png, 'action' => 'share'])
            ->assertOk()->assertJson(['ok' => true, 'mode' => 'browser']);

        $this->postJson(route('events.card', $e), ['action' => 'save'])
            ->assertStatus(422)->assertJson(['retry_base64' => true]);

        $this->postJson(route('events.card', $e), ['card_base64' => base64_encode('not a png')])
            ->assertStatus(422);

        $this->postJson(route('client-log'), ['stage' => 'render', 'message' => 'boom'])->assertNoContent();
    }

    public function test_financial_totals_are_admin_only(): void
    {
        Event::create(['groom_name' => 'X', 'date' => today()->toDateString(), 'start_time' => '19:00', 'total_cost' => 7000, 'paid_amount' => 2500]);

        $this->signIn('staff');
        $this->get('/')->assertOk()->assertDontSee('2,500')->assertDontSee('4,500');

        $this->signIn('admin');
        $this->get('/')->assertOk()->assertSee('2,500')->assertSee('4,500');
    }

    public function test_admin_pages_require_admin(): void
    {
        $this->signIn('staff');
        $this->get('/admin/users')->assertForbidden();

        $this->signIn('admin');
        $this->get('/admin/users')->assertOk();
        $this->get('/admin/devices')->assertOk();
    }
}
