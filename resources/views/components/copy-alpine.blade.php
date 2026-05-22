@props([
    /**
     * The value to display + copy. The component does NOT post the value
     * (it's read-only) · pass `name` only if you want a hidden input
     * carried along in the same form (e.g. a token shown to the user that
     * also rides on a submit).
     */
    'value',
    'name' => null,
    'id' => null,
    /**
     * Visible style · 'field' (input-shaped box) or 'code' (mono pill).
     */
    'variant' => 'field',
    /**
     * Mask the visible value · shows a row of dots until the user clicks
     * Reveal. Useful for tokens / passwords stored in plain text.
     */
    'mask' => false,
    /** Hint label shown above the value · optional. */
    'label' => null,
])

@php
    $id ??= $name ?? 'copy-'.uniqid();
    $variantClass = $variant === 'code' ? 'lc-copy--code' : 'lc-copy--field';
@endphp

<div
    x-data="lcInputCopy({
        value: @js((string) $value),
        mask: @js((bool) $mask),
    })"
    class="lc-input lc-input--copy {{ $variantClass }} {{ $attributes->get('class') }}"
    {{ $attributes->except('class')->merge(['data-component' => 'input::copy']) }}
>
    @if ($label)
        <span class="lc-copy-label">{{ $label }}</span>
    @endif

    <div class="lc-copy-row">
        <span class="lc-copy-value" x-text="display" :title="display"></span>

        @if ($mask)
            <button type="button" @click="revealed = ! revealed"
                    class="lc-copy-icon-btn"
                    :aria-label="revealed ? 'Hide value' : 'Reveal value'">
                <svg x-show="! revealed" viewBox="0 0 24 24" width="16" height="16" fill="none"
                     stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                    <circle cx="12" cy="12" r="3"/>
                </svg>
                <svg x-show="revealed" viewBox="0 0 24 24" width="16" height="16" fill="none"
                     stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M17.94 17.94A10.94 10.94 0 0 1 12 20c-7 0-11-8-11-8a19 19 0 0 1 5.06-5.94"/>
                    <line x1="1" y1="1" x2="23" y2="23"/>
                </svg>
            </button>
        @endif

        <button type="button" @click="copy()"
                class="lc-copy-icon-btn"
                :aria-label="copied ? 'Copied!' : 'Copy to clipboard'">
            <svg x-show="! copied" viewBox="0 0 24 24" width="16" height="16" fill="none"
                 stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="9" y="9" width="13" height="13" rx="2" ry="2"/>
                <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>
            </svg>
            <svg x-show="copied" x-cloak viewBox="0 0 24 24" width="16" height="16" fill="none"
                 stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"
                 style="color: var(--lc-input-accent-resolved, #2C66E8);">
                <polyline points="20 6 9 17 4 12"/>
            </svg>
        </button>
    </div>

    @if ($name)
        <input type="hidden" id="{{ $id }}" name="{{ $name }}" :value="value">
    @endif
</div>

@once
    @push('scripts')
        <script>
            window.lcInputCopy = function ({ value, mask }) {
                return {
                    value,
                    revealed: ! mask,
                    copied: false,
                    get display() {
                        return this.revealed ? this.value : '•'.repeat(Math.min(value.length, 24));
                    },
                    async copy() {
                        try {
                            await navigator.clipboard.writeText(this.value);
                            this.copied = true;
                            setTimeout(() => this.copied = false, 1200);
                        } catch (e) {
                            // Fallback for browsers / contexts where the
                            // Clipboard API is unavailable (HTTP, embedded
                            // webview). Use the legacy execCommand path.
                            const ta = document.createElement('textarea');
                            ta.value = this.value;
                            ta.setAttribute('readonly', '');
                            ta.style.position = 'absolute';
                            ta.style.left = '-9999px';
                            document.body.appendChild(ta);
                            ta.select();
                            try { document.execCommand('copy'); this.copied = true;
                                setTimeout(() => this.copied = false, 1200);
                            } catch (_) {}
                            document.body.removeChild(ta);
                        }
                    },
                };
            };
        </script>
    @endpush
    @push('scripts')
        <style>
            .lc-input--copy .lc-copy-label {
                display: block;
                font-size: .8rem;
                color: var(--lc-input-ink-dim-resolved, var(--ink-dim, #A3A099));
                margin-bottom: .3rem;
            }
            .lc-input--copy .lc-copy-row {
                display: flex;
                align-items: center;
                gap: .35rem;
                background: var(--lc-input-bg-resolved, var(--surface-2, #1E1F22));
                border: 1px solid var(--lc-input-border-resolved, var(--line, #3A3D40));
                border-radius: var(--lc-input-radius-resolved, .5rem);
                padding: .5rem .65rem;
            }
            .lc-input--copy.lc-copy--code .lc-copy-row {
                font-family: ui-monospace, monospace;
                font-size: .85rem;
            }
            .lc-input--copy .lc-copy-value {
                flex: 1;
                color: var(--lc-input-ink-resolved, var(--ink, #F0EDE5));
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
                font-variant-numeric: tabular-nums;
            }
            .lc-input--copy .lc-copy-icon-btn {
                background: transparent;
                border: 0;
                color: var(--lc-input-ink-dim-resolved, var(--ink-dim, #A3A099));
                cursor: pointer;
                padding: .25rem;
                border-radius: .25rem;
                display: inline-flex;
                align-items: center;
                justify-content: center;
            }
            .lc-input--copy .lc-copy-icon-btn:hover {
                color: var(--lc-input-ink-resolved, var(--ink, #F0EDE5));
                background: rgba(255, 255, 255, .06);
            }
        </style>
    @endpush
@endonce
