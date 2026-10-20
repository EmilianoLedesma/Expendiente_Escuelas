{{-- Metadatos de un documento (MetadatosDocumentales): etiqueta, valor y, en la vigencia, su estado semántico. Solo lectura. --}}
@if ($metadatos !== [])
    <dl class="mt-xs grid grid-cols-1 gap-x-md gap-y-xxs text-body-sm sm:grid-cols-3">
        @foreach ($metadatos as $dato)
            <dt class="font-semibold text-ink">{{ $dato['etiqueta'] }}</dt>
            <dd class="flex flex-wrap items-center gap-sm break-words text-body sm:col-span-2">
                <span @class(['text-muted' => $dato['valor'] === \App\Application\Documentos\MetadatosDocumentales::NO_CAPTURADO])>{{ $dato['valor'] }}</span>
                @if ($dato['estado'] !== null)
                    <x-ui.status-tag :estado="$estadosVigencia[$dato['estado']] ?? 'pendiente'" :texto="$textosVigencia[$dato['estado']] ?? $dato['estado']" />
                @endif
            </dd>
        @endforeach
    </dl>
@endif
