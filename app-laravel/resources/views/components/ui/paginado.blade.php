{{-- Pagina un bloque largo de tarjetas en el navegador (Alpine, sin viaje al servidor), como el stepper: todas siguen en el DOM,
     así que wire:model y los #ancla de x-ui.error-summary funcionan igual.
     Uso: <x-ui.paginado :total="$n"> y en cada tarjeta data-pag="{{ intdiv($i, 3) }}" x-show="pagina === {{ intdiv($i, 3) }}" (3 por página). --}}
@props(['total', 'porPagina' => 3])

@php $paginas = (int) ceil($total / $porPagina); @endphp

<div
    {{ $attributes }}
    x-data="{
        pagina: 0,
        ir(p) { this.pagina = Math.max(0, Math.min({{ $paginas - 1 }}, p)); },
        aCampo(id) {
            const campo = id ? document.getElementById(id) : null;
            const tarjeta = campo ? campo.closest('[data-pag]') : null;
            if (tarjeta && this.$root.contains(tarjeta)) this.pagina = Number(tarjeta.dataset.pag);
        },
    }"
    x-on:click.window="const e = $event.target.closest('[role=alert] a[href^=&quot;#&quot;]'); if (e) aCampo(e.getAttribute('href').slice(1))"
    x-on:stepper-ir-a.window="aCampo($event.detail.id)"
>
    {{ $slot }}

    @if ($paginas > 1)
        <nav aria-label="Paginación de espacios" class="mt-md flex items-center justify-between gap-md">
            <x-ui.button-secondary x-on:click="ir(pagina - 1)" x-bind:disabled="pagina === 0">Anterior</x-ui.button-secondary>
            <span class="text-body-sm text-muted" x-text="'Página ' + (pagina + 1) + ' de {{ $paginas }}'">Página 1 de {{ $paginas }}</span>
            <x-ui.button-secondary x-on:click="ir(pagina + 1)" x-bind:disabled="pagina === {{ $paginas - 1 }}">Siguiente</x-ui.button-secondary>
        </nav>
    @endif
</div>
