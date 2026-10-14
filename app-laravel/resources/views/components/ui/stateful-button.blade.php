{{-- Botón con estado para acciones asíncronas de Livewire: reposo → cargando → éxito / error, con el mismo lenguaje de color que x-ui.button-primary.
     `action` es el NOMBRE del método de Livewire. Solo `true` cuenta como éxito; cualquier otro valor (false, null, validación fallida) o una excepción es error y se puede reintentar.
     Éxito significa que la acción terminó bien (p. ej. el correo quedó en cola), no que el destinatario lo recibió.
     `reset-after` (ms) regresa a reposo tras un éxito; sin él el estado se queda hasta el siguiente clic.
     wire:ignore: el estado vive solo en Alpine y un re-render de Livewire no debe pisar clases ni estado. Con movimiento reducido no gira ni se anima; quedan el arco fijo y el texto. --}}
@props([
    'action',
    'label',
    'loadingLabel' => 'Enviando...',
    'successLabel' => 'Listo',
    'errorLabel' => 'No se pudo completar. Reintentar',
    'disabled' => false,
    'resetAfter' => null,
    'type' => 'button',
])

@php
    $base = 'inline-flex min-h-11 items-center justify-center rounded-md bg-primary px-lg font-sans text-body-md font-semibold text-on-primary transition-colors duration-150 hover:bg-primary-active focus-visible:outline-2 focus-visible:outline-offset-2 motion-reduce:transition-none';
    $icono = 'inline-flex h-5 shrink-0 items-center justify-center overflow-hidden transition-all duration-200 ease-out motion-reduce:transition-none';
@endphp

<span wire:ignore class="inline-flex"
    x-data="{
        estado: 'idle',
        resetAfter: {{ $resetAfter === null ? 'null' : (int) $resetAfter }},
        temporizador: null,
        etiquetas: { loading: {{ \Illuminate\Support\Js::from($loadingLabel) }}, success: {{ \Illuminate\Support\Js::from($successLabel) }}, error: {{ \Illuminate\Support\Js::from($errorLabel) }} },
        async enviar() {
            if (this.estado === 'loading') return;
            clearTimeout(this.temporizador);
            this.estado = 'loading';
            try {
                this.estado = (await this.$wire.$call({{ \Illuminate\Support\Js::from($action) }})) === true ? 'success' : 'error';
            } catch (e) {
                this.estado = 'error';
            }
            if (this.estado === 'success' && this.resetAfter) {
                this.temporizador = setTimeout(() => { this.estado = 'idle'; }, this.resetAfter);
            }
        },
    }">
    <button type="{{ $type }}" @disabled($disabled)
        x-on:click.prevent="enviar()"
        :aria-busy="estado === 'loading'"
        :aria-disabled="estado === 'loading'"
        :class="{ 'cursor-wait': estado === 'loading', '!bg-success-ink': estado === 'success', '!bg-error-ink': estado === 'error' }"
        {{ $attributes->merge(['class' => $base.($disabled ? ' cursor-not-allowed opacity-60' : '')]) }}>
        {{-- Cargando: entra desde ancho 0 y escala 0, y gira sin parar mientras dure la petición. --}}
        <span aria-hidden="true" class="{{ $icono }}" :class="estado === 'loading' ? 'mr-xs w-5 scale-100' : 'w-0 scale-0'">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5 animate-spin motion-reduce:animate-none">
                <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                <path d="M12 3a9 9 0 1 0 9 9"/>
            </svg>
        </span>
        {{-- Éxito: solo con la confirmación real de la acción. --}}
        <span aria-hidden="true" class="{{ $icono }}" :class="estado === 'success' ? 'mr-xs w-5 scale-100' : 'w-0 scale-0'">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5">
                <circle cx="12" cy="12" r="9"/>
                <path d="m9 12 2 2 4-4"/>
            </svg>
        </span>
        {{-- Error: el texto también lo dice, el color no es la única señal. --}}
        <span aria-hidden="true" class="{{ $icono }}" :class="estado === 'error' ? 'mr-xs w-5 scale-100' : 'w-0 scale-0'">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5">
                <circle cx="12" cy="12" r="9"/>
                <path d="m9.5 9.5 5 5m0-5-5 5"/>
            </svg>
        </span>
        {{-- Las cuatro etiquetas comparten celda: el botón mide la más larga y no se mueve al cambiar de estado. --}}
        <span class="inline-grid">
            <span class="col-start-1 row-start-1" :class="{ invisible: estado !== 'idle' }">{{ $label }}</span>
            <span class="invisible col-start-1 row-start-1" :class="{ '!visible': estado === 'loading' }">{{ $loadingLabel }}</span>
            <span class="invisible col-start-1 row-start-1" :class="{ '!visible': estado === 'success' }">{{ $successLabel }}</span>
            <span class="invisible col-start-1 row-start-1" :class="{ '!visible': estado === 'error' }">{{ $errorLabel }}</span>
        </span>
    </button>
    <span class="sr-only" role="status" aria-live="polite" x-text="estado === 'idle' ? '' : etiquetas[estado]"></span>
</span>
