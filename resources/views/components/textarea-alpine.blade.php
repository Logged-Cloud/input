@props([
    'name',
    'id' => null,
    'value' => '',
    /**
     * Maximum height before scroll kicks in (in pixels). Defaults to
     * `input.textarea.max_height` (320 out of the box).
     */
    'maxHeight' => null,
    /**
     * Show a "N / max" counter when `maxlength` is set on the input.
     * Defaults to `input.textarea.counter` (true out of the box).
     */
    'counter' => null,
    'rows' => 2,
    'placeholder' => null,
    'maxlength' => null,
    'required' => false,
])

@php
    $id ??= $name;
    $maxHeight ??= (int) config('input.textarea.max_height', 320);
    $counter ??= (bool) config('input.textarea.counter', true);
@endphp

<div
    x-data="lcInputTextarea({
        initial: @js($value),
        maxHeight: @js((int) $maxHeight),
    })"
    x-init="init()"
    class="lc-input lc-input--textarea {{ $attributes->get('class') }}"
    {{ $attributes->except('class')->merge(['data-component' => 'input::textarea']) }}
>
    <textarea
        id="{{ $id }}"
        name="{{ $name }}"
        rows="{{ $rows }}"
        x-ref="ta"
        x-model="value"
        @input="resize()"
        @if ($placeholder) placeholder="{{ $placeholder }}" @endif
        @if ($maxlength) maxlength="{{ $maxlength }}" @endif
        @if ($required) required @endif
    >{{ $value }}</textarea>

    @if ($counter && $maxlength)
        <p class="lc-counter"
           :class="value.length >= {{ $maxlength }} - 10 ? 'is-near-limit' : ''">
            <span x-text="value.length"></span> / {{ $maxlength }}
        </p>
    @endif
</div>

@once
    @push('scripts')
        <script>
            window.lcInputTextarea = function ({ initial, maxHeight }) {
                return {
                    value: initial || '',
                    init() {
                        // Run resize after the model has rendered the seed
                        // value · otherwise scrollHeight reads as the rows
                        // default and we miss the initial fit.
                        this.$nextTick(() => this.resize());
                    },
                    resize() {
                        const ta = this.$refs.ta;
                        if (! ta) return;
                        ta.style.height = 'auto';
                        const next = Math.min(ta.scrollHeight, maxHeight);
                        ta.style.height = next + 'px';
                        ta.style.overflowY = ta.scrollHeight > maxHeight ? 'auto' : 'hidden';
                    },
                };
            };
        </script>
    @endpush
@endonce
