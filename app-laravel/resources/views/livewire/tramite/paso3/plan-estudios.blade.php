<div class="max-w-3xl">
    <x-ui.page-header :eyebrow="$encabezado" title="Plan de estudios y modalidad">
        <x-slot:intro>Indica cómo se impartirá el nivel: modalidad, turno y tipo de alumnado.</x-slot:intro>
    </x-ui.page-header>

    <form wire:submit="guardar" class="space-y-lg">
        <x-ui.error-summary />

        <x-ui.radio-group id="modalidad" legend="Modalidad" wire:model.live="modalidad"
            :opciones="['escolarizada' => 'Escolarizada', 'no_escolarizada' => 'No escolarizada', 'mixta' => 'Mixta', 'virtual' => 'Virtual']" />

        @if ($modalidad !== '' && $modalidad !== 'escolarizada')
            <x-ui.radio-group id="plataformaEducativaTipo" legend="Plataforma educativa" wire:model="plataformaEducativaTipo"
                :opciones="['propia' => 'Propia', 'rentada' => 'Rentada']" />
        @endif

        <x-ui.radio-group id="turno" legend="Turno" wire:model="turno"
            :opciones="['matutino' => 'Matutino', 'vespertino' => 'Vespertino', 'mixto' => 'Mixto']" />

        <x-ui.radio-group id="tipoAlumnado" legend="Tipo de alumnado" wire:model="tipoAlumnado"
            :opciones="['mixto' => 'Mixto', 'femenino' => 'Femenino', 'masculino' => 'Masculino']" />

        <x-ui.field id="planEstudiosReferencia" label="Plan de estudios" hint="Nombre o referencia del plan que se impartirá (por ejemplo, el plan oficial de la SEP)." optional>
            <x-ui.input wire:model="planEstudiosReferencia" maxlength="200" />
        </x-ui.field>

        <x-ui.action-bar accion="guardar" :back-href="route('tramite.resumen', ['escuela' => $escuelaNivel->escuela_id])" />
    </form>
</div>
