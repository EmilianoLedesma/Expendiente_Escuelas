<x-tramite-layout title="Resumen del trámite" :resumen="$resumen" seccion-actual="resumen">
    <div class="max-w-3xl">
        <x-ui.page-header :eyebrow="'Resumen del trámite · Nº '.$resumen->numero()" :title="$resumen->nombre ?? 'Sin nombre propuesto'">
            <x-slot:intro><span class="break-words">{{ $resumen->domicilio }}</span></x-slot:intro>
        </x-ui.page-header>

        @php($siguiente = $resumen->siguiente())
        @if ($resumen->conObservaciones)
            <x-ui.alert tipo="warning" :titulo="$resumen->puedeReenviar ? 'Tu trámite tiene observaciones: corrige solo los pasos marcados y vuelve a enviar.' : 'Tu trámite tiene observaciones. El titular del trámite corregirá y reenviará; abajo ves las que corresponden a tu nivel.'" class="mb-lg">
                @if ($resumen->observaciones !== [])
                    <ul class="mt-xs list-disc space-y-xxs pl-lg">
                        @foreach ($resumen->observaciones as $observacion)
                            <li><span class="font-semibold">{{ $observacion['titulo'] }}@if ($observacion['nivel']) ({{ $observacion['nivel'] }})@endif:</span> {{ $observacion['motivo'] }}</li>
                        @endforeach
                    </ul>
                @endif
                @if ($resumen->puedeReenviar)
                    {{-- Mismo patrón de confirmación que «Enviar a SEDEQ» (validación final): <dialog> modal y solo su botón envía. --}}
                    <x-ui.button-primary type="button" onclick="document.getElementById('reenviar-correcciones').showModal()" class="mt-md">
                        Reenviar correcciones
                    </x-ui.button-primary>
                    <dialog id="reenviar-correcciones" aria-labelledby="reenviar-correcciones-titulo" onclick="if (event.target === this) this.close()" class="modal rounded-lg border border-hairline bg-canvas p-lg text-left text-body-md text-ink">
                        <h2 id="reenviar-correcciones-titulo" class="text-title-md font-semibold text-ink">¿Reenviar las correcciones a SEDEQ?</h2>
                        <p class="mt-sm">Revisamos que los pasos con observaciones estén completos. Una vez reenviado, el trámite ya no podrá modificarse mientras SEDEQ lo revisa.</p>
                        <form method="POST" action="{{ route('tramite.reenviar', ['escuela' => $resumen->escuelaId]) }}" class="mt-lg flex flex-wrap justify-end gap-sm">
                            @csrf
                            <button type="button" onclick="this.closest('dialog').close()" class="inline-flex min-h-11 items-center justify-center rounded-md border border-control bg-canvas px-lg text-body-md font-semibold text-ink transition duration-150 hover:border-ink hover:bg-surface-card active:bg-surface-soft">
                                Cancelar
                            </button>
                            <x-ui.button-primary type="submit">
                                Sí, reenviar correcciones
                            </x-ui.button-primary>
                        </form>
                    </dialog>
                @endif
            </x-ui.alert>
        @elseif ($resumen->enviado)
            <x-ui.alert tipo="info" :titulo="'Enviado el '.$resumen->fechaEnvio?->format('d/m/Y').' — en revisión por SEDEQ'" class="mb-lg">
                El trámite ya no puede modificarse.
                @if ($puedeDescargarReporte && $resumen->reporteEnviadoId !== null)
                    <a href="{{ route('tramite.validacion.reporte', ['escuela' => $resumen->escuelaId, 'evaluacion' => $resumen->reporteEnviadoId]) }}" target="_blank" class="font-semibold">Descargar el reporte de validación enviado</a>
                @endif
            </x-ui.alert>
        @elseif ($siguiente)
            <div class="mb-lg rounded-lg bg-surface-card p-lg">
                <p class="text-section-eyebrow font-semibold uppercase text-muted">Siguiente paso</p>
                <p class="mt-xs text-title-md font-semibold text-ink">{{ $siguiente->nombre }}</p>
                <p class="text-body-sm text-muted">{{ $siguiente->descripcion }}</p>
                <x-ui.button-primary :href="$siguiente->href" class="mt-md">
                    {{ ['comenzar' => 'Comenzar', 'continuar' => 'Continuar'][$siguiente->accion] }}<span class="sr-only">: {{ $siguiente->nombre }}</span>
                </x-ui.button-primary>
            </div>
        @elseif ($resumen->completo)
            <div class="mb-lg rounded-lg bg-surface-card p-lg">
                <p class="text-section-eyebrow font-semibold uppercase text-muted">Siguiente paso</p>
                <p class="mt-xs text-title-md font-semibold text-ink">Validación final</p>
                <p class="text-body-sm text-muted">Todas las secciones están capturadas. Revisamos que tus datos coincidan con tus documentos antes de enviar.</p>
                <x-ui.button-primary :href="route('tramite.validacion', ['escuela' => $resumen->escuelaId])" class="mt-md">
                    Ir a la validación final
                </x-ui.button-primary>
            </div>
        @endif

        @if ($resumen->generales !== [])
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
        @endif

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

        @if ($resumen->puedeEliminar)
            <x-tramite.eliminar :tramite="$resumen" class="mt-xl" />
        @endif
    </div>
</x-tramite-layout>
