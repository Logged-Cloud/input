<?php

/*
| Static structural tests · the package doesn't boot Laravel, so we assert
| against the source files directly: prop signatures, Alpine wiring, key
| handlers, accessibility hooks. Behaviour-level coverage lives in
| consumer apps where a real browser drives the markup.
*/

$mask     = __DIR__.'/../resources/views/components/mask-alpine.blade.php';
$password = __DIR__.'/../resources/views/components/password-alpine.blade.php';
$otp      = __DIR__.'/../resources/views/components/otp-alpine.blade.php';
$textarea = __DIR__.'/../resources/views/components/textarea-alpine.blade.php';
$provider = __DIR__.'/../src/InputServiceProvider.php';

// ─── mask-alpine ────────────────────────────────────────────────────

test('mask-alpine component file exists', function () use ($mask) {
    expect(file_exists($mask))->toBeTrue();
});

test('mask-alpine posts both formatted and raw values by default', function () use ($mask) {
    $template = file_get_contents($mask);
    expect($template)
        ->toContain('name="{{ $name }}"')
        ->and($template)->toContain('name="{{ $rawName }}"')
        ->and($template)->toContain('$rawName ??= $name.\'_raw\';');
});

test('mask-alpine clamps to the pattern slot count', function () use ($mask) {
    $template = file_get_contents($mask);
    expect($template)
        ->toContain('slotCount')
        ->and($template)->toContain('slice(0, slotCount)');
});

test('mask-alpine handles paste explicitly', function () use ($mask) {
    $template = file_get_contents($mask);
    expect($template)->toContain('onPaste');
});

// ─── password-alpine ────────────────────────────────────────────────

test('password-alpine component file exists', function () use ($password) {
    expect(file_exists($password))->toBeTrue();
});

test('password-alpine toggles between password and text input', function () use ($password) {
    $template = file_get_contents($password);
    expect($template)
        ->toContain(":type=\"visible ? 'text' : 'password'\"")
        ->and($template)->toContain('visible = ! visible');
});

test('password-alpine reveal button carries an aria-label', function () use ($password) {
    $template = file_get_contents($password);
    expect($template)
        ->toContain(":aria-label=\"visible ? 'Hide password' : 'Show password'\"");
});

test('password-alpine ships a strength estimator with 0-4 score', function () use ($password) {
    $template = file_get_contents($password);
    expect($template)
        ->toContain('recompute()')
        ->and($template)->toContain('this.score = Math.min(s, 4)');
});

// ─── otp-alpine ─────────────────────────────────────────────────────

test('otp-alpine component file exists', function () use ($otp) {
    expect(file_exists($otp))->toBeTrue();
});

test('otp-alpine renders N boxes from the length prop', function () use ($otp) {
    $template = file_get_contents($otp);
    expect($template)
        ->toContain('Array.from({ length }, () => \'\')')
        ->and($template)->toContain('x-for="(digit, i) in digits"');
});

test('otp-alpine wires the autofill autocomplete', function () use ($otp) {
    $template = file_get_contents($otp);
    expect($template)
        ->toContain("autocomplete=\"{{ \$autocomplete }}\"")
        ->and($template)->toContain("'autocomplete' => 'one-time-code'");
});

test('otp-alpine arrow keys + backspace move focus between boxes', function () use ($otp) {
    $template = file_get_contents($otp);
    foreach ([
        '@keydown.arrow-left',
        '@keydown.arrow-right',
        '@keydown.backspace',
        'onBackspace',
        'focusBox(i - 1)',
        'focusBox(i + 1)',
    ] as $needle) {
        expect($template)->toContain($needle);
    }
});

test('otp-alpine handles SMS-autofill paste across boxes', function () use ($otp) {
    $template = file_get_contents($otp);
    expect($template)
        ->toContain('@paste')
        ->and($template)->toContain('onPaste');
});

// ─── textarea-alpine ────────────────────────────────────────────────

test('textarea-alpine component file exists', function () use ($textarea) {
    expect(file_exists($textarea))->toBeTrue();
});

test('textarea-alpine resizes on input up to a max height', function () use ($textarea) {
    $template = file_get_contents($textarea);
    expect($template)
        ->toContain("@input=\"resize()\"")
        ->and($template)->toContain('Math.min(ta.scrollHeight, maxHeight)');
});

test('textarea-alpine shows a counter when maxlength is set', function () use ($textarea) {
    $template = file_get_contents($textarea);
    expect($template)
        ->toContain('@if ($counter && $maxlength)')
        ->and($template)->toContain('value.length');
});

// ─── service provider ──────────────────────────────────────────────

test('service provider loads views under the input:: namespace', function () use ($provider) {
    $source = file_get_contents($provider);
    expect($source)
        ->toContain("loadViewsFrom(__DIR__.'/../resources/views', 'input')")
        ->and($source)->toContain('input-config')
        ->and($source)->toContain('input-views');
});
