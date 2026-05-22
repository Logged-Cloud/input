@props([
    /**
     * Posted form name · receives the typed text, emoji insertions and all.
     */
    'name',
    'id' => null,
    'value' => '',
    'placeholder' => null,
    'maxlength' => null,
    'required' => false,
])

@php
    $id ??= $name;
@endphp

<div
    x-data="lcInputEmoji({ initial: @js((string) $value) })"
    @click.outside="open = false"
    class="lc-input lc-input--emoji {{ $attributes->get('class') }}"
    {{ $attributes->except('class')->merge(['data-component' => 'input::emoji']) }}
>
    <div class="lc-emoji-row">
        <input
            type="text"
            id="{{ $id }}"
            name="{{ $name }}"
            x-ref="input"
            x-model="value"
            @if ($placeholder) placeholder="{{ $placeholder }}" @endif
            @if ($maxlength) maxlength="{{ $maxlength }}" @endif
            @if ($required) required @endif
            class="lc-emoji-input">
        <button type="button" @click="open = ! open"
                :aria-expanded="open ? 'true' : 'false'"
                class="lc-emoji-trigger"
                aria-label="Insert emoji">
            😀
        </button>
    </div>

    <div x-show="open" x-cloak class="lc-emoji-menu" role="dialog" aria-label="Emoji picker">
        <input type="search" x-model="query"
               placeholder="Search…" class="lc-emoji-search"
               @keydown.escape.prevent="open = false">
        <div class="lc-emoji-grid" role="listbox">
            <template x-for="e in filtered" :key="e.c">
                <button type="button" @click="insert(e.c)"
                        :title="e.k.join(' · ')"
                        class="lc-emoji-cell"
                        x-text="e.c"></button>
            </template>
        </div>
    </div>
</div>

@once
    @push('scripts')
        <script>
            window.lcInputEmoji = function ({ initial }) {
                // Curated short list of useful emoji · enough for a notes
                // field without bloating the script with the full 4000+
                // Unicode 16 set. Each entry is [char, keywords[]].
                const palette = [
                    ['😀', ['happy', 'smile']],   ['😄', ['happy', 'grin']],    ['😅', ['relief', 'sweat']],
                    ['😂', ['joy', 'laugh']],     ['🙂', ['neutral']],          ['😉', ['wink']],
                    ['😍', ['love', 'heart']],    ['😘', ['kiss']],             ['😎', ['cool', 'sunglasses']],
                    ['🤔', ['think', 'curious']], ['😴', ['sleep']],           ['😢', ['sad', 'cry']],
                    ['😭', ['sob']],              ['😡', ['angry']],            ['🤯', ['mind blown']],
                    ['🥳', ['party', 'celebrate']],['👍', ['like', 'yes']],     ['👎', ['dislike', 'no']],
                    ['👏', ['clap']],             ['🙏', ['thanks', 'pray']],   ['❤️', ['love', 'red']],
                    ['💔', ['heartbreak']],       ['🔥', ['fire', 'hot']],      ['💯', ['100', 'best']],
                    ['🎉', ['celebrate']],        ['✨', ['sparkle']],         ['🚀', ['rocket', 'launch']],
                    ['☕', ['coffee']],           ['🍕', ['pizza']],            ['🍔', ['burger']],
                    ['🥦', ['veg', 'broccoli']],  ['🍎', ['apple']],            ['🐍', ['snake']],
                    ['🐠', ['fish']],             ['🐶', ['dog']],              ['🐱', ['cat']],
                    ['🌧', ['rain']],             ['☀️', ['sun']],              ['🌙', ['moon']],
                    ['⭐', ['star']],             ['📷', ['camera', 'photo']],  ['📍', ['pin', 'location']],
                    ['⚠️', ['warning']],          ['✅', ['done', 'check']],    ['❌', ['no', 'cross']],
                    ['❓', ['question']],         ['❗', ['exclaim']],          ['🔒', ['lock', 'secure']],
                ];
                const map = palette.map(([c, k]) => ({ c, k }));
                return {
                    value: initial || '',
                    open: false,
                    query: '',
                    get filtered() {
                        const q = this.query.toLowerCase().trim();
                        if (! q) return map;
                        return map.filter((e) => e.k.some((kw) => kw.includes(q)) || e.c === q);
                    },
                    insert(ch) {
                        const input = this.$refs.input;
                        const start = input.selectionStart ?? this.value.length;
                        const end = input.selectionEnd ?? this.value.length;
                        this.value = this.value.slice(0, start) + ch + this.value.slice(end);
                        this.$nextTick(() => {
                            input.focus();
                            const caret = start + ch.length;
                            input.setSelectionRange(caret, caret);
                        });
                        this.open = false;
                    },
                };
            };
        </script>
    @endpush
    @push('scripts')
        <style>
            .lc-input--emoji {
                position: relative;
            }
            .lc-input--emoji .lc-emoji-row {
                display: flex;
                background: var(--lc-input-bg-resolved, var(--surface-2, #1E1F22));
                border: 1px solid var(--lc-input-border-resolved, var(--line, #3A3D40));
                border-radius: var(--lc-input-radius-resolved, .5rem);
                overflow: hidden;
            }
            .lc-input--emoji .lc-emoji-row:focus-within {
                border-color: var(--lc-input-accent-resolved, var(--accent, #2C66E8));
            }
            .lc-input--emoji .lc-emoji-input {
                flex: 1;
                background: transparent;
                color: var(--lc-input-ink-resolved, var(--ink, #F0EDE5));
                border: 0;
                outline: none;
                padding: .65rem .75rem;
                font: inherit;
            }
            .lc-input--emoji .lc-emoji-trigger {
                background: transparent;
                border: 0;
                border-left: 1px solid var(--lc-input-border-resolved, var(--line, #3A3D40));
                padding: 0 .85rem;
                font-size: 1.05rem;
                cursor: pointer;
            }
            .lc-input--emoji .lc-emoji-menu {
                position: absolute;
                z-index: 50;
                top: 100%;
                right: 0;
                margin-top: .25rem;
                width: 17rem;
                padding: .5rem;
                background: var(--lc-input-bg-resolved, var(--surface-2, #1E1F22));
                border: 1px solid var(--lc-input-border-resolved, var(--line, #3A3D40));
                border-radius: var(--lc-input-radius-resolved, .5rem);
                box-shadow: 0 8px 24px rgba(0, 0, 0, .35);
            }
            .lc-input--emoji .lc-emoji-search {
                width: 100%;
                background: rgba(255, 255, 255, .05);
                color: var(--lc-input-ink-resolved, var(--ink, #F0EDE5));
                border: 0;
                outline: none;
                padding: .4rem .55rem;
                margin-bottom: .35rem;
                border-radius: .35rem;
                font: inherit;
                font-size: .85rem;
            }
            .lc-input--emoji .lc-emoji-grid {
                display: grid;
                grid-template-columns: repeat(8, 1fr);
                gap: .15rem;
                max-height: 14rem;
                overflow-y: auto;
            }
            .lc-input--emoji .lc-emoji-cell {
                background: transparent;
                border: 0;
                padding: .25rem;
                font-size: 1.15rem;
                cursor: pointer;
                border-radius: .25rem;
            }
            .lc-input--emoji .lc-emoji-cell:hover {
                background: color-mix(in srgb, var(--lc-input-accent-resolved, #2C66E8) 18%, transparent);
            }
        </style>
    @endpush
@endonce
