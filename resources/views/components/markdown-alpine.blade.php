@props([
    'name',
    'id' => null,
    'value' => '',
    /** Max textarea height before scroll, in px. */
    'maxHeight' => null,
    /** Show a character counter when `maxlength` is set. */
    'counter' => null,
    'rows' => 4,
    'placeholder' => null,
    'maxlength' => null,
    'required' => false,
])

@php
    $id ??= $name;
    $maxHeight ??= (int) config('input.markdown.max_height', 320);
    $counter ??= (bool) config('input.markdown.counter', true);
@endphp

<div
    x-data="lcInputMarkdown({
        initial: @js((string) $value),
        maxHeight: @js((int) $maxHeight),
    })"
    x-init="init()"
    class="lc-input lc-input--markdown {{ $attributes->get('class') }}"
    {{ $attributes->except('class')->merge(['data-component' => 'input::markdown']) }}
>
    <div class="lc-md-tabs" role="tablist">
        <button type="button" role="tab" :aria-selected="tab === 'write'"
                :class="tab === 'write' ? 'is-active' : ''"
                @click="tab = 'write'">Write</button>
        <button type="button" role="tab" :aria-selected="tab === 'preview'"
                :class="tab === 'preview' ? 'is-active' : ''"
                @click="tab = 'preview'">Preview</button>
        <div class="lc-md-shortcut-hints" x-show="tab === 'write'">
            <kbd>Cmd</kbd>+<kbd>B</kbd> bold ·
            <kbd>Cmd</kbd>+<kbd>I</kbd> italic ·
            <kbd>Cmd</kbd>+<kbd>K</kbd> link
        </div>
    </div>

    <textarea
        x-show="tab === 'write'"
        id="{{ $id }}"
        name="{{ $name }}"
        rows="{{ $rows }}"
        x-ref="ta"
        x-model="value"
        @input="resize()"
        @keydown="onKey($event)"
        @if ($placeholder) placeholder="{{ $placeholder }}" @endif
        @if ($maxlength) maxlength="{{ $maxlength }}" @endif
        @if ($required) required @endif
        class="lc-md-textarea"
    >{{ $value }}</textarea>

    <div x-show="tab === 'preview'" x-cloak class="lc-md-preview"
         x-html="renderedHtml"></div>

    @if ($counter && $maxlength)
        <p class="lc-md-counter"
           :class="value.length >= {{ $maxlength }} - 10 ? 'is-near-limit' : ''">
            <span x-text="value.length"></span> / {{ $maxlength }}
        </p>
    @endif
</div>

