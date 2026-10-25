<?php

namespace Alqasr\Updater;

use Illuminate\Support\ServiceProvider;

class UpdaterServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Updater::class);
    }
}
