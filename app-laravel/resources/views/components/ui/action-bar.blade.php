@props(['accion', 'backHref' => null, 'backLabel' => 'Volver al resumen', 'label' => 'Guardar y continuar'])

<div class="mt-xl flex flex-col-reverse gap-md border-t border-hairline bg-canvas py-md sm:sticky sm:bottom-0 sm:z-10 sm:flex-row sm:items-center sm:justify-between sm:bg-canvas/95 sm:backdrop-blur">
    @if ($backHref)
        <a href="{{ $backHref }}" class="inline-flex min-h-11 items-center justify-center text-body-md font-semibold text-primary underline underline-offset-4 hover:no-underline">{{ $backLabel }}</a>
    @else
        <span></span>
    @endif

    <x-ui.button-primary type="submit" wire:loading.attr="disabled" wire:target="{{ $accion }}">
        <span wire:loading.remove wire:target="{{ $accion }}">{{ $label }}</span>
        <span wire:loading wire:target="{{ $accion }}">Guardando…</span>
    </x-ui.button-primary>
</div>
