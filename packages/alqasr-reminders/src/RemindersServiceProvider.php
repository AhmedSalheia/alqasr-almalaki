<?php

namespace Alqasr\Reminders;

use Illuminate\Support\ServiceProvider;

class RemindersServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Reminders::class);
    }
}
