{{-- Aviso con cuenta regresiva para deshacer una acción diferida. `expira` corre al terminar el tiempo (la acción real);
     `deshacer` corre si la persona pulsa "Deshacer". El relleno avanza de izquierda a derecha; sin movimiento si se reduce. --}}
@props(['mensaje', 'expira', 'deshacer', 'duracion' => 5000])

<div role="status" aria-live="polite"
     x-data="{
         contando: false,
         temporizador: null,
         init() {
             {{-- Dos cuadros: con uno solo el navegador a veces aún no pintó el estado inicial y la transición no arranca. --}}
             requestAnimationFrame(() => requestAnimationFrame(() => this.contando = true));
             this.temporizador = setTimeout(() => { {{ $expira }}; }, {{ (int) $duracion }});
         },
         deshacerAccion() {
             clearTimeout(this.temporizador);
             {{ $deshacer }};
         },
     }"
     {{ $attributes->merge(['class' => 'flex min-h-11 max-w-xl items-center justify-between gap-md rounded-lg border border-hairline bg-surface-soft p-xs pl-md']) }}>
    <p class="min-w-0 text-body-md font-medium text-ink">{{ $mensaje }}</p>

    <button type="button" x-on:click="deshacerAccion()"
            class="relative isolate inline-flex min-h-9 min-w-28 shrink-0 cursor-pointer items-center justify-center overflow-hidden rounded-md border border-hairline bg-canvas px-sm text-body-sm font-semibold text-ink focus-visible:outline-2 focus-visible:outline-offset-2">
        <span class="inline-flex items-center gap-xs">
            <x-ui.icon nombre="arrow-path" class="h-4 w-4" />
            Deshacer
        </span>
        <span aria-hidden="true" class="absolute inset-0 flex items-center justify-center gap-xs bg-ink px-sm text-canvas transition-[clip-path] ease-linear motion-reduce:!transition-none"
              :style="{ clipPath: contando ? 'inset(0 100% 0 0)' : 'inset(0 0 0 0)', transitionDuration: '{{ (int) $duracion }}ms' }">
            <x-ui.icon nombre="arrow-path" class="h-4 w-4" />
            Deshacer
        </span>
    </button>
</div>
