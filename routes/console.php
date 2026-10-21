<?php

use App\Models\AppState;
use App\Models\Event;
use Illuminate\Support\Facades\Artisan;

/*
 * Browser preview helper: signs in a local demo admin and fills the calendar with sample
 * reservations so the UI can be explored with `php artisan serve` before Firebase is set up.
 * Never run this on a device build.
 */
Artisan::command('alqasr:demo {--clear : Remove the demo session and events}', function () {
    if (app()->isProduction()) {
        $this->error('Not available in production.');

        return 1;
    }

    if ($this->option('clear')) {
        AppState::query()->delete();
        Event::query()->delete();
        $this->info('Demo data removed.');

        return 0;
    }

    AppState::put('auth', [
        'uid' => 'demo-admin',
        'email' => 'admin@alqasr-almalaky.app',
        'name' => 'أحمد الإداري',
        'role' => 'admin',
        'id_token' => 'demo',
        'refresh_token' => 'demo',
        'expires_at' => time() + 10 * 365 * 86400,
        'device_status' => 'approved',
    ]);
    AppState::put('sync.online', true);
    AppState::put('sync.last_success_at', now()->subMinutes(2)->toIso8601String());

    $grooms = ['محمد أحمد الجعبري', 'يوسف خالد التميمي', 'عبد الرحمن سامي النتشة', 'أنس محمود القواسمي', 'Omar Haddad', 'حمزة إبراهيم الشرباتي', 'كريم وليد أبو سنينة', 'مصطفى رائد الزعتري'];
    $djs = ['DJ Sami', 'DJ Nour', 'DJ Rami', 'DJ Basel', null];
    $start = now()->startOfMonth();

    Event::query()->delete();
    foreach ([2, 5, 5, 9, 12, 16, 19, 19, 23, 26, 30, 34, 40] as $i => $offset) {
        $total = [6500, 8000, 12000, 9500, 15000][$i % 5];
        Event::create([
            'groom_name' => $grooms[$i % count($grooms)],
            'date' => $start->copy()->addDays($offset)->toDateString(),
            'start_time' => ['19:00', '20:30', '18:00', '21:00'][$i % 4],
            'end_time' => ['23:30', null, '22:00', '01:00'][$i % 4],
            'dj_name' => $djs[$i % count($djs)],
            'dj_phone' => $djs[$i % count($djs)] ? '0522'.str_pad((string) (40000 + $i * 313), 6, '0', STR_PAD_LEFT) : null,
            'phone' => '0599'.str_pad((string) (123456 + $i * 7919), 6, '0', STR_PAD_LEFT),
            'total_cost' => $total,
            'paid_amount' => [$total, $total / 2, 0, 3000][$i % 4],
            'notes' => $i % 3 === 0 ? "قاعة رئيسية + زفة شعبية.\nتجهيز طاولة العرسان بالورد الأبيض." : null,
            'created_by' => 'demo-admin',
            'created_by_name' => 'أحمد الإداري',
            'sync_status' => $i === 1 ? Event::PENDING : Event::SYNCED,
        ]);
    }

    $this->info('Demo admin signed in and '.Event::count().' sample reservations created. Run: php artisan serve');

    return 0;
})->purpose('Fill the app with demo data for a browser preview');
