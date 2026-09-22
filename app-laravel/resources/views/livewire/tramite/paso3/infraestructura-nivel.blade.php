<div class="rounded-lg border-[0.5px] border-hairline bg-canvas p-lg">
    <h1 class="font-display text-display-sm font-semibold text-ink mb-xs">Infraestructura del nivel</h1>
    <p class="font-sans text-body-sm text-body mb-lg">
        Declara los espacios del plantel y las aulas del nivel que se pretende incorporar.
    </p>

    <form wire:submit="guardar" class="space-y-lg">
        @if ($espaciosCapturados->isNotEmpty() || $sanitariosCapturados->isNotEmpty())
            <div class="rounded-md border-[0.5px] border-hairline bg-surface-soft p-md space-y-xs">
                <p class="font-sans text-body-sm font-medium text-ink">Infraestructura del plantel (ya registrada)</p>
                <p class="font-sans text-[11px] text-muted">
                    Estos datos son del plantel y ya fueron capturados. Cambiarlos requiere autorización previa de la Dirección de Educación.
                </p>
                <ul class="font-sans text-body-sm text-body space-y-xxs">
                    @foreach ($espaciosCapturados as $espacio)
                        <li>{{ $espacio->tipoEspacio->nombre }} — {{ $espacio->cantidad ?? '—' }} · {{ $espacio->superficie_m2 ?? '—' }} m²</li>
                    @endforeach
                    @foreach ($sanitariosCapturados as $sanitario)
                        <li>Sanitarios {{ str_replace('_', ' ', $sanitario->categoria) }} — {{ $sanitario->cantidad_retretes ?? '—' }} retretes · {{ $sanitario->cantidad_lavabos ?? '—' }} lavabos</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if ($tipos->isNotEmpty())
            @foreach ($tipos->groupBy('categoria') as $categoria => $tiposCategoria)
                <fieldset class="space-y-sm">
                    <legend class="font-sans text-body-sm font-medium text-ink mb-xxs">
                        {{ ['administrativo' => 'Espacios administrativos', 'cubiculo' => 'Cubículos', 'recreativo_deportivo' => 'Actividades físicas y recreativas', 'especial' => 'Instalaciones especiales'][$categoria] ?? $categoria }}
                    </legend>

                    @foreach ($tiposCategoria as $tipo)
                        <div class="space-y-xxs">
                            <p class="font-sans text-body-sm text-ink">{{ $tipo->nombre }}</p>
                            <div class="flex flex-wrap gap-xs">
                                <input type="number" min="0" placeholder="Cantidad" wire:model.blur="espacios.{{ $tipo->id }}.cantidad" class="w-[110px] h-[38px] px-[13px] rounded-md border-[0.5px] border-hairline font-sans text-body-sm text-ink bg-canvas">
                                <input type="number" step="0.01" min="0" placeholder="Superficie m²" wire:model.blur="espacios.{{ $tipo->id }}.superficieM2" class="w-[130px] h-[38px] px-[13px] rounded-md border-[0.5px] border-hairline font-sans text-body-sm text-ink bg-canvas">
                                <input type="number" min="0" placeholder="Capacidad" wire:model.blur="espacios.{{ $tipo->id }}.capacidadPromedio" class="w-[110px] h-[38px] px-[13px] rounded-md border-[0.5px] border-hairline font-sans text-body-sm text-ink bg-canvas">
                                @if ($tipo->clave === 'bodega')
                                    <select wire:model.blur="espacios.{{ $tipo->id }}.destinadoA" class="flex-1 min-w-[160px] h-[38px] px-[13px] rounded-md border-[0.5px] border-hairline font-sans text-body-sm text-ink bg-canvas">
                                        <option value="">Destinado a…</option>
                                        <option value="limpieza">Limpieza</option>
                                        <option value="general">General</option>
                                        <option value="otro">Otro</option>
                                    </select>
                                @else
                                    <input type="text" placeholder="Destinado a" wire:model.blur="espacios.{{ $tipo->id }}.destinadoA" class="flex-1 min-w-[160px] h-[38px] px-[13px] rounded-md border-[0.5px] border-hairline font-sans text-body-sm text-ink bg-canvas">
                                @endif
                            </div>
                            <div class="flex flex-wrap gap-sm">
                                <label class="flex items-center gap-xxs font-sans text-[11px] text-body">
                                    <input type="checkbox" wire:model.blur="espacios.{{ $tipo->id }}.ventilacionNatural"> Ventilación natural
                                </label>
                                <label class="flex items-center gap-xxs font-sans text-[11px] text-body">
                                    <input type="checkbox" wire:model.blur="espacios.{{ $tipo->id }}.iluminacionNatural"> Iluminación natural
                                </label>
                            </div>

                            @if ($tipo->permite_campo_futbol)
                                <div class="flex flex-wrap gap-xs">
                                    <input type="text" placeholder="Tipo de superficie" wire:model.blur="espacios.{{ $tipo->id }}.campoFutbolTipoSuperficie" class="w-[180px] h-[38px] px-[13px] rounded-md border-[0.5px] border-hairline font-sans text-body-sm text-ink bg-canvas">
                                    <select wire:model.blur="espacios.{{ $tipo->id }}.campoFutbolFormato" class="w-[140px] h-[38px] px-[13px] rounded-md border-[0.5px] border-hairline font-sans text-body-sm text-ink bg-canvas">
                                        <option value="">Formato…</option>
                                        <option value="11">11</option>
                                        <option value="7">7</option>
                                        <option value="5">5</option>
                                        <option value="baby_fut">Baby fut</option>
                                    </select>
                                </div>
                            @endif

                            @if ($tipo->permite_material_biblioteca)
                                <div class="space-y-xxs pl-md">
                                    <p class="font-sans text-[11px] text-muted">Material de la biblioteca</p>
                                    @foreach ($materiales as $material)
                                        <div class="flex items-center gap-xs">
                                            <span class="font-sans text-[11px] text-body w-[160px]">{{ $material->nombre }}</span>
                                            <input type="number" min="0" placeholder="Títulos" wire:model.blur="materialesBiblioteca.{{ $material->id }}.numeroTitulos" class="w-[100px] h-[32px] px-[10px] rounded-md border-[0.5px] border-hairline font-sans text-[11px] text-ink bg-canvas">
                                            <input type="number" min="0" placeholder="Volúmenes" wire:model.blur="materialesBiblioteca.{{ $material->id }}.numeroVolumenes" class="w-[110px] h-[32px] px-[10px] rounded-md border-[0.5px] border-hairline font-sans text-[11px] text-ink bg-canvas">
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endforeach
                </fieldset>
            @endforeach
        @endif

        @if (count($categorias) > 0)
            <fieldset class="space-y-sm">
                <legend class="font-sans text-body-sm font-medium text-ink mb-xxs">Sanitarios</legend>
                @foreach ($categorias as $categoria)
                    <div class="space-y-xxs">
                        <p class="font-sans text-body-sm text-ink">{{ ucfirst(str_replace('_', ' ', $categoria)) }}</p>
                        <div class="flex flex-wrap gap-xs">
                            <input type="number" min="0" placeholder="Retretes" wire:model.blur="sanitarios.{{ $categoria }}.cantidadRetretes" class="w-[110px] h-[38px] px-[13px] rounded-md border-[0.5px] border-hairline font-sans text-body-sm text-ink bg-canvas">
                            <input type="number" min="0" placeholder="Mingitorios" wire:model.blur="sanitarios.{{ $categoria }}.cantidadMingitorios" class="w-[120px] h-[38px] px-[13px] rounded-md border-[0.5px] border-hairline font-sans text-body-sm text-ink bg-canvas">
                            <input type="number" min="0" placeholder="Lavabos" wire:model.blur="sanitarios.{{ $categoria }}.cantidadLavabos" class="w-[110px] h-[38px] px-[13px] rounded-md border-[0.5px] border-hairline font-sans text-body-sm text-ink bg-canvas">
                            <input type="number" step="0.01" min="0" placeholder="Superficie m²" wire:model.blur="sanitarios.{{ $categoria }}.superficieM2" class="w-[130px] h-[38px] px-[13px] rounded-md border-[0.5px] border-hairline font-sans text-body-sm text-ink bg-canvas">
                            @if ($categoria === 'alumnado_maternal')
                                <input type="number" min="0" placeholder="Bacinicas" wire:model.blur="sanitarios.{{ $categoria }}.cantidadBacinicas" class="w-[110px] h-[38px] px-[13px] rounded-md border-[0.5px] border-hairline font-sans text-body-sm text-ink bg-canvas">
                            @endif
                        </div>
                    </div>
                @endforeach
            </fieldset>
        @endif

        <fieldset class="space-y-xs">
            <legend class="font-sans text-body-sm font-medium text-ink mb-xxs">Aulas del nivel</legend>
            <div class="flex flex-wrap gap-xs">
                <div>
                    <label for="numeroAulas" class="block font-sans text-[11px] text-body mb-xxs">Número de aulas <span class="text-error">*</span></label>
                    <input id="numeroAulas" type="number" min="1" wire:model.blur="numeroAulas" class="w-[130px] h-[38px] px-[13px] rounded-md border-[0.5px] font-sans text-body-sm text-ink bg-canvas {{ $errors->has('numeroAulas') ? 'border-error' : 'border-hairline' }}">
                    @error('numeroAulas')
                        <p class="mt-xxs font-sans text-[11px] text-error">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label for="superficieAulasM2" class="block font-sans text-[11px] text-body mb-xxs">Superficie total m²</label>
                    <input id="superficieAulasM2" type="number" step="0.01" min="0" wire:model.blur="superficieAulasM2" class="w-[150px] h-[38px] px-[13px] rounded-md border-[0.5px] border-hairline font-sans text-body-sm text-ink bg-canvas">
                </div>
            </div>
        </fieldset>

        <x-ui.button-primary type="submit">Guardar y continuar</x-ui.button-primary>
    </form>
</div>
