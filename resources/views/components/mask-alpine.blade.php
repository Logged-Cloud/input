@props([
    /**
     * Form input name · posted to the server.
     */
    'name',
    /**
     * Mask pattern. Each `#` character is a slot the user types into;
     * everything else is a literal that is rendered as the user types.
     * Examples:
     *   '###-##-####'          · US social-security
     *   '#### #### #### ####'  · credit card
     *   '##/##/####'           · short date
     *   '+## (###) ###-####'   · international phone
     */
    'pattern',
    /**
     * Optional initial value. Either raw digits or already-masked · the
     * component strips non-pattern characters on init.
     */
    'value' => '',
    /**
     * Posted form name for the "raw" digits-only value · useful when the
     * server wants the stripped form (e.g. storing phone numbers without
     * formatting). Defaults to "{$name}_raw". Set to false to omit.
     */
    'rawName' => null,
    /**
     * id of the input · falls back to the name so a <label for> still
     * works without the consumer writing it twice.
     */
    'id' => null,
    /**
     * Placeholder character used to indicate empty slots in the visible
     * preview that sits behind the input. Defaults to the configured
     * `input.mask.placeholder_char` ('_' out of the box).
     */
    'placeholderChar' => null,
    /**
     * Whether the input is required at the form level.
     */
    'required' => false,
    'autocomplete' => 'off',
])

@php
    $id ??= $name;
    $rawName ??= $name.'_raw';
    $ph = $placeholderChar ?? config('input.mask.placeholder_char', '_');
@endphp

<div
    x-data="lcInputMask({
        pattern: @js($pattern),
        initial: @js($value),
        placeholder: @js($ph),
    })"
    x-init="init()"
    class="lc-input lc-input--mask {{ $attributes->get('class') }}"
    {{ $attributes->except('class')->merge(['data-component' => 'input::mask']) }}
>
    <input
        type="text"
        id="{{ $id }}"
        name="{{ $name }}"
        x-model="display"
        @input="onInput($event)"
        @paste="onPaste($event)"
        @keydown.backspace="onBackspace($event)"
        :placeholder="placeholderText"
        autocomplete="{{ $autocomplete }}"
        inputmode="text"
        @if ($required) required @endif
    >

    @unless ($rawName === false)
        {{-- Stripped-of-literals form value · what the server stores.
             Kept in sync with the visible input via Alpine. --}}
        <input type="hidden" name="{{ $rawName }}" x-model="raw">
    @endunless
</div>

@once
    @push('scripts')
        <script>
            window.lcInputMask = function ({ pattern, initial, placeholder }) {
                // Build template arrays: which positions are slots (#) vs
                // literals. We pre-compute once on init so each keystroke
                // only walks fixed arrays.
                const literalAt = pattern.split('').map((c) => c === '#' ? null : c);
                const slotCount = literalAt.filter((c) => c === null).length;

                return {
                    raw: '',
                    display: '',
                    placeholderText: '',
                    init() {
                        const seedRaw = String(initial || '').replace(/[^\dA-Za-z]/g, '');
                        this.raw = seedRaw.slice(0, slotCount);
                        this.display = this.format(this.raw);
                        this.placeholderText = pattern.replace(/#/g, placeholder);
                    },
                    onInput(e) {
                        const seed = e.target.value.replace(/[^\dA-Za-z]/g, '');
                        this.raw = seed.slice(0, slotCount);
                        this.display = this.format(this.raw);
                    },
                    onPaste(e) {
                        // Browsers paste into the input naturally · the
                        // input handler picks it up. We just clamp here
                        // so very long pastes don't lag formatting.
                        const text = (e.clipboardData || window.clipboardData)?.getData('text') ?? '';
                        if (! text) return;
                        e.preventDefault();
                        const seed = (this.raw + text).replace(/[^\dA-Za-z]/g, '');
                        this.raw = seed.slice(0, slotCount);
                        this.display = this.format(this.raw);
                    },
                    onBackspace(e) {
                        // Native backspace · let the input handler reformat.
                        // No-op here; placeholder for future "skip literals"
                        // behaviour if a complex pattern needs it.
                    },
                    /** Lay the raw characters out across the literal pattern. */
                    format(raw) {
                        if (! raw) return '';
                        let out = '';
                        let rawIndex = 0;
                        for (const lit of literalAt) {
                            if (lit === null) {
                                if (rawIndex >= raw.length) break;
                                out += raw[rawIndex++];
                            } else {
                                if (rawIndex >= raw.length) break;
                                out += lit;
                            }
                        }
                        return out;
                    },
                };
            };
        </script>
    @endpush
@endonce
