{{-- Una visita para el solicitante. $v: arreglo de ConsultarVisita; $nivelId; $descargas: solo el dueño recibe enlaces. Nombres de archivo y notas son texto del cliente: siempre escapados. --}}
@php
    $resultado = match ($v['resultado']) {
        'cumple' => 'Sin observaciones',
        'con_observaciones' => 'Con observaciones',
        default => null,
    };
@endphp

<x-ui.summary-list :filas="[
    'Fecha y hora programadas' => $v['programadaPara']->format('d/m/Y H:i'),
    'Domicilio del plantel' => $domicilio,
    'Inspector' => $v['inspector'],
    'Resultado' => $resultado,
    'Realizada el' => $v['realizadaEl']?->format('d/m/Y'),
]" />

@if ($v['tieneActa'])
    <p class="text-body-md">
        Acta:
        @if ($descargas)
            <a href="{{ route('visita.acta', ['escuelaNivel' => $nivelId, 'visita' => $v['id']]) }}" class="inline-flex min-h-11 items-center break-all font-semibold text-primary underline">{{ $v['actaNombre'] }}</a>
        @else
            <span class="break-all">{{ $v['actaNombre'] }}</span>
        @endif
    </p>
@endif

@if ($v['observaciones'] !== [])
    <div>
        <h3 class="text-body-md font-semibold text-ink">Observaciones</h3>
        <ol class="mt-xs list-decimal space-y-xs pl-lg text-body-md text-body">
            @foreach ($v['observaciones'] as $texto)
                <li class="break-words">{{ $texto }}</li>
            @endforeach
        </ol>
    </div>
@endif

@if ($v['respuestas'] !== [])
    <div>
        <h3 class="text-body-md font-semibold text-ink">Respuestas enviadas</h3>
        <ul class="mt-xs space-y-sm">
            @foreach ($v['respuestas'] as $r)
                <li class="rounded-md border border-hairline p-sm">
                    <p class="text-body-sm text-muted">{{ $r['creadaEn']->format('d/m/Y H:i') }}</p>
                    <p class="mt-xxs whitespace-pre-line break-words text-body-md text-body">{{ $r['nota'] }}</p>
                    @if ($r['archivos'] !== [])
                        <ul class="mt-xs">
                            @foreach ($r['archivos'] as $a)
                                <li>
                                    @if ($descargas)
                                        <a href="{{ route('visita.respuesta-archivo', ['escuelaNivel' => $nivelId, 'visita' => $v['id'], 'archivo' => $a['id']]) }}" class="inline-flex min-h-11 items-center break-all font-semibold text-primary underline">{{ $a['nombre'] }}</a>
                                    @else
                                        <span class="break-all text-body-sm">{{ $a['nombre'] }}</span>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </li>
            @endforeach
        </ul>
    </div>
@endif
