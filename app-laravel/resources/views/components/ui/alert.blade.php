@props([
    'variant' => 'info',
    'title' => null,
])

@php
    [$bg, $text] = match ($variant) {
        'success' => ['bg-[#d1fae5]', 'text-[#065f46]'],
        'warning' => ['bg-[#fef3c7]', 'text-[#92400e]'],
        'error' => ['bg-[#fee2e2]', 'text-[#991b1b]'],
        default => ['bg-badge-blue', 'text-[#1a2a5e]'],
    };
@endphp

<div {{ $attributes->merge(['class' => "rounded-md px-[14px] py-[12px] font-sans text-body-sm $bg $text"]) }}>
    @if ($title)
        <p class="font-semibold text-[13px]">{{ $title }}</p>
    @endif
    <div class="text-[12px]">{{ $slot }}</div>
</div>
