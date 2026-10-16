{{-- Solo lectura (HUD SEDEQ): sin formularios ni enlaces de edición; los únicos enlaces son descargas protegidas por `view`, con vista previa (x-ui.pdf-preview). --}}
<div>
    <x-ui.page-header :title="$tramite['nombre'] ?? 'Plantel sin nombre'" eyebrow="Trámite · solo lectura" :back-href="$volver" back-label="Volver" />

    <x-ui.section title="Datos del plantel">
        <x-ui.summary-list :filas="$tramite['plantel']" />
    </x-ui.section>

    <x-ui.section title="Responsable legal">
        @if ($tramite['responsable'] === null)
            <p class="text-body-md text-muted">Sin datos del responsable legal.</p>
        @else
            <x-ui.summary-list :filas="$tramite['responsable']" />
        @endif
    </x-ui.section>

    <x-ui.section title="Documentos">
        @forelse ($tramite['documentos'] as $doc)
            <x-ui.pdf-preview :url="$doc['href']" :title="$doc['nombre']" class="flex min-h-11 items-center justify-between gap-md border-b border-hairline py-xs text-body-md text-primary hover:underline">
                <span>{{ $doc['nombre'] }}</span>
                <span class="text-caption text-muted">{{ $doc['archivo'] }}</span>
            </x-ui.pdf-preview>
        @empty
            <p class="text-body-md text-muted">Sin documentos del plantel.</p>
        @endforelse
    </x-ui.section>

    @foreach ($tramite['niveles'] as $nivel)
        <x-ui.section :title="$nivel['nombre']">
            <x-ui.summary-list :filas="['Estado' => $nivel['estado'], 'Folio de expediente' => $nivel['folio']]" />
            <h3 class="text-body-md font-semibold text-ink">Documentos del nivel</h3>
            @forelse ($nivel['documentos'] as $doc)
                <x-ui.pdf-preview :url="$doc['href']" :title="$doc['nombre']" class="flex min-h-11 items-center justify-between gap-md border-b border-hairline py-xs text-body-md text-primary hover:underline">
                    <span>{{ $doc['nombre'] }}</span>
                    <span class="text-caption text-muted">{{ $doc['archivo'] }}</span>
                </x-ui.pdf-preview>
            @empty
                <p class="text-body-md text-muted">Sin documentos del nivel.</p>
            @endforelse
        </x-ui.section>
    @endforeach
</div>
