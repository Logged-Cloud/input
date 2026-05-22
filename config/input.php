<?php

return [

    /*
    |--------------------------------------------------------------------------
    | logged-cloud/input · package config
    |--------------------------------------------------------------------------
    |
    | Each variant reads sane defaults from this file and lets the consumer
    | override at the call site via Blade props. Publish a local copy with
    | `php artisan vendor:publish --tag=input-config` to change app-wide.
    |
    */

    'mask' => [
        // Default placeholder character used by mask-alpine when the user
        // has not typed anything yet · renders inline behind the mask.
        'placeholder_char' => '_',
    ],

    'password' => [
        // Minimum length the strength meter considers "ok".
        'min_length' => 8,
        // Whether the eye-toggle reveal control is shown by default.
        'reveal' => true,
    ],

    'otp' => [
        // Number of boxes the variant renders by default. Override per
        // instance via `length="6"`.
        'length' => 6,
        // Character whitelist · digits-only by default · set to e.g.
        // `[A-Z0-9]` for codes that mix letters.
        'pattern' => '\d',
    ],

    'textarea' => [
        // Maximum height before scroll kicks in, in pixels.
        'max_height' => 320,
        // Show a "N / max" counter when `maxlength` is set on the input.
        'counter' => true,
    ],

];
