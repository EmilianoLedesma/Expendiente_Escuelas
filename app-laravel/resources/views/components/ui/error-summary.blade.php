{{-- Enlaza a #{clave}: cada campo debe tener id = clave de error. Alpine (incluido con Livewire) lo enfoca al aparecer. --}}
@props(['excluir' => []])

@php
    $errores = collect($errors->messages())->except($excluir);
@endphp

@if ($errores->isNotEmpty())
    <div role="alert" tabindex="-1" x-data x-init="$el.focus(); $dispatch('stepper-ir-a', { id: ($el.querySelector('a') || {getAttribute: () => ''}).getAttribute('href').slice(1), enfocar: false })" aria-labelledby="resumen-errores-titulo" class="mb-lg rounded-sm border-2 border-error-ink bg-error-soft p-md">
        <h2 id="resumen-errores-titulo" class="flex items-center gap-xs text-title-md font-semibold text-error-ink">
            <x-ui.icon nombre="exclamation-triangle" class="h-6 w-6" />
            Revisa los siguientes datos
        </h2>
        <ul class="mt-xs list-disc space-y-xxs pl-lg text-error-ink">
            @foreach ($errores as $clave => $mensajes)
                <li>
                    <a href="#{{ $clave }}" class="font-semibold underline underline-offset-4 hover:no-underline">{{ $mensajes[0] }}</a>
                </li>
            @endforeach
        </ul>
    </div>
@endif
