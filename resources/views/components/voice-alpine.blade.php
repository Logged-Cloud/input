@props([
    /**
     * Posted form name · receives a real audio file via DataTransfer.
     */
    'name' => 'voice',
    'id' => null,
    /**
     * Preferred MediaRecorder MIME type · the browser falls back when the
     * codec isn't supported. Defaults to `input.voice.mime`.
     */
    'mime' => null,
    /**
     * Hard cap on a single recording in seconds. The component stops and
     * commits the blob when this is reached. Defaults to
     * `input.voice.max_seconds`.
     */
    'maxSeconds' => null,
    'required' => false,
])

@php
    $id ??= $name;
    $mime ??= config('input.voice.mime', 'audio/webm;codecs=opus');
    $maxSeconds ??= (int) config('input.voice.max_seconds', 120);
@endphp

<div
    x-data="lcInputVoice({
        mime: @js($mime),
        maxSeconds: @js((int) $maxSeconds),
        fileName: @js($name),
    })"
    x-init="init()"
    class="lc-input lc-input--voice {{ $attributes->get('class') }}"
    {{ $attributes->except('class')->merge(['data-component' => 'input::voice']) }}
>
    <div class="lc-voice-row">
        {{-- Recording start / stop. The shutter colour signals state. --}}
        <button type="button"
                @click="toggleRecord()"
                :disabled="! canRecord || busy"
                class="lc-voice-record"
                :class="recording ? 'is-recording' : ''"
                :aria-label="recording ? 'Stop recording' : 'Start recording'">
            <span class="lc-voice-record-dot"></span>
        </button>

        <div class="lc-voice-info">
            <span class="lc-voice-time" x-text="elapsedLabel"></span>
            <span class="lc-voice-status" x-show="status" x-text="status" x-cloak></span>
        </div>

        {{-- Inline playback once a clip exists. --}}
        <audio x-ref="audio" :src="previewUrl" x-show="previewUrl" x-cloak
               controls class="lc-voice-player"></audio>

        <button type="button" x-show="previewUrl"
                @click="discard()" x-cloak
                class="lc-voice-discard"
                aria-label="Discard recording">
            ✕
        </button>
    </div>

    <p x-show="error" class="lc-voice-error" x-text="error" x-cloak></p>

    <input
        type="file"
        id="{{ $id }}"
        name="{{ $name }}"
        accept="audio/*"
        x-ref="fileField"
        class="lc-voice-file"
        @if ($required) required @endif
    >
</div>

