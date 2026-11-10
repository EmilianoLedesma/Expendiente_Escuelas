{{-- Campo de texto con contador de palabras en vivo y su rango. El contador es solo una ayuda: el servidor cuenta de nuevo
     al guardar (ConteoPalabras::contar), con la misma definición: trozos separados por espacio en blanco. Es la única copia
     del conteo en el navegador. El id es el nombre de la propiedad Livewire y la clave de error. --}}
@props(['id', 'label', 'min', 'max', 'rows' => 8])

<div x-data="{ texto: $wire.entangle('{{ $id }}') }">
    <x-ui.field :id="$id" :label="$label">
        <x-ui.textarea :rows="$rows" x-model="texto" {{ $attributes }} />
    </x-ui.field>
    <p class="mt-xs text-body-sm text-muted" aria-live="polite">
        <span x-text="(texto || '').trim().split(/\s+/).filter(Boolean).length">0</span> palabras (de {{ $min }} a {{ $max }})
    </p>
</div>
