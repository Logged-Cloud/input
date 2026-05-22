<?php

/*
| Static structural tests · the package doesn't boot Laravel, so we assert
| against the source files directly: prop signatures, Alpine wiring, key
| handlers, accessibility hooks. Behaviour-level coverage lives in
| consumer apps where a real browser drives the markup.
*/

$mask         = __DIR__.'/../resources/views/components/mask-alpine.blade.php';
$password     = __DIR__.'/../resources/views/components/password-alpine.blade.php';
$otp          = __DIR__.'/../resources/views/components/otp-alpine.blade.php';
$textarea     = __DIR__.'/../resources/views/components/textarea-alpine.blade.php';
$camera       = __DIR__.'/../resources/views/components/camera-alpine.blade.php';
$toggle       = __DIR__.'/../resources/views/components/toggle-alpine.blade.php';
$currency     = __DIR__.'/../resources/views/components/currency-alpine.blade.php';
$mobile       = __DIR__.'/../resources/views/components/mobile-alpine.blade.php';
$geolocation  = __DIR__.'/../resources/views/components/geolocation-alpine.blade.php';
$signature    = __DIR__.'/../resources/views/components/signature-alpine.blade.php';
$voice        = __DIR__.'/../resources/views/components/voice-alpine.blade.php';
$autocomplete = __DIR__.'/../resources/views/components/autocomplete-alpine.blade.php';
$tags         = __DIR__.'/../resources/views/components/tags-alpine.blade.php';
$fileMulti    = __DIR__.'/../resources/views/components/file-multi-alpine.blade.php';
$range        = __DIR__.'/../resources/views/components/range-alpine.blade.php';
$dualRange    = __DIR__.'/../resources/views/components/dual-range-alpine.blade.php';
$markdown     = __DIR__.'/../resources/views/components/markdown-alpine.blade.php';
$emoji        = __DIR__.'/../resources/views/components/emoji-alpine.blade.php';
$copy         = __DIR__.'/../resources/views/components/copy-alpine.blade.php';
$provider     = __DIR__.'/../src/InputServiceProvider.php';

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

// ─── camera-alpine ──────────────────────────────────────────────────

test('camera-alpine component file exists', function () use ($camera) {
    expect(file_exists($camera))->toBeTrue();
});

test('camera-alpine uses getUserMedia · not the OS camera app', function () use ($camera) {
    $template = file_get_contents($camera);
    // No `capture` attribute on the file input · that would punt to the
    // OS camera. We use an explicit getUserMedia call instead.
    expect($template)
        ->not->toContain('capture="')
        ->and($template)->toContain('navigator.mediaDevices.getUserMedia')
        ->and($template)->toContain('facingMode');
});

test('camera-alpine compresses captured frames via canvas + toBlob', function () use ($camera) {
    $template = file_get_contents($camera);
    expect($template)
        ->toContain("canvas.getContext('2d')")
        ->and($template)->toContain('canvas.toBlob(resolve, mime, quality)')
        // longest-edge scaling clamps phone-camera megapixel output
        ->and($template)->toContain('maxEdge / Math.max(vw, vh)');
});

test('camera-alpine injects the captured blob into a real file input', function () use ($camera) {
    $template = file_get_contents($camera);
    expect($template)
        ->toContain('new DataTransfer()')
        ->and($template)->toContain('dt.items.add(file)')
        ->and($template)->toContain('this.$refs.fileField.files = dt.files')
        // change event so wire:model / onchange listeners pick it up
        ->and($template)->toContain("new Event('change', { bubbles: true })");
});

test('camera-alpine offers a gallery fallback', function () use ($camera) {
    $template = file_get_contents($camera);
    expect($template)
        ->toContain('onGalleryChoose')
        ->and($template)->toContain("'allowGallery' => null")
        // the gallery path also runs through the canvas pipeline so
        // user-picked images get the same compression treatment.
        ->and($template)->toContain('img.onload = async');
});

test('camera-alpine handles permission denied gracefully', function () use ($camera) {
    $template = file_get_contents($camera);
    expect($template)
        ->toContain("e.name === 'NotAllowedError'")
        ->and($template)->toContain('Camera permission denied');
});

test('camera-alpine stops the media stream after capture', function () use ($camera) {
    $template = file_get_contents($camera);
    expect($template)
        ->toContain('stopStream()')
        ->and($template)->toContain('this.stream.getTracks()');
});

test('camera-alpine sets playsinline so iOS does not go fullscreen', function () use ($camera) {
    $template = file_get_contents($camera);
    expect($template)->toContain('playsinline');
});

