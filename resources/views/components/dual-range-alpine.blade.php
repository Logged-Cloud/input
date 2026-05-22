@props([
    /**
     * Base posted name · the component posts `{name}_min` and `{name}_max`
     * as separate hidden inputs.
     */
    'name' => 'range',
    'minName' => null,
    'maxName' => null,
    'min' => 0,
    'max' => 100,
    'step' => null,
    /** Initial values · `[low, high]` or `{ min: ..., max: ... }`. */
    'value' => null,
    'format' => '{value}',
    'required' => false,
])

@php
    $minName ??= $name.'_min';
    $maxName ??= $name.'_max';
    $step    ??= config('input.range.step', 1);

    $initialLow  = $min;
    $initialHigh = $max;
    if (is_array($value)) {
        $initialLow  = $value['min'] ?? $value[0] ?? $min;
        $initialHigh = $value['max'] ?? $value[1] ?? $max;
    }
@endphp

<div
    x-data="lcInputDualRange({
        initialLow:  @js((float) $initialLow),
        initialHigh: @js((float) $initialHigh),
        min:  @js((float) $min),
        max:  @js((float) $max),
        step: @js((float) $step),
        format: @js((string) $format),
    })"
    x-init="init()"
    class="lc-input lc-input--range lc-input--dual-range {{ $attributes->get('class') }}"
    {{ $attributes->except('class')->merge(['data-component' => 'input::dual-range']) }}
>
    <div class="lc-range-stage">
        <div class="lc-range-track">
            <div class="lc-range-fill"
                 :style="`left:${lowPct}%; width:${highPct - lowPct}%;`"></div>
        </div>
        <input type="range" min="{{ $min }}" max="{{ $max }}" step="{{ $step }}"
               x-model.number="low" @input="clamp()"
               :style="`z-index:${low >= max ? 6 : 5};`"
               aria-label="Minimum">
        <input type="range" min="{{ $min }}" max="{{ $max }}" step="{{ $step }}"
               x-model.number="high" @input="clamp()"
               aria-label="Maximum">
        <span class="lc-range-bubble lc-range-bubble--low"
              :style="`left:${lowPct}%;`" x-text="labelOf(low)"></span>
        <span class="lc-range-bubble lc-range-bubble--high"
              :style="`left:${highPct}%;`" x-text="labelOf(high)"></span>
    </div>

    <input type="hidden" name="{{ $minName }}" x-model="low"  @if ($required) required @endif>
    <input type="hidden" name="{{ $maxName }}" x-model="high" @if ($required) required @endif>
</div>

@once
    @push('scripts')
        <script>
            window.lcInputDualRange = function ({ initialLow, initialHigh, min, max, step, format }) {
                return {
                    low: initialLow,
                    high: initialHigh,
                    init() { this.clamp(); },
                    get lowPct()  { return this.pctOf(this.low); },
                    get highPct() { return this.pctOf(this.high); },
                    pctOf(v) {
                        if (max === min) return 0;
                        return ((v - min) / (max - min)) * 100;
                    },
                    labelOf(v) {
                        return format
                            .replace('{value}', v)
                            .replace('{pct}', Math.round(this.pctOf(v)));
                    },
                    clamp() {
                        // Prevent the thumbs from crossing · the second
                        // <input range> is layered above the first so
                        // either can be grabbed at any point.
                        this.low  = Math.min(this.low,  this.high - step);
                        this.high = Math.max(this.high, this.low  + step);
                        this.low  = Math.max(this.low,  min);
                        this.high = Math.min(this.high, max);
                    },
                };
            };
        </script>
    @endpush
    @push('scripts')
        <style>
            .lc-input--dual-range .lc-range-stage input[type="range"] {
                pointer-events: none;
            }
            .lc-input--dual-range .lc-range-stage input[type="range"]::-webkit-slider-thumb {
                pointer-events: auto;
            }
            .lc-input--dual-range .lc-range-stage input[type="range"]::-moz-range-thumb {
                pointer-events: auto;
            }
            .lc-input--dual-range .lc-range-bubble--high {
                top: auto;
                bottom: -1.5rem;
            }
        </style>
    @endpush
@endonce
