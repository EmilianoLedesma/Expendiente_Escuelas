<div class="max-w-3xl">
    <x-ui.page-header :back-href="route('tramite.index')" back-label="Mis trámites" :eyebrow="$encabezado" title="Datos del plantel">
        <x-slot:intro>Indica si este trámite es para un plantel nuevo o para uno que ya registraste en el sistema.</x-slot:intro>
    </x-ui.page-header>

    <form wire:submit="guardar">
        <x-ui.error-summary />

        <x-ui.radio-group
            id="bifurcacion"
            legend="¿Para qué plantel es este trámite?"
            wire:model.live="bifurcacion"
            :opciones="[
                'nuevo' => ['etiqueta' => 'Plantel/escuela nuevo (primer trámite)', 'descripcion' => 'Capturarás el domicilio y los datos de contacto del plantel.'],
                'existente' => ['etiqueta' => 'Plantel ya registrado en el sistema', 'descripcion' => 'Elige uno de los planteles que ya registraste en otro trámite.'],
            ]"
        />

        @if ($bifurcacion === 'existente')
            <x-ui.section title="Plantel registrado">
                <x-ui.field id="plantelId" label="Plantel registrado">
                    <x-ui.select wire:model.blur="plantelId">
                        <option value="">Selecciona un plantel…</option>
                        @foreach ($planteles as $plantel)
                            <option value="{{ $plantel['id'] }}">{{ $plantel['etiqueta'] }}</option>
                        @endforeach
                    </x-ui.select>
                </x-ui.field>
            </x-ui.section>
        @else
            <x-ui.section title="Domicilio del plantel">
                <div class="grid grid-cols-1 gap-md sm:grid-cols-2">
                    <x-ui.field id="calle" label="Calle y número" class="sm:col-span-2">
                        <x-ui.input wire:model.blur="calle" autocomplete="address-line1" />
                    </x-ui.field>
                    <x-ui.field id="numeroExt" label="Número exterior" optional>
                        <x-ui.input wire:model.blur="numeroExt" />
                    </x-ui.field>
                    <x-ui.field id="numeroInt" label="Número interior" optional>
                        <x-ui.input wire:model.blur="numeroInt" />
                    </x-ui.field>
                    <x-ui.field id="colonia" label="Colonia">
                        <x-ui.input wire:model.blur="colonia" />
                    </x-ui.field>
                    <x-ui.field id="localidad" label="Localidad" optional>
                        <x-ui.input wire:model.blur="localidad" />
                    </x-ui.field>
                    <x-ui.field id="municipio" label="Municipio">
                        <x-ui.input wire:model.blur="municipio" />
                    </x-ui.field>
                    <x-ui.field id="codigoPostal" label="Código postal">
                        <x-ui.input wire:model.blur="codigoPostal" inputmode="numeric" autocomplete="postal-code" />
                    </x-ui.field>
                </div>
            </x-ui.section>

            <x-ui.section title="Datos de contacto">
                <div class="grid grid-cols-1 gap-md sm:grid-cols-2">
                    <x-ui.field id="telefono" label="Teléfono" optional>
                        <x-ui.input type="tel" inputmode="tel" wire:model.blur="telefono" autocomplete="tel" />
                    </x-ui.field>
                    <x-ui.field id="correoElectronico" label="Correo electrónico" optional>
                        <x-ui.input type="email" wire:model.blur="correoElectronico" autocomplete="email" />
                    </x-ui.field>
                </div>
            </x-ui.section>
        @endif

        <x-ui.action-bar accion="guardar" :back-href="route('tramite.index')" back-label="Volver a Mis trámites" />
    </form>
</div>
