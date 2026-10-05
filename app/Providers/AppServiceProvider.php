<?php

namespace App\Providers;

use App\Mock\Clock\MockClock;
use App\Mock\Manifest\Manifest;
use App\Mock\Settings\MockSettings;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Manifest::class, fn () => new Manifest(Manifest::path()));
        $this->app->singleton(MockSettings::class);
        $this->app->singleton(MockClock::class);
    }

    public function boot(): void {}
}
