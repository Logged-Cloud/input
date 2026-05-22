@props([
    /**
     * Posted form name · stores the E.164 canonical (e.g. "+447123456789").
     */
    'name' => 'phone',
    'id' => null,
    /**
     * Initial value · E.164 string · the component parses out the country
     * code prefix and lays the remaining digits across the visible field.
     */
    'value' => '',
    /**
     * Initial country selection · ISO 3166-1 alpha-2 (e.g. "GB", "US").
     * Defaults to `input.mobile.default_country`.
     */
    'country' => null,
    /**
     * Subset of supported countries · array of ISO alpha-2 codes. Defaults
     * to the curated short list shipped in the JS factory.
     */
    'countries' => null,
    'required' => false,
    'placeholder' => null,
    'autocomplete' => 'tel',
])

@php
    $id ??= $name;
    $country ??= config('input.mobile.default_country', 'GB');
    // null means "use the built-in short list"; array overrides it.
    $countriesJs = $countries !== null ? json_encode($countries) : 'null';
@endphp

<div
    x-data="lcInputMobile({
        initial: @js((string) $value),
        country: @js((string) $country),
        countries: {{ $countriesJs }},
    })"
    x-init="init()"
    class="lc-input lc-input--mobile {{ $attributes->get('class') }}"
    {{ $attributes->except('class')->merge(['data-component' => 'input::mobile']) }}
>
    <div class="lc-input-row">
        {{-- Country picker · narrow dropdown showing flag emoji + dial code.
             Selecting changes the prefix used by the E.164 normaliser. --}}
        <select x-model="selected" @change="onCountryChange()"
                class="lc-mobile-country" :aria-label="`Country code (${current.dial})`">
            <template x-for="c in supported" :key="c.iso">
                <option :value="c.iso" x-text="`${c.flag} ${c.dial}`"></option>
            </template>
        </select>

        <input
            type="tel"
            id="{{ $id }}-display"
            inputmode="tel"
            x-ref="display"
            x-model="display"
            @input="onInput($event)"
            @blur="onBlur()"
            autocomplete="{{ $autocomplete }}"
            @if ($placeholder) placeholder="{{ $placeholder }}" @endif
        >
    </div>

    {{-- Canonical E.164 (+CC followed by digits). Standard validation rules
         can introspect this without parsing the display form. --}}
    <input type="hidden"
           name="{{ $name }}"
           x-model="e164"
           @if ($required) required @endif>
</div>

@once
    @push('scripts')
        <script>
            window.lcInputMobile = function ({ initial, country, countries }) {
                // Curated short list · covers most users out of the box ·
                // pass `countries=['GB','US','CA','AU',...]` to override.
                // dial = international dialling code, mask = visible
                // formatting pattern (digit chunks separated by spaces).
                const all = [
                    { iso: 'GB', flag: '🇬🇧', dial: '+44', mask: 'XXXX XXXXXX' },
                    { iso: 'US', flag: '🇺🇸', dial: '+1',  mask: '(XXX) XXX-XXXX' },
                    { iso: 'CA', flag: '🇨🇦', dial: '+1',  mask: '(XXX) XXX-XXXX' },
                    { iso: 'IE', flag: '🇮🇪', dial: '+353', mask: 'XX XXX XXXX' },
                    { iso: 'AU', flag: '🇦🇺', dial: '+61', mask: 'XXX XXX XXX' },
                    { iso: 'NZ', flag: '🇳🇿', dial: '+64', mask: 'XX XXX XXXX' },
                    { iso: 'FR', flag: '🇫🇷', dial: '+33', mask: 'X XX XX XX XX' },
                    { iso: 'DE', flag: '🇩🇪', dial: '+49', mask: 'XXX XXXXXXX' },
                    { iso: 'ES', flag: '🇪🇸', dial: '+34', mask: 'XXX XXX XXX' },
                    { iso: 'IT', flag: '🇮🇹', dial: '+39', mask: 'XXX XXX XXXX' },
                    { iso: 'NL', flag: '🇳🇱', dial: '+31', mask: 'X XXXXXXXX' },
                    { iso: 'PT', flag: '🇵🇹', dial: '+351', mask: 'XXX XXX XXX' },
                    { iso: 'JP', flag: '🇯🇵', dial: '+81', mask: 'XX XXXX XXXX' },
                ];
                const supported = countries
                    ? all.filter((c) => countries.includes(c.iso))
                    : all;

                return {
                    supported,
                    selected: country,
                    display: '',
                    e164: '',
                    get current() {
                        return this.supported.find((c) => c.iso === this.selected) || this.supported[0];
                    },
                    init() {
                        if (initial) {
                            // Try to detect the country from the E.164 prefix.
                            const match = this.supported.find((c) => initial.startsWith(c.dial));
                            if (match) {
                                this.selected = match.iso;
                                const rest = initial.slice(match.dial.length);
                                this.display = this.formatMasked(rest);
                                this.e164 = match.dial + rest.replace(/\D/g, '');
                                return;
                            }
                        }
                        this.recompute();
                    },
                    onInput() { this.recompute(); },
                    onBlur() { this.recompute(); this.display = this.formatMasked(this.rawDigits()); },
                    onCountryChange() {
                        // Country flip · reformat the existing digits under
                        // the new mask but keep the same raw input.
                        this.display = this.formatMasked(this.rawDigits());
                        this.recompute();
                    },
                    rawDigits() {
                        return this.display.replace(/\D/g, '');
                    },
                    formatMasked(digits) {
                        // Walk the X-pattern and the digits in lockstep.
                        // Anything in the pattern that isn't X is a
                        // literal (space, dash, parens) we inject as we go.
                        const mask = this.current.mask;
                        let out = '';
                        let di = 0;
                        for (const ch of mask) {
                            if (di >= digits.length) break;
                            if (ch === 'X') {
                                out += digits[di++];
                            } else {
                                out += ch;
                            }
                        }
                        if (di < digits.length) out += digits.slice(di);
                        return out;
                    },
                    recompute() {
                        const raw = this.rawDigits();
                        this.e164 = raw ? this.current.dial + raw : '';
                    },
                };
            };
        </script>
    @endpush
    @push('scripts')
        <style>
            .lc-input--mobile .lc-input-row {
                display: flex;
                background: var(--lc-input-bg-resolved, var(--surface-2, #1E1F22));
                border: 1px solid var(--lc-input-border-resolved, var(--line, #3A3D40));
                border-radius: var(--lc-input-radius-resolved, .5rem);
                overflow: hidden;
            }
            .lc-input--mobile .lc-input-row:focus-within {
                border-color: var(--lc-input-accent-resolved, var(--accent, #2C66E8));
            }
            .lc-input--mobile .lc-mobile-country {
                background: transparent;
                color: var(--lc-input-ink-resolved, var(--ink, #F0EDE5));
                border: 0;
                border-right: 1px solid var(--lc-input-border-resolved, var(--line, #3A3D40));
                padding: 0 .5rem;
                outline: none;
                cursor: pointer;
                font: inherit;
            }
            .lc-input--mobile input[type="tel"] {
                flex: 1;
                background: transparent;
                color: var(--lc-input-ink-resolved, var(--ink, #F0EDE5));
                border: 0;
                outline: none;
                padding: .65rem .75rem;
                font: inherit;
                font-variant-numeric: tabular-nums;
                width: 100%;
            }
        </style>
    @endpush
@endonce
