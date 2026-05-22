<?php

namespace LoggedCloud\Input;

use Illuminate\Support\ServiceProvider;

class InputServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/input.php', 'input');
    }

    public function boot(): void
    {
        // Views are namespaced `input::*`; components render as
        // <x-input::mask-alpine />, <x-input::otp-alpine />, etc.
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'input');

        $this->publishes([
            __DIR__.'/../config/input.php' => config_path('input.php'),
        ], 'input-config');

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/input'),
        ], 'input-views');
    }
}