test('camera-alpine ships its own CSS for the capture UI', function () use ($camera) {
    $template = file_get_contents($camera);
    expect($template)
        ->toContain('.lc-input--camera .lc-camera-shutter')
        ->and($template)->toContain('aspect-ratio: 1 / 1');
});

test('camera-alpine cleans up object URLs to avoid leaks', function () use ($camera) {
    $template = file_get_contents($camera);
    expect($template)
        ->toContain('URL.revokeObjectURL(this.previewUrl)')
        ->and($template)->toContain('URL.revokeObjectURL(img.src)');
});

// ─── toggle-alpine ──────────────────────────────────────────────────

test('toggle-alpine renders a switch role with aria-checked', function () use ($toggle) {
    $template = file_get_contents($toggle);
    expect($template)
        ->toContain('role="switch"')
        ->and($template)->toContain(":aria-checked=\"on ? 'true' : 'false'\"");
});

test('toggle-alpine posts configurable on / off values', function () use ($toggle) {
    $template = file_get_contents($toggle);
    expect($template)
        ->toContain("'onValue' => null")
        ->and($template)->toContain("'offValue' => null")
        ->and($template)->toContain('get postedValue() { return this.on ? onValue : offValue; }');
});

test('toggle-alpine responds to space and enter as well as click', function () use ($toggle) {
    $template = file_get_contents($toggle);
    expect($template)
        ->toContain('@keydown.space.prevent')
        ->and($template)->toContain('@keydown.enter.prevent');
});

// ─── currency-alpine ────────────────────────────────────────────────

test('currency-alpine separates display from raw numeric posted value', function () use ($currency) {
    $template = file_get_contents($currency);
    expect($template)
        ->toContain('name="{{ $name }}"')
        ->and($template)->toContain('x-model="raw"');
});

test('currency-alpine derives separator + symbol from Intl', function () use ($currency) {
    $template = file_get_contents($currency);
    expect($template)
        ->toContain('new Intl.NumberFormat(locale')
        ->and($template)->toContain("p.type === 'decimal'")
        ->and($template)->toContain("p.type === 'group'")
        ->and($template)->toContain("p.type === 'currency'");
});

test('currency-alpine reformats on blur · not while typing', function () use ($currency) {
    $template = file_get_contents($currency);
    expect($template)
        ->toContain('onBlur()')
        ->and($template)->toContain("@blur=\"onBlur()\"");
});

test('currency-alpine clamps min / max if supplied', function () use ($currency) {
    $template = file_get_contents($currency);
    expect($template)
        ->toContain('if (min !== null && num < min) num = min;')
        ->and($template)->toContain('if (max !== null && num > max) num = max;');
});

// ─── mobile-alpine ──────────────────────────────────────────────────

test('mobile-alpine posts E.164 not the visible formatted string', function () use ($mobile) {
    $template = file_get_contents($mobile);
    expect($template)
        ->toContain('name="{{ $name }}"')
        ->and($template)->toContain('x-model="e164"')
        ->and($template)->toContain('this.e164 = raw ? this.current.dial + raw : \'\'');
});

test('mobile-alpine ships a curated country picker with flags + dial codes', function () use ($mobile) {
    $template = file_get_contents($mobile);
    foreach (["iso: 'GB'", "iso: 'US'", "dial: '+44'", "dial: '+1'", 'flag:'] as $needle) {
        expect($template)->toContain($needle);
    }
});

test('mobile-alpine reformats existing digits when the country changes', function () use ($mobile) {
    $template = file_get_contents($mobile);
    expect($template)
        ->toContain('onCountryChange()')
        ->and($template)->toContain('this.display = this.formatMasked(this.rawDigits())');
});

test('mobile-alpine detects the country from a pre-filled E.164 value', function () use ($mobile) {
    $template = file_get_contents($mobile);
    expect($template)
        ->toContain('initial.startsWith(c.dial)');
});

// ─── geolocation-alpine ─────────────────────────────────────────────

test('geolocation-alpine posts lat + lng as separate hidden inputs', function () use ($geolocation) {
    $template = file_get_contents($geolocation);
    expect($template)
        ->toContain('name="{{ $latName }}"')
        ->and($template)->toContain('name="{{ $lngName }}"')
        ->and($template)->toContain('$latName ??= $name.\'_lat\'')
        ->and($template)->toContain('$lngName ??= $name.\'_lng\'');
});

