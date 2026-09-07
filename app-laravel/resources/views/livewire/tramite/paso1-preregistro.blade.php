<div class="rounded-lg border-[0.5px] border-hairline bg-canvas p-lg">
        <h1 class="font-display text-display-sm font-semibold text-ink mb-xs">Preregistro</h1>
        <p class="font-sans text-body-sm text-body mb-lg">
            Indica si este trámite es para un plantel/escuela nuevo o uno ya existente.
        </p>

        @if ($errors->has('bifurcacion') && !$errors->has('plantelId'))
            <div class="mb-lg">
                <x-ui.alert variant="error" title="No se pudo iniciar el trámite">
                    {{ $errors->first('bifurcacion') }}
                </x-ui.alert>
            </div>
        @endif

        <form wire:submit="guardar" class="space-y-lg">
            <fieldset class="space-y-xs">
                <label class="flex items-center gap-xs font-sans text-body-sm text-ink">
                    <input type="radio" wire:model.live="bifurcacion" value="nuevo">
                    Plantel/escuela nuevo (primer trámite)
                </label>
                <label class="flex items-center gap-xs font-sans text-body-sm text-ink">
                    <input type="radio" wire:model.live="bifurcacion" value="existente">
                    Plantel ya registrado en el sistema
                </label>
            </fieldset>

            @if ($bifurcacion === 'existente')
                <div>
                    <label for="plantelId" class="block font-sans text-body-sm font-medium text-ink mb-xxs">
                        Plantel registrado <span class="text-error">*</span>
                    </label>
                    <select
                        id="plantelId"
                        wire:model.blur="plantelId"
                        class="w-full h-[38px] px-[13px] rounded-md border-[0.5px] font-sans text-body-sm text-ink bg-canvas {{ $errors->has('plantelId') ? 'border-error' : 'border-hairline' }}"
                    >
                        <option value="">Selecciona un plantel…</option>
                        @foreach ($planteles as $plantel)
                            <option value="{{ $plantel['id'] }}">{{ $plantel['etiqueta'] }}</option>
                        @endforeach
                    </select>
                    @error('plantelId')
                        <p class="mt-xxs font-sans text-[11px] text-error">{{ $message }}</p>
                    @enderror
                </div>
            @else
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-md">
                    <div class="sm:col-span-2">
                        <x-ui.text-input name="calle" label="Calle y número" wire:model.blur="calle" required />
                    </div>
                    <x-ui.text-input name="numeroExt" label="Número exterior" wire:model.blur="numeroExt" />
                    <x-ui.text-input name="numeroInt" label="Número interior" wire:model.blur="numeroInt" />
                    <x-ui.text-input name="colonia" label="Colonia" wire:model.blur="colonia" required />
                    <x-ui.text-input name="localidad" label="Localidad" wire:model.blur="localidad" />
                    <x-ui.text-input name="municipio" label="Municipio" wire:model.blur="municipio" required />
                    <x-ui.text-input name="codigoPostal" label="Código postal" wire:model.blur="codigoPostal" required />
                    <x-ui.text-input name="telefono" label="Teléfono" wire:model.blur="telefono" />
                    <x-ui.text-input name="correoElectronico" label="Correo electrónico" type="email" wire:model.blur="correoElectronico" />
                </div>
            @endif

            <div class="flex justify-end gap-sm pt-md border-t-[0.5px] border-hairline">
                <x-ui.button-primary type="submit">Continuar</x-ui.button-primary>
            </div>
        </form>
    </div>
