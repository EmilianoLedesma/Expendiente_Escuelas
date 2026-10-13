{{-- Botón destructivo que se confirma manteniéndolo presionado (puntero, Enter o Espacio). Soltar, salir del botón o perder el foco cancela.
     `accion` es la expresión Alpine/Livewire que corre al completarse (p. ej. $wire.eliminarCuenta()). Sin movimiento si el usuario lo reduce. --}}
@props(['accion', 'duracion' => 1600])

<button type="button"
    x-data="{
        sosteniendo: false,
        temporizador: null,
        iniciar() {
            if (this.sosteniendo) return;
            this.sosteniendo = true;
            this.temporizador = setTimeout(() => { this.sosteniendo = false; {{ $accion }}; }, {{ (int) $duracion }});
        },
        cancelar() {
            this.sosteniendo = false;
            clearTimeout(this.temporizador);
        },
    }"
    x-on:pointerdown="if ($event.isPrimary && $event.button === 0) iniciar()"
    x-on:pointerup="cancelar()"
    x-on:pointerleave="cancelar()"
    x-on:pointercancel="cancelar()"
    x-on:keydown.enter.prevent="if (! $event.repeat) iniciar()"
    x-on:keydown.space.prevent="if (! $event.repeat) iniciar()"
    x-on:keyup.enter="cancelar()"
    x-on:keyup.space="cancelar()"
    x-on:blur="cancelar()"
    x-on:contextmenu.prevent
    :aria-busy="sosteniendo"
    {{ $attributes->merge(['class' => 'relative isolate inline-flex min-h-11 min-w-48 touch-none select-none items-center justify-center overflow-hidden rounded-md border border-error-ink bg-error-soft px-lg text-body-md font-semibold text-error-ink focus-visible:outline-2 focus-visible:outline-offset-2']) }}>
    <span class="relative inline-flex items-center gap-xs">{{ $slot }}</span>
    {{-- Relleno que avanza de izquierda a derecha mientras se mantiene. --}}
    <span aria-hidden="true" class="absolute inset-0 flex items-center justify-center gap-xs bg-error-ink px-lg text-on-primary transition-[clip-path] ease-linear motion-reduce:!transition-none"
          :style="{ clipPath: sosteniendo ? 'inset(0 0 0 0)' : 'inset(0 100% 0 0)', transitionDuration: sosteniendo ? '{{ (int) $duracion }}ms' : '180ms' }">
        {{ $slot }}
    </span>
</button>
