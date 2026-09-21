<div class="rounded-lg border-[0.5px] border-hairline bg-canvas p-lg">
    <h1 class="font-display text-display-sm font-semibold text-ink mb-xs">Mobiliario</h1>
    <p class="font-sans text-body-sm text-body mb-lg">
        Declara la cantidad de mobiliario y equipo con que cuenta cada sala.
    </p>

    @error('cantidades')
        <div class="mb-lg">
            <x-ui.alert variant="error" title="No se pudo guardar">{{ $message }}</x-ui.alert>
        </div>
    @enderror

    <form wire:submit="guardar" class="space-y-lg">
        @foreach ($grupos as $grupo)
            <fieldset class="space-y-xs">
                <legend class="font-sans text-body-sm font-medium text-ink mb-xxs">{{ $grupo->nombre }}</legend>

                @foreach ($grupo->conceptos as $concepto)
                    <div class="flex items-center justify-between gap-sm">
                        <label for="concepto-{{ $concepto->id }}" class="font-sans text-body-sm text-body">
                            {{ $concepto->nombre }}
                        </label>
                        <input
                            id="concepto-{{ $concepto->id }}"
                            type="number"
                            min="0"
                            wire:model.blur="cantidades.{{ $concepto->id }}"
                            class="w-[96px] h-[38px] px-[13px] rounded-md border-[0.5px] font-sans text-body-sm text-ink bg-canvas {{ $errors->has('cantidades.'.$concepto->id) ? 'border-error' : 'border-hairline' }}"
                        >
                    </div>
                    @error('cantidades.'.$concepto->id)
                        <p class="mt-xxs font-sans text-[11px] text-error">{{ $message }}</p>
                    @enderror
                @endforeach
            </fieldset>
        @endforeach

        <x-ui.button-primary type="submit">Guardar y continuar</x-ui.button-primary>
    </form>
</div>
