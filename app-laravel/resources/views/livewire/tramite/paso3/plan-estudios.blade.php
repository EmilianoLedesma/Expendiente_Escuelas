<div class="max-w-3xl">
    <x-ui.page-header :eyebrow="$encabezado" title="Plan de estudios y modalidad">
        <x-slot:intro>Indica cómo se impartirá el nivel: modalidad y plan de estudios.</x-slot:intro>
    </x-ui.page-header>

    <form novalidate wire:submit="guardar" class="space-y-lg">
        <x-ui.error-summary />

        <x-ui.radio-group id="modalidad" legend="Modalidad" wire:model.live="modalidad"
            :opciones="['escolarizada' => 'Escolarizada', 'no_escolarizada' => 'No escolarizada', 'mixta' => 'Mixta', 'virtual' => 'Virtual']" />

        @if ($modalidad !== '' && $modalidad !== 'escolarizada')
            <x-ui.radio-group id="plataformaEducativaTipo" legend="Plataforma educativa" wire:model="plataformaEducativaTipo"
                :opciones="['propia' => 'Propia', 'rentada' => 'Rentada']" />
        @endif

        {{-- Captured in Paso 2.4 (Documentos del nivel), where changing them discards the Formato de Solicitud. --}}
        <p class="text-body-sm text-muted">
            Turno: {{ ['matutino' => 'Matutino', 'vespertino' => 'Vespertino', 'mixto' => 'Mixto'][$escuelaNivel->turno] ?? 'sin capturar' }}
            · Tipo de alumnado: {{ ['mixto' => 'Mixto', 'femenino' => 'Femenino', 'masculino' => 'Masculino'][$escuelaNivel->tipo_alumnado] ?? 'sin capturar' }}
            <span class="block">Se capturan en Documentos del nivel.</span>
        </p>

        <x-ui.field id="planEstudiosReferencia" label="Plan de estudios" hint="Nombre o referencia del plan que se impartirá (por ejemplo, el plan oficial de la SEP)." optional>
            <x-ui.input wire:model.blur="planEstudiosReferencia" maxlength="200" />
        </x-ui.field>

        <x-ui.action-bar accion="guardar" :back-href="route('tramite.resumen', ['escuela' => $escuelaNivel->escuela_id])" />
    </form>
</div>
