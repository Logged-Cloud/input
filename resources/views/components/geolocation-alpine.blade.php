@props([
    /**
     * Base form name · the lat / lng are posted as `{name}_lat` and
     * `{name}_lng`. Override the suffixes via latName / lngName.
     */
    'name' => 'location',
    'latName' => null,
    'lngName' => null,
    /**
     * Optional initial pair · '51.5074,-0.1278' or
     * ['lat' => 51.5074, 'lng' => -0.1278].
     */
    'value' => null,
    /**
     * 'high' enables enableHighAccuracy (longer wait, better fix). 'low'
     * is faster · cell-tower / wifi-based position. Defaults to
     * `input.geolocation.precision`.
     */
    'precision' => null,
    /**
     * Timeout for the navigator.geolocation call, in ms. Defaults to
     * `input.geolocation.timeout_ms`.
     */
    'timeoutMs' => null,
    'required' => false,
    'label' => 'Use my location',
])

@php
    $latName ??= $name.'_lat';
    $lngName ??= $name.'_lng';
    $precision ??= config('input.geolocation.precision', 'high');
    $timeoutMs ??= (int) config('input.geolocation.timeout_ms', 12000);

    $initialLat = '';
    $initialLng = '';
    if (is_array($value)) {
        $initialLat = (string) ($value['lat'] ?? '');
        $initialLng = (string) ($value['lng'] ?? '');
    } elseif (is_string($value) && str_contains($value, ',')) {
        [$initialLat, $initialLng] = array_map('trim', explode(',', $value, 2));
    }
@endphp

<div
    x-data="lcInputGeolocation({
        initialLat: @js((string) $initialLat),
        initialLng: @js((string) $initialLng),
        precision: @js($precision),
        timeoutMs: @js((int) $timeoutMs),
    })"
    x-init="init()"
    class="lc-input lc-input--geolocation {{ $attributes->get('class') }}"
    {{ $attributes->except('class')->merge(['data-component' => 'input::geolocation']) }}
>
    <div class="lc-geo-row">
        <button type="button" @click="locate()" :disabled="busy"
                class="lc-geo-btn"
                :aria-busy="busy">
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none"
                 stroke="currentColor" stroke-width="2"
                 stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="3"/>
                <path d="M12 1v3M12 20v3M1 12h3M20 12h3"/>
                <circle cx="12" cy="12" r="9"/>
            </svg>
            <span x-text="busy ? 'Locating…' : '{{ $label }}'"></span>
        </button>

        <p x-show="display" class="lc-geo-display" x-text="display" x-cloak></p>
    </div>

    <p x-show="error" class="lc-geo-error" x-text="error" x-cloak></p>

    {{-- Server posts decimal degrees · standard numeric validation works. --}}
    <input type="hidden" name="{{ $latName }}" x-model="lat" @if ($required) required @endif>
    <input type="hidden" name="{{ $lngName }}" x-model="lng" @if ($required) required @endif>
</div>

@once
    @push('scripts')
        <script>
            window.lcInputGeolocation = function ({ initialLat, initialLng, precision, timeoutMs }) {
                return {
                    lat: initialLat || '',
                    lng: initialLng || '',
                    busy: false,
                    error: '',
                    accuracyM: null,
                    init() {
                        if (this.lat && this.lng) this.refreshDisplay();
                    },
                    get display() {
                        if (! this.lat || ! this.lng) return '';
                        const lat = parseFloat(this.lat).toFixed(5);
                        const lng = parseFloat(this.lng).toFixed(5);
                        const acc = this.accuracyM !== null
                            ? ` · ±${Math.round(this.accuracyM)} m`
                            : '';
                        return `${lat}, ${lng}${acc}`;
                    },
                    refreshDisplay() {
                        // No-op · `display` getter recomputes on read.
                    },
                    locate() {
                        if (! navigator.geolocation) {
                            this.error = 'Geolocation is not available in this browser.';
                            return;
                        }
                        this.busy = true;
                        this.error = '';
                        navigator.geolocation.getCurrentPosition(
                            (pos) => {
                                this.lat = pos.coords.latitude.toString();
                                this.lng = pos.coords.longitude.toString();
                                this.accuracyM = pos.coords.accuracy ?? null;
                                this.busy = false;
                                // Bubble a custom event so consumers can
                                // react (e.g. centre a map on the result).
                                this.$root.dispatchEvent(new CustomEvent('lc-input:geolocation:resolved', {
                                    detail: { lat: pos.coords.latitude, lng: pos.coords.longitude, accuracy: pos.coords.accuracy },
                                    bubbles: true,
                                }));
                            },
                            (err) => {
                                this.busy = false;
                                this.error = err.code === err.PERMISSION_DENIED
                                    ? 'Location permission denied · check your browser settings.'
                                    : err.code === err.POSITION_UNAVAILABLE
                                        ? 'Location unavailable · try again outside.'
                                        : 'Could not get your location · try again.';
                            },
                            {
                                enableHighAccuracy: precision === 'high',
                                timeout: timeoutMs,
                                maximumAge: 30000,
                            },
                        );
                    },
                };
            };
        </script>
    @endpush
    @push('scripts')
        <style>
            .lc-input--geolocation .lc-geo-row {
                display: flex;
                flex-wrap: wrap;
                align-items: center;
                gap: .65rem;
            }
            .lc-input--geolocation .lc-geo-btn {
                display: inline-flex;
                align-items: center;
                gap: .5rem;
                background: var(--lc-input-bg-resolved, var(--surface-2, #1E1F22));
                color: var(--lc-input-ink-resolved, var(--ink, #F0EDE5));
                border: 1px solid var(--lc-input-border-resolved, var(--line, #3A3D40));
                border-radius: var(--lc-input-radius-resolved, .5rem);
                padding: .55rem .9rem;
                font: inherit;
                cursor: pointer;
            }
            .lc-input--geolocation .lc-geo-btn:hover {
                background: color-mix(in srgb, var(--lc-input-accent-resolved, #2C66E8) 12%, var(--lc-input-bg-resolved, #1E1F22));
            }
            .lc-input--geolocation .lc-geo-btn:disabled {
                opacity: .55;
                cursor: not-allowed;
            }
            .lc-input--geolocation .lc-geo-display {
                margin: 0;
                font-size: .85rem;
                color: var(--lc-input-ink-dim-resolved, var(--ink-dim, #A3A099));
                font-variant-numeric: tabular-nums;
            }
            .lc-input--geolocation .lc-geo-error {
                margin: .35rem 0 0;
                font-size: .8rem;
                color: var(--lc-input-danger-resolved, var(--danger, #ef4444));
            }
        </style>
    @endpush
@endonce
