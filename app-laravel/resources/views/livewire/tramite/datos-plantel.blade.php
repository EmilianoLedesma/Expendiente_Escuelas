<div class="max-w-3xl">
    <x-ui.page-header :eyebrow="$encabezado" title="Datos del plantel">
        <x-slot:intro>Puedes actualizar el teléfono y el correo electrónico del plantel. El domicilio no se cambia aquí: un cambio de domicilio requiere autorización previa de la Dirección de Educación.</x-slot:intro>
    </x-ui.page-header>

    <x-ui.section title="Domicilio del plantel">
        <x-ui.summary-list :filas="[
            'Calle' => $plantel->calle,
            'Número exterior' => $plantel->numero_ext,
            'Número interior' => $plantel->numero_int,
            'Colonia' => $plantel->colonia,
            'Localidad' => $plantel->localidad,
            'Municipio' => $plantel->municipio,
            'Código postal' => $plantel->codigo_postal,
        ]" />
    </x-ui.section>

    <form novalidate wire:submit="guardar" class="mt-xl">
        <x-ui.error-summary :excluir="['plantel']" />

        @error('plantel')
            <x-ui.alert tipo="error" id="plantel" class="mb-md">{{ $message }}</x-ui.alert>
        @enderror

        <x-ui.section title="Datos de contacto">
            <div class="grid grid-cols-1 gap-md sm:grid-cols-2">
                <x-ui.field id="telefono" label="Teléfono" optional>
                    <x-ui.input maxlength="20" type="tel" inputmode="tel" oninput="this.value=this.value.replace(/\D/g,'')" wire:model.blur="telefono" autocomplete="tel" />
                </x-ui.field>
                <x-ui.field id="correoElectronico" label="Correo electrónico" optional>
                    <x-ui.input maxlength="150" type="email" wire:model.blur="correoElectronico" autocomplete="email" />
                </x-ui.field>
            </div>
        </x-ui.section>

        <x-ui.action-bar accion="guardar" label="Guardar cambios" :back-href="route('tramite.resumen', ['escuela' => $escuela->id])" />
    </form>
</div>
