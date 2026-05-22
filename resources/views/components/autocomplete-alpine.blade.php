@props([
    /**
     * Posted form name · receives the chosen value (e.g. an id or slug).
     */
    'name',
    'id' => null,
    /**
     * Where suggestions come from · either a URL (string · queried with
     * `?q=...`) or a local array of `[{ value, label, subtitle? }, ...]`.
     */
    'source',
    /**
     * Initial selection · pair of `{ value, label }` so the visible field
     * starts populated when editing an existing record.
     */
    'value' => null,
    'label' => null,
    /**
     * Minimum query length before a remote / local search fires. Defaults
     * to `input.autocomplete.min_chars`.
     */
    'minChars' => null,
    /**
     * Debounce in ms between keystrokes and the search. Defaults to
     * `input.autocomplete.debounce_ms`.
     */
    'debounceMs' => null,
    'placeholder' => null,
    'required' => false,
    'autocomplete' => 'off',
])

@php
    $id ??= $name;
    $minChars   ??= (int) config('input.autocomplete.min_chars', 2);
    $debounceMs ??= (int) config('input.autocomplete.debounce_ms', 200);
    // Source is either a URL (kept as a string) or an inline PHP array
    // that we Js::from into the JS factory.
    if (is_string($source)) {
        $sourceJs = json_encode(['kind' => 'url', 'url' => $source]);
    } else {
        $sourceJs = json_encode(['kind' => 'local', 'items' => $source ?: []]);
    }
@endphp

<div
    x-data="lcInputAutocomplete({
        source: {{ $sourceJs }},
        initial: {{ json_encode(['value' => (string) ($value ?? ''), 'label' => (string) ($label ?? '')]) }},
        minChars: @js((int) $minChars),
        debounceMs: @js((int) $debounceMs),
    })"
    x-init="init()"
    @keydown.escape.window="close()"
    @click.outside="close()"
    class="lc-input lc-input--autocomplete {{ $attributes->get('class') }}"
    {{ $attributes->except('class')->merge(['data-component' => 'input::autocomplete']) }}
>
    <div class="lc-ac-control">
        <input
            type="text"
            id="{{ $id }}-q"
            x-ref="search"
            x-model="query"
            @input.debounce.{{ $debounceMs }}ms="onQuery()"
            @focus="open()"
            @keydown.arrow-down.prevent="step(1)"
            @keydown.arrow-up.prevent="step(-1)"
            @keydown.enter.prevent="commitActive()"
            @keydown.tab="commitActive()"
            role="combobox"
            aria-autocomplete="list"
            :aria-expanded="openState ? 'true' : 'false'"
            :aria-controls="`{{ $id }}-list`"
            :aria-activedescendant="active >= 0 ? `{{ $id }}-opt-${active}` : ''"
            autocomplete="{{ $autocomplete }}"
            @if ($placeholder) placeholder="{{ $placeholder }}" @endif
        >
        <button type="button" x-show="query" @click="reset()" x-cloak
                class="lc-ac-clear" aria-label="Clear">✕</button>
    </div>

    <ul x-show="openState && items.length > 0"
        x-cloak
        role="listbox"
        id="{{ $id }}-list"
        class="lc-ac-menu">
        <template x-for="(item, i) in items" :key="item.value">
            <li
                :id="`{{ $id }}-opt-${i}`"
                role="option"
                :aria-selected="active === i ? 'true' : 'false'"
                :data-active="active === i"
                @mousedown.prevent="commit(i)"
                @mouseenter="active = i"
                class="lc-ac-option">
                <div class="lc-ac-option-label" x-text="item.label"></div>
                <div class="lc-ac-option-sub" x-show="item.subtitle" x-text="item.subtitle"></div>
            </li>
        </template>
    </ul>

    <p x-show="openState && query.length >= minChars && items.length === 0 && ! loading"
       x-cloak class="lc-ac-empty">
        No matches for <strong x-text="query"></strong>.
    </p>

    <p x-show="loading" x-cloak class="lc-ac-loading">Searching…</p>

    {{-- Server posts the canonical value (id / slug). --}}
    <input type="hidden" name="{{ $name }}" x-model="value" @if ($required) required @endif>
</div>

