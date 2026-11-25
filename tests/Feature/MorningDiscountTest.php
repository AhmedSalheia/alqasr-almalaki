<?php

namespace Tests\Feature;

use App\Livewire\Calendar;
use App\Livewire\EventForm;
use App\Livewire\EventShow;
use App\Models\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MorningDiscountTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app()->setLocale('ar');
        $this->signIn();
    }

    public function test_discount_is_a_fixed_amount_off_the_total(): void
    {
        $e = new Event(['total_cost' => 4000, 'discount' => 500, 'paid_amount' => 1000]);

        $this->assertEquals(3500, $e->netCost());
        $this->assertEquals(2500, $e->remaining());
        $this->assertTrue($e->hasDiscount());
        $this->assertTrue((new Event(['total_cost' => 4000, 'discount' => 500, 'paid_amount' => 3500]))->isPaidInFull());
    }

    public function test_form_saves_discount_and_checks_limits(): void
    {
        $form = fn () => Livewire::test(EventForm::class)->set('groom_name', 'سامر')->set('date', '2026-12-01')->set('total_cost', '4000');

        $form()->set('discount', '5000')->call('save')->assertHasErrors(['discount' => 'lte']);
        $form()->set('discount', '500')->set('paid_amount', '3600')->call('save')->assertHasErrors(['paid_amount']);
        $form()->set('discount', '٥٠٠')->set('paid_amount', '3500')->call('save')->assertHasNoErrors();

        $e = Event::firstOrFail();
        $this->assertEquals(500, $e->discount);
        $this->assertTrue($e->isPaidInFull());
    }

    public function test_morning_is_just_the_start_time(): void
    {
        $this->assertTrue(Event::startsInMorning('09:00'));
        $this->assertTrue(Event::startsInMorning('13:59'));
        $this->assertFalse(Event::startsInMorning('14:00'));
        $this->assertFalse(Event::startsInMorning('20:00'));
    }

    public function test_discount_syncs_and_defaults_to_zero(): void
    {
        $e = Event::create(['groom_name' => 'A', 'date' => '2026-12-02', 'start_time' => '10:00', 'total_cost' => 3000, 'discount' => 250]);
        $f = $e->toFirestoreFields();
        $this->assertSame(250.0, $f['discount']['doubleValue']);
        $this->assertArrayNotHasKey('morning', $f);

        // Documents written by older app versions have no "discount" field.
        $this->assertSame(0.0, Event::attributesFromFirestore(['groomName' => 'B', 'date' => '2026-12-03', 'startTime' => '11:30'])['discount']);
        $this->assertEquals(0, Event::create(['groom_name' => 'C', 'date' => '2026-12-04', 'start_time' => '20:00'])->fresh()->discount);
    }

    public function test_calendar_filter_defaults_to_all(): void
    {
        $d = today()->toDateString();
        Event::create(['groom_name' => 'صباح الخير', 'date' => $d, 'start_time' => '10:00']);
        Event::create(['groom_name' => 'مساء النور', 'date' => $d, 'start_time' => '20:00']);

        Livewire::test(Calendar::class)
            ->assertSet('period', 'all')->assertSee('صباح الخير')->assertSee('مساء النور')
            ->call('setPeriod', 'morning')->assertSee('صباح الخير')->assertDontSee('مساء النور')
            ->call('setPeriod', 'evening')->assertDontSee('صباح الخير')->assertSee('مساء النور');
    }

    public function test_screens_show_morning_and_discount(): void
    {
        $e = Event::create(['groom_name' => 'سامر', 'date' => today()->toDateString(), 'start_time' => '10:00',
            'total_cost' => 4000, 'discount' => 500, 'paid_amount' => 1000]);

        Livewire::test(Calendar::class)->assertSeeHtml('bg-morning')->assertSee('صباحي');
        Livewire::test(EventShow::class, ['event' => $e])
            ->assertSee('حجز صباحي')->assertSee('الخصم')->assertSee('3,500');

        $card = view('components.event-card', ['event' => $e])->render();
        $this->assertStringNotContainsString(__('event.morning'), $card);
        $this->assertStringNotContainsString('−', $card);
        $this->assertStringContainsString('grid-cols-4', $card);
    }
}
