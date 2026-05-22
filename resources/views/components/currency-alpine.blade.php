@props([
    /**
     * Posted form name · receives the canonical decimal value (e.g. 1234.56).
     */
    'name',
    'id' => null,
    /**
     * Initial value · numeric or string · displayed formatted, posted raw.
     */
    'value' => null,
    /**
     * BCP 47 locale string · drives separator + decimal style via
     * `Intl.NumberFormat`. Defaults to `input.currency.locale`.
     */
    'locale' => null,
    /**
     * ISO 4217 currency code · drives the symbol prefix. Defaults to
     * `input.currency.currency`.
     */
    'currency' => null,
    /**
     * Decimal places · usually 2 · 0 for JPY / KRW. Defaults to
     * `input.currency.decimals`.
     */
    'decimals' => null,
    /**
     * Optional min / max in canonical decimal units · enforced on blur.
     */
    'min' => null,
    'max' => null,
    'placeholder' => null,
    'required' => false,
])

@php
    $id ??= $name;
    $locale   ??= config('input.currency.locale', 'en-GB');
    $currency ??= config('input.currency.currency', 'GBP');
    $decimals ??= (int) config('input.currency.decimals', 2);
@endphp

<div
    x-data="lcInputCurrency({
        initial: @js($value !== null ? (string) $value : ''),
        locale: @js($locale),
        currency: @js($currency),
        decimals: @js((int) $decimals),
        min: @js($min !== null ? (float) $min : null),
        max: @js($max !== null ? (float) $max : null),
    })"
    x-init="init()"
    class="lc-input lc-input--currency {{ $attributes->get('class') }}"
    {{ $attributes->except('class')->merge(['data-component' => 'input::currency']) }}
>
    <div class="lc-input-row">
        <span class="lc-input-affix lc-input-affix--prefix" x-text="symbol"></span>
        <input
            type="text"
            id="{{ $id }}-display"
            inputmode="decimal"
            x-ref="display"
            x-model="display"
            @input="onInput($event)"
            @blur="onBlur()"
            @if ($placeholder) placeholder="{{ $placeholder }}" @endif
            autocomplete="off"
        >
    </div>

    {{-- Server receives the canonical numeric string · "1234.56" not
         "£1,234.56" · so standard `decimal:2` / `numeric` rules work. --}}
    <input type="hidden"
           name="{{ $name }}"
           x-model="raw"
           @if ($required) required @endif>
</div>

@once
    @push('scripts')
        <script>
            window.lcInputCurrency = function ({ initial, locale, currency, decimals, min, max }) {
                const fmt = new Intl.NumberFormat(locale, {
                    style: 'decimal', // We add the symbol separately so it
                                       // stays outside the editable field.
                    minimumFractionDigits: decimals,
                    maximumFractionDigits: decimals,
                    useGrouping: true,
                });
                // Discover the locale's decimal + group separators by
                // formatting a known number and reading the parts.
                const parts = new Intl.NumberFormat(locale).formatToParts(12345.6);
                const decimal = parts.find((p) => p.type === 'decimal')?.value || '.';
                const group   = parts.find((p) => p.type === 'group')?.value || ',';
                // Currency symbol via NumberFormat 'currency' style.
                const symbol = (() => {
                    const cp = new Intl.NumberFormat(locale, { style: 'currency', currency })
                        .formatToParts(0)
                        .find((p) => p.type === 'currency');
                    return cp ? cp.value : currency;
                })();

                return {
                    raw: '',
                    display: '',
                    symbol,
                    init() {
                        if (initial !== '' && initial !== null) {
                            const num = parseFloat(initial);
                            if (! isNaN(num)) {
                                this.raw = num.toFixed(decimals);
                                this.display = fmt.format(num);
                            }
                        }
                    },
                    onInput(e) {
                        // Accept digits + the locale's decimal separator ·
                        // strip everything else as the user types so paste
                        // of "£1,234.56" cleans up to "1234.56" raw.
                        const cleaned = e.target.value
                            .split('')
                            .filter((c) => /\d/.test(c) || c === decimal || c === '-')
                            .join('');
                        const asEnglish = cleaned.replace(decimal, '.');
                        const num = parseFloat(asEnglish);
                        if (isNaN(num)) {
                            this.raw = '';
                            return;
                        }
                        this.raw = num.toFixed(decimals);
                        // Don't reformat the display while typing · waits
                        // until blur so the caret doesn't jump. Just keep
                        // the user's visible string in display.
                        this.display = cleaned;
                    },
                    onBlur() {
                        if (this.raw === '') {
                            this.display = '';
                            return;
                        }
                        let num = parseFloat(this.raw);
                        if (min !== null && num < min) num = min;
                        if (max !== null && num > max) num = max;
                        this.raw = num.toFixed(decimals);
                        this.display = fmt.format(num);
                    },
                };
            };
        </script>
    @endpush
    @push('scripts')
        <style>
            .lc-input--currency .lc-input-row {
                display: flex;
                align-items: stretch;
                background: var(--lc-input-bg-resolved, var(--surface-2, #1E1F22));
                border: 1px solid var(--lc-input-border-resolved, var(--line, #3A3D40));
                border-radius: var(--lc-input-radius-resolved, .5rem);
                overflow: hidden;
            }
            .lc-input--currency .lc-input-row:focus-within {
                border-color: var(--lc-input-accent-resolved, var(--accent, #2C66E8));
            }
            .lc-input--currency .lc-input-affix--prefix {
                display: inline-flex;
                align-items: center;
                padding: 0 .75rem;
                color: var(--lc-input-ink-dim-resolved, var(--ink-dim, #A3A099));
                border-right: 1px solid var(--lc-input-border-resolved, var(--line, #3A3D40));
                font-variant-numeric: tabular-nums;
            }
            .lc-input--currency input[type="text"] {
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
