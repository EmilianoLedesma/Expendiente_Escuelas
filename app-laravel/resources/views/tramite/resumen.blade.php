<x-tramite-layout title="Resumen del trámite" :resumen="$resumen" seccion-actual="resumen">
    <div class="max-w-3xl">
        <x-ui.page-header :eyebrow="'Trámite Nº '.$resumen->numero()" title="Resumen del trámite">
            <x-slot:intro><span class="break-words">{{ $resumen->domicilio }}</span></x-slot:intro>
        </x-ui.page-header>

        @php($siguiente = $resumen->siguiente())
        @if ($siguiente)
            <div class="mb-lg rounded-lg bg-surface-card p-lg">
                <p class="text-section-eyebrow font-semibold uppercase text-muted">Siguiente paso</p>
                <p class="mt-xs text-title-md font-semibold text-ink">{{ $siguiente->nombre }}</p>
                <p class="text-body-sm text-muted">{{ $siguiente->descripcion }}</p>
                <x-ui.button-primary :href="$siguiente->href" class="mt-md">
                    {{ ['comenzar' => 'Comenzar', 'continuar' => 'Continuar'][$siguiente->accion] }}<span class="sr-only">: {{ $siguiente->nombre }}</span>
                </x-ui.button-primary>
            </div>
        @elseif ($resumen->completo)
            <x-ui.alert tipo="success" titulo="Captura inicial completa" class="mb-lg">Todas las secciones disponibles están capturadas.</x-ui.alert>
        @endif

        <x-ui.section title="Información general">
            <ol class="divide-y divide-hairline">
                @foreach ($resumen->generales as $seccion)
                    @if ($seccion->clave === 'plantel')
                        <li class="py-md first:pt-0" data-clave="plantel" data-estado="{{ $seccion->estado }}">
                            <div class="flex flex-wrap items-center justify-between gap-sm">
                                <h3 class="text-body-md font-semibold text-ink">{{ $seccion->paso }}. {{ $seccion->nombre }}</h3>
                                <x-ui.status-tag :estado="$seccion->estado" />
                            </div>
                            <x-ui.summary-list :filas="$resumen->plantel" class="mt-sm" />
                        </li>
                    @else
                        <x-tramite.task-row :seccion="$seccion" numerado />
                    @endif
                @endforeach
            </ol>
        </x-ui.section>

        @foreach ($resumen->niveles as $nivel)
            <x-ui.section :title="$nivel->nombre">
                <x-tramite.nivel :clave="$nivel->clave" como="franja">
                    <ol class="divide-y divide-hairline">
                        @foreach ($nivel->secciones as $seccion)
                            <x-tramite.task-row :seccion="$seccion" />
                        @endforeach
                    </ol>
                </x-tramite.nivel>
            </x-ui.section>
        @endforeach
    </div>
</x-tramite-layout>
