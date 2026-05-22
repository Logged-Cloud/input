@props([
    'name',
    'id' => null,
    /**
     * Initial state · the string value of the hidden input determines on/off
     * by comparing against `onValue`.
     */
    'value' => null,
    /**
     * Posted value when the toggle is on. Defaults to `input.toggle.on_value`.
     */
    'onValue' => null,
    /**
     * Posted value when the toggle is off. Defaults to `input.toggle.off_value`.
     */
    'offValue' => null,
    /**
     * Optional labels next to the switch · pass either, both, or neither.
     */
    'onLabel' => null,
    'offLabel' => null,
    'disabled' => false,
    'required' => false,
])

@php
    $id ??= $name;
    $onValue  ??= config('input.toggle.on_value', '1');
    $offValue ??= config('input.toggle.off_value', '0');
    // Default to off when no value provided. We compare strings so '0' and
    // 'off' both register correctly.
    $value ??= $offValue;
    $initialOn = (string) $value === (string) $onValue;
@endphp

<div
    x-data="lcInputToggle({
        on: @js($initialOn),
        onValue: @js((string) $onValue),
        offValue: @js((string) $offValue),
        disabled: @js((bool) $disabled),
    })"
    class="lc-input lc-input--toggle {{ $attributes->get('class') }}"
    {{ $attributes->except('class')->merge(['data-component' => 'input::toggle']) }}
>
    <label class="lc-toggle-row" :data-on="on" :data-disabled="disabled">
        @if ($offLabel)
            <span class="lc-toggle-label lc-toggle-label--off">{{ $offLabel }}</span>
        @endif

        <button
            type="button"
            role="switch"
            :aria-checked="on ? 'true' : 'false'"
            :aria-disabled="disabled"
            :disabled="disabled"
            @click="toggle()"
            @keydown.space.prevent="toggle()"
            @keydown.enter.prevent="toggle()"
            class="lc-toggle-track"
            :class="on ? 'is-on' : ''"
        >
            <span class="lc-toggle-thumb"></span>
        </button>

        @if ($onLabel)
            <span class="lc-toggle-label lc-toggle-label--on">{{ $onLabel }}</span>
        @endif
    </label>

    <input type="hidden"
           id="{{ $id }}"
           name="{{ $name }}"
           x-model="postedValue"
           @if ($required) required @endif>
</div>

@once
    @push('scripts')
        <script>
            window.lcInputToggle = function ({ on, onValue, offValue, disabled }) {
                return {
                    on,
                    disabled,
                    get postedValue() { return this.on ? onValue : offValue; },
                    toggle() {
                        if (this.disabled) return;
                        this.on = ! this.on;
                        // Bubble a change event so wire:model / onchange
                        // listeners on the hidden input see the new value.
                        this.$nextTick(() => {
                            const input = this.$root.querySelector('input[type="hidden"]');
                            if (input) input.dispatchEvent(new Event('change', { bubbles: true }));
                        });
                    },
                };
            };
        </script>
    @endpush
    @push('scripts')
        <style>
            .lc-input--toggle .lc-toggle-row {
                display: inline-flex;
                align-items: center;
                gap: .65rem;
                cursor: pointer;
                user-select: none;
            }
            .lc-input--toggle .lc-toggle-row[data-disabled="true"] {
                cursor: not-allowed;
                opacity: .55;
            }
            .lc-input--toggle .lc-toggle-track {
                position: relative;
                width: 2.6rem;
                height: 1.45rem;
                padding: 0;
                border: 0;
                border-radius: 999px;
                background: var(--lc-input-border-resolved, var(--line, #3A3D40));
                cursor: pointer;
                transition: background-color .15s ease;
                outline: none;
                flex: 0 0 auto;
            }
            .lc-input--toggle .lc-toggle-track:focus-visible {
                box-shadow: 0 0 0 3px color-mix(in srgb, var(--lc-input-accent-resolved, #2C66E8) 30%, transparent);
            }
            .lc-input--toggle .lc-toggle-track.is-on {
                background: var(--lc-input-accent-resolved, var(--accent, #2C66E8));
            }
            .lc-input--toggle .lc-toggle-thumb {
                position: absolute;
                top: 50%;
                left: .15rem;
                transform: translateY(-50%);
                width: 1.15rem;
                height: 1.15rem;
                border-radius: 50%;
                background: white;
                box-shadow: 0 1px 2px rgba(0, 0, 0, .25);
                transition: transform .15s ease;
            }
            .lc-input--toggle .lc-toggle-track.is-on .lc-toggle-thumb {
                transform: translateY(-50%) translateX(1.15rem);
            }
            .lc-input--toggle .lc-toggle-label {
                font-size: .9rem;
                color: var(--lc-input-ink-dim-resolved, var(--ink-dim, #A3A099));
            }
            .lc-input--toggle .lc-toggle-row[data-on="true"] .lc-toggle-label--on,
            .lc-input--toggle .lc-toggle-row[data-on="false"] .lc-toggle-label--off {
                color: var(--lc-input-ink-resolved, var(--ink, #F0EDE5));
            }
        </style>
    @endpush
@endonce
