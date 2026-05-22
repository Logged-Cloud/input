@props([
    /**
     * Posted form name · the component posts an array via repeated
     * `name[]` hidden inputs so `request->validate(['tags' => 'array'])`
     * works without further plumbing.
     */
    'name' => 'tags',
    /**
     * Initial tags · accepts an array of strings or a comma-separated
     * string for convenience when re-rendering after a validation error.
     */
    'value' => [],
    /**
     * Allow duplicates · defaults to `input.tags.allow_duplicates`.
     */
    'allowDuplicates' => null,
    /**
     * Hard cap on number of tags · defaults to `input.tags.max`.
     */
    'max' => null,
    /**
     * Characters that commit the in-progress tag. Always includes Enter
     * and Tab; this prop adds explicit separators (default ", ").
     */
    'separators' => ', ',
    'placeholder' => 'Add tag…',
    'required' => false,
])

@php
    $allowDuplicates ??= (bool) config('input.tags.allow_duplicates', false);
    $max ??= config('input.tags.max');
    $initial = is_array($value) ? $value : array_filter(array_map('trim', explode(',', (string) $value)));
@endphp

<div
    x-data="lcInputTags({
        initial: @js(array_values($initial)),
        allowDuplicates: @js((bool) $allowDuplicates),
        max: @js($max !== null ? (int) $max : null),
        separators: @js((string) $separators),
    })"
    x-init="init()"
    @click="$refs.input.focus()"
    class="lc-input lc-input--tags {{ $attributes->get('class') }}"
    {{ $attributes->except('class')->merge(['data-component' => 'input::tags']) }}
>
    <div class="lc-tags-row">
        <template x-for="(tag, i) in tags" :key="i + ':' + tag">
            <span class="lc-tag-chip">
                <span x-text="tag"></span>
                <button type="button" @click.stop="remove(i)"
                        class="lc-tag-chip-remove"
                        :aria-label="`Remove ${tag}`">×</button>
            </span>
        </template>

        <input
            type="text"
            x-ref="input"
            x-model="draft"
            @keydown.enter.prevent="commit()"
            @keydown.tab="onTab($event)"
            @keydown.backspace="onBackspace()"
            @keydown="onKey($event)"
            @paste="onPaste($event)"
            @blur="commit()"
            placeholder="{{ $placeholder }}"
            class="lc-tags-draft"
            :disabled="atMax">
    </div>

    {{-- Posted as repeated name[] entries · server gets a clean array. --}}
    <template x-for="(tag, i) in tags" :key="i">
        <input type="hidden" :name="`{{ $name }}[]`" :value="tag">
    </template>

    @if ($required)
        {{-- Sentinel · forces a 422 if the user clears every tag and
             submits without typing anything. --}}
        <input type="hidden" :required="tags.length === 0" name="{{ $name }}_required_sentinel">
    @endif
</div>

@once
    @push('scripts')
        <script>
            window.lcInputTags = function ({ initial, allowDuplicates, max, separators }) {
                return {
                    tags: Array.isArray(initial) ? [...initial] : [],
                    draft: '',
                    get atMax() {
                        return max !== null && this.tags.length >= max;
                    },
                    init() {},
                    onKey(e) {
                        // Any custom separator keystroke commits the draft.
                        if (separators.includes(e.key) && this.draft.trim() !== '') {
                            e.preventDefault();
                            this.commit();
                        }
                    },
                    onTab(e) {
                        // Tab commits only when there's something to commit;
                        // otherwise let it move focus naturally.
                        if (this.draft.trim() !== '') {
                            e.preventDefault();
                            this.commit();
                        }
                    },
                    onBackspace() {
                        // Empty-draft backspace deletes the last chip · gives
                        // a feeling of "I'm editing a continuous string".
                        if (this.draft === '' && this.tags.length > 0) {
                            this.tags.pop();
                        }
                    },
                    onPaste(e) {
                        // Bulk-paste · "alpha, beta, gamma" → three chips.
                        const text = (e.clipboardData || window.clipboardData)?.getData('text') || '';
                        if (! text || ! separators.split('').some((s) => text.includes(s))) return;
                        e.preventDefault();
                        const parts = text.split(new RegExp(`[${separators.replace(/[-/\\^$*+?.()|[\]{}]/g, '\\$&')}]`));
                        for (const p of parts) {
                            const v = p.trim();
                            if (! v) continue;
                            if (! this.canAdd(v)) continue;
                            this.tags.push(v);
                        }
                    },
                    commit() {
                        const v = this.draft.trim();
                        if (! v) return;
                        if (! this.canAdd(v)) {
                            this.draft = '';
                            return;
                        }
                        this.tags.push(v);
                        this.draft = '';
                    },
                    canAdd(v) {
                        if (this.atMax) return false;
                        if (! allowDuplicates && this.tags.some((t) => t.toLowerCase() === v.toLowerCase())) return false;
                        return true;
                    },
                    remove(i) {
                        this.tags.splice(i, 1);
                    },
                };
            };
        </script>
    @endpush
    @push('scripts')
        <style>
            .lc-input--tags {
                cursor: text;
            }
            .lc-input--tags .lc-tags-row {
                display: flex;
                flex-wrap: wrap;
                gap: .35rem;
                background: var(--lc-input-bg-resolved, var(--surface-2, #1E1F22));
                border: 1px solid var(--lc-input-border-resolved, var(--line, #3A3D40));
                border-radius: var(--lc-input-radius-resolved, .5rem);
                padding: .45rem .55rem;
            }
            .lc-input--tags .lc-tags-row:focus-within {
                border-color: var(--lc-input-accent-resolved, var(--accent, #2C66E8));
            }
            .lc-input--tags .lc-tag-chip {
                display: inline-flex;
                align-items: center;
                gap: .35rem;
                padding: .15rem .55rem;
                border-radius: 999px;
                background: color-mix(in srgb, var(--lc-input-accent-resolved, #2C66E8) 18%, transparent);
                color: var(--lc-input-ink-resolved, var(--ink, #F0EDE5));
                font-size: .85rem;
                line-height: 1.4;
            }
            .lc-input--tags .lc-tag-chip-remove {
                background: transparent;
                border: 0;
                color: var(--lc-input-ink-dim-resolved, var(--ink-dim, #A3A099));
                cursor: pointer;
                padding: 0;
                font-size: 1.1rem;
                line-height: 1;
            }
            .lc-input--tags .lc-tag-chip-remove:hover {
                color: var(--lc-input-danger-resolved, var(--danger, #ef4444));
            }
            .lc-input--tags .lc-tags-draft {
                flex: 1;
                min-width: 6rem;
                background: transparent;
                color: var(--lc-input-ink-resolved, var(--ink, #F0EDE5));
                border: 0;
                outline: none;
                font: inherit;
                padding: .25rem .15rem;
            }
            .lc-input--tags .lc-tags-draft:disabled {
                cursor: not-allowed;
            }
        </style>
    @endpush
@endonce
