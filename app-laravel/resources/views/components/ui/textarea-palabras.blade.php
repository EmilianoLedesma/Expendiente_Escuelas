{{-- Campo de texto con rango de palabras (todos los que lo usan son obligatorios). Ayuda para quien captura, el servidor
     manda: cuenta de nuevo con ConteoPalabras::contar, con la misma definición (trozos separados por espacio en blanco).
     Es la única copia del conteo en el navegador. Tope duro: si se teclea o se pega más del máximo, se recorta a ese
     número de palabras; el valor que va a Livewire (`texto`, entangle) es el mismo que se ve. El contador se pone en
     rojo por debajo del mínimo (ya con texto) o por encima del máximo. El id es el nombre de la propiedad Livewire. --}}
@props(['id', 'label', 'min', 'max', 'rows' => 8])

<div x-data="{
    texto: $wire.entangle('{{ $id }}'),
    min: {{ $min }},
    max: {{ $max }},
    n() { return (this.texto || '').trim().split(/\s+/).filter(Boolean).length; },
    fuera() { return this.n() > this.max || (this.n() > 0 && this.n() < this.min); },
    recortar() {
        if (this.n() <= this.max) return;
        let cuenta = 0, m;
        const palabra = /\S+/g;
        while ((m = palabra.exec(this.texto))) {
            if (++cuenta === this.max) { this.texto = this.texto.slice(0, m.index + m[0].length); return; }
        }
    },
}">
    <x-ui.field :id="$id" :label="$label">
        <x-ui.textarea :rows="$rows" required x-model="texto" x-on:input="recortar()" {{ $attributes }} />
    </x-ui.field>
    <p class="mt-xs text-body-sm" :class="fuera() ? 'font-semibold text-error-ink' : 'text-muted'" aria-live="polite">
        <span x-text="n()">0</span> palabras (de {{ $min }} a {{ $max }})<span x-show="n() > 0 && n() < min" x-text="' · faltan ' + (min - n())"></span><span x-show="n() >= max"> · máximo alcanzado</span>
    </p>
</div>
