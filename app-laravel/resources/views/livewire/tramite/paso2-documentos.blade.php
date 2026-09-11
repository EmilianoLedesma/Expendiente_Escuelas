{{-- resources/views/livewire/tramite/paso2-documentos.blade.php --}}
<div class="rounded-lg border-[0.5px] border-hairline bg-canvas p-lg">
    <h1 class="font-display text-display-sm font-semibold text-ink mb-xs">Documentos</h1>

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
</div>
