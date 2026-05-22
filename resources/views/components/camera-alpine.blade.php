@props([
    /**
     * Posted file name. The component injects a real File object into a
     * hidden <input type="file"> via DataTransfer, so server-side
     * validation can use the standard "image|max:2048" rules.
     */
    'name' => 'photo',
    /**
     * 'environment' (rear) or 'user' (front). Browsers fall back when the
     * preferred camera is not on the device. Defaults to
     * `input.camera.facing`.
     */
    'facing' => null,
    /**
     * Output MIME type · 'image/jpeg' (most compatible) or 'image/webp'
     * (better compression on supported browsers). Defaults to
     * `input.camera.mime`.
     */
    'mime' => null,
    /**
     * 0.0 - 1.0 compression quality. Defaults to `input.camera.quality`.
     */
    'quality' => null,
    /**
     * Longest-edge cap in pixels · resized proportionally before
     * encoding. Defaults to `input.camera.max_edge`.
     */
    'maxEdge' => null,
    /**
     * Whether to surface the OS gallery picker as a fallback button when
     * getUserMedia is unavailable / denied. Defaults to
     * `input.camera.allow_gallery`.
     */
    'allowGallery' => null,
    /**
     * Camera frame aspect · 'square', 'portrait', '4:3', '16:9', or 'auto'.
     */
    'aspect' => 'square',
    'required' => false,
    'id' => null,
])

@php
    $id ??= $name;
    $facing       ??= config('input.camera.facing', 'environment');
    $mime         ??= config('input.camera.mime', 'image/jpeg');
    $quality      ??= config('input.camera.quality', 0.85);
    $maxEdge      ??= (int) config('input.camera.max_edge', 1600);
    $allowGallery ??= (bool) config('input.camera.allow_gallery', true);

    $aspectClass = match ($aspect) {
        'square'   => 'aspect-square',
        'portrait' => 'aspect-portrait',
        '4:3'      => 'aspect-4-3',
        '16:9'     => 'aspect-16-9',
        default    => 'aspect-auto',
    };
@endphp

<div
    x-data="lcInputCamera({
        facing: @js($facing),
        mime: @js($mime),
        quality: @js((float) $quality),
        maxEdge: @js((int) $maxEdge),
        allowGallery: @js((bool) $allowGallery),
        fileName: @js($name),
    })"
    x-init="init()"
    class="lc-input lc-input--camera {{ $attributes->get('class') }}"
    {{ $attributes->except('class')->merge(['data-component' => 'input::camera']) }}
>
    {{-- The real file input that ships with the form submission. We
         programmatically populate it via DataTransfer after each capture
         so the server sees a normal multipart upload · no Livewire
         wire:model rebinds, no base64-blob plumbing. --}}
    <input
        type="file"
        id="{{ $id }}"
        name="{{ $name }}"
        accept="image/*"
        x-ref="fileField"
        class="lc-camera-file"
        @if ($required) required @endif
        @change="onGalleryChoose($event)"
    >

    {{-- Live camera stream (visible while capturing). --}}
    <div class="lc-camera-stage {{ $aspectClass }}" x-show="stage === 'stream'" x-cloak>
        <video x-ref="video" autoplay muted playsinline></video>
        <button type="button" class="lc-camera-shutter" @click="capture()" aria-label="Capture">
            <span class="lc-camera-shutter-ring"></span>
        </button>
    </div>

    {{-- Captured preview (visible after a capture). --}}
    <div class="lc-camera-stage {{ $aspectClass }}" x-show="stage === 'preview'" x-cloak>
        <img :src="previewUrl" alt="Captured preview">
        <div class="lc-camera-controls">
            <button type="button" class="lc-camera-btn lc-camera-btn--secondary"
                    @click="retake()">Retake</button>
            <button type="button" class="lc-camera-btn lc-camera-btn--primary"
                    @click="commit()">Use photo</button>
        </div>
    </div>

    {{-- Idle / fallback (no permission, no camera, or initial state). --}}
    <div class="lc-camera-stage lc-camera-stage--idle {{ $aspectClass }}" x-show="stage === 'idle'" x-cloak>
        <button type="button" class="lc-camera-btn lc-camera-btn--primary"
                @click="openCamera()" x-show="canCapture">
            <svg viewBox="0 0 24 24" width="20" height="20" fill="none"
                 stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/>
                <circle cx="12" cy="13" r="4"/>
            </svg>
            Open camera
        </button>
        @if ($allowGallery)
            <button type="button" class="lc-camera-btn lc-camera-btn--secondary"
                    @click="$refs.fileField.click()">
                Choose from gallery
            </button>
        @endif
        <p x-show="status" :class="error ? 'lc-camera-error' : 'lc-camera-hint'"
           x-text="status"></p>
    </div>

    {{-- Filled state · committed photo, can be cleared. --}}
    <div class="lc-camera-stage {{ $aspectClass }}" x-show="stage === 'filled'" x-cloak>
        <img :src="previewUrl" alt="Selected photo">
        <button type="button" class="lc-camera-btn lc-camera-btn--secondary lc-camera-clear"
                @click="clear()" aria-label="Remove photo">
            Replace
        </button>
    </div>

    <canvas x-ref="canvas" class="lc-camera-canvas-hidden"></canvas>
