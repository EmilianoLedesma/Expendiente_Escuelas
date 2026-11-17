@php($espera = fn (int $d) => $d.' '.($d === 1 ? 'día' : 'días'))
<div>
    <x-ui.page-header title="Proyectos educativos" eyebrow="Revisión SEDEQ">
        <x-slot:intro>Los proyectos educativos de media superior que esperan tu revisión, primero los que llevan más tiempo esperando.</x-slot:intro>
    </x-ui.page-header>

    @if ($filas === [])
        <x-ui.alert>Todavía no hay proyectos educativos por revisar. Cuando un plantel de media superior envíe su trámite, aparecerá aquí.</x-ui.alert>
    @else
        <ol class="space-y-xs">
            @foreach ($filas as $i => $fila)
                <li wire:key="proyecto-{{ $fila['escuelaNivelId'] }}">
                    <a href="{{ route('sedeq.proyecto-revision', ['escuelaNivel' => $fila['escuelaNivelId']]) }}" class="flex min-h-11 flex-wrap items-center justify-between gap-sm rounded-lg border border-hairline bg-canvas p-sm hover:bg-surface-soft">
                        <span>
                            <span class="block font-semibold text-ink"><span class="tabular-nums">{{ $i + 1 }} · N.º {{ $fila['escuelaNivelId'] }}</span> {{ $fila['plantel'] }}</span>
                            <span class="block text-body-sm text-muted">{{ $fila['nivel'] }} · espera {{ $espera($fila['diasEspera']) }}</span>
                        </span>
                        <span class="text-body-sm font-semibold tabular-nums text-muted">{{ $fila['revisadas'] }} de {{ $fila['total'] }} secciones revisadas</span>
                    </a>
                </li>
            @endforeach
        </ol>
    @endif
</div>
