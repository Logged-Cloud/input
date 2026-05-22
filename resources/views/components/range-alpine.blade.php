@props([
    'name',
    'id' => null,
    'value' => null,
    'min' => 0,
    'max' => 100,
    /** Step granularity · defaults to `input.range.step`. */
    'step' => null,
    /** Format the bubble · supports {value}, {pct} placeholders. */
    'format' => '{value}',
    /** Tick marks at each step (sparse for small ranges). */
    'ticks' => false,
    'required' => false,
])

@php
    $id ??= $name;
    $step ??= config('input.range.step', 1);
    $value ??= $min;
@endphp

<div
    x-data="lcInputRange({
        initial: @js((float) $value),
        min: @js((float) $min),
        max: @js((float) $max),
        step: @js((float) $step),
        format: @js((string) $format),
    })"
    x-init="init()"
    class="lc-input lc-input--range {{ $attributes->get('class') }}"
    {{ $attributes->except('class')->merge(['data-component' => 'input::range']) }}
>
    <div class="lc-range-stage">
        <div class="lc-range-track">
            <div class="lc-range-fill" :style="`width:${pct}%;`"></div>
        </div>
        <input
            type="range"
            id="{{ $id }}"
            name="{{ $name }}"
            min="{{ $min }}"
            max="{{ $max }}"
            step="{{ $step }}"
            x-model.number="current"
            @input="onInput()"
            @if ($required) required @endif
        >
        {{-- Value bubble · floats along with the thumb. --}}
        <span class="lc-range-bubble" :style="`left:${pct}%;`" x-text="label"></span>
    </div>
    @if ($ticks)
        <div class="lc-range-ticks" aria-hidden="true">
            <template x-for="t in ticks" :key="t">
                <span class="lc-range-tick" :style="`left:${tickPct(t)}%;`"></span>
            </template>
        </div>
    @endif
</div>

@once
    @push('scripts')
        <script>
            window.lcInputRange = function ({ initial, min, max, step, format }) {
                return {
                    current: initial,
                    min, max, step,
                    init() {},
                    get pct() {
                        if (max === min) return 0;
                        return ((this.current - min) / (max - min)) * 100;
                    },
                    get label() {
                        return format
                            .replace('{value}', this.current)
                            .replace('{pct}', Math.round(this.pct));
                    },
                    get ticks() {
                        const steps = Math.floor((max - min) / step);
                        if (steps > 20) return [];
                        return Array.from({ length: steps + 1 }, (_, i) => min + i * step);
                    },
                    tickPct(v) {
                        return ((v - min) / (max - min)) * 100;
                    },
                    onInput() {
                        this.$root.dispatchEvent(new CustomEvent('lc-input:range:changed', {
                            detail: { value: this.current }, bubbles: true,
                        }));
                    },
                };
            };
        </script>
    @endpush
    @push('scripts')
        <style>
            .lc-input--range .lc-range-stage {
                position: relative;
                padding: 1.75rem .5rem .35rem;
            }
            .lc-input--range .lc-range-track {
                position: relative;
                height: .35rem;
                background: var(--lc-input-border-resolved, var(--line, #3A3D40));
                border-radius: 999px;
            }
            .lc-input--range .lc-range-fill {
                height: 100%;
                background: var(--lc-input-accent-resolved, var(--accent, #2C66E8));
                border-radius: 999px;
                transition: width .05s ease;
            }
            .lc-input--range input[type="range"] {
                position: absolute;
                inset: 1.75rem .5rem .35rem;
                margin: 0;
                width: calc(100% - 1rem);
                height: .35rem;
                background: transparent;
                appearance: none;
                outline: none;
                pointer-events: auto;
            }
            .lc-input--range input[type="range"]::-webkit-slider-thumb {
                appearance: none;
                width: 1.15rem;
                height: 1.15rem;
                border-radius: 50%;
                background: white;
                border: 2px solid var(--lc-input-accent-resolved, var(--accent, #2C66E8));
                box-shadow: 0 1px 3px rgba(0, 0, 0, .25);
                cursor: grab;
                margin-top: -.4rem;
            }
            .lc-input--range input[type="range"]:active::-webkit-slider-thumb {
                cursor: grabbing;
            }
            .lc-input--range input[type="range"]::-moz-range-thumb {
                width: 1.15rem;
                height: 1.15rem;
                border-radius: 50%;
                background: white;
                border: 2px solid var(--lc-input-accent-resolved, var(--accent, #2C66E8));
                cursor: grab;
            }
            .lc-input--range .lc-range-bubble {
                position: absolute;
                top: 0;
                transform: translateX(-50%);
                background: var(--lc-input-accent-resolved, var(--accent, #2C66E8));
                color: white;
                font-size: .75rem;
                font-weight: 600;
                font-variant-numeric: tabular-nums;
                padding: .15rem .5rem;
                border-radius: .25rem;
                pointer-events: none;
                transition: left .05s ease;
            }
            .lc-input--range .lc-range-ticks {
                position: relative;
                height: .5rem;
                margin: .35rem .5rem 0;
            }
            .lc-input--range .lc-range-tick {
                position: absolute;
                width: 1px;
                height: 100%;
                background: var(--lc-input-ink-dim-resolved, var(--ink-dim, #A3A099));
                opacity: .35;
                transform: translateX(-50%);
            }
        </style>
    @endpush
@endonce
