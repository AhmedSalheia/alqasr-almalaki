<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class NativeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        //
    }

    /**
     * The NativePHP plugins to enable.
     *
     * Only plugins listed here will be compiled into your native builds.
     *
     * @return array<int, class-string<\Illuminate\Support\ServiceProvider>>
     */
    public function plugins(): array
    {
        return [
            // Native share sheet (share the event card to WhatsApp, Drive, ...)
            \Native\Mobile\Providers\ShareServiceProvider::class,
            // Save the event card PNG straight into the phone gallery (local plugin in packages/)
            \Alqasr\GallerySaver\GallerySaverServiceProvider::class,
            // Local reminder notifications before reservations (local plugin in packages/)
            \Alqasr\Reminders\RemindersServiceProvider::class,
        ];
    }
}
