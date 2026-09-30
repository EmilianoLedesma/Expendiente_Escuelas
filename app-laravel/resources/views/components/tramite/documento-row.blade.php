{{--
    Fila de la checklist de Documentos. Si es editable dibuja el formulario: archivo PDF,
    luego el slot (campos estructurados) y "Guardar". Si ya está capturada: Reemplazar / Descargar.
    $capturado: ['nombreArchivo' => string, 'subidoEn' => Carbon] | null. $vencido: mensaje de vigencia | null. $descargarHref: ruta de descarga (Paso 2.4); por omisión la de Paso 2.2 por escuela.
--}}
@props(['clave', 'titulo', 'escuelaId', 'accion', 'capturado' => null, 'editable' => true, 'vencido' => null, 'etiquetaArchivo' => 'Archivo PDF', 'descargarHref' => null])

@php
    $metodo = \Illuminate\Support\Str::before($accion, '(');
@endphp

<li id="documento-{{ $clave }}" data-clave="{{ $clave }}" class="py-md" {{ $attributes->only('wire:key') }}>
    <div class="flex flex-col gap-sm sm:flex-row sm:items-start sm:justify-between">
        <div class="min-w-0">
            <h2 class="text-body-md font-semibold text-ink">{{ $titulo }}</h2>
            <p class="text-body-sm text-muted">Archivo PDF, máximo 10 MB.</p>
            @if ($capturado)
                <p class="mt-xxs break-all text-body-sm text-body">{{ $capturado['nombreArchivo'] }}</p>
                <p class="text-body-sm text-muted">Subido el {{ $capturado['subidoEn']->format('d/m/Y H:i') }}</p>
            @endif
            @if ($vencido)
                <p class="mt-xxs flex items-start gap-xxs text-body-sm font-semibold text-error-ink">
                    <x-ui.icon nombre="exclamation-circle" class="mt-px h-5 w-5" />
                    {{ $vencido }}
                </p>
            @endif
        </div>
        <x-ui.status-tag :estado="$vencido ? 'error' : ($capturado ? 'completado' : 'pendiente')" />
    </div>

    @isset($extra)
        <div class="mt-sm">{{ $extra }}</div>
    @endisset

    @if ($editable)
        <form wire:submit="{{ $accion }}" class="mt-md space-y-md">
            <x-ui.field id="archivos.{{ $clave }}" :label="$etiquetaArchivo">
                <input
                    type="file"
                    id="archivos.{{ $clave }}"
                    wire:model="archivos.{{ $clave }}"
                    accept="application/pdf"
                    @error('archivos.'.$clave) aria-invalid="true" aria-describedby="archivos.{{ $clave }}-error" @enderror
                    class="block w-full text-body-md text-body file:mr-sm file:min-h-11 file:cursor-pointer file:rounded-md file:border file:border-solid file:border-primary file:bg-canvas file:px-md file:font-semibold file:text-primary"
                >
                <p wire:loading wire:target="archivos.{{ $clave }}" class="mt-xxs text-body-sm text-muted">Subiendo…</p>
            </x-ui.field>

            {{ $slot }}

            <x-ui.button-primary type="submit" wire:loading.attr="disabled" wire:target="archivos.{{ $clave }}, {{ $metodo }}">Guardar</x-ui.button-primary>
        </form>
    @else
        <div class="mt-sm flex flex-wrap gap-sm">
            <x-ui.button-secondary type="button" wire:click="toggleReemplazar('{{ $clave }}')">
                <x-ui.icon nombre="arrow-up-tray" class="h-5 w-5" />
                Reemplazar<span class="sr-only"> {{ $titulo }}</span>
            </x-ui.button-secondary>
            <x-ui.button-secondary :href="$descargarHref ?? route('tramite.paso2-documentos.descargar', ['escuela' => $escuelaId, 'clave' => $clave])">
                <x-ui.icon nombre="arrow-down-tray" class="h-5 w-5" />
                Descargar<span class="sr-only"> {{ $titulo }}</span>
            </x-ui.button-secondary>
        </div>
    @endif
</li>