</div>

@once
    @push('scripts')
        <script>
            window.lcInputCamera = function ({ facing, mime, quality, maxEdge, allowGallery, fileName }) {
                return {
                    stage: 'idle',       // 'idle' | 'stream' | 'preview' | 'filled'
                    canCapture: false,
                    status: '',
                    error: false,
                    stream: null,
                    blob: null,
                    previewUrl: '',
                    init() {
                        // Feature-detect once · disables the "Open camera"
                        // button on desktops without a webcam and on http://
                        // dev servers where getUserMedia is unavailable.
                        this.canCapture = !! (
                            navigator.mediaDevices &&
                            typeof navigator.mediaDevices.getUserMedia === 'function'
                        );
                        if (! this.canCapture && ! allowGallery) {
                            this.status = 'Camera not supported on this device or browser.';
                            this.error = true;
                        } else if (! this.canCapture) {
                            this.status = 'No camera detected · choose a photo instead.';
                        }
                    },
                    async openCamera() {
                        this.status = ''; this.error = false;
                        try {
                            this.stream = await navigator.mediaDevices.getUserMedia({
                                video: {
                                    facingMode: { ideal: facing },
                                    // Asking for a smallish ideal cuts cold-
                                    // start latency · we resize anyway before
                                    // upload via maxEdge.
                                    width:  { ideal: 1920 },
                                    height: { ideal: 1080 },
                                },
                                audio: false,
                            });
                            this.$refs.video.srcObject = this.stream;
                            // iOS Safari sometimes needs an explicit play()
                            // even with autoplay; await it so a failure
                            // surfaces as a catch rather than a silent
                            // black frame.
                            await this.$refs.video.play().catch(() => {});
                            this.stage = 'stream';
                        } catch (e) {
                            this.status = (e && e.name === 'NotAllowedError')
                                ? 'Camera permission denied · check your browser settings.'
                                : 'Could not start the camera.';
                            this.error = true;
                        }
                    },
                    async capture() {
                        const video = this.$refs.video;
                        const canvas = this.$refs.canvas;
                        if (! video || ! canvas || ! video.videoWidth) return;

                        // Compute scaled dimensions · keep aspect, cap the
                        // longest edge so a 12 MP iPhone capture lands at a
                        // sensible upload size.
                        const vw = video.videoWidth;
                        const vh = video.videoHeight;
                        const scale = Math.min(1, maxEdge / Math.max(vw, vh));
                        const w = Math.round(vw * scale);
                        const h = Math.round(vh * scale);
                        canvas.width = w;
                        canvas.height = h;

                        const ctx = canvas.getContext('2d');
                        ctx.drawImage(video, 0, 0, w, h);

                        // Convert to a Blob in the chosen format · async
                        // because toBlob is callback-based on every browser.
                        this.blob = await new Promise((resolve) => {
                            canvas.toBlob(resolve, mime, quality);
                        });
                        if (this.blob) {
                            if (this.previewUrl) URL.revokeObjectURL(this.previewUrl);
                            this.previewUrl = URL.createObjectURL(this.blob);
                            this.stage = 'preview';
                        }
                        this.stopStream();
                    },
                    retake() {
                        this.blob = null;
                        if (this.previewUrl) {
                            URL.revokeObjectURL(this.previewUrl);
                            this.previewUrl = '';
                        }
                        this.openCamera();
                    },
                    commit() {
                        // Inject the captured Blob into the real file input
                        // via DataTransfer · the form submits as normal
                        // multipart/form-data and the server gets a valid
                        // UploadedFile that "image" / "mimes:" validation
                        // can introspect.
                        const ext = mime === 'image/webp' ? 'webp' : 'jpg';
                        const file = new File([this.blob], `${fileName}-${Date.now()}.${ext}`, {
                            type: mime, lastModified: Date.now(),
                        });
                        try {
                            const dt = new DataTransfer();
                            dt.items.add(file);
                            this.$refs.fileField.files = dt.files;
                            // Bubble a change event so any wire:model binding
                            // or stock onchange listener notices the new file.
                            this.$refs.fileField.dispatchEvent(new Event('change', { bubbles: true }));
                        } catch (e) {
                            // DataTransfer is read-only on a couple of very
                            // old browsers · fall back to a plain custom
                            // event that the consumer can listen for.
                            this.$root.dispatchEvent(new CustomEvent('lc-input:camera:committed', {
                                detail: { blob: this.blob, file }, bubbles: true,
                            }));
                        }
                        this.stage = 'filled';
                    },
                    clear() {
                        this.blob = null;
                        if (this.previewUrl) {
                            URL.revokeObjectURL(this.previewUrl);
                            this.previewUrl = '';
                        }
                        // Reset the underlying file input so a fresh re-
                        // commit fires a change event next time.
                        if (this.$refs.fileField) this.$refs.fileField.value = '';
                        this.stage = 'idle';
                    },
                    onGalleryChoose(event) {
                        // Gallery-fallback path · the user picked an image
                        // file via the system picker. We still run it
                        // through the same canvas pipeline so the upload
                        // size is bounded by maxEdge / quality.
                        const file = event.target.files?.[0];
                        if (! file) return;
                        const img = new Image();
                        img.onload = async () => {
                            const canvas = this.$refs.canvas;
                            const scale = Math.min(1, maxEdge / Math.max(img.width, img.height));
                            const w = Math.round(img.width * scale);
                            const h = Math.round(img.height * scale);
                            canvas.width = w;
                            canvas.height = h;
                            canvas.getContext('2d').drawImage(img, 0, 0, w, h);
                            this.blob = await new Promise((r) => canvas.toBlob(r, mime, quality));
                            if (this.blob) {
                                if (this.previewUrl) URL.revokeObjectURL(this.previewUrl);
                                this.previewUrl = URL.createObjectURL(this.blob);
                                this.stage = 'preview';
                            }
                            URL.revokeObjectURL(img.src);
                        };
                        img.src = URL.createObjectURL(file);
                    },
                    stopStream() {
                        if (this.stream) {
                            for (const t of this.stream.getTracks()) t.stop();
                            this.stream = null;
                        }
                    },
                    destroy() {
                        this.stopStream();
                        if (this.previewUrl) URL.revokeObjectURL(this.previewUrl);
                    },
                };
            };
        </script>
    @endpush
    @push('scripts')
        <style>
            .lc-input--camera {
                width: 100%;
                max-width: 24rem;
            }
            .lc-input--camera .lc-camera-file {
                position: absolute;
                left: -9999px;
                width: 1px;
                height: 1px;
                opacity: 0;
            }
            .lc-input--camera .lc-camera-stage {
                position: relative;
                border: 1px solid var(--lc-input-border-resolved, var(--line, #3A3D40));
                border-radius: var(--lc-input-radius-resolved, .65rem);
                background: var(--lc-input-bg-resolved, var(--surface-2, #1E1F22));
                color: var(--lc-input-ink-resolved, var(--ink, #F0EDE5));
                overflow: hidden;
            }
            .lc-input--camera .lc-camera-stage.aspect-square   { aspect-ratio: 1 / 1; }
            .lc-input--camera .lc-camera-stage.aspect-portrait { aspect-ratio: 3 / 4; }
            .lc-input--camera .lc-camera-stage.aspect-4-3      { aspect-ratio: 4 / 3; }
            .lc-input--camera .lc-camera-stage.aspect-16-9     { aspect-ratio: 16 / 9; }
            .lc-input--camera .lc-camera-stage--idle {
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                gap: .75rem;
                padding: 1.5rem;
            }
            .lc-input--camera video,
            .lc-input--camera img {
                position: absolute;
                inset: 0;
                width: 100%;
                height: 100%;
                object-fit: cover;
                display: block;
            }
            .lc-input--camera .lc-camera-shutter {
                position: absolute;
                bottom: 1rem;
                left: 50%;
                transform: translateX(-50%);
                width: 4rem;
                height: 4rem;
                border-radius: 50%;
                background: rgba(255, 255, 255, .9);
                border: 0;
                cursor: pointer;
                padding: 0;
                display: flex;
                align-items: center;
                justify-content: center;
                box-shadow: 0 2px 8px rgba(0, 0, 0, .4);
            }
            .lc-input--camera .lc-camera-shutter:active {
                transform: translateX(-50%) scale(.95);
            }
            .lc-input--camera .lc-camera-shutter-ring {
                width: 3rem;
                height: 3rem;
                border-radius: 50%;
                background: white;
                border: 2px solid rgba(0, 0, 0, .15);
            }
            .lc-input--camera .lc-camera-controls {
                position: absolute;
                bottom: 0;
                left: 0;
                right: 0;
                display: flex;
                gap: .5rem;
                padding: .75rem;
                background: linear-gradient(to top, rgba(0, 0, 0, .55), transparent);
            }
            .lc-input--camera .lc-camera-btn {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                gap: .5rem;
                padding: .65rem 1.1rem;
                border-radius: .5rem;
                font-weight: 600;
                font-size: .95rem;
                cursor: pointer;
                border: 0;
            }
            .lc-input--camera .lc-camera-btn--primary {
                background: var(--lc-input-accent-resolved, var(--accent, #2C66E8));
                color: white;
                flex: 1;
            }
            .lc-input--camera .lc-camera-btn--secondary {
                background: rgba(255, 255, 255, .15);
                color: white;
                flex: 1;
            }
            .lc-input--camera .lc-camera-stage--idle .lc-camera-btn--secondary {
                background: transparent;
                color: var(--lc-input-ink-dim-resolved, var(--ink-dim, #A3A099));
                border: 1px solid var(--lc-input-border-resolved, var(--line, #3A3D40));
            }
            .lc-input--camera .lc-camera-clear {
                position: absolute;
                top: .65rem;
                right: .65rem;
                flex: 0 0 auto;
                padding: .35rem .7rem;
                font-size: .75rem;
                background: rgba(0, 0, 0, .55);
                color: white;
            }
            .lc-input--camera .lc-camera-hint,
            .lc-input--camera .lc-camera-error {
                font-size: .8rem;
                margin: 0;
                text-align: center;
            }
            .lc-input--camera .lc-camera-error {
                color: var(--lc-input-danger-resolved, var(--danger, #ef4444));
            }
            .lc-input--camera .lc-camera-hint {
                color: var(--lc-input-ink-dim-resolved, var(--ink-dim, #A3A099));
            }
            .lc-input--camera .lc-camera-canvas-hidden {
                display: none;
            }
        </style>
    @endpush
@endonce
