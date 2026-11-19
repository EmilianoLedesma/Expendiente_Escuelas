{{-- Visita de inspección de un nivel (supervisor). Los datos vienen de ConsultarVisita; cada formulario llama a un caso de uso. --}}
@php
    $actual = $datos['visita'];
    $archivoClases = 'block w-full text-body-md text-body file:mr-sm file:min-h-11 file:cursor-pointer file:rounded-md file:border file:border-solid file:border-primary file:bg-canvas file:px-md file:font-semibold file:text-primary';
@endphp
<div>
    <x-ui.page-header title="Visita de inspección" eyebrow="Revisión SEDEQ" :back-href="route('sedeq.expedientes')" back-label="Expedientes">
        <x-slot:intro>{{ $datos['escuela'] }} · {{ $datos['nivel'] }} · {{ $datos['estado']['etiqueta'] }}</x-slot:intro>
    </x-ui.page-header>

    @error('general')
        <x-ui.alert tipo="error" class="mb-lg">{{ $message }}</x-ui.alert>
    @enderror

    @if ($datos['hayRespuestaNueva'])
        <x-ui.alert tipo="warning" titulo="Respuesta recibida" class="mb-lg">El solicitante respondió a las observaciones. Revisa su respuesta y, si queda subsanado, acepta la subsanación.</x-ui.alert>
    @endif

    <div class="space-y-lg">
        @if ($datos['puedeProgramar'])
            <x-ui.section title="Programar visita">
                <form novalidate wire:submit="programar" class="space-y-md">
                    <x-ui.field id="fecha" label="Fecha y hora"><x-ui.input type="datetime-local" wire:model="fecha" /></x-ui.field>
                    <x-ui.field id="inspector" label="Inspector"><x-ui.input wire:model="inspector" maxlength="150" /></x-ui.field>
                    <x-ui.button-primary wire:loading.attr="disabled" wire:target="programar">Programar visita</x-ui.button-primary>
                </form>
            </x-ui.section>
        @endif

        @if ($actual && $actual['estado'] === 'programada')
            <x-ui.section :title="'Visita '.$actual['numero'].' programada'">
                @include('livewire.sedeq.partials.visita-detalle', ['v' => $actual, 'nivelId' => $escuelaNivelId, 'domicilio' => $datos['domicilioPlantel']])
            </x-ui.section>
        @endif

        @if ($datos['puedeActualizar'])
            <x-ui.section title="Corregir la visita">
                <form novalidate wire:submit="actualizar" class="space-y-md">
                    <x-ui.field id="editFecha" label="Fecha y hora"><x-ui.input type="datetime-local" wire:model="editFecha" /></x-ui.field>
                    <x-ui.field id="editInspector" label="Inspector"><x-ui.input wire:model="editInspector" maxlength="150" /></x-ui.field>
                    <x-ui.button-secondary type="submit" wire:loading.attr="disabled" wire:target="actualizar">Guardar cambios</x-ui.button-secondary>
                </form>
            </x-ui.section>
        @endif

        @if ($datos['puedeRegistrarActa'])
            <x-ui.section title="Registrar acta">
                <form novalidate wire:submit="registrarActa" class="space-y-md">
                    <x-ui.field id="resultado" label="Resultado">
                        <x-ui.select wire:model.live="resultado">
                            <option value="">Elige el resultado</option>
                            <option value="cumple">Cumple</option>
                            <option value="con_observaciones">Con observaciones</option>
                        </x-ui.select>
                    </x-ui.field>
                    <x-ui.field id="realizada_el" label="Fecha en que se realizó"><x-ui.input type="date" wire:model="realizada_el" max="{{ today()->toDateString() }}" /></x-ui.field>

                    <x-ui.field id="observaciones" label="Observaciones" hint="Una por campo. Con observaciones, el solicitante tiene 5 días hábiles para subsanar.">
                        <div class="space-y-xs">
                            @foreach ($observaciones as $i => $texto)
                                <div class="flex items-center gap-xs" wire:key="observacion-{{ $i }}">
                                    <x-ui.input id="observacion-{{ $i }}" aria-label="Observación {{ $i + 1 }}" wire:model="observaciones.{{ $i }}" />
                                    <button type="button" wire:click="quitarObservacion({{ $i }})" class="inline-flex min-h-11 min-w-11 shrink-0 items-center justify-center rounded-md border border-control text-body-sm font-semibold text-ink">
                                        <span aria-hidden="true">&times;</span><span class="sr-only">Quitar la observación {{ $i + 1 }}</span>
                                    </button>
                                </div>
                            @endforeach
                        </div>
                        <x-ui.button-secondary wire:click="agregarObservacion" class="mt-xs">Agregar observación</x-ui.button-secondary>
                    </x-ui.field>

                    <x-ui.field id="acta" label="Acta (PDF, opcional)">
                        <input type="file" id="acta" wire:model="acta" accept="application/pdf" @error('acta') aria-invalid="true" aria-describedby="acta-error" @enderror class="{{ $archivoClases }}">
                        <p wire:loading wire:target="acta" class="mt-xxs text-body-sm text-muted">Subiendo…</p>
                    </x-ui.field>

                    <x-ui.button-primary wire:loading.attr="disabled" wire:target="registrarActa">Registrar acta</x-ui.button-primary>
                </form>
            </x-ui.section>
        @endif

        @if ($actual && $actual['estado'] === 'realizada')
            <x-ui.section :title="'Visita '.$actual['numero'].' realizada'">
                @include('livewire.sedeq.partials.visita-detalle', ['v' => $actual, 'nivelId' => $escuelaNivelId, 'domicilio' => $datos['domicilioPlantel']])
            </x-ui.section>
        @endif

        @if ($datos['puedeAdjuntarActa'])
            <x-ui.section :title="$actual['tieneActa'] ? 'Reemplazar el acta' : 'Adjuntar el acta'">
                <form novalidate wire:submit="adjuntarActa" class="space-y-md">
                    <x-ui.field id="actaNueva" label="Acta (PDF)">
                        <input type="file" id="actaNueva" wire:model="actaNueva" accept="application/pdf" @error('actaNueva') aria-invalid="true" aria-describedby="actaNueva-error" @enderror class="{{ $archivoClases }}">
                        <p wire:loading wire:target="actaNueva" class="mt-xxs text-body-sm text-muted">Subiendo…</p>
                    </x-ui.field>
                    <x-ui.button-secondary type="submit" wire:loading.attr="disabled" wire:target="adjuntarActa">Adjuntar acta</x-ui.button-secondary>
                </form>
            </x-ui.section>
        @endif

        @if ($datos['puedeAceptar'])
            <x-ui.section title="Subsanación">
                <p class="text-body-md text-body">Si el solicitante ya atendió las observaciones, acepta la subsanación para dar por aprobada la visita.</p>
                <x-ui.button-primary type="button" wire:click="aceptar" wire:confirm="¿Aceptar la subsanación? El nivel pasará a «Visita aprobada»." wire:loading.attr="disabled" wire:target="aceptar">Aceptar subsanación</x-ui.button-primary>
            </x-ui.section>
        @endif

        @if (count($datos['visitas']) > ($actual ? 1 : 0))
            <x-ui.section title="Historial de visitas">
                @foreach (array_reverse($datos['visitas']) as $v)
                    @continue($v['id'] === $actual['id'])
                    <div class="space-y-md" wire:key="historial-{{ $v['id'] }}">
                        <h3 class="text-body-md font-semibold text-ink">Visita {{ $v['numero'] }}</h3>
                        @include('livewire.sedeq.partials.visita-detalle', ['v' => $v, 'nivelId' => $escuelaNivelId, 'domicilio' => $datos['domicilioPlantel']])
                    </div>
                @endforeach
            </x-ui.section>
        @endif
    </div>
</div>
