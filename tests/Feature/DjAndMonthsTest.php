<?php

namespace Tests\Feature;

use App\Livewire\EventForm;
use App\Models\Event;
use App\Support\Format;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DjAndMonthsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app()->setLocale('ar');
        $this->signIn();
    }

    public function test_month_number_only_when_asked(): void
    {
        app('translator')->addLines(['calendar.months.10' => 'أكتوبر / 10'], 'ar');

        $this->assertSame('أكتوبر', Format::monthName(10));
        $this->assertSame('أكتوبر / 10', Format::monthName(10, withNumber: true));
        $this->assertSame('25 أكتوبر', Format::shortDate(\Carbon\Carbon::parse('2026-10-25')));
    }

    public function test_new_event_defaults_to_venue_dj(): void
    {
        Livewire::test(EventForm::class)
            ->assertSet('dj_external', false)
            ->set('groom_name', 'سامر')
            ->set('date', '2026-12-01')
            ->call('save')
            ->assertHasNoErrors();

        $e = Event::firstOrFail();
        $this->assertSame('أحمد النجار', $e->dj_name);
        $this->assertSame('0592758021', $e->dj_phone);
    }

    public function test_external_dj_requires_name_and_phone(): void
    {
        Livewire::test(EventForm::class)
            ->set('groom_name', 'سامر')
            ->set('date', '2026-12-01')
            ->call('useExternalDj')
            ->call('save')
            ->assertHasErrors(['dj_name' => 'required', 'dj_phone' => 'required']);
    }

    public function test_editing_detects_external_dj(): void
    {
        $venue = Event::create(['groom_name' => 'A', 'date' => '2026-12-02', 'start_time' => '20:00', 'dj_name' => 'أحمد النجار', 'dj_phone' => '059 275 8021']);
        $ext = Event::create(['groom_name' => 'B', 'date' => '2026-12-03', 'start_time' => '20:00', 'dj_name' => 'DJ Nour', 'dj_phone' => '0591112222']);

        Livewire::test(EventForm::class, ['event' => $venue])->assertSet('dj_external', false)->assertSet('dj_name', '');
        Livewire::test(EventForm::class, ['event' => $ext])->assertSet('dj_external', true)->assertSet('dj_name', 'DJ Nour');
    }
}
