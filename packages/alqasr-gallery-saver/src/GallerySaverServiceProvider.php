<?php

namespace Alqasr\GallerySaver;

use Illuminate\Support\ServiceProvider;

class GallerySaverServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Gallery::class);
    }
}
