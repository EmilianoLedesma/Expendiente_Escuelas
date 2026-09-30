<div class="max-w-3xl">
    <x-ui.page-header :eyebrow="$encabezado" title="Matrícula">
        <x-slot:intro>
            @if ($etiquetaAlumnos === 'Alumnos inscritos')
                Captura los alumnos inscritos actualmente.
            @else
                Captura la capacidad que se solicita: los alumnos que se pretende atender.
            @endif
            Con este dato se revisan la superficie, el personal y el mobiliario del nivel.
        </x-slot:intro>
    </x-ui.page-header>

    <form wire:submit="guardar">
        <x-ui.error-summary />

        @error('matricula')
            <x-ui.alert tipo="error" id="matricula" class="mb-md">{{ $message }}</x-ui.alert>
        @enderror

        @if ($porSala)
            <div class="overflow-hidden rounded-lg border border-hairline">
                <table class="w-full border-collapse text-left">
                    <thead>
                        <tr class="border-b border-hairline text-body-sm text-muted">
                            <th scope="col" class="px-lg py-xs font-semibold">Sala</th>
                            <th scope="col" class="w-40 px-lg py-xs font-semibold">{{ $etiquetaAlumnos }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-hairline">
                        @foreach ($salas as $salaId => $alumnos)
                            <tr wire:key="sala-{{ $salaId }}">
                                <th scope="row" class="px-lg py-xs text-body-md font-normal text-body"><label for="salas.{{ $salaId }}">{{ $nombresSalas[$salaId] ?? $salaId }}</label></th>
                                <td class="px-lg py-xs">
                                    <x-ui.input id="salas.{{ $salaId }}" type="number" inputmode="numeric" min="0" class="text-right tabular-nums" wire:model="salas.{{ $salaId }}" />
                                    @error("salas.{$salaId}")
                                        <p id="salas.{{ $salaId }}-error" class="mt-xxs text-body-sm font-semibold text-error-ink">{{ $message }}</p>
                                    @enderror
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <ol class="space-y-md">
                @foreach ($grupos as $i => $grupo)
                    <li wire:key="grupo-{{ $i }}" class="grid items-start gap-md rounded-lg border border-hairline p-md sm:grid-cols-[1fr_7rem_10rem_auto]">
                        <x-ui.field id="grupos.{{ $i }}.gradoId" label="Grado">
                            <x-ui.select wire:model="grupos.{{ $i }}.gradoId">
                                <option value="">Selecciona</option>
                                @foreach ($grados as $id => $nombre)
                                    <option value="{{ $id }}">{{ $nombre }}</option>
                                @endforeach
                            </x-ui.select>
                        </x-ui.field>
                        <x-ui.field id="grupos.{{ $i }}.grupo" label="Grupo"><x-ui.input wire:model="grupos.{{ $i }}.grupo" maxlength="5" class="uppercase" /></x-ui.field>
                        <x-ui.field id="grupos.{{ $i }}.alumnos" :label="$etiquetaAlumnos"><x-ui.input type="number" inputmode="numeric" min="0" wire:model="grupos.{{ $i }}.alumnos" /></x-ui.field>
                        @if (count($grupos) > 1)
                            <button type="button" wire:click="quitarGrupo({{ $i }})" class="min-h-11 self-end font-semibold text-primary underline underline-offset-4 hover:no-underline">
                                Quitar<span class="sr-only"> grupo {{ $i + 1 }}</span>
                            </button>
                        @endif
                    </li>
                @endforeach
            </ol>

            <x-ui.button-secondary type="button" wire:click="agregarGrupo" class="mt-md">Agregar grupo</x-ui.button-secondary>
        @endif

        <x-ui.action-bar accion="guardar" label="Guardar y terminar el nivel" :back-href="route('tramite.resumen', ['escuela' => $escuelaNivel->escuela_id])" />
    </form>
</div>
