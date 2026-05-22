@props([
    /**
     * Posted form name · the concatenated value lands here.
     */
    'name' => 'code',
    /**
     * Number of boxes · also the length of the posted value. Defaults to
     * `input.otp.length` (6 out of the box). Works for any positive int ·
     * 4-digit pins, 6-digit TOTP, 8-character backup codes, etc.
     */
    'length' => null,
    /**
     * Allowed character class for each box · digits-only by default. Set
     * to `[A-Za-z0-9]` for codes that mix letters.
     */
    'pattern' => null,
    /**
     * Visual grouping · an array of group sizes that sums to length,
     * e.g. `[3, 3]` renders "[ ][ ][ ] - [ ][ ][ ]" for a 6-digit code.
     * Defaults to no grouping (one continuous row).
     */
    'groups' => null,
    /**
     * Mask the visible characters (show • instead of the typed digits ·
     * useful for secrets that should not shoulder-surf as the user types).
     */
    'mask' => false,
    /**
     * Auto-submit the closest <form> when every box is filled. Defaults
     * to `input.otp.auto_submit` (true out of the box).
     */
    'autoSubmit' => null,
    /**
     * Focus the first box on mount. Defaults to `input.otp.auto_focus`.
     */
    'autoFocus' => null,
    /**
     * Error state · adds aria-invalid + a danger outline to every box.
     */
    'error' => false,
    'disabled' => false,
    'autocomplete' => 'one-time-code',
    'required' => false,
    'id' => null,
])

@php
    $id ??= $name;
    $length     ??= (int) config('input.otp.length', 6);
    $pattern    ??= config('input.otp.pattern', '\d');
    $autoSubmit ??= (bool) config('input.otp.auto_submit', true);
    $autoFocus  ??= (bool) config('input.otp.auto_focus', true);
    $groupsJs   = $groups ? json_encode($groups) : 'null';
    // inputmode: 'numeric' when the pattern accepts only digits, otherwise
    // 'text' (so iOS keyboards stay sensible for letter codes).
    $inputmode = preg_match('/^\\\\d|0-9|\[0-9\]$/', $pattern) ? 'numeric' : 'text';
@endphp

<div
    x-data="lcInputOtp({
        length: @js((int) $length),
        pattern: @js($pattern),
        groups: {{ $groupsJs }},
        mask: @js((bool) $mask),
        autoSubmit: @js((bool) $autoSubmit),
        autoFocus: @js((bool) $autoFocus),
        disabled: @js((bool) $disabled),
    })"
    x-init="init()"
    class="lc-input lc-input--otp {{ $attributes->get('class') }}"
    :data-error="error"
    {{ $attributes->except('class')->merge([
        'data-component' => 'input::otp',
        'data-error' => $error ? 'true' : 'false',
    ]) }}
>
    <div class="lc-otp-boxes" role="group" :aria-disabled="disabled" aria-label="One-time code">
        <template x-for="(slot, i) in slots" :key="i">
            <template x-if="slot.kind === 'box'">
                <input
                    :type="mask ? 'password' : 'text'"
                    inputmode="{{ $inputmode }}"
                    maxlength="1"
                    autocomplete="{{ $autocomplete }}"
                    :aria-label="`Digit ${slot.index + 1}`"
                    :aria-invalid="@js($error) ? 'true' : 'false'"
                    :disabled="disabled"
                    x-model="digits[slot.index]"
                    @input="onInput(slot.index, $event)"
                    @keydown.backspace="onBackspace(slot.index, $event)"
                    @keydown.arrow-left.prevent="focusBox(slot.index - 1)"
                    @keydown.arrow-right.prevent="focusBox(slot.index + 1)"
                    @keydown.home.prevent="focusBox(0)"
                    @keydown.end.prevent="focusBox(length - 1)"
                    @focus="onFocus(slot.index, $event)"
                    @paste="onPaste($event)"
                    :ref="`box${slot.index}`"
                    class="lc-otp-box"
                    :data-filled="!! digits[slot.index]"
                >
            </template>
            <template x-if="slot.kind === 'sep'">
                <span class="lc-otp-sep" aria-hidden="true">·</span>
            </template>
        </template>
    </div>

    {{-- Server posts the concatenated value via this hidden input. The
         model `value` getter is kept in sync with every keystroke. --}}
    <input
        type="hidden"
        id="{{ $id }}"
        name="{{ $name }}"
        x-model="value"
        @if ($required) required @endif
    >
</div>

