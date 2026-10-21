<?php

use App\Http\Controllers\CardController;
use App\Http\Controllers\PreferenceController;
use App\Livewire\Admin\DeviceRequests;
use App\Livewire\Admin\Users;
use App\Livewire\Auth\Login;
use App\Livewire\Auth\PendingDevice;
use App\Livewire\Calendar;
use App\Livewire\EventForm;
use App\Livewire\EventShow;
use App\Livewire\Settings;
use Illuminate\Support\Facades\Route;

Route::get('/prefs/locale/{locale}', [PreferenceController::class, 'locale'])->name('prefs.locale');
Route::get('/prefs/theme/{theme}', [PreferenceController::class, 'theme'])->name('prefs.theme');

Route::middleware('guest.only')->group(function () {
    Route::get('/login', Login::class)->name('login');
});

Route::get('/device-pending', PendingDevice::class)->name('device.pending');

Route::middleware('signed.in')->group(function () {
    Route::get('/', Calendar::class)->name('calendar');
    Route::get('/events/create', EventForm::class)->name('events.create');
    Route::get('/events/{event}', EventShow::class)->name('events.show');
    Route::get('/events/{event}/edit', EventForm::class)->name('events.edit');
    Route::post('/events/{event}/card', [CardController::class, 'store'])->name('events.card');
    Route::get('/settings', Settings::class)->name('settings');

    Route::middleware('admin')->prefix('admin')->group(function () {
        Route::get('/users', Users::class)->name('admin.users');
        Route::get('/devices', DeviceRequests::class)->name('admin.devices');
    });
});
