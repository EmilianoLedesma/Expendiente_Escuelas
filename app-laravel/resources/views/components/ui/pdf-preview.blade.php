{{-- Vista previa de un PDF en un <dialog> modal (mismo patrón y animación que eliminar-trámite). Sin JS es un enlace de descarga normal.
     Con JS, `vistaPdf` (resources/js/app.js) carga pdf.js la primera vez que se abre una vista previa, pinta la página 1 como portada y
     hasta 4 más detrás, que se abren en abanico al pasar el cursor o enfocar. Solo lee el href que pintó el servidor (x-ref="enlace").
     El <dialog> nativo da Escape, devolver el foco al disparador y bloquear el scroll del fondo. --}}
@props(['url', 'title'])

@php($id = 'vista-pdf-'.substr(md5($url), 0, 10))

<div x-data="vistaPdf">
    <a href="{{ $url }}" x-ref="enlace" x-on:click.prevent="abrir()" {{ $attributes }}>{{ $slot->isEmpty() ? $title : $slot }}</a>

    <dialog x-ref="dialogo" aria-labelledby="{{ $id }}" x-on:click="$event.target === $el && $el.close()" x-on:close="abanico = false"
            class="modal vista-pdf rounded-lg border border-hairline bg-canvas p-lg text-left text-body-md text-ink">
        <div class="flex items-start justify-between gap-md">
            <div>
                <h2 id="{{ $id }}" class="text-title-md font-semibold text-ink">{{ $title }}</h2>
                <p class="text-caption text-muted" x-text="listo ? paginas : (error ? '' : 'Cargando…')">Cargando…</p>
            </div>
            <button type="button" x-on:click="$refs.dialogo.close()" aria-label="Cerrar vista previa"
                    class="-mr-xs -mt-xs flex size-11 shrink-0 cursor-pointer items-center justify-center rounded-md text-muted transition-colors hover:bg-surface-soft hover:text-ink">
                <x-ui.icon nombre="x-mark" class="h-5 w-5" />
            </button>
        </div>

        <div class="flex justify-center pb-xl pt-lg">
            <button type="button" x-ref="pila" class="pila-pdf" aria-label="Abrir {{ $title }} en pantalla completa"
                    x-bind:class="{ 'es-abanico': abanico && listo, 'esta-lista': listo }" x-bind:style="{ '--hojas': hojas }" x-bind:aria-busy="cargando"
                    x-on:mouseenter="abanico = true" x-on:mouseleave="abanico = false" x-on:focus="abanico = true" x-on:blur="abanico = false"
                    x-on:click="abrirCompleto()">
                @for ($i = 1; $i <= 4; $i++)
                    <span class="pila-pdf-hoja pila-pdf-detras" style="--i: {{ $i }}" x-bind:class="{ 'es-visible': listo && hojas >= {{ $i }} }" aria-hidden="true">
                        <canvas x-ref="hoja{{ $i }}"></canvas>
                    </span>
                @endfor
                <span class="pila-pdf-hoja pila-pdf-portada" aria-hidden="true">
                    <canvas x-ref="portada"></canvas>
                    <span class="pila-pdf-esqueleto" x-show="!listo && !error" x-transition.opacity></span>
                </span>
                <span class="pila-pdf-insignia" x-text="paginas" aria-hidden="true"></span>
            </button>
        </div>

        <p role="alert" x-show="error" x-cloak class="mb-md text-body-sm text-error-ink">No se pudo cargar el documento.</p>
        <div class="flex justify-end">
            <x-ui.button-primary :href="$url" target="_blank" rel="noopener noreferrer">
                Abrir en pantalla completa
            </x-ui.button-primary>
        </div>
    </dialog>
</div>
