@props(['disabled' => false, 'href' => null])

@php
    $clases = 'inline-flex min-h-11 items-center justify-center gap-xs rounded-sm border border-primary bg-canvas px-lg font-sans text-body-md font-semibold text-primary transition-colors duration-150 hover:bg-surface-soft '
        .($disabled ? 'cursor-not-allowed opacity-60' : '');
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $clases]) }}>{{ $slot }}</a>
@else
    <button @disabled($disabled) {{ $attributes->merge(['type' => 'button', 'class' => $clases]) }}>{{ $slot }}</button>
@endif
