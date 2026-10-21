<?php

namespace App\Providers;

use App\Services\AuthSession;
use App\Services\Firebase\FirebaseClient;
use App\Support\Native;
use App\Support\Prefs;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Throwable;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(FirebaseClient::class, fn () => FirebaseClient::fromConfig());
        $this->app->bind(\App\Services\Reminders\ReminderGateway::class, fn () => Native::can('Reminders.Schedule')
            ? new \App\Services\Reminders\NativeReminderGateway
            : new \App\Services\Reminders\NullReminderGateway);
    }

    public function boot(): void
    {
        View::composer(['components.layouts.*'], function ($view) {
            // Not cached across requests on purpose (NativePHP keeps PHP alive between requests).
            try {
                $session = app(AuthSession::class);
                $view->with([
                    'theme' => Prefs::theme(),
                    'dir' => Prefs::dir(),
                    'currentUser' => $session->user(),
                    'isAdmin' => $session->isAdmin(),
                    'isNative' => Native::isNative(),
                ]);
            } catch (Throwable) {
                $view->with(['theme' => 'system', 'dir' => 'rtl', 'currentUser' => null, 'isAdmin' => false, 'isNative' => false]);
            }
        });
    }
}