@once
    @push('scripts')
        <script>
            window.lcInputMarkdown = function ({ initial, maxHeight }) {
                return {
                    value: initial || '',
                    tab: 'write',
                    init() {
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
                    onKey(e) {
                        // Markdown shortcut shorthand. Wrap selection or
                        // insert at caret · matches the GitHub-style
                        // Cmd/Ctrl + B / I / K.
                        const wrap = (mark, end = mark) => {
                            e.preventDefault();
                            const ta = e.target;
                            const start = ta.selectionStart, finish = ta.selectionEnd;
                            const before = ta.value.slice(0, start);
                            const sel    = ta.value.slice(start, finish);
                            const after  = ta.value.slice(finish);
                            ta.value = `${before}${mark}${sel}${end}${after}`;
                            const caret = start + mark.length + sel.length;
                            ta.setSelectionRange(caret, caret + (sel ? 0 : 0));
                            this.value = ta.value;
                        };
                        const cmd = e.metaKey || e.ctrlKey;
                        if (! cmd) return;
                        if (e.key === 'b') wrap('**');
                        else if (e.key === 'i') wrap('_');
                        else if (e.key === 'k') wrap('[', '](https://)');
                    },
                    get renderedHtml() {
                        return renderMarkdown(this.value);
                    },
                };
            };

            // Tiny markdown renderer · headers, bold, italic, code, links,
            // line breaks, lists. Deliberately not a CommonMark parser ·
            // for that, swap renderMarkdown for a host-app library. Enough
            // for "what does this comment look like" preview tabs.
            function renderMarkdown(src) {
                const escape = (s) => s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
                let html = escape(src);
                // Code blocks ```...```
                html = html.replace(/```([\s\S]*?)```/g, (_, code) =>
                    `<pre><code>${code.replace(/^\n/, '')}</code></pre>`);
                // Headers (#, ##, ###) at line start
                html = html.replace(/^(#{1,6})\s+(.+)$/gm, (_, hashes, text) =>
                    `<h${hashes.length}>${text}</h${hashes.length}>`);
                // Bold · **text** or __text__
                html = html.replace(/\*\*([^*\n]+)\*\*/g, '<strong>$1</strong>');
                html = html.replace(/__([^_\n]+)__/g, '<strong>$1</strong>');
                // Italic · *text* or _text_
                html = html.replace(/(?<!\*)\*([^*\n]+)\*(?!\*)/g, '<em>$1</em>');
                html = html.replace(/(?<!_)_([^_\n]+)_(?!_)/g, '<em>$1</em>');
                // Inline code · `text`
                html = html.replace(/`([^`\n]+)`/g, '<code>$1</code>');
                // Links · [label](url)
                html = html.replace(/\[([^\]]+)\]\((https?:\/\/[^)]+)\)/g,
                    '<a href="$2" target="_blank" rel="noopener">$1</a>');
                // Unordered lists · simple line-start dashes
                html = html.replace(/(?:^|\n)((?:- .+\n?)+)/g, (_, block) =>
                    '\n<ul>' + block.trim().split('\n').map((line) =>
                        `<li>${line.replace(/^-\s+/, '')}</li>`).join('') + '</ul>');
                // Line breaks (two spaces at end + newline → <br>)
                html = html.replace(/  \n/g, '<br>');
                // Paragraph splits on blank lines
                html = html.split(/\n{2,}/).map((chunk) => {
                    if (/^<(h\d|ul|pre|blockquote)/.test(chunk.trim())) return chunk;
                    return chunk.trim() ? `<p>${chunk}</p>` : '';
                }).join('\n');
                return html;
            }
        </script>
    @endpush
    @push('scripts')
        <style>
            .lc-input--markdown .lc-md-tabs {
                display: flex;
                gap: .25rem;
                align-items: center;
                border-bottom: 1px solid var(--lc-input-border-resolved, var(--line, #3A3D40));
                margin-bottom: .35rem;
                padding-bottom: .25rem;
            }
            .lc-input--markdown .lc-md-tabs button {
                background: transparent;
                color: var(--lc-input-ink-dim-resolved, var(--ink-dim, #A3A099));
                border: 0;
                padding: .35rem .65rem;
                font: inherit;
                cursor: pointer;
                border-radius: .35rem;
            }
            .lc-input--markdown .lc-md-tabs button.is-active {
                color: var(--lc-input-ink-resolved, var(--ink, #F0EDE5));
                background: color-mix(in srgb, var(--lc-input-accent-resolved, #2C66E8) 18%, transparent);
            }
            .lc-input--markdown .lc-md-shortcut-hints {
                margin-left: auto;
                font-size: .7rem;
                color: var(--lc-input-ink-dim-resolved, var(--ink-dim, #A3A099));
            }
            .lc-input--markdown .lc-md-shortcut-hints kbd {
                background: var(--lc-input-bg-resolved, var(--surface-2, #1E1F22));
                border: 1px solid var(--lc-input-border-resolved, var(--line, #3A3D40));
                border-radius: .25rem;
                padding: 0 .35rem;
                font: inherit;
                font-size: .65rem;
            }
            .lc-input--markdown .lc-md-textarea,
            .lc-input--markdown .lc-md-preview {
                width: 100%;
                background: var(--lc-input-bg-resolved, var(--surface-2, #1E1F22));
                color: var(--lc-input-ink-resolved, var(--ink, #F0EDE5));
                border: 1px solid var(--lc-input-border-resolved, var(--line, #3A3D40));
                border-radius: var(--lc-input-radius-resolved, .5rem);
                padding: .65rem .75rem;
                font: inherit;
                box-sizing: border-box;
                resize: none;
            }
            .lc-input--markdown .lc-md-preview {
                min-height: 6rem;
            }
            .lc-input--markdown .lc-md-preview p { margin: 0 0 .65em; }
            .lc-input--markdown .lc-md-preview code,
            .lc-input--markdown .lc-md-preview pre {
                background: rgba(255, 255, 255, .06);
                border-radius: .25rem;
                padding: .1em .35em;
                font-family: ui-monospace, monospace;
                font-size: .9em;
            }
            .lc-input--markdown .lc-md-preview pre {
                padding: .65rem .75rem;
                overflow-x: auto;
            }
            .lc-input--markdown .lc-md-counter {
                margin: .35rem 0 0;
                font-size: .75rem;
                color: var(--lc-input-ink-dim-resolved, var(--ink-dim, #A3A099));
                text-align: right;
                font-variant-numeric: tabular-nums;
            }
            .lc-input--markdown .lc-md-counter.is-near-limit {
                color: var(--lc-input-danger-resolved, var(--danger, #ef4444));
            }
        </style>
    @endpush
@endonce