@once
    @push('scripts')
        <script>
            window.lcInputVoice = function ({ mime, maxSeconds, fileName }) {
                return {
                    canRecord: false,
                    recording: false,
                    busy: false,
                    status: '',
                    error: '',
                    chunks: [],
                    recorder: null,
                    stream: null,
                    blob: null,
                    previewUrl: '',
                    elapsedSec: 0,
                    timer: null,
                    init() {
                        this.canRecord = !! (
                            navigator.mediaDevices &&
                            typeof navigator.mediaDevices.getUserMedia === 'function' &&
                            typeof window.MediaRecorder !== 'undefined'
                        );
                        if (! this.canRecord) {
                            this.error = 'Audio recording not supported on this device or browser.';
                        }
                    },
                    get elapsedLabel() {
                        const m = Math.floor(this.elapsedSec / 60);
                        const s = this.elapsedSec % 60;
                        return `${m}:${s.toString().padStart(2, '0')}`;
                    },
                    async toggleRecord() {
                        if (this.recording) {
                            this.stop();
                        } else {
                            await this.start();
                        }
                    },
                    async start() {
                        if (! this.canRecord) return;
                        this.error = '';
                        this.status = '';
                        try {
                            this.stream = await navigator.mediaDevices.getUserMedia({ audio: true });
                            const supported = window.MediaRecorder.isTypeSupported(mime) ? mime : '';
                            this.recorder = new MediaRecorder(this.stream, supported ? { mimeType: supported } : undefined);
                            this.chunks = [];
                            this.recorder.ondataavailable = (e) => {
                                if (e.data && e.data.size > 0) this.chunks.push(e.data);
                            };
                            this.recorder.onstop = () => this.finalise();
                            this.recorder.start();
                            this.recording = true;
                            this.elapsedSec = 0;
                            // Tick the elapsed counter once a second; stop
                            // automatically when we cross maxSeconds.
                            this.timer = setInterval(() => {
                                this.elapsedSec++;
                                if (this.elapsedSec >= maxSeconds) this.stop();
                            }, 1000);
                        } catch (e) {
                            this.error = e?.name === 'NotAllowedError'
                                ? 'Microphone permission denied.'
                                : 'Could not start the microphone.';
                        }
                    },
                    stop() {
                        if (this.recorder && this.recorder.state !== 'inactive') {
                            this.recorder.stop();
                        }
                        if (this.timer) clearInterval(this.timer);
                        this.timer = null;
                        this.recording = false;
                        this.stopStream();
                    },
                    finalise() {
                        if (! this.chunks.length) {
                            this.error = 'No audio captured · the recording was too short.';
                            return;
                        }
                        const blobMime = this.recorder?.mimeType || mime;
                        this.blob = new Blob(this.chunks, { type: blobMime });
                        if (this.previewUrl) URL.revokeObjectURL(this.previewUrl);
                        this.previewUrl = URL.createObjectURL(this.blob);
                        this.commit();
                    },
                    commit() {
                        const blobMime = this.blob.type || mime;
                        const ext = blobMime.includes('webm') ? 'webm'
                                  : blobMime.includes('ogg')  ? 'ogg'
                                  : blobMime.includes('mp4')  ? 'm4a'
                                  : 'wav';
                        const file = new File([this.blob], `${fileName}-${Date.now()}.${ext}`, {
                            type: blobMime,
                        });
                        try {
                            const dt = new DataTransfer();
                            dt.items.add(file);
                            this.$refs.fileField.files = dt.files;
                            this.$refs.fileField.dispatchEvent(new Event('change', { bubbles: true }));
                        } catch (e) {
                            this.$root.dispatchEvent(new CustomEvent('lc-input:voice:committed', {
                                detail: { blob: this.blob, file }, bubbles: true,
                            }));
                        }
                    },
                    discard() {
                        if (this.previewUrl) URL.revokeObjectURL(this.previewUrl);
                        this.previewUrl = '';
                        this.blob = null;
                        this.chunks = [];
                        this.elapsedSec = 0;
                        if (this.$refs.fileField) this.$refs.fileField.value = '';
                    },
                    stopStream() {
                        if (this.stream) {
                            for (const t of this.stream.getTracks()) t.stop();
                            this.stream = null;
                        }
                    },
                };
            };
        </script>
    @endpush
    @push('scripts')
        <style>
            .lc-input--voice .lc-voice-row {
                display: flex;
                align-items: center;
                gap: .75rem;
                flex-wrap: wrap;
            }
            .lc-input--voice .lc-voice-record {
                width: 2.75rem;
                height: 2.75rem;
                border-radius: 50%;
                border: 0;
                background: var(--lc-input-bg-resolved, var(--surface-2, #1E1F22));
                cursor: pointer;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                outline: none;
                transition: background-color .15s;
            }
            .lc-input--voice .lc-voice-record:focus-visible {
                box-shadow: 0 0 0 3px color-mix(in srgb, var(--lc-input-accent-resolved, #2C66E8) 30%, transparent);
            }
            .lc-input--voice .lc-voice-record-dot {
                display: block;
                width: 1rem;
                height: 1rem;
                border-radius: 50%;
                background: var(--lc-input-danger-resolved, var(--danger, #ef4444));
                transition: border-radius .15s, transform .15s;
            }
            .lc-input--voice .lc-voice-record.is-recording .lc-voice-record-dot {
                border-radius: .2rem;
                transform: scale(.9);
                animation: lc-voice-pulse 1.2s ease-in-out infinite;
            }
            @keyframes lc-voice-pulse {
                0%, 100% { opacity: 1; }
                50%      { opacity: .55; }
            }
            .lc-input--voice .lc-voice-record:disabled {
                opacity: .55;
                cursor: not-allowed;
            }
            .lc-input--voice .lc-voice-info {
                display: flex;
                flex-direction: column;
                gap: .15rem;
                min-width: 4rem;
            }
            .lc-input--voice .lc-voice-time {
                font-variant-numeric: tabular-nums;
                font-weight: 600;
                color: var(--lc-input-ink-resolved, var(--ink, #F0EDE5));
            }
            .lc-input--voice .lc-voice-status {
                font-size: .75rem;
                color: var(--lc-input-ink-dim-resolved, var(--ink-dim, #A3A099));
            }
            .lc-input--voice .lc-voice-player {
                flex: 1;
                min-width: 8rem;
                max-width: 18rem;
            }
            .lc-input--voice .lc-voice-discard {
                background: transparent;
                color: var(--lc-input-ink-dim-resolved, var(--ink-dim, #A3A099));
                border: 0;
                cursor: pointer;
                font-size: 1rem;
                padding: .25rem .5rem;
            }
            .lc-input--voice .lc-voice-error {
                margin: .35rem 0 0;
                font-size: .8rem;
                color: var(--lc-input-danger-resolved, var(--danger, #ef4444));
            }
            .lc-input--voice .lc-voice-file {
                position: absolute;
                left: -9999px;
                width: 1px;
                height: 1px;
                opacity: 0;
            }
        </style>
    @endpush
@endonce
