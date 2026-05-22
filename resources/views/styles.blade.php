{{-- Shared CSS for <x-input::*> variants. Each component @once-pushes its
     own <style> block too, so this file is optional · only include it if
     you want a single override surface across the app:

         @include('input::styles')

     in your layout. --}}
@php
    $theme = config('input.theme');
@endphp
<style>
.lc-input,
.lc-input--mask,
.lc-input--password,
.lc-input--otp,
.lc-input--textarea {
    --lc-input-bg-resolved:     {!! $theme['bg'] !!};
    --lc-input-border-resolved: {!! $theme['border'] !!};
    --lc-input-ink-resolved:    {!! $theme['ink'] !!};
    --lc-input-ink-dim-resolved:{!! $theme['ink_dim'] !!};
    --lc-input-accent-resolved: {!! $theme['accent'] !!};
    --lc-input-danger-resolved: {!! $theme['danger'] !!};
    --lc-input-radius-resolved: {!! $theme['radius'] !!};
}
</style>
