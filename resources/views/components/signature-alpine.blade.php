@props([
    /**
     * Posted form name · receives a real PNG file via DataTransfer so
     * server-side `image|max:N` validation works as-is.
     */
    'name' => 'signature',
    'id' => null,
    /**
     * Display height for the canvas · the width is responsive. Pixel-
     * exact dimensions on the rendered bitmap are computed in JS so
     * retina displays draw crisp strokes (devicePixelRatio multiply).
     */
    'height' => 180,
    /**
     * Stroke colour · CSS colour. Defaults to `input.signature.stroke`.
     */
    'stroke' => null,
    /**
     * Stroke width in CSS px · multiplied by DPR internally.
     */
    'strokeWidth' => null,
    'required' => false,
])

@php
    $id ??= $name;
    $stroke ??= config('input.signature.stroke', 'currentColor');
    $strokeWidth ??= (float) config('input.signature.stroke_width', 2.5);
@endphp

<div
    x-data="lcInputSignature({
        stroke: @js($stroke),
        strokeWidth: @js((float) $strokeWidth),
        fileName: @js($name),
    })"
    x-init="init()"
    class="lc-input lc-input--signature {{ $attributes->get('class') }}"
    {{ $attributes->except('class')->merge(['data-component' => 'input::signature']) }}
>
    <div class="lc-sig-stage" :style="`height:{{ (int) $height }}px;`">
        <canvas
            x-ref="canvas"
            @pointerdown.prevent="onPointerDown($event)"
            @pointermove.prevent="onPointerMove($event)"
            @pointerup="onPointerUp($event)"
            @pointerleave="onPointerUp($event)"
            @pointercancel="onPointerUp($event)"
            class="lc-sig-canvas"
            aria-label="Signature pad"
        ></canvas>
        <span class="lc-sig-baseline" x-show="empty">Sign here</span>
    </div>
    <div class="lc-sig-controls">
        <button type="button" class="lc-sig-btn" @click="clear()" :disabled="empty">
            Clear
        </button>
    </div>

    {{-- File input populated via DataTransfer when the form submits or
         when commit() is called explicitly. Posts a real PNG so the
         server gets an UploadedFile. --}}
    <input
        type="file"
        id="{{ $id }}"
        name="{{ $name }}"
        accept="image/png"
        x-ref="fileField"
        class="lc-sig-file"
        @if ($required) required @endif
    >
</div>

