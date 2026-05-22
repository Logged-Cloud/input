@props([
    'name' => 'code',
    /**
     * Number of boxes · also the length of the posted value. Defaults to
     * `input.otp.length` (6 out of the box).
     */
    'length' => null,
    /**
     * Allowed character class for each box · digits-only by default.
     * Set to `[A-Za-z0-9]` for codes that mix letters.
     */
    'pattern' => null,
    'autocomplete' => 'one-time-code',
    'required' => false,
])

@php
    $length ??= (int) config('input.otp.length', 6);
    $pattern ??= config('input.otp.pattern', '\d');
@endphp

<div
    x-data="lcInputOtp({
        length: @js((int) $length),
        pattern: @js($pattern),
    })"
    x-init="init()"
    class="lc-input lc-input--otp {{ $attributes->get('class') }}"
    {{ $attributes->except('class')->merge(['data-component' => 'input::otp']) }}
>
    <div class="lc-otp-boxes" role="group" aria-label="One-time code">
        <template x-for="(digit, i) in digits" :key="i">
            <input
                type="text"
                inputmode="numeric"
                maxlength="1"
                autocomplete="{{ $autocomplete }}"
                :aria-label="`Digit ${i + 1}`"
                x-model="digits[i]"
                @input="onInput(i, $event)"
                @keydown.backspace="onBackspace(i, $event)"
                @keydown.arrow-left="focusBox(i - 1)"
                @keydown.arrow-right="focusBox(i + 1)"
                @paste="onPaste($event)"
                :ref="`box${i}`">
        </template>
    </div>

    {{-- Server posts the concatenated value via this hidden input. --}}
    <input type="hidden" name="{{ $name }}" x-model="value" @if ($required) required @endif>
</div>

@once
    @push('scripts')
        <script>
            window.lcInputOtp = function ({ length, pattern }) {
                const allow = new RegExp(`^${pattern}$`);
                return {
                    digits: Array.from({ length }, () => ''),
                    get value() { return this.digits.join(''); },
                    init() {
                        this.$nextTick(() => this.focusBox(0));
                    },
                    onInput(i, e) {
                        const ch = e.target.value.slice(-1);
                        if (ch && ! allow.test(ch)) {
                            // Reject non-matching characters · zero the box.
                            this.digits[i] = '';
                            e.target.value = '';
                            return;
                        }
                        this.digits[i] = ch;
                        if (ch) this.focusBox(i + 1);
                    },
                    onBackspace(i, e) {
                        // Empty current box · jump back to the previous one
                        // so the user can clear right-to-left without
                        // clicking. If the current box already has content,
                        // let the native backspace clear it first.
                        if (this.digits[i]) return;
                        e.preventDefault();
                        if (i > 0) {
                            this.digits[i - 1] = '';
                            this.focusBox(i - 1);
                        }
                    },
                    onPaste(e) {
                        // Spread a pasted code across the boxes from where
                        // the caret is. SMS auto-fill on iOS / Android lands
                        // here as a single paste of the full code.
                        const text = (e.clipboardData || window.clipboardData)?.getData('text') || '';
                        const cleaned = text.split('').filter((c) => allow.test(c)).slice(0, length);
                        if (! cleaned.length) return;
                        e.preventDefault();
                        cleaned.forEach((c, idx) => this.digits[idx] = c);
                        this.focusBox(Math.min(cleaned.length, length - 1));
                    },
                    focusBox(i) {
                        const ref = this.$refs[`box${Math.max(0, Math.min(length - 1, i))}`];
                        if (ref) ref.focus();
                    },
                };
            };
        </script>
    @endpush
@endonce