test('geolocation-alpine uses the navigator geolocation API', function () use ($geolocation) {
    $template = file_get_contents($geolocation);
    expect($template)
        ->toContain('navigator.geolocation.getCurrentPosition')
        ->and($template)->toContain('enableHighAccuracy:');
});

test('geolocation-alpine surfaces permission + availability errors', function () use ($geolocation) {
    $template = file_get_contents($geolocation);
    expect($template)
        ->toContain('PERMISSION_DENIED')
        ->and($template)->toContain('POSITION_UNAVAILABLE')
        ->and($template)->toContain('Location permission denied');
});

test('geolocation-alpine emits a custom event consumers can hook', function () use ($geolocation) {
    $template = file_get_contents($geolocation);
    expect($template)
        ->toContain("'lc-input:geolocation:resolved'");
});

// ─── signature-alpine ───────────────────────────────────────────────

test('signature-alpine wires the pointer event family · mouse + touch + stylus', function () use ($signature) {
    $template = file_get_contents($signature);
    foreach ([
        '@pointerdown.prevent',
        '@pointermove.prevent',
        '@pointerup',
        '@pointerleave',
        '@pointercancel',
    ] as $needle) {
        expect($template)->toContain($needle);
    }
});

test('signature-alpine scales the canvas bitmap for retina displays', function () use ($signature) {
    $template = file_get_contents($signature);
    expect($template)
        ->toContain('window.devicePixelRatio')
        ->and($template)->toContain('canvas.width  = rect.width * this.dpr');
});

test('signature-alpine commits a PNG via DataTransfer on form submit', function () use ($signature) {
    $template = file_get_contents($signature);
    expect($template)
        ->toContain("canvas.toBlob((blob)")
        ->and($template)->toContain('new DataTransfer()')
        ->and($template)->toContain("this.\$root.closest('form')")
        ->and($template)->toContain("addEventListener('submit'");
});

test('signature-alpine offers a clear button', function () use ($signature) {
    $template = file_get_contents($signature);
    expect($template)
        ->toContain('@click="clear()"')
        ->and($template)->toContain('clearRect(0, 0, canvas.width, canvas.height)');
});

// ─── voice-alpine ───────────────────────────────────────────────────

test('voice-alpine uses MediaRecorder + getUserMedia', function () use ($voice) {
    $template = file_get_contents($voice);
    expect($template)
        ->toContain('navigator.mediaDevices.getUserMedia')
        ->and($template)->toContain('new MediaRecorder(this.stream')
        ->and($template)->toContain('window.MediaRecorder.isTypeSupported(mime)');
});

test('voice-alpine stops automatically at maxSeconds', function () use ($voice) {
    $template = file_get_contents($voice);
    expect($template)
        ->toContain('if (this.elapsedSec >= maxSeconds) this.stop()');
});

test('voice-alpine commits the recording via DataTransfer on stop', function () use ($voice) {
    $template = file_get_contents($voice);
    expect($template)
        ->toContain('new Blob(this.chunks')
        ->and($template)->toContain('new DataTransfer()')
        ->and($template)->toContain("new Event('change', { bubbles: true })");
});

test('voice-alpine handles permission denied gracefully', function () use ($voice) {
    $template = file_get_contents($voice);
    expect($template)
        ->toContain("e?.name === 'NotAllowedError'")
        ->and($template)->toContain('Microphone permission denied');
});

// ─── autocomplete-alpine ────────────────────────────────────────────

test('autocomplete-alpine wires the full ARIA combobox surface', function () use ($autocomplete) {
    $template = file_get_contents($autocomplete);
    foreach ([
        'role="combobox"',
        'role="listbox"',
        'role="option"',
        'aria-autocomplete="list"',
        ':aria-expanded',
        ':aria-controls',
        ':aria-activedescendant',
        ':aria-selected',
    ] as $needle) {
        expect($template)->toContain($needle);
    }
});

test('autocomplete-alpine supports both URL and inline-array sources', function () use ($autocomplete) {
    $template = file_get_contents($autocomplete);
    expect($template)
        ->toContain("source.kind === 'local'")
        ->and($template)->toContain('filterLocal(source.items, this.query)')
        ->and($template)->toContain('await fetch(url');
});

test('autocomplete-alpine aborts stale requests to prevent race conditions', function () use ($autocomplete) {
    $template = file_get_contents($autocomplete);
    expect($template)
        ->toContain('new AbortController()')
        ->and($template)->toContain('this.aborter.abort()')
        ->and($template)->toContain("e.name === 'AbortError'");
});

