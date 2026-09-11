{{-- resources/views/livewire/tramite/paso2-documentos.blade.php --}}
<div class="rounded-lg border-[0.5px] border-hairline bg-canvas p-lg">
    <h1 class="font-display text-display-sm font-semibold text-ink mb-xs">Documentos</h1>

    @error('vigencia')
        <div class="mb-lg"><x-ui.alert variant="error">{{ $message }}</x-ui.alert></div>
    @enderror

    @if (in_array($fase, ['ine', 'acta_nacimiento', 'escritura_poder_facultades']))
        <form wire:submit="guardarDocumentoSimple" class="space-y-md">
            <label class="block font-sans text-body-sm font-medium text-ink mb-xxs">
                Archivo PDF
            </label>
            <input type="file" wire:model="archivo" accept="application/pdf">
            @error('archivo')
                <p class="mt-xxs font-sans text-[11px] text-error">{{ $message }}</p>
            @enderror

            <x-ui.button-primary type="submit">Continuar</x-ui.button-primary>
        </form>
    @endif

    @if ($fase === 'escritura_inmueble')
        <form wire:submit="guardarAcreditacion" class="space-y-md">
            <label class="block font-sans text-body-sm font-medium text-ink mb-xxs">Archivo PDF</label>
            <input type="file" wire:model="archivo" accept="application/pdf">
            @error('archivo') <p class="mt-xxs font-sans text-[11px] text-error">{{ $message }}</p> @enderror

            <div>
                <label class="block font-sans text-body-sm font-medium text-ink mb-xxs">Tipo de acreditación</label>
                <label class="block"><input type="radio" wire:model.live="acreditacionForm.tipo" value="escritura_publica"> Escritura pública</label>
                <label class="block"><input type="radio" wire:model.live="acreditacionForm.tipo" value="arrendamiento"> Arrendamiento</label>
                <label class="block"><input type="radio" wire:model.live="acreditacionForm.tipo" value="comodato"> Comodato</label>
                <label class="block"><input type="radio" wire:model.live="acreditacionForm.tipo" value="otro"> Otro</label>
                @error('acreditacionForm.tipo') <p class="mt-xxs font-sans text-[11px] text-error">{{ $message }}</p> @enderror
            </div>

            @if ($acreditacionForm->tipo === 'escritura_publica')
                <x-ui.text-input name="acreditacionForm.numeroEscritura" label="Número de escritura" wire:model="acreditacionForm.numeroEscritura" required />
                <x-ui.text-input name="acreditacionForm.notarioNombre" label="Nombre del notario" wire:model="acreditacionForm.notarioNombre" required />
                <x-ui.text-input name="acreditacionForm.notarioNumero" label="Número del notario" wire:model="acreditacionForm.notarioNumero" />
                <x-ui.text-input name="acreditacionForm.notarioLocalidad" label="Localidad del notario" wire:model="acreditacionForm.notarioLocalidad" />
                <x-ui.text-input name="acreditacionForm.folioRpp" label="Folio del Registro Público de la Propiedad" wire:model="acreditacionForm.folioRpp" />
                <x-ui.text-input name="acreditacionForm.fechaInscripcionRpp" label="Fecha de inscripción RPP" type="date" wire:model="acreditacionForm.fechaInscripcionRpp" />
            @endif

            @if (in_array($acreditacionForm->tipo, ['arrendamiento', 'comodato']))
                <x-ui.text-input name="acreditacionForm.arrendadorComodante" label="Arrendador / comodante" wire:model="acreditacionForm.arrendadorComodante" required />
                <x-ui.text-input name="acreditacionForm.arrendatarioComodatario" label="Arrendatario / comodatario" wire:model="acreditacionForm.arrendatarioComodatario" required />
                <x-ui.text-input name="acreditacionForm.fechaContrato" label="Fecha del contrato" type="date" wire:model="acreditacionForm.fechaContrato" required />
                <x-ui.text-input name="acreditacionForm.vigenciaContrato" label="Vigencia del contrato" type="date" wire:model="acreditacionForm.vigenciaContrato" required />
                <x-ui.text-input name="acreditacionForm.usoAutorizado" label="Uso autorizado" wire:model="acreditacionForm.usoAutorizado" />
            @endif

            @if ($acreditacionForm->tipo === 'otro')
                <x-ui.text-input name="acreditacionForm.otroEspecifique" label="Especifique" wire:model="acreditacionForm.otroEspecifique" required />
            @endif

            <div>
                <label for="acreditacionForm.observaciones" class="block font-sans text-body-sm font-medium text-ink mb-xxs">Observaciones</label>
                <textarea id="acreditacionForm.observaciones" wire:model="acreditacionForm.observaciones" class="w-full px-[13px] py-[9px] rounded-md border-[0.5px] border-hairline font-sans text-body-sm text-ink bg-canvas focus:outline-none focus:ring-[3px] focus:border-primary focus:ring-primary/10"></textarea>
                @error('acreditacionForm.observaciones') <p class="mt-xxs font-sans text-[11px] text-error">{{ $message }}</p> @enderror
            </div>

            <x-ui.button-primary type="submit">Continuar</x-ui.button-primary>
        </form>
    @endif

    @if ($fase === 'dictamen_uso_suelo')
        <form wire:submit="guardarDictamen" class="space-y-md">
            <label class="block font-sans text-body-sm font-medium text-ink mb-xxs">Archivo PDF</label>
            <input type="file" wire:model="archivo" accept="application/pdf">
            @error('archivo') <p class="mt-xxs font-sans text-[11px] text-error">{{ $message }}</p> @enderror

            <x-ui.text-input name="dictamenForm.fechaEmision" label="Fecha de emisión" type="date" wire:model="dictamenForm.fechaEmision" required />

            <x-ui.button-primary type="submit">Continuar</x-ui.button-primary>
        </form>
    @endif

    @if ($fase === 'constancia_seguridad_estructural')
        <form wire:submit="guardarConstancia" class="space-y-md">
            <label class="block font-sans text-body-sm font-medium text-ink mb-xxs">Archivo PDF</label>
            <input type="file" wire:model="archivo" accept="application/pdf">
            @error('archivo') <p class="mt-xxs font-sans text-[11px] text-error">{{ $message }}</p> @enderror

            <x-ui.text-input name="constanciaForm.fechaEmision" label="Fecha de emisión" type="date" wire:model="constanciaForm.fechaEmision" required />
            <x-ui.text-input name="constanciaForm.peritoNombre" label="Nombre del perito" wire:model="constanciaForm.peritoNombre" required />
            <x-ui.text-input name="constanciaForm.peritoRegistroDro" label="Número de registro DRO" wire:model="constanciaForm.peritoRegistroDro" required />
            <x-ui.text-input name="constanciaForm.peritoRegistroVigencia" label="Vigencia del registro" type="date" wire:model="constanciaForm.peritoRegistroVigencia" />

            <x-ui.button-primary type="submit">Continuar</x-ui.button-primary>
        </form>
    @endif

    @if ($fase === 'formato_solicitud')
        <a href="{{ route('tramite.paso2-documentos.formato-solicitud', ['escuela' => $escuela->id]) }}" target="_blank" class="underline text-primary">
            Generar y descargar Formato de Solicitud
        </a>

        <form wire:submit="guardarFormatoSolicitud" class="space-y-md mt-md">
            <label class="block font-sans text-body-sm font-medium text-ink mb-xxs">Subir Formato de Solicitud firmado</label>
            <input type="file" wire:model="archivo" accept="application/pdf">
            @error('archivo') <p class="mt-xxs font-sans text-[11px] text-error">{{ $message }}</p> @enderror

            <x-ui.button-primary type="submit">Continuar</x-ui.button-primary>
        </form>
    @endif
</div>
