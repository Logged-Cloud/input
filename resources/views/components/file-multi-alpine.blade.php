@props([
    /**
     * Posted form name · the component posts `name[]` per file via
     * a real <input type="file" multiple> so server-side validation
     * (`required|array`, `file`, `image`, `mimes:`, `max:`) works.
     */
    'name' => 'files',
    'id' => null,
    /**
     * Maximum number of files. Defaults to `input.file_multi.max_files`.
     */
    'maxFiles' => null,
    /**
     * Per-file size cap in MB · enforced client-side as a UX guard.
     */
    'maxSizeMb' => null,
    /**
     * MIME pattern for the accept attribute · 'image/*', 'application/pdf',
     * or '*/*' for any.
     */
    'accept' => null,
    'required' => false,
])

@php
    $id ??= $name;
    $maxFiles   ??= (int) config('input.file_multi.max_files', 8);
    $maxSizeMb  ??= (int) config('input.file_multi.max_size_mb', 10);
    $accept     ??= config('input.file_multi.accept', '*/*');
@endphp

<div
    x-data="lcInputFileMulti({
        maxFiles: @js((int) $maxFiles),
        maxSizeMb: @js((int) $maxSizeMb),
        accept: @js((string) $accept),
    })"
    x-init="init()"
    @dragover.prevent="dragActive = true"
    @dragleave="dragActive = false"
    @drop.prevent="onDrop($event)"
    class="lc-input lc-input--file-multi {{ $attributes->get('class') }}"
    :class="dragActive ? 'is-drag-active' : ''"
    {{ $attributes->except('class')->merge(['data-component' => 'input::file-multi']) }}
>
    <label class="lc-fm-zone">
        <svg viewBox="0 0 24 24" width="22" height="22" fill="none"
             stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
            <polyline points="17 8 12 3 7 8"/>
            <line x1="12" y1="3" x2="12" y2="15"/>
        </svg>
        <span class="lc-fm-zone-prompt">
            <strong>Drop files here</strong> or click to choose
        </span>
        <span class="lc-fm-zone-limits">
            Up to {{ $maxFiles }} files · {{ $maxSizeMb }} MB each
        </span>
        <input
            type="file"
            id="{{ $id }}"
            name="{{ $name }}[]"
            x-ref="fileField"
            multiple
            accept="{{ $accept }}"
            @change="onChoose($event)"
            class="lc-fm-input-hidden"
            @if ($required) required @endif
        >
    </label>

    {{-- Preview list · thumbs for images, generic file rows for others. --}}
    <ul x-show="files.length > 0" x-cloak class="lc-fm-list">
        <template x-for="(item, i) in files" :key="item.id">
            <li class="lc-fm-item">
                <div class="lc-fm-thumb">
                    <img x-show="item.previewUrl" :src="item.previewUrl" alt="" x-cloak>
                    <svg x-show="! item.previewUrl" viewBox="0 0 24 24" width="22" height="22"
                         fill="none" stroke="currentColor" stroke-width="2"
                         stroke-linecap="round" stroke-linejoin="round">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                        <polyline points="14 2 14 8 20 8"/>
                    </svg>
                </div>
                <div class="lc-fm-meta">
                    <div class="lc-fm-name" x-text="item.name"></div>
                    <div class="lc-fm-size" x-text="formatSize(item.size)"></div>
                </div>
                <button type="button" @click="remove(i)"
                        class="lc-fm-remove"
                        :aria-label="`Remove ${item.name}`">×</button>
            </li>
        </template>
    </ul>

    <p x-show="error" x-cloak class="lc-fm-error" x-text="error"></p>
</div>

