{{-- Línea de tiempo de un trámite (ConsultarHistorialTramite), dentro del panel lateral de la acción Historial. --}}
<div>
    @forelse ($eventos as $e)
        <div class="border-b border-gray-200 py-3 dark:border-white/10">
            <div class="text-sm font-medium">{{ $e['que'] }}@if ($e['nivel']) <span class="font-normal text-gray-500">· {{ $e['nivel'] }}</span>@endif</div>
            <div class="text-xs text-gray-500">{{ $e['cuando']->format('d/m/Y H:i') }}@if ($e['quien']) · {{ $e['quien'] }}@endif</div>
            @if ($e['detalle'])
                <div class="mt-1 text-sm">{{ $e['detalle'] }}</div>
            @endif
        </div>
    @empty
        <p class="text-sm text-gray-500">Este trámite aún no tiene eventos registrados.</p>
    @endforelse
</div>
