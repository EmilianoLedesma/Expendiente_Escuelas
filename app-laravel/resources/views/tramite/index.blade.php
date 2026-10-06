<x-tramite-layout title="Mis trámites">
    <div class="flex flex-col gap-md sm:flex-row sm:items-start sm:justify-between">
        <x-ui.page-header title="Mis trámites">
            <x-slot:intro>Solicitudes de incorporación que has iniciado. Cada una corresponde a una escuela.</x-slot:intro>
        </x-ui.page-header>
        <x-ui.button-primary :href="route('tramite.preregistro')" class="shrink-0 sm:mt-lg">
            <x-ui.icon nombre="plus" class="h-5 w-5" />
            Iniciar nuevo trámite
        </x-ui.button-primary>
    </div>

    @if (session('status'))
        <x-ui.alert tipo="success" class="mb-lg">{{ session('status') }}</x-ui.alert>
    @endif
    @if (session('error'))
        <x-ui.alert tipo="error" class="mb-lg">{{ session('error') }}</x-ui.alert>
    @endif

    @if ($tramites === [])
        <div class="max-w-3xl space-y-md rounded-lg bg-surface-card p-lg text-body-md text-body">
            <p>Este sistema te guía para capturar la solicitud de incorporación de una escuela particular: los datos del plantel, el responsable legal, los documentos y la información de cada nivel educativo.</p>
            <p>Ten a la mano, en PDF, la identificación oficial del responsable legal y los documentos del inmueble: escritura o contrato, dictamen de uso de suelo y constancia de seguridad estructural.</p>
            <p>Tu avance se guarda al terminar cada sección: puedes salir y continuar después desde esta página.</p>
        </div>
    @else
        <div class="overflow-hidden rounded-lg border border-hairline">
            <table class="w-full text-left text-body-sm">
                <caption class="sr-only">Trámites iniciados</caption>
                <thead class="hidden bg-surface-soft text-caption uppercase text-muted md:table-header-group">
                    <tr>
                        <th scope="col" class="px-lg py-sm font-semibold">Escuela</th>
                        <th scope="col" class="px-lg py-sm font-semibold">Niveles</th>
                        <th scope="col" class="w-56 px-lg py-sm font-semibold">Avance</th>
                        <th scope="col" class="px-lg py-sm font-semibold">Estado</th>
                        <th scope="col" class="w-px whitespace-nowrap px-lg py-sm"><span class="sr-only">Acción</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-hairline">
                    @foreach ($tramites as $tramite)
                        @php
                            $avance = $tramite->avance();
                            $siguiente = $tramite->siguiente();
                            $nombre = $tramite->nombre ?? 'Sin nombre propuesto';
                        @endphp
                        <tr class="block p-md md:table-row md:p-0">
                            <td class="block md:table-cell md:px-lg md:py-md">
                                <p @class(['break-words font-semibold', 'text-ink' => $tramite->nombre !== null, 'italic text-muted' => $tramite->nombre === null])>{{ $nombre }}</p>
                                <p class="mt-xxs break-words text-caption text-muted">{{ $tramite->domicilio }}</p>
                                <p class="text-caption tabular-nums text-muted">Nº {{ $tramite->numero() }} · Iniciado el {{ $tramite->iniciadoEl->format('d/m/Y') }}</p>
                            </td>
                            <td class="mt-sm block md:mt-0 md:table-cell md:px-lg md:py-md">
                                <ul class="flex flex-wrap gap-xs" aria-label="Niveles educativos">
                                    @forelse ($tramite->niveles as $nivel)
                                        <li><x-tramite.nivel :clave="$nivel->clave">{{ $nivel->nombre }}</x-tramite.nivel></li>
                                    @empty
                                        <li class="text-caption text-muted">Sin niveles seleccionados</li>
                                    @endforelse
                                </ul>
                            </td>
                            <td class="mt-sm block md:mt-0 md:table-cell md:px-lg md:py-md">
                                <div class="flex items-center gap-sm">
                                    <div class="h-1 flex-1 rounded-pill bg-surface-card" aria-hidden="true">
                                        <div class="h-full rounded-pill bg-primary" style="width: {{ $avance['porcentaje'] }}%"></div>
                                    </div>
                                    <span class="text-caption tabular-nums text-muted">{{ $avance['hechas'] }} de {{ $avance['total'] }}</span>
                                </div>
                                <p class="mt-xxs text-caption text-muted">
                                    @if ($siguiente)
                                        Sigue: {{ $siguiente->nombre }}
                                    @elseif ($tramite->completo)
                                        Todo capturado
                                    @endif
                                </p>
                            </td>
                            <td class="mt-sm block md:mt-0 md:table-cell md:px-lg md:py-md">
                                <x-ui.status-tag
                                    :estado="$tramite->completo ? 'completado' : 'en_curso'"
                                    :texto="$tramite->completo ? 'Captura inicial completa' : 'En captura'"
                                />
                            </td>
                            <td class="mt-sm block whitespace-nowrap md:mt-0 md:table-cell md:px-lg md:py-md md:text-right">
                                @if ($tramite->completo)
                                    <x-ui.button-secondary :href="route('tramite.resumen', ['escuela' => $tramite->escuelaId])" class="whitespace-nowrap">
                                        Ver trámite<span class="sr-only">: {{ $nombre }}, Nº {{ $tramite->numero() }}</span>
                                    </x-ui.button-secondary>
                                @else
                                    <x-ui.button-primary :href="route('tramite.resumen', ['escuela' => $tramite->escuelaId])" class="whitespace-nowrap">
                                        Continuar<span class="sr-only">: {{ $nombre }}, Nº {{ $tramite->numero() }}</span>
                                    </x-ui.button-primary>
                                @endif
                                @if ($tramite->puedeEliminar)
                                    <x-tramite.eliminar :tramite="$tramite" class="mt-sm" />
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-tramite-layout>