@once
    @push('scripts')
        <script>
            window.lcInputOtp = function ({ length, pattern, groups, mask, autoSubmit, autoFocus, disabled }) {
                const allow = new RegExp(`^${pattern}$`);

                // Build slot list once · a mix of boxes and separators in
                // visual order so the template can iterate one collection.
                // Without groups: [box0, box1, …, boxN]
                // With groups[3,3]: [box0,box1,box2, sep, box3,box4,box5]
                const slots = [];
                let cursor = 0;
                if (groups && groups.reduce((s, n) => s + n, 0) === length) {
                    groups.forEach((size, gi) => {
                        for (let k = 0; k < size; k++) {
                            slots.push({ kind: 'box', index: cursor++ });
                        }
                        if (gi < groups.length - 1) slots.push({ kind: 'sep' });
                    });
                } else {
                    for (let i = 0; i < length; i++) slots.push({ kind: 'box', index: i });
                }

                return {
                    digits: Array.from({ length }, () => ''),
                    slots,
                    length,
                    mask,
                    disabled,
                    /** Concatenated value for the hidden input. */
                    get value() { return this.digits.join(''); },
                    get isComplete() { return this.digits.every((d) => d !== ''); },
                    init() {
                        if (autoFocus && ! disabled) {
                            this.$nextTick(() => this.focusBox(this.firstEmpty()));
                        }
                    },
                    firstEmpty() {
                        const i = this.digits.findIndex((d) => d === '');
                        return i === -1 ? 0 : i;
                    },
                    onInput(i, e) {
                        // Browsers report the full value · take the last
                        // typed character so retyping over a filled box
                        // replaces in place rather than rejecting.
                        const ch = e.target.value.slice(-1);
                        if (ch && ! allow.test(ch)) {
                            this.digits[i] = '';
                            e.target.value = '';
                            return;
                        }
                        this.digits[i] = ch;
                        if (ch) this.focusBox(i + 1);
                        this.maybeSubmit();
                    },
                    onBackspace(i, e) {
                        // Empty current box · jump back to clear the prior
                        // one. If the current box already has content, the
                        // native backspace clears it first.
                        if (this.digits[i]) return;
                        e.preventDefault();
                        if (i > 0) {
                            this.digits[i - 1] = '';
                            this.focusBox(i - 1);
                        }
                    },
                    onFocus(i, e) {
                        // Select existing text so retyping replaces rather
                        // than appends · matches the iOS / Authenticator UX.
                        if (e.target?.select) e.target.select();
                    },
                    onPaste(e) {
                        // SMS-autofill on iOS / Android lands here as a
                        // single paste of the full code. Also handles users
                        // copying a code with spaces or dashes from email
                        // ("123 456" / "123-456") · we strip non-pattern
                        // characters before spreading.
                        const text = (e.clipboardData || window.clipboardData)?.getData('text') || '';
                        const cleaned = text.split('').filter((c) => allow.test(c)).slice(0, length);
                        if (! cleaned.length) return;
                        e.preventDefault();
                        cleaned.forEach((c, idx) => this.digits[idx] = c);
                        this.focusBox(Math.min(cleaned.length, length - 1));
                        this.maybeSubmit();
                    },
                    maybeSubmit() {
                        if (! autoSubmit || ! this.isComplete) return;
                        // Find the closest <form> ancestor and submit it
                        // via requestSubmit so HTML5 validation still runs.
                        // Also fires a custom event for non-form consumers
                        // (Livewire, dialogs, etc).
                        this.$nextTick(() => {
                            this.$root.dispatchEvent(new CustomEvent('lc-input:otp:complete', {
                                detail: { value: this.value }, bubbles: true,
                            }));
                            const form = this.$root.closest('form');
                            if (form) {
                                form.requestSubmit ? form.requestSubmit() : form.submit();
                            }
                        });
                    },
                    focusBox(i) {
                        if (this.disabled) return;
                        const clamped = Math.max(0, Math.min(length - 1, i));
                        const ref = this.$refs[`box${clamped}`];
                        if (ref) ref.focus();
                    },
                };
            };
        </script>
    @endpush
    @push('scripts')
        <style>
            .lc-input--otp .lc-otp-boxes {
                display: flex;
                align-items: center;
                gap: .5rem;
                flex-wrap: nowrap;
            }
            .lc-input--otp .lc-otp-box {
                width: 2.75rem;
                height: 3.25rem;
                text-align: center;
                font-size: 1.5rem;
                font-weight: 600;
                font-variant-numeric: tabular-nums;
                background: var(--lc-input-bg-resolved, var(--surface-2, #1E1F22));
                color: var(--lc-input-ink-resolved, var(--ink, #F0EDE5));
                border: 1px solid var(--lc-input-border-resolved, var(--line, #3A3D40));
                border-radius: var(--lc-input-radius-resolved, .5rem);
                outline: none;
                transition: border-color .12s ease, box-shadow .12s ease;
                padding: 0;
                caret-color: transparent;
            }
            .lc-input--otp .lc-otp-box:focus {
                border-color: var(--lc-input-accent-resolved, var(--accent, #2C66E8));
                box-shadow: 0 0 0 3px color-mix(in srgb, var(--lc-input-accent-resolved, #2C66E8) 25%, transparent);
            }
            .lc-input--otp .lc-otp-box[data-filled="true"] {
                border-color: var(--lc-input-ink-dim-resolved, #A3A099);
            }
            .lc-input--otp .lc-otp-box:disabled {
                opacity: .5;
                cursor: not-allowed;
            }
            .lc-input--otp[data-error="true"] .lc-otp-box {
                border-color: var(--lc-input-danger-resolved, var(--danger, #ef4444));
            }
            .lc-input--otp .lc-otp-sep {
                color: var(--lc-input-ink-dim-resolved, #A3A099);
                opacity: .5;
                user-select: none;
            }
            @media (max-width: 480px) {
                .lc-input--otp .lc-otp-box {
                    width: 2.25rem;
                    height: 2.75rem;
                    font-size: 1.25rem;
                }
            }
        </style>
    @endpush
@endonce