test('autocomplete-alpine debounces keystrokes and gates on min chars', function () use ($autocomplete) {
    $template = file_get_contents($autocomplete);
    expect($template)
        ->toContain('@input.debounce.{{ $debounceMs }}ms="onQuery()"')
        ->and($template)->toContain('if (this.query.length < minChars)');
});

test('autocomplete-alpine wires the keyboard nav · arrows + enter + escape', function () use ($autocomplete) {
    $template = file_get_contents($autocomplete);
    foreach ([
        '@keydown.arrow-down.prevent',
        '@keydown.arrow-up.prevent',
        '@keydown.enter.prevent',
        '@keydown.escape.window',
        '@keydown.tab',
    ] as $needle) {
        expect($template)->toContain($needle);
    }
});

test('autocomplete-alpine emits a custom event on selection', function () use ($autocomplete) {
    $template = file_get_contents($autocomplete);
    expect($template)
        ->toContain("'lc-input:autocomplete:selected'");
});

// ─── tags-alpine ────────────────────────────────────────────────────

test('tags-alpine posts an array via name[] hidden inputs', function () use ($tags) {
    $template = file_get_contents($tags);
    expect($template)
        ->toContain(':name="`{{ $name }}[]`"');
});

test('tags-alpine commits on Enter / Tab / configured separators', function () use ($tags) {
    $template = file_get_contents($tags);
    expect($template)
        ->toContain('@keydown.enter.prevent="commit()"')
        ->and($template)->toContain('@keydown.tab')
        ->and($template)->toContain('separators.includes(e.key)');
});

test('tags-alpine deletes the last chip on empty-draft backspace', function () use ($tags) {
    $template = file_get_contents($tags);
    expect($template)
        ->toContain('@keydown.backspace="onBackspace()"')
        ->and($template)->toContain("if (this.draft === '' && this.tags.length > 0)");
});

test('tags-alpine respects allowDuplicates + max caps', function () use ($tags) {
    $template = file_get_contents($tags);
    expect($template)
        ->toContain('canAdd(v)')
        ->and($template)->toContain('if (this.atMax) return false')
        ->and($template)->toContain('this.tags.some((t) => t.toLowerCase() === v.toLowerCase())');
});

test('tags-alpine splits a pasted "a, b, c" into separate chips', function () use ($tags) {
    $template = file_get_contents($tags);
    expect($template)
        ->toContain('@paste="onPaste($event)"')
        ->and($template)->toContain('text.split(new RegExp');
});

// ─── file-multi-alpine ──────────────────────────────────────────────

test('file-multi-alpine accepts drag-drop and click-to-choose', function () use ($fileMulti) {
    $template = file_get_contents($fileMulti);
    expect($template)
        ->toContain('@dragover.prevent')
        ->and($template)->toContain('@drop.prevent="onDrop($event)"')
        ->and($template)->toContain("@change=\"onChoose(\$event)\"");
});

test('file-multi-alpine enforces max-files and max-size client-side', function () use ($fileMulti) {
    $template = file_get_contents($fileMulti);
    expect($template)
        ->toContain('this.files.length >= maxFiles')
        ->and($template)->toContain('f.size > maxSizeMb * 1024 * 1024');
});

test('file-multi-alpine renders image previews via object URLs', function () use ($fileMulti) {
    $template = file_get_contents($fileMulti);
    expect($template)
        ->toContain("f.type.startsWith('image/') ? URL.createObjectURL(f)")
        ->and($template)->toContain('URL.revokeObjectURL(item.previewUrl)');
});

test('file-multi-alpine syncs the underlying file input via DataTransfer', function () use ($fileMulti) {
    $template = file_get_contents($fileMulti);
    expect($template)
        ->toContain('new DataTransfer()')
        ->and($template)->toContain('this.$refs.fileField.files = dt.files');
});

test('file-multi-alpine honours the accept pattern (mime/*, .ext, mime/type)', function () use ($fileMulti) {
    $template = file_get_contents($fileMulti);
    expect($template)
        ->toContain('matchesAccept(file)')
        ->and($template)->toContain("pattern.endsWith('/*')")
        ->and($template)->toContain("pattern.startsWith('.')");
});

// ─── range-alpine ───────────────────────────────────────────────────

test('range-alpine wraps the native input with a tracking value bubble', function () use ($range) {
    $template = file_get_contents($range);
    expect($template)
        ->toContain('class="lc-range-bubble"')
        ->and($template)->toContain(':style="`left:${pct}%;`"');
});

test('range-alpine computes pct from current value vs min / max', function () use ($range) {
    $template = file_get_contents($range);
    expect($template)
        ->toContain('get pct()')
        ->and($template)->toContain('((this.current - min) / (max - min)) * 100');
});

