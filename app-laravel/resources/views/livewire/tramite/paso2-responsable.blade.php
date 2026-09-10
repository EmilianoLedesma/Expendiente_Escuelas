<div class="rounded-lg border-[0.5px] border-hairline bg-canvas p-lg">
    @if ($fase === 'responsable')
        <h1 class="font-display text-display-sm font-semibold text-ink mb-xs">Responsable legal</h1>

        <fieldset class="space-y-xs mb-lg">
            <label class="flex items-center gap-xs font-sans text-body-sm text-ink">
                <input type="radio" wire:model.live="tipoPersona" value="fisica"> Persona física
            </label>
            <label class="flex items-center gap-xs font-sans text-body-sm text-ink">
                <input type="radio" wire:model.live="tipoPersona" value="fisica_con_gestor"> Persona física con gestor
            </label>
            <label class="flex items-center gap-xs font-sans text-body-sm text-ink">
                <input type="radio" wire:model.live="tipoPersona" value="moral"> Persona moral
            </label>
        </fieldset>

        <form wire:submit="guardarResponsable" class="space-y-md">
            @if ($tipoPersona !== 'moral')
                <x-ui.text-input name="personaFisicaForm.nombre" label="Nombre completo" wire:model="personaFisicaForm.nombre" required />
                <x-ui.text-input name="personaFisicaForm.rfc" label="RFC" wire:model="personaFisicaForm.rfc" />
                <x-ui.text-input name="personaFisicaForm.curp" label="CURP" wire:model="personaFisicaForm.curp" />

                @if ($tipoPersona === 'fisica_con_gestor')
                    <x-ui.text-input name="gestorForm.nombre" label="Nombre del gestor" wire:model="gestorForm.nombre" required />
                    <x-ui.text-input name="gestorForm.numeroPoder" label="Número de poder" wire:model="gestorForm.numeroPoder" />
                @endif
            @else
                <x-ui.text-input name="personaMoralForm.razonSocial" label="Razón social" wire:model="personaMoralForm.razonSocial" required />
                <x-ui.text-input name="personaMoralForm.nombreRepresentanteLegal" label="Representante legal" wire:model="personaMoralForm.nombreRepresentanteLegal" required />
            @endif

            <x-ui.button-primary type="submit">Continuar</x-ui.button-primary>
        </form>
    @endif

    @if ($fase === 'niveles')
        <h1 class="font-display text-display-sm font-semibold text-ink mb-xs">Selección de niveles</h1>

        @error('nivelesSeleccionados')
            <div class="mb-lg"><x-ui.alert variant="error">{{ $message }}</x-ui.alert></div>
        @enderror

        <form wire:submit="guardarNiveles" class="space-y-md">
            <fieldset class="space-y-xs">
                @foreach ($nivelesDisponibles as $nivel)
                    <label class="flex items-center gap-xs font-sans text-body-sm text-ink">
                        <input type="checkbox" wire:model="nivelesSeleccionados" value="{{ $nivel->id }}">
                        {{ $nivel->nombre }}
                    </label>
                @endforeach
            </fieldset>

            <x-ui.button-primary type="submit">Continuar</x-ui.button-primary>
        </form>
    @endif
</div>
