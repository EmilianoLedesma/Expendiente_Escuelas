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
            <x-ui.text-input name="domicilioNotificaciones" label="Domicilio para notificaciones" wire:model="domicilioNotificaciones" required />
            <x-ui.text-input name="personaAutorizadaRecoger" label="Persona autorizada para recoger notificaciones" wire:model="personaAutorizadaRecoger" />

            <x-ui.text-input name="nombrePropuesto1" label="Propuesta de nombre 1" wire:model="nombrePropuesto1" required />
            <x-ui.text-input name="nombrePropuesto2" label="Propuesta de nombre 2" wire:model="nombrePropuesto2" required />
            <x-ui.text-input name="nombrePropuesto3" label="Propuesta de nombre 3" wire:model="nombrePropuesto3" required />

            @if ($tipoPersona !== 'moral')
                <x-ui.text-input name="personaFisicaForm.nombre" label="Nombre completo" wire:model="personaFisicaForm.nombre" required />
                <x-ui.text-input name="personaFisicaForm.fechaNacimiento" label="Fecha de nacimiento" type="date" wire:model="personaFisicaForm.fechaNacimiento" />
                <x-ui.text-input name="personaFisicaForm.rfc" label="RFC" wire:model="personaFisicaForm.rfc" class="uppercase" />
                <x-ui.text-input name="personaFisicaForm.curp" label="CURP" wire:model="personaFisicaForm.curp" class="uppercase" />

                @if ($tipoPersona === 'fisica_con_gestor')
                    <x-ui.text-input name="gestorForm.nombre" label="Nombre del gestor" wire:model="gestorForm.nombre" required />
                    <x-ui.text-input name="gestorForm.numeroPoder" label="Número de poder" wire:model="gestorForm.numeroPoder" />
                    <x-ui.text-input name="gestorForm.notarioNombre" label="Nombre del notario" wire:model="gestorForm.notarioNombre" />
                    <x-ui.text-input name="gestorForm.notarioNumero" label="Número de notaría" wire:model="gestorForm.notarioNumero" />
                    <x-ui.text-input name="gestorForm.fechaPoder" label="Fecha del poder" type="date" wire:model="gestorForm.fechaPoder" />
                @endif
            @else
                <x-ui.text-input name="personaMoralForm.razonSocial" label="Razón social" wire:model="personaMoralForm.razonSocial" required />
                <x-ui.text-input name="personaMoralForm.nombreRepresentanteLegal" label="Representante legal" wire:model="personaMoralForm.nombreRepresentanteLegal" required />
                <x-ui.text-input name="personaMoralForm.numeroEscrituraConstitutiva" label="Número de escritura constitutiva" wire:model="personaMoralForm.numeroEscrituraConstitutiva" />
                <x-ui.text-input name="personaMoralForm.fechaEscrituraConstitutiva" label="Fecha de escritura constitutiva" type="date" wire:model="personaMoralForm.fechaEscrituraConstitutiva" />
                <x-ui.text-input name="personaMoralForm.notarioNombre" label="Nombre del notario" wire:model="personaMoralForm.notarioNombre" />
                <x-ui.text-input name="personaMoralForm.notarioNumero" label="Número de notaría" wire:model="personaMoralForm.notarioNumero" />
                <x-ui.text-input name="personaMoralForm.notarioCiudad" label="Ciudad de la notaría" wire:model="personaMoralForm.notarioCiudad" />
                <x-ui.text-input name="personaMoralForm.folioRegistroPublico" label="Folio del Registro Público" wire:model="personaMoralForm.folioRegistroPublico" />
                <x-ui.text-input name="personaMoralForm.fechaInscripcionRpp" label="Fecha de inscripción en el Registro Público" type="date" wire:model="personaMoralForm.fechaInscripcionRpp" />
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
