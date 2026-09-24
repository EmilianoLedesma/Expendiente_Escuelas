{{-- Etiqueta + ayuda + error de un campo. El id es también la clave de error y el ancla del resumen de errores. --}}
@props(['id', 'label', 'hint' => null, 'optional' => false])

<div {{ $attributes }}>
    <label for="{{ $id }}" class="block text-body-md font-semibold text-ink">
        {{ $label }}
        @if ($optional)
            <span class="font-normal text-muted">(opcional)</span>
        @endif
    </label>

    @if ($hint)
        <p id="{{ $id }}-hint" class="mt-xxs text-body-sm text-muted">{{ $hint }}</p>
    @endif

    @error($id)
        <p id="{{ $id }}-error" class="mt-xxs flex items-start gap-xxs text-body-sm font-semibold text-error-ink">
            <x-ui.icon nombre="exclamation-circle" class="mt-px h-5 w-5" />
            <span><span class="sr-only">Error:</span> {{ $message }}</span>
        </p>
    @enderror

    <div class="mt-xs">{{ $slot }}</div>
</div>