@once
    @push('scripts')
        <script>
            window.lcInputSignature = function ({ stroke, strokeWidth, fileName }) {
                return {
                    empty: true,
                    drawing: false,
                    last: null,
                    ctx: null,
                    dpr: 1,
                    init() {
                        const canvas = this.$refs.canvas;
                        if (! canvas) return;
                        this.dpr = window.devicePixelRatio || 1;
                        // Size the bitmap to the displayed CSS pixels ×
                        // DPR · canvas internals stay sharp on retina.
                        const rect = canvas.getBoundingClientRect();
                        canvas.width  = rect.width * this.dpr;
                        canvas.height = rect.height * this.dpr;
                        this.ctx = canvas.getContext('2d');
                        this.ctx.scale(this.dpr, this.dpr);
                        this.ctx.lineCap = 'round';
                        this.ctx.lineJoin = 'round';
                        this.ctx.lineWidth = strokeWidth;
                        // 'currentColor' literally evaluates · resolve via
                        // computed style on the canvas.
                        this.ctx.strokeStyle = stroke === 'currentColor'
                            ? getComputedStyle(canvas).color
                            : stroke;

                        // Commit on the closest form's submit so the server
                        // gets the latest signature, not whatever stale
                        // state the file input held.
                        const form = this.$root.closest('form');
                        if (form) form.addEventListener('submit', () => this.commit(), { capture: true });

                        // Resize the bitmap if the layout reflows (the
                        // canvas would otherwise stretch and blur).
                        window.addEventListener('resize', () => this.resize());
                    },
                    onPointerDown(e) {
                        if (! this.ctx) return;
                        this.drawing = true;
                        this.last = this.point(e);
                        e.target.setPointerCapture?.(e.pointerId);
                    },
                    onPointerMove(e) {
                        if (! this.drawing) return;
                        const p = this.point(e);
                        this.ctx.beginPath();
                        this.ctx.moveTo(this.last.x, this.last.y);
                        this.ctx.lineTo(p.x, p.y);
                        this.ctx.stroke();
                        this.last = p;
                        this.empty = false;
                    },
                    onPointerUp() {
                        this.drawing = false;
                        this.last = null;
                    },
                    point(e) {
                        const rect = e.target.getBoundingClientRect();
                        return {
                            x: e.clientX - rect.left,
                            y: e.clientY - rect.top,
                        };
                    },
                    clear() {
                        if (! this.ctx) return;
                        const canvas = this.$refs.canvas;
                        this.ctx.save();
                        this.ctx.setTransform(1, 0, 0, 1, 0, 0);
                        this.ctx.clearRect(0, 0, canvas.width, canvas.height);
                        this.ctx.restore();
                        this.empty = true;
                        if (this.$refs.fileField) this.$refs.fileField.value = '';
                    },
                    resize() {
                        // Preserve the existing drawing by re-rendering
                        // after the bitmap resize. We capture the canvas
                        // pixels first then redraw onto the new dimensions.
                        const canvas = this.$refs.canvas;
                        if (! canvas) return;
                        const snapshot = canvas.toDataURL();
                        const rect = canvas.getBoundingClientRect();
                        canvas.width  = rect.width * this.dpr;
                        canvas.height = rect.height * this.dpr;
                        this.ctx.scale(this.dpr, this.dpr);
                        this.ctx.lineCap = 'round';
                        this.ctx.lineJoin = 'round';
                        this.ctx.lineWidth = strokeWidth;
                        this.ctx.strokeStyle = stroke === 'currentColor'
                            ? getComputedStyle(canvas).color
                            : stroke;
                        const img = new Image();
                        img.onload = () => this.ctx.drawImage(img, 0, 0, rect.width, rect.height);
                        img.src = snapshot;
                    },
                    commit() {
                        // Turn the canvas into a PNG Blob, drop it into the
                        // file input via DataTransfer · same pattern as
                        // camera-alpine. Skipped when the pad is empty so
                        // an unsigned form posts the field as empty (which
                        // the server's `required` rule catches naturally).
                        if (this.empty) return;
                        const canvas = this.$refs.canvas;
                        canvas.toBlob((blob) => {
                            if (! blob) return;
                            const file = new File([blob], `${fileName}-${Date.now()}.png`, {
                                type: 'image/png',
                            });
                            try {
                                const dt = new DataTransfer();
                                dt.items.add(file);
                                this.$refs.fileField.files = dt.files;
                            } catch (e) {
                                this.$root.dispatchEvent(new CustomEvent('lc-input:signature:committed', {
                                    detail: { blob, file }, bubbles: true,
                                }));
                            }
                        }, 'image/png');
                    },
                };
            };
        </script>
    @endpush
    @push('scripts')
        <style>
            .lc-input--signature .lc-sig-stage {
                position: relative;
                background: var(--lc-input-bg-resolved, var(--surface-2, #1E1F22));
                border: 1px solid var(--lc-input-border-resolved, var(--line, #3A3D40));
                border-radius: var(--lc-input-radius-resolved, .65rem);
                overflow: hidden;
                touch-action: none;
            }
            .lc-input--signature .lc-sig-canvas {
                position: absolute;
                inset: 0;
                width: 100%;
                height: 100%;
                color: var(--lc-input-ink-resolved, var(--ink, #F0EDE5));
                cursor: crosshair;
            }
            .lc-input--signature .lc-sig-baseline {
                position: absolute;
                inset: auto 0 .5rem 0;
                text-align: center;
                color: var(--lc-input-ink-dim-resolved, var(--ink-dim, #A3A099));
                font-size: .8rem;
                pointer-events: none;
            }
            .lc-input--signature .lc-sig-controls {
                display: flex;
                justify-content: flex-end;
                margin-top: .5rem;
            }
            .lc-input--signature .lc-sig-btn {
                background: transparent;
                color: var(--lc-input-ink-dim-resolved, var(--ink-dim, #A3A099));
                border: 0;
                cursor: pointer;
                font: inherit;
                padding: .25rem .5rem;
            }
            .lc-input--signature .lc-sig-btn:disabled {
                opacity: .4;
                cursor: not-allowed;
            }
            .lc-input--signature .lc-sig-file {
                position: absolute;
                left: -9999px;
                width: 1px;
                height: 1px;
                opacity: 0;
            }
        </style>
    @endpush
@endonce
