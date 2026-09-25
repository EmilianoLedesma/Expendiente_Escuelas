<div class="max-w-3xl">
    <x-ui.page-header :eyebrow="$encabezado" title="Mobiliario">
        <x-slot:intro>Declara la cantidad de mobiliario y equipo con que cuenta cada sala.</x-slot:intro>
    </x-ui.page-header>

    <form wire:submit="guardar">
        <x-ui.error-summary />

        {{-- id="cantidades": ancla del error "declara al menos un concepto" (D9). --}}
        <div id="cantidades" class="space-y-xl">
            @foreach ($grupos as $grupo)
                <div class="overflow-hidden rounded-lg border border-hairline">
                    <table class="w-full border-collapse text-left">
                        <caption class="border-b border-hairline bg-surface-soft px-lg py-sm text-left text-title-sm font-semibold text-ink">{{ $grupo->nombre }}</caption>
                        <thead>
                            <tr class="border-b border-hairline text-body-sm text-muted">
                                <th scope="col" class="px-lg py-xs font-semibold">Concepto</th>
                                <th scope="col" class="w-32 px-lg py-xs font-semibold">Cantidad</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-hairline">
                            @foreach ($grupo->conceptos as $concepto)
                                <tr wire:key="concepto-{{ $concepto->id }}">
                                    <th scope="row" class="px-lg py-xs text-body-md font-normal text-body">
                                        <label for="cantidades.{{ $concepto->id }}">{{ $concepto->nombre }}</label>
                                    </th>
                                    <td class="px-lg py-xs">
                                        <x-ui.input id="cantidades.{{ $concepto->id }}" type="number" inputmode="numeric" min="0" class="text-right tabular-nums" wire:model.blur="cantidades.{{ $concepto->id }}" />
                                        @error("cantidades.{$concepto->id}")
                                            <p id="cantidades.{{ $concepto->id }}-error" class="mt-xxs text-body-sm font-semibold text-error-ink">{{ $message }}</p>
                                        @enderror
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endforeach
        </div>

        <x-ui.action-bar accion="guardar" :back-href="route('tramite.resumen', ['escuela' => $escuelaNivel->escuela_id])" />
    </form>
</div>