test('range-alpine optionally renders sparse tick marks', function () use ($range) {
    $template = file_get_contents($range);
    expect($template)
        ->toContain('get ticks()')
        ->and($template)->toContain('if (steps > 20) return []');
});

// ─── dual-range-alpine ──────────────────────────────────────────────

test('dual-range-alpine posts paired _min / _max hidden inputs', function () use ($dualRange) {
    $template = file_get_contents($dualRange);
    expect($template)
        ->toContain('name="{{ $minName }}"')
        ->and($template)->toContain('name="{{ $maxName }}"')
        ->and($template)->toContain('$minName ??= $name.\'_min\'')
        ->and($template)->toContain('$maxName ??= $name.\'_max\'');
});

test('dual-range-alpine prevents the two thumbs from crossing', function () use ($dualRange) {
    $template = file_get_contents($dualRange);
    expect($template)
        ->toContain('clamp()')
        ->and($template)->toContain('this.low  = Math.min(this.low,  this.high - step)')
        ->and($template)->toContain('this.high = Math.max(this.high, this.low  + step)');
});

// ─── markdown-alpine ────────────────────────────────────────────────

test('markdown-alpine toggles between write and preview', function () use ($markdown) {
    $template = file_get_contents($markdown);
    expect($template)
        ->toContain("tab === 'write'")
        ->and($template)->toContain("tab === 'preview'")
        ->and($template)->toContain('x-html="renderedHtml"');
});

test('markdown-alpine ships keyboard shortcuts for bold / italic / link', function () use ($markdown) {
    $template = file_get_contents($markdown);
    expect($template)
        ->toContain("if (e.key === 'b')")
        ->and($template)->toContain("if (e.key === 'i')")
        ->and($template)->toContain("if (e.key === 'k')");
});

test('markdown-alpine has a tiny renderer covering common syntax', function () use ($markdown) {
    $template = file_get_contents($markdown);
    foreach ([
        '<strong>',  // bold replacement
        '<em>',      // italic replacement
        '<code>',    // inline code
        '<pre><code>', // code blocks
        '<a href',   // links
        '<h${hashes.length}>', // headers
        '<ul>',      // lists
    ] as $needle) {
        expect($template)->toContain($needle);
    }
});

test('markdown-alpine escapes HTML in user input to stay XSS-safe', function () use ($markdown) {
    $template = file_get_contents($markdown);
    expect($template)
        ->toContain('escape = (s)')
        ->and($template)->toContain("replace(/&/g, '&amp;')")
        ->and($template)->toContain("replace(/</g, '&lt;')");
});

// ─── emoji-alpine ───────────────────────────────────────────────────

test('emoji-alpine renders a picker popup with keyword search', function () use ($emoji) {
    $template = file_get_contents($emoji);
    expect($template)
        ->toContain('class="lc-emoji-grid"')
        ->and($template)->toContain('x-model="query"')
        ->and($template)->toContain('e.k.some((kw) => kw.includes(q))');
});

test('emoji-alpine inserts at the current caret instead of appending', function () use ($emoji) {
    $template = file_get_contents($emoji);
    expect($template)
        ->toContain('input.selectionStart')
        ->and($template)->toContain('input.setSelectionRange(caret, caret)');
});

// ─── copy-alpine ────────────────────────────────────────────────────

test('copy-alpine writes to the clipboard with an execCommand fallback', function () use ($copy) {
    $template = file_get_contents($copy);
    expect($template)
        ->toContain('navigator.clipboard.writeText')
        ->and($template)->toContain("document.execCommand('copy')");
});

test('copy-alpine surfaces a "copied" indicator that auto-clears', function () use ($copy) {
    $template = file_get_contents($copy);
    expect($template)
        ->toContain('this.copied = true')
        ->and($template)->toContain('setTimeout(() => this.copied = false, 1200)');
});

test('copy-alpine masks the value behind dots when mask=true', function () use ($copy) {
    $template = file_get_contents($copy);
    expect($template)
        ->toContain("'•'.repeat(Math.min(value.length, 24))")
        ->and($template)->toContain('revealed = ! revealed');
});

// ─── service provider ──────────────────────────────────────────────

test('service provider loads views under the input:: namespace', function () use ($provider) {
    $source = file_get_contents($provider);
    expect($source)
        ->toContain("loadViewsFrom(__DIR__.'/../resources/views', 'input')")
        ->and($source)->toContain('input-config')
        ->and($source)->toContain('input-views');
});
