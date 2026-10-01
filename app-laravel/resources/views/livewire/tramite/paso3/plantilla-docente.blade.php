<div class="max-w-3xl">
    <x-ui.page-header :eyebrow="$encabezado" title="Plantilla docente">
        <x-slot:intro>Registra a cada persona que laborará en el nivel (Anexo 1), bajo protesta de decir verdad. Incluye al Director Técnico.</x-slot:intro>
    </x-ui.page-header>

    <form novalidate wire:submit="guardar">
        <x-ui.error-summary />

        <ol id="personas" class="space-y-lg">
            @foreach ($personas as $i => $persona)
                @php($cargo = $cargos[(int) $persona['cargoPuestoId']] ?? null)
                <li wire:key="persona-{{ $i }}" class="rounded-lg border border-hairline p-md sm:p-lg">
                    <div class="flex items-center justify-between gap-sm">
                        <h2 class="text-title-sm font-semibold text-ink">Persona {{ $i + 1 }}</h2>
                        @if (count($personas) > 1)
                            <button type="button" wire:click="quitarPersona({{ $i }})" class="min-h-11 font-semibold text-primary underline underline-offset-4 hover:no-underline">
                                Quitar<span class="sr-only"> persona {{ $i + 1 }}</span>
                            </button>
                        @endif
                    </div>

                    <div class="mt-md grid gap-md sm:grid-cols-2">
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
                </li>
            @endforeach
        </ol>

        <x-ui.button-secondary type="button" wire:click="agregarPersona" class="mt-md">Agregar persona</x-ui.button-secondary>

        <x-ui.action-bar accion="guardar" :back-href="route('tramite.resumen', ['escuela' => $escuelaNivel->escuela_id])" />
    </form>
</div>
