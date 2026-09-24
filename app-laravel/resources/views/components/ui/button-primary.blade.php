@props(['disabled' => false, 'href' => null])

@php
    $clases = 'inline-flex min-h-11 items-center justify-center gap-xs rounded-sm px-lg font-sans text-body-md font-semibold transition-colors duration-150 '
        .($disabled
            ? 'cursor-not-allowed bg-primary-disabled text-muted'
            : 'bg-primary text-on-primary hover:bg-primary-active disabled:cursor-wait disabled:opacity-70');
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $clases]) }}>{{ $slot }}</a>
@else
    <button @disabled($disabled) {{ $attributes->merge(['type' => 'submit', 'class' => $clases]) }}>{{ $slot }}</button>
@endif
