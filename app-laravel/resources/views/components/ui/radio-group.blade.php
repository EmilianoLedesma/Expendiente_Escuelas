{{-- $opciones: valor => 'Etiqueta' | valor => ['etiqueta' => ..., 'descripcion' => ...]. wire:model* se aplica a cada radio. --}}
@props(['id', 'legend', 'opciones'])

<fieldset id="{{ $id }}" @error($id) aria-describedby="{{ $id }}-error" @enderror {{ $attributes->whereDoesntStartWith('wire:model') }}>
    <legend class="text-body-md font-semibold text-ink">{{ $legend }}</legend>

    @error($id)
        <p id="{{ $id }}-error" class="mt-xxs flex items-start gap-xxs text-body-sm font-semibold text-error-ink">
            <x-ui.icon nombre="exclamation-circle" class="mt-px h-5 w-5" />
            <span><span class="sr-only">Error:</span> {{ $message }}</span>
        </p>
    @enderror

    <div class="mt-xs space-y-xxs">
        @foreach ($opciones as $valor => $opcion)
            <label for="{{ $id }}-{{ $valor }}" class="flex min-h-11 cursor-pointer items-start gap-sm py-xs">
                <input
                    type="radio"
                    id="{{ $id }}-{{ $valor }}"
                    value="{{ $valor }}"
                    {{ $attributes->whereStartsWith('wire:model') }}
                    class="mt-0.5 h-5 w-5 shrink-0 border-control text-primary"
                >
                <span>
                    <span class="block text-body-md text-ink">{{ is_array($opcion) ? $opcion['etiqueta'] : $opcion }}</span>
                    @if (is_array($opcion) && isset($opcion['descripcion']))
                        <span class="block text-body-sm text-muted">{{ $opcion['descripcion'] }}</span>
                    @endif
                </span>
            </label>
        @endforeach
    </div>
</fieldset>
