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
        ->toContain("Array.from({ length }, () => '')")
        ->and($template)->toContain('x-for="(slot, i) in slots"');
});

test('otp-alpine wires the SMS-autofill autocomplete', function () use ($otp) {
    $template = file_get_contents($otp);
    expect($template)
        ->toContain('autocomplete="{{ $autocomplete }}"')
        ->and($template)->toContain("'autocomplete' => 'one-time-code'");
});

test('otp-alpine covers the full keyboard navigation surface', function () use ($otp) {
    $template = file_get_contents($otp);
    foreach ([
        '@keydown.arrow-left.prevent',
        '@keydown.arrow-right.prevent',
        '@keydown.home.prevent',
        '@keydown.end.prevent',
        '@keydown.backspace',
        'onBackspace',
        'focusBox(slot.index - 1)',
        'focusBox(slot.index + 1)',
        'focusBox(0)',
        'focusBox(length - 1)',
    ] as $needle) {
        expect($template)->toContain($needle);
    }
});

test('otp-alpine handles SMS-autofill paste and strips non-pattern chars', function () use ($otp) {
    $template = file_get_contents($otp);
    expect($template)
        ->toContain('@paste')
        ->and($template)->toContain('onPaste')
        // pasted text with spaces / dashes from email is normalised
        ->and($template)->toContain("text.split('').filter((c) => allow.test(c)).slice(0, length)");
});

test('otp-alpine supports visual groups via the groups prop', function () use ($otp) {
    $template = file_get_contents($otp);
    expect($template)
        ->toContain("groups.reduce((s, n) => s + n, 0) === length")
        ->and($template)->toContain("slots.push({ kind: 'sep' })")
        ->and($template)->toContain('class="lc-otp-sep"');
});

test('otp-alpine supports mask mode for shoulder-surf-safe codes', function () use ($otp) {
    $template = file_get_contents($otp);
    expect($template)
        ->toContain("'mask' => false")
        ->and($template)->toContain(":type=\"mask ? 'password' : 'text'\"");
});

test('otp-alpine auto-submits the closest form when complete', function () use ($otp) {
    $template = file_get_contents($otp);
    expect($template)
        ->toContain('isComplete')
        ->and($template)->toContain("this.\$root.closest('form')")
        ->and($template)->toContain('requestSubmit')
        ->and($template)->toContain("'lc-input:otp:complete'");
});

test('otp-alpine respects the disabled prop end-to-end', function () use ($otp) {
    $template = file_get_contents($otp);
    expect($template)
        ->toContain("'disabled' => false")
        ->and($template)->toContain(':disabled="disabled"')
        ->and($template)->toContain('if (this.disabled) return');
});

test('otp-alpine surfaces an error state via aria-invalid + data-error', function () use ($otp) {
    $template = file_get_contents($otp);
    expect($template)
        ->toContain("'error' => false")
        ->and($template)->toContain("'data-error' => \$error ? 'true' : 'false'")
        ->and($template)->toContain(":aria-invalid=\"@js(\$error) ? 'true' : 'false'\"");
});

test('otp-alpine selects existing content on focus so retyping replaces', function () use ($otp) {
    $template = file_get_contents($otp);
    expect($template)
        ->toContain('@focus="onFocus(slot.index, $event)"')
        ->and($template)->toContain('e.target.select()');
});

test('otp-alpine picks numeric inputmode for digits-only patterns', function () use ($otp) {
    $template = file_get_contents($otp);
    expect($template)
        ->toContain("preg_match('/^\\\\\\\\d|0-9|\\[0-9\\]\$/', \$pattern)")
        ->and($template)->toContain('inputmode="{{ $inputmode }}"');
});

test('otp-alpine ships its own CSS for the box look', function () use ($otp) {
    $template = file_get_contents($otp);
    expect($template)
        ->toContain('.lc-input--otp .lc-otp-box')
        ->and($template)->toContain('@media (max-width: 480px)');
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
