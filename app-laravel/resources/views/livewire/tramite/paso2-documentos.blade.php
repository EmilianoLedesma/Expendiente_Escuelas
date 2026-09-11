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

            <x-ui.text-input name="acreditacionForm.numeroEscritura" label="Número de escritura" wire:model="acreditacionForm.numeroEscritura" />
            <x-ui.text-input name="acreditacionForm.notarioNombre" label="Nombre del notario" wire:model="acreditacionForm.notarioNombre" />

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