@once
    @push('scripts')
        <script>
            window.lcInputFileMulti = function ({ maxFiles, maxSizeMb, accept }) {
                let idSeed = 0;
                return {
                    files: [],
                    dragActive: false,
                    error: '',
                    init() {},
                    onChoose(event) {
                        this.absorb(event.target.files);
                    },
                    onDrop(event) {
                        this.dragActive = false;
                        this.absorb(event.dataTransfer.files);
                    },
                    absorb(fileList) {
                        this.error = '';
                        const incoming = Array.from(fileList || []);
                        for (const f of incoming) {
                            if (this.files.length >= maxFiles) {
                                this.error = `Maximum ${maxFiles} files.`;
                                break;
                            }
                            if (f.size > maxSizeMb * 1024 * 1024) {
                                this.error = `${f.name} is over the ${maxSizeMb} MB limit.`;
                                continue;
                            }
                            if (! this.matchesAccept(f)) {
                                this.error = `${f.name} is not an accepted file type.`;
                                continue;
                            }
                            const item = {
                                id: ++idSeed,
                                name: f.name,
                                size: f.size,
                                file: f,
                                previewUrl: f.type.startsWith('image/') ? URL.createObjectURL(f) : '',
                            };
                            this.files.push(item);
                        }
                        // Re-populate the file input from our internal list ·
                        // mixes a fresh selection with earlier drag-drops,
                        // and survives a remove(). DataTransfer can be
                        // read-only on a few old browsers · we fall back to
                        // letting the consumer poll `this.files`.
                        this.syncInput();
                    },
                    matchesAccept(file) {
                        if (! accept || accept === '*/*') return true;
                        return accept.split(',').map((s) => s.trim()).some((pattern) => {
                            if (pattern.endsWith('/*')) return file.type.startsWith(pattern.slice(0, -1));
                            if (pattern.startsWith('.')) return file.name.toLowerCase().endsWith(pattern.toLowerCase());
                            return file.type === pattern;
                        });
                    },
                    syncInput() {
                        try {
                            const dt = new DataTransfer();
                            for (const item of this.files) dt.items.add(item.file);
                            this.$refs.fileField.files = dt.files;
                            this.$refs.fileField.dispatchEvent(new Event('change', { bubbles: true }));
                        } catch (e) {
                            // DataTransfer read-only · consumers can fall
                            // back to a custom event with the list.
                            this.$root.dispatchEvent(new CustomEvent('lc-input:file-multi:changed', {
                                detail: { files: this.files.map((i) => i.file) }, bubbles: true,
                            }));
                        }
                    },
                    remove(i) {
                        const [item] = this.files.splice(i, 1);
                        if (item?.previewUrl) URL.revokeObjectURL(item.previewUrl);
                        this.syncInput();
                    },
                    formatSize(bytes) {
                        if (bytes < 1024) return `${bytes} B`;
                        if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(0)} KB`;
                        return `${(bytes / 1024 / 1024).toFixed(1)} MB`;
                    },
                };
            };
        </script>
    @endpush
    @push('scripts')
        <style>
            .lc-input--file-multi .lc-fm-input-hidden {
                position: absolute;
                left: -9999px;
                width: 1px;
                height: 1px;
                opacity: 0;
            }
            .lc-input--file-multi .lc-fm-zone {
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                gap: .25rem;
                padding: 1.5rem;
                border: 2px dashed var(--lc-input-border-resolved, var(--line, #3A3D40));
                border-radius: var(--lc-input-radius-resolved, .65rem);
                background: var(--lc-input-bg-resolved, var(--surface-2, #1E1F22));
                color: var(--lc-input-ink-resolved, var(--ink, #F0EDE5));
                cursor: pointer;
                transition: border-color .15s, background-color .15s;
            }
            .lc-input--file-multi.is-drag-active .lc-fm-zone {
                border-color: var(--lc-input-accent-resolved, var(--accent, #2C66E8));
                background: color-mix(in srgb, var(--lc-input-accent-resolved, #2C66E8) 8%, var(--lc-input-bg-resolved, #1E1F22));
            }
            .lc-input--file-multi .lc-fm-zone-prompt {
                font-size: .9rem;
            }
            .lc-input--file-multi .lc-fm-zone-limits {
                font-size: .75rem;
                color: var(--lc-input-ink-dim-resolved, var(--ink-dim, #A3A099));
            }
            .lc-input--file-multi .lc-fm-list {
                list-style: none;
                margin: .75rem 0 0;
                padding: 0;
                display: flex;
                flex-direction: column;
                gap: .35rem;
            }
            .lc-input--file-multi .lc-fm-item {
                display: flex;
                align-items: center;
                gap: .65rem;
                padding: .4rem .55rem;
                border: 1px solid var(--lc-input-border-resolved, var(--line, #3A3D40));
                border-radius: var(--lc-input-radius-resolved, .5rem);
                background: var(--lc-input-bg-resolved, var(--surface-2, #1E1F22));
            }
            .lc-input--file-multi .lc-fm-thumb {
                width: 2.25rem;
                height: 2.25rem;
                border-radius: .35rem;
                background: rgba(255, 255, 255, .05);
                display: flex;
                align-items: center;
                justify-content: center;
                color: var(--lc-input-ink-dim-resolved, var(--ink-dim, #A3A099));
                overflow: hidden;
                flex: 0 0 auto;
            }
            .lc-input--file-multi .lc-fm-thumb img {
                width: 100%;
                height: 100%;
                object-fit: cover;
            }
            .lc-input--file-multi .lc-fm-meta {
                flex: 1;
                min-width: 0;
            }
            .lc-input--file-multi .lc-fm-name {
                font-size: .85rem;
                color: var(--lc-input-ink-resolved, var(--ink, #F0EDE5));
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }
            .lc-input--file-multi .lc-fm-size {
                font-size: .75rem;
                color: var(--lc-input-ink-dim-resolved, var(--ink-dim, #A3A099));
                font-variant-numeric: tabular-nums;
            }
            .lc-input--file-multi .lc-fm-remove {
                background: transparent;
                border: 0;
                color: var(--lc-input-ink-dim-resolved, var(--ink-dim, #A3A099));
                cursor: pointer;
                font-size: 1.2rem;
                padding: 0 .35rem;
            }
            .lc-input--file-multi .lc-fm-remove:hover {
                color: var(--lc-input-danger-resolved, var(--danger, #ef4444));
            }
            .lc-input--file-multi .lc-fm-error {
                margin: .5rem 0 0;
                font-size: .8rem;
                color: var(--lc-input-danger-resolved, var(--danger, #ef4444));
            }
        </style>
    @endpush
@endonce
