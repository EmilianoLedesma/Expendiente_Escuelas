<div class="max-w-3xl">
    <x-ui.page-header :eyebrow="$encabezado" title="Plantilla docente">
        <x-slot:intro>Registra a cada persona que laborará en el nivel (Anexo 1), bajo protesta de decir verdad. Incluye al Director Técnico.</x-slot:intro>
    </x-ui.page-header>

    <form novalidate wire:submit="guardar">
        <x-ui.error-summary />

        <ol id="personas" class="space-y-lg">
            @foreach ($personas as $i => $persona)
                @if ($i < $inicioPersonas || $i >= $finPersonas)
                    @continue
                @endif
                @php($cargo = $cargos[(int) $persona['cargoPuestoId']] ?? null)
                <li wire:key="persona-{{ $i }}" class="rounded-lg border border-hairline">
                    <div class="flex items-center gap-sm p-md sm:p-lg">
                        <button
                            type="button"
                            wire:click="alternarPersona({{ $i }})"
                            aria-controls="persona-panel-{{ $i }}"
                            aria-expanded="{{ $personaAbierta === $i ? 'true' : 'false' }}"
                            class="flex min-h-11 flex-1 items-center justify-between gap-sm text-left text-title-sm font-semibold text-ink"
                        >
                            <span>Persona {{ $i + 1 }}</span>
                            <x-ui.icon nombre="chevron-down" class="size-5 shrink-0 transition-transform duration-200 {{ $personaAbierta === $i ? 'rotate-180' : '' }}" aria-hidden="true" />
                        </button>
                        @if (count($personas) > 1)
                            <button type="button" wire:click="quitarPersona({{ $i }})" class="min-h-11 font-semibold text-primary">
                                Quitar<span class="sr-only"> persona {{ $i + 1 }}</span>
                            </button>
                        @endif
                    </div>

                    @if ($personaAbierta === $i)
                        <div id="persona-panel-{{ $i }}" class="grid gap-md border-t border-hairline px-md pb-md pt-md sm:grid-cols-2 sm:px-lg sm:pb-lg">
                        <x-ui.field id="personas.{{ $i }}.cargoPuestoId" label="Cargo o puesto" class="sm:col-span-2">
                            <x-ui.select wire:model.live="personas.{{ $i }}.cargoPuestoId">
                                <option value="">Selecciona un cargo</option>
                                @foreach ($cargos as $id => $datosCargo)
                                    <option value="{{ $id }}">{{ $datosCargo['nombre'] }}</option>
                                @endforeach
                            </x-ui.select>
                        </x-ui.field>

                        @if ($cargo['requiereSala'] ?? false)
                            <x-ui.field id="personas.{{ $i }}.salaId" label="Sala asignada" class="sm:col-span-2">
                                <x-ui.select wire:model="personas.{{ $i }}.salaId">
                                    <option value="">Selecciona una sala</option>
                                    @foreach ($salas as $id => $nombre)
                                        <option value="{{ $id }}">{{ $nombre }}</option>
                                    @endforeach
                                </x-ui.select>
                            </x-ui.field>
                        @endif

                        @if ($cargo['requiereAsignatura'] ?? false)
                            <x-ui.field id="personas.{{ $i }}.asignaturaId" label="Asignatura que imparte" class="sm:col-span-2">
                                <x-ui.select wire:model="personas.{{ $i }}.asignaturaId">
                                    <option value="">Selecciona una asignatura</option>
                                    @foreach ($asignaturas as $id => $nombre)
                                        <option value="{{ $id }}">{{ $nombre }}</option>
                                    @endforeach
                                </x-ui.select>
                            </x-ui.field>
                        @endif

                        <x-ui.field id="personas.{{ $i }}.nombre" label="Nombre completo" class="sm:col-span-2"><x-ui.input maxlength="200" wire:model.blur="personas.{{ $i }}.nombre" /></x-ui.field>
                        <x-ui.field id="personas.{{ $i }}.nacionalidad" label="Nacionalidad"><x-ui.input maxlength="100" wire:model.blur="personas.{{ $i }}.nacionalidad" /></x-ui.field>
                        <x-ui.field id="personas.{{ $i }}.sexo" label="Sexo">
                            <x-ui.select wire:model="personas.{{ $i }}.sexo">
                                <option value="">Selecciona</option>
                                <option value="F">F</option>
                                <option value="M">M</option>
                            </x-ui.select>
                        </x-ui.field>
                        <x-ui.field id="personas.{{ $i }}.estudios" label="Estudios"><x-ui.input maxlength="200" wire:model.blur="personas.{{ $i }}.estudios" /></x-ui.field>
                        <x-ui.field id="personas.{{ $i }}.cedulaODocumento" label="Cédula profesional o documento académico"><x-ui.input maxlength="100" wire:model.blur="personas.{{ $i }}.cedulaODocumento" /></x-ui.field>
                        </div>
                    @endif
                </li>
            @endforeach
        </ol>

        @if ($totalPaginas > 1)
            <nav aria-label="Paginación de personas" class="mt-lg flex items-center justify-between gap-md">
                <button
                    type="button"
                    wire:click="paginaAnterior"
                    @disabled($paginaPersonas === 1)
                    class="min-h-11 font-semibold text-primary disabled:cursor-not-allowed disabled:opacity-50"
                >
                    Anterior
                </button>
                <span class="text-sm text-muted">Página {{ $paginaPersonas }} de {{ $totalPaginas }}</span>
                <button
                    type="button"
                    wire:click="paginaSiguiente"
                    @disabled($paginaPersonas === $totalPaginas)
                    class="min-h-11 font-semibold text-primary disabled:cursor-not-allowed disabled:opacity-50"
                >
                    Siguiente
                </button>
            </nav>
        @endif

        <x-ui.button-secondary type="button" wire:click="agregarPersona" class="mt-md">Agregar persona</x-ui.button-secondary>

        <x-ui.action-bar accion="guardar" :back-href="route('tramite.resumen', ['escuela' => $escuelaNivel->escuela_id])" />
    </form>
</div>
