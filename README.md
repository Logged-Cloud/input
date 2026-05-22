# logged-cloud/input

Alpine-driven free-form input components for Laravel + Livewire. Sibling to [`logged-cloud/select`](https://github.com/Logged-Cloud/select) · this package covers **type-shaped** controls (mask, password, OTP, textarea, tags, autocomplete); `select` covers **pick-shaped** controls (radio grids, date pickers, map drilldowns, etc.).

## Install

```bash
composer require logged-cloud/input
```

Components ship pre-registered; no `vendor:publish` needed unless you want to fork the views or tweak `config/input.php`.

## Variants

| Component | Use case |
|---|---|
| `<x-input::mask-alpine />` | Format-as-you-type for any `#`-pattern (credit cards, dates, IDs) |
| `<x-input::password-alpine />` | Show/hide toggle + zero-dependency strength meter |
| `<x-input::otp-alpine />` | N-box one-time code · arrow / home / end nav, paste-spread, SMS autofill, visual groups, mask mode, auto-submit on complete |
| `<x-input::textarea-alpine />` | Auto-resizing textarea with optional character counter |
| `<x-input::camera-alpine />` | In-page camera via `getUserMedia` + canvas compression · skips the OS camera app, ships a real `File` to the server |

Phone, currency, tags, and autocomplete variants are planned · the four above are the foundation. PRs welcome.

## Example

```blade
{{-- Credit card number · format as the user types, post the raw digits --}}
<x-input::mask-alpine
    name="card_number_display"
    raw-name="card_number"
    pattern="#### #### #### ####"
    autocomplete="cc-number"
    required
/>

{{-- New-password field with strength meter --}}
<x-input::password-alpine name="password" :strength="true" required />

{{-- 6-digit MFA code · paste-aware, auto-advance, auto-submit --}}
<x-input::otp-alpine name="code" length="6" required />

{{-- 8-character backup code with letters, visually grouped --}}
<x-input::otp-alpine name="backup" length="8" pattern="[A-Z0-9]"
                     :groups="[4, 4]" />

{{-- 4-digit PIN with shoulder-surf protection, error state --}}
<x-input::otp-alpine name="pin" length="4" mask
                     :error="$errors->has('pin')" />

{{-- Auto-resizing comment box with a character limit --}}
<x-input::textarea-alpine name="notes" maxlength="500" placeholder="Add notes…" />

{{-- In-page camera · uses the live stream, no OS camera app handoff,
     compresses to ~1 MB JPEG before posting. The form submits as
     normal multipart/form-data so server-side `image` validation works
     untouched. --}}
<form method="post" action="/upload" enctype="multipart/form-data">
    @csrf
    <x-input::camera-alpine name="photo" facing="environment"
                            :quality="0.85" max-edge="1600" required />
    <button type="submit">Upload</button>
</form>
```

## Conventions

Same Alpine + Blade conventions as `logged-cloud/select`:

- Each component is self-contained; the only required external dependencies are Alpine.js (bundled with Livewire) and Tailwind for styling (you control the look via `class` overrides).
- All components emit a `data-component="input::<name>"` attribute so consumer Dusk suites can target them stably.
- The `@once @push('scripts')` block ensures each variant's JS is emitted once per page even if the component renders multiple times.
- All variants surface a hidden form input under the visible UI so the standard Laravel `request()->validate(...)` pipeline works without per-component plumbing.

## Configuration

Publish the config to override package-wide defaults:

```bash
php artisan vendor:publish --tag=input-config
```

The shipped values:

```php
return [
    'mask' => [
        'placeholder_char' => '_',
    ],
    'password' => [
        'min_length' => 8,
        'reveal' => true,
    ],
    'otp' => [
        'length' => 6,
        'pattern' => '\d',
    ],
    'textarea' => [
        'max_height' => 320,
        'counter' => true,
    ],
];
```

Per-instance props always override config defaults.

## Testing

```bash
composer test
```

Structural tests assert against the source files directly · no Laravel container is booted, so the suite runs in under a second. Behaviour-level coverage (typing, paste, focus management) lives in consumer apps' Dusk suites where a real browser drives the markup.

## License

MIT
