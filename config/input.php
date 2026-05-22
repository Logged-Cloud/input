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

    /*
    | Visual defaults · the fallback chain on each CSS variable is:
    |   --lc-input-<key>          (host explicit override)
    |   then a common host-app var (--surface, --accent, --line, …)
    |   then a hard-coded RGB so a fresh app still looks reasonable.
    */
    'theme' => [
        'bg'        => 'var(--lc-input-bg, var(--surface-2, var(--surface, #1E1F22)))',
        'border'    => 'var(--lc-input-border, var(--line, #3A3D40))',
        'ink'       => 'var(--lc-input-ink, var(--ink, #F0EDE5))',
        'ink_dim'   => 'var(--lc-input-ink-dim, var(--ink-dim, #A3A099))',
        'accent'    => 'var(--lc-input-accent, var(--accent, #2C66E8))',
        'danger'    => 'var(--lc-input-danger, var(--danger, #ef4444))',
        'radius'    => 'var(--lc-input-radius, .5rem)',
    ],

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
        // Auto-submit the closest <form> when every box is filled. Useful
        // for the typical "type 6 digits → submit" MFA flow. Disable for
        // forms that need an explicit confirm step.
        'auto_submit' => true,
        // Focus the first box on mount.
        'auto_focus' => true,
    ],

    'textarea' => [
        // Maximum height before scroll kicks in, in pixels.
        'max_height' => 320,
        // Show a "N / max" counter when `maxlength` is set on the input.
        'counter' => true,
    ],

    'camera' => [
        // Preferred camera · 'environment' = rear (best for documents,
        // food, scenery), 'user' = front (best for selfies). Browsers
        // fall back to whatever is available when the preferred one is
        // not on the device.
        'facing' => 'environment',
        // Output image format · 'image/jpeg' is the most compatible,
        // 'image/webp' compresses better on supported browsers.
        'mime' => 'image/jpeg',
        // 0.0 - 1.0 · 0.85 keeps text legible while shrinking JPEGs ~70%.
        'quality' => 0.85,
        // Cap longest edge in pixels · resizes proportionally before
        // encoding so a 12 MP phone photo lands under ~1 MB at 1600px.
        'max_edge' => 1600,
        // Fallback to a normal file picker when getUserMedia is denied
        // / unavailable / on a desktop without a webcam.
        'allow_gallery' => true,
    ],

];
