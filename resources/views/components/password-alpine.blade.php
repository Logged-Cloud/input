@props([
    'name' => 'password',
    'id' => null,
    'value' => '',
    /**
     * Whether to show the eye-toggle that reveals the typed password.
     * Defaults to `input.password.reveal`.
     */
    'reveal' => null,
    /**
     * Whether to render the strength meter under the input.
     */
    'strength' => true,
    /**
     * Minimum length the strength meter considers "ok". Defaults to
     * `input.password.min_length`.
     */
    'minLength' => null,
    'required' => false,
    'autocomplete' => 'new-password',
    'placeholder' => null,
])

@php
    $id ??= $name;
    $reveal ??= config('input.password.reveal', true);
    $minLength ??= config('input.password.min_length', 8);
@endphp

<div
    x-data="lcInputPassword({
        initial: @js($value),
        showMeter: @js((bool) $strength),
        minLength: @js((int) $minLength),
    })"
    x-init="init()"
    class="lc-input lc-input--password {{ $attributes->get('class') }}"
    {{ $attributes->except('class')->merge(['data-component' => 'input::password']) }}
>
    <div class="lc-input-row">
        <input
            :type="visible ? 'text' : 'password'"
            id="{{ $id }}"
            name="{{ $name }}"
            x-model="value"
            @input="recompute()"
            autocomplete="{{ $autocomplete }}"
            @if ($placeholder) placeholder="{{ $placeholder }}" @endif
            @if ($required) required @endif
        >
        @if ($reveal)
            <button type="button"
                    @click="visible = ! visible"
                    :aria-label="visible ? 'Hide password' : 'Show password'"
                    :title="visible ? 'Hide password' : 'Show password'"
                    class="lc-input-affix">
                <svg x-show="! visible" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                     stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                     style="width:1.1rem;height:1.1rem;">
                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                    <circle cx="12" cy="12" r="3"/>
                </svg>
                <svg x-show="visible" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                     stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                     style="width:1.1rem;height:1.1rem;">
                    <path d="M17.94 17.94A10.94 10.94 0 0 1 12 20c-7 0-11-8-11-8a19 19 0 0 1 5.06-5.94"/>
                    <path d="M9.9 5.08A11 11 0 0 1 12 5c7 0 11 8 11 8a19 19 0 0 1-2.16 3.19"/>
                    <line x1="1" y1="1" x2="23" y2="23"/>
                </svg>
            </button>
        @endif
    </div>

    @if ($strength)
        <div class="lc-strength-bar" :data-score="score">
            <span :style="{ width: (score / 4 * 100) + '%' }"></span>
        </div>
        <p class="lc-strength-label" x-text="label" :data-score="score"></p>
    @endif
</div>

@once
    @push('scripts')
        <script>
            window.lcInputPassword = function ({ initial, showMeter, minLength }) {
                return {
                    value: initial || '',
                    visible: false,
                    score: 0,
                    label: '',
                    init() {
                        if (showMeter) this.recompute();
                    },
                    recompute() {
                        // Heuristic · same approach as Bitwarden's quick
                        // estimator · count categories (lower, upper, digit,
                        // symbol) and reward length. Not a substitute for
                        // zxcvbn, which is 200KB · this stays in-tree.
                        const v = this.value;
                        if (! v) { this.score = 0; this.label = ''; return; }
                        let s = 0;
                        if (v.length >= minLength) s++;
                        if (v.length >= minLength + 4) s++;
                        const cats = [/[a-z]/, /[A-Z]/, /\d/, /[^A-Za-z0-9]/]
                            .reduce((n, re) => n + (re.test(v) ? 1 : 0), 0);
                        if (cats >= 3) s++;
                        if (cats === 4 && v.length >= minLength + 4) s++;
                        this.score = Math.min(s, 4);
                        this.label = ['Too short', 'Weak', 'Fair', 'Good', 'Strong'][this.score];
                    },
                };
            };
        </script>
    @endpush
@endonce
