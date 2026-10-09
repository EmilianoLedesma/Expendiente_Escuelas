@props(['indice', 'total', 'titulo'])

<div role="group" aria-label="{{ $titulo }}" data-paso="{{ $indice }}" x-show="paso === {{ $indice }}" @if ($indice > 0) x-cloak @endif>
    {{ $slot }}

    @if ($total > 1)
        <div class="mt-lg flex items-center justify-between gap-md">
            @if ($indice > 0)
                <x-ui.button-secondary x-on:click="ir({{ $indice - 1 }})">Anterior</x-ui.button-secondary>
            @else
                <span></span>
            @endif

            @if ($indice < $total - 1)
                <x-ui.button-primary type="button" x-on:click="ir({{ $indice + 1 }})">Siguiente</x-ui.button-primary>
            @endif
        </div>
    @endif
</div>
