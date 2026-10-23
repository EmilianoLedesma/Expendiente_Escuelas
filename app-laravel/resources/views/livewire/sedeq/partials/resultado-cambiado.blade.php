{{-- Aviso G6: reglas que cumplían en la última evaluación guardada y ya no. Texto + icono (no solo color); informativo, no cambia la decisión. --}}
<x-ui.alert tipo="warning" titulo="Resultado cambiado desde que se aceptó" class="mb-md" data-resultado-cambiado>
    <p class="text-body-sm">El motor de validación da hoy un resultado distinto al de la última evaluación guardada. Revisa el paso otra vez; la decisión no cambia sola.</p>
    <ul class="mt-xs list-disc space-y-xxs pl-lg text-body-sm">
        @foreach ($descripciones as $descripcion)
            <li>{{ $descripcion }}</li>
        @endforeach
    </ul>
</x-ui.alert>