@once
    @push('scripts')
        <script>
            window.lcInputAutocomplete = function ({ source, initial, minChars, debounceMs }) {
                return {
                    query: initial.label || '',
                    value: initial.value || '',
                    items: [],
                    openState: false,
                    active: -1,
                    loading: false,
                    aborter: null,
                    minChars,
                    init() {
                        // No-op · state is seeded from props.
                    },
                    open() {
                        if (this.items.length > 0 || this.query.length >= minChars) {
                            this.openState = true;
                            if (this.items.length > 0 && this.active < 0) this.active = 0;
                        }
                    },
                    close() {
                        this.openState = false;
                        this.active = -1;
                    },
                    reset() {
                        this.query = '';
                        this.value = '';
                        this.items = [];
                        this.close();
                        this.$refs.search?.focus();
                    },
                    async onQuery() {
                        if (this.query.length < minChars) {
                            this.items = [];
                            this.openState = false;
                            return;
                        }
                        // The user has typed something different to the
                        // committed label · invalidate any prior selection.
                        if (this.query !== initial.label) this.value = '';

                        if (source.kind === 'local') {
                            this.items = this.filterLocal(source.items, this.query);
                            this.active = this.items.length > 0 ? 0 : -1;
                            this.openState = this.items.length > 0 || this.loading === false;
                            return;
                        }

                        // Remote · cancel any prior in-flight request so a
                        // slow earlier query can't overwrite a newer result.
                        if (this.aborter) this.aborter.abort();
                        this.aborter = new AbortController();
                        this.loading = true;
                        try {
                            const url = new URL(source.url, window.location.origin);
                            url.searchParams.set('q', this.query);
                            const r = await fetch(url, {
                                credentials: 'same-origin',
                                headers: { Accept: 'application/json' },
                                signal: this.aborter.signal,
                            });
                            const body = await r.json();
                            // Accept either a plain array or a `{ items: [...] }`
                            // envelope so we slot into Laravel resource
                            // responses without further plumbing.
                            this.items = Array.isArray(body) ? body : (body.items || []);
                            this.active = this.items.length > 0 ? 0 : -1;
                            this.openState = true;
                        } catch (e) {
                            if (e.name === 'AbortError') return;
                            this.items = [];
                            this.openState = false;
                        } finally {
                            this.loading = false;
                        }
                    },
                    filterLocal(all, q) {
                        const needle = q.toLowerCase();
                        return all.filter((it) =>
                            (it.label || '').toLowerCase().includes(needle) ||
                            (it.subtitle || '').toLowerCase().includes(needle),
                        ).slice(0, 12);
                    },
                    step(d) {
                        if (! this.items.length) return;
                        this.open();
                        this.active = (this.active + d + this.items.length) % this.items.length;
                    },
                    commitActive() {
                        if (this.active < 0 || ! this.items[this.active]) return;
                        this.commit(this.active);
                    },
                    commit(i) {
                        const item = this.items[i];
                        if (! item) return;
                        this.value = item.value;
                        this.query = item.label;
                        this.close();
                        // Bubble selection so consumers can react · e.g.
                        // fetch related data when an account is picked.
                        this.$root.dispatchEvent(new CustomEvent('lc-input:autocomplete:selected', {
                            detail: { value: item.value, label: item.label, item }, bubbles: true,
                        }));
                    },
                };
            };
        </script>
    @endpush
    @push('scripts')
        <style>
            .lc-input--autocomplete {
                position: relative;
            }
            .lc-input--autocomplete .lc-ac-control {
                display: flex;
                align-items: stretch;
                background: var(--lc-input-bg-resolved, var(--surface-2, #1E1F22));
                border: 1px solid var(--lc-input-border-resolved, var(--line, #3A3D40));
                border-radius: var(--lc-input-radius-resolved, .5rem);
                overflow: hidden;
            }
            .lc-input--autocomplete .lc-ac-control:focus-within {
                border-color: var(--lc-input-accent-resolved, var(--accent, #2C66E8));
            }
            .lc-input--autocomplete input[type="text"] {
                flex: 1;
                background: transparent;
                color: var(--lc-input-ink-resolved, var(--ink, #F0EDE5));
                border: 0;
                outline: none;
                padding: .65rem .75rem;
                font: inherit;
                width: 100%;
            }
            .lc-input--autocomplete .lc-ac-clear {
                background: transparent;
                color: var(--lc-input-ink-dim-resolved, var(--ink-dim, #A3A099));
                border: 0;
                padding: 0 .65rem;
                cursor: pointer;
            }
            .lc-input--autocomplete .lc-ac-menu {
                position: absolute;
                z-index: 50;
                top: 100%;
                left: 0;
                right: 0;
                margin: .25rem 0 0;
                padding: .25rem;
                background: var(--lc-input-bg-resolved, var(--surface-2, #1E1F22));
                border: 1px solid var(--lc-input-border-resolved, var(--line, #3A3D40));
                border-radius: var(--lc-input-radius-resolved, .5rem);
                list-style: none;
                max-height: 18rem;
                overflow: auto;
                box-shadow: 0 8px 24px rgba(0, 0, 0, .35);
            }
            .lc-input--autocomplete .lc-ac-option {
                padding: .55rem .65rem;
                border-radius: .35rem;
                cursor: pointer;
                line-height: 1.25;
            }
            .lc-input--autocomplete .lc-ac-option[data-active="true"] {
                background: color-mix(in srgb, var(--lc-input-accent-resolved, #2C66E8) 18%, transparent);
            }
            .lc-input--autocomplete .lc-ac-option-label {
                color: var(--lc-input-ink-resolved, var(--ink, #F0EDE5));
            }
            .lc-input--autocomplete .lc-ac-option-sub {
                font-size: .8rem;
                color: var(--lc-input-ink-dim-resolved, var(--ink-dim, #A3A099));
            }
            .lc-input--autocomplete .lc-ac-empty,
            .lc-input--autocomplete .lc-ac-loading {
                position: absolute;
                z-index: 50;
                top: 100%;
                left: 0;
                right: 0;
                margin: .25rem 0 0;
                padding: .55rem .75rem;
                background: var(--lc-input-bg-resolved, var(--surface-2, #1E1F22));
                border: 1px solid var(--lc-input-border-resolved, var(--line, #3A3D40));
                border-radius: var(--lc-input-radius-resolved, .5rem);
                color: var(--lc-input-ink-dim-resolved, var(--ink-dim, #A3A099));
                font-size: .85rem;
            }
        </style>
    @endpush
@endonce
