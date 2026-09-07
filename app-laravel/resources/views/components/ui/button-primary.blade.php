@props(['disabled' => false])

<button
    @disabled($disabled)
    {{ $attributes->merge([
        'type' => 'submit',
        'class' => 'inline-flex items-center justify-center h-[38px] px-[18px] py-[9px] rounded-md font-sans text-button font-semibold '
            . ($disabled
                ? 'bg-primary-disabled text-muted cursor-not-allowed'
                : 'bg-primary text-on-primary active:bg-primary-active focus:outline-none focus:ring-[3px] focus:ring-primary/20'),
    ]) }}
>
    {{ $slot }}
</button>
