<x-tramite-layout title="Resumen del trámite">
    <x-ui.page-header :back-href="route('tramite.index')" back-label="Mis trámites" title="Resumen del trámite">
        <x-slot:intro><span class="break-words">{{ $resumen->domicilio }}</span></x-slot:intro>
    </x-ui.page-header>

    <x-ui.section title="Información general">
        <ol class="divide-y divide-hairline">
            @foreach ($resumen->generales as $seccion)
                @if ($seccion->clave === 'plantel')
                    <li class="py-md" data-clave="plantel" data-estado="{{ $seccion->estado }}">
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
</x-tramite-layout>
