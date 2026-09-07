@props(['disabled' => false])

<button
    @disabled($disabled)
    {{ $attributes->merge([
        'type' => 'button',
        'class' => 'inline-flex items-center justify-center h-[38px] px-[18px] py-[9px] rounded-md border-[0.5px] border-hairline font-sans text-button font-semibold '
            . ($disabled
                ? 'bg-surface-soft text-muted cursor-not-allowed'
                : 'bg-canvas text-ink hover:bg-surface-soft focus:outline-none focus:ring-[3px] focus:ring-primary/10'),
    ]) }}
>
    {{ $slot }}
</button>
