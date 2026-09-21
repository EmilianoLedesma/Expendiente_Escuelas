<div class="rounded-lg border-[0.5px] border-hairline bg-canvas p-lg">
    <h1 class="font-display text-display-sm font-semibold text-ink mb-xs">Datos del inmueble</h1>
    <p class="font-sans text-body-sm text-body mb-lg">
        Dimensiones, colindancias y servicios del inmueble donde operará la escuela.
    </p>

    {{-- Contexto de solo lectura, ya capturado en Paso 2.1 / 2.2. Texto plano
         a propósito: no es una pantalla de revisión de documentos. --}}
    <div class="rounded-md border-[0.5px] border-hairline bg-surface-soft p-md mb-lg space-y-xxs">
        <p class="font-sans text-[11px] text-muted">Ya capturado en pasos anteriores</p>
        @if ($terna->isNotEmpty())
            <p class="font-sans text-body-sm text-body">
                Terna de nombres: {{ $terna->pluck('nombre_propuesto')->implode(' · ') }}
            </p>
        @endif
        @if ($acreditacion !== null)
            <p class="font-sans text-body-sm text-body">
                Acreditación de ocupación legal: {{ str_replace('_', ' ', $acreditacion->tipo) }}
            </p>
        @endif
        @if ($constancia !== null)
            <p class="font-sans text-body-sm text-body">
                Constancia de seguridad estructural: perito {{ $constancia->perito_nombre }}
            </p>
        @endif
    </div>

    <form wire:submit="guardar" class="space-y-lg">
        <fieldset class="space-y-xs">
            <legend class="font-sans text-body-sm font-medium text-ink mb-xxs">Dimensiones</legend>
            <div class="flex flex-wrap gap-xs">
                <div>
                    <label for="metrosTotales" class="block font-sans text-[11px] text-body mb-xxs">Superficie del predio m² <span class="text-error">*</span></label>
                    <input id="metrosTotales" type="number" step="0.01" min="0" wire:model.blur="metrosTotales" class="w-[170px] h-[38px] px-[13px] rounded-md border-[0.5px] font-sans text-body-sm text-ink bg-canvas {{ $errors->has('metrosTotales') ? 'border-error' : 'border-hairline' }}">
                    @error('metrosTotales')
                        <p class="mt-xxs font-sans text-[11px] text-error">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label for="metrosConstruidos" class="block font-sans text-[11px] text-body mb-xxs">Superficie construida m²</label>
                    <input id="metrosConstruidos" type="number" step="0.01" min="0" wire:model.blur="metrosConstruidos" class="w-[170px] h-[38px] px-[13px] rounded-md border-[0.5px] border-hairline font-sans text-body-sm text-ink bg-canvas">
                </div>
                <div>
                    <label for="areaCivicaM2" class="block font-sans text-[11px] text-body mb-xxs">Área cívica m²</label>
                    <input id="areaCivicaM2" type="number" step="0.01" min="0" wire:model.blur="areaCivicaM2" class="w-[150px] h-[38px] px-[13px] rounded-md border-[0.5px] border-hairline font-sans text-body-sm text-ink bg-canvas">
                </div>
            </div>
            <label class="flex items-center gap-xs font-sans text-body-sm text-ink">
                <input type="checkbox" wire:model.blur="tieneAstaBandera"> Cuenta con asta bandera
            </label>
        </fieldset>

        <fieldset class="space-y-xs">
            <legend class="font-sans text-body-sm font-medium text-ink mb-xxs">Colindancias</legend>
            <div class="flex flex-wrap gap-xs">
                @foreach (['Norte' => 'colindanciaNorte', 'Sur' => 'colindanciaSur', 'Este' => 'colindanciaEste', 'Oeste' => 'colindanciaOeste'] as $etiqueta => $propiedad)
                    <div>
                        <label for="{{ $propiedad }}" class="block font-sans text-[11px] text-body mb-xxs">{{ $etiqueta }}</label>
                        <input id="{{ $propiedad }}" type="text" wire:model.blur="{{ $propiedad }}" class="w-[200px] h-[38px] px-[13px] rounded-md border-[0.5px] border-hairline font-sans text-body-sm text-ink bg-canvas">
                    </div>
                @endforeach
            </div>
        </fieldset>

        <fieldset class="space-y-xs">
            <legend class="font-sans text-body-sm font-medium text-ink mb-xxs">Ubicación geográfica</legend>
            <div class="flex flex-wrap gap-xs">
                <div>
                    <label for="latitud" class="block font-sans text-[11px] text-body mb-xxs">Latitud</label>
                    <input id="latitud" type="number" step="0.0000001" wire:model.blur="latitud" class="w-[170px] h-[38px] px-[13px] rounded-md border-[0.5px] border-hairline font-sans text-body-sm text-ink bg-canvas">
                </div>
                <div>
                    <label for="longitud" class="block font-sans text-[11px] text-body mb-xxs">Longitud</label>
                    <input id="longitud" type="number" step="0.0000001" wire:model.blur="longitud" class="w-[170px] h-[38px] px-[13px] rounded-md border-[0.5px] border-hairline font-sans text-body-sm text-ink bg-canvas">
                </div>
            </div>
        </fieldset>

        <fieldset class="space-y-sm">
            <legend class="font-sans text-body-sm font-medium text-ink mb-xxs">Instituciones de salud y emergencia cercanas</legend>
            @foreach ($serviciosCercanos as $indice => $servicio)
                <div class="flex flex-wrap items-end gap-xs">
                    <input type="text" placeholder="Nombre" wire:model.blur="serviciosCercanos.{{ $indice }}.nombre" class="flex-1 min-w-[180px] h-[38px] px-[13px] rounded-md border-[0.5px] border-hairline font-sans text-body-sm text-ink bg-canvas">
                    <select wire:model.blur="serviciosCercanos.{{ $indice }}.tipo" class="w-[140px] h-[38px] px-[13px] rounded-md border-[0.5px] border-hairline font-sans text-body-sm text-ink bg-canvas">
                        <option value="salud">Salud</option>
                        <option value="emergencia">Emergencia</option>
                    </select>
                    <label class="flex items-center gap-xxs font-sans text-[11px] text-body">
                        <input type="checkbox" wire:model.blur="serviciosCercanos.{{ $indice }}.esPublico"> Público
                    </label>
                    <input type="number" step="0.01" min="0" placeholder="Distancia" wire:model.blur="serviciosCercanos.{{ $indice }}.distanciaValor" class="w-[120px] h-[38px] px-[13px] rounded-md border-[0.5px] border-hairline font-sans text-body-sm text-ink bg-canvas">
                    <select wire:model.blur="serviciosCercanos.{{ $indice }}.distanciaUnidad" class="w-[90px] h-[38px] px-[13px] rounded-md border-[0.5px] border-hairline font-sans text-body-sm text-ink bg-canvas">
                        <option value="m">m</option>
                        <option value="km">km</option>
                    </select>
                    <button type="button" wire:click="quitarServicio({{ $indice }})" class="font-sans text-[11px] text-error underline">Quitar</button>
                </div>
                @error('serviciosCercanos.'.$indice.'.nombre')
                    <p class="font-sans text-[11px] text-error">{{ $message }}</p>
                @enderror
            @endforeach
            <button type="button" wire:click="agregarServicio" class="font-sans text-[11px] text-primary underline">Agregar institución</button>
        </fieldset>

        <fieldset class="space-y-sm">
            <legend class="font-sans text-body-sm font-medium text-ink mb-xxs">Estudios que el inmueble ya imparte</legend>
            @foreach ($estudiosActuales as $indice => $estudio)
                <div class="flex flex-wrap items-end gap-xs">
                    <select wire:model.blur="estudiosActuales.{{ $indice }}.nivelEducativoId" class="w-[190px] h-[38px] px-[13px] rounded-md border-[0.5px] border-hairline font-sans text-body-sm text-ink bg-canvas">
                        <option value="">Otro (especificar)</option>
                        @foreach ($niveles as $nivel)
                            <option value="{{ $nivel->id }}">{{ $nivel->nombre }}</option>
                        @endforeach
                    </select>
                    <input type="text" placeholder="Especificar otro" wire:model.blur="estudiosActuales.{{ $indice }}.otroNivelTexto" class="flex-1 min-w-[180px] h-[38px] px-[13px] rounded-md border-[0.5px] border-hairline font-sans text-body-sm text-ink bg-canvas">
                    <input type="number" min="0" placeholder="Alumnos" wire:model.blur="estudiosActuales.{{ $indice }}.numeroAlumnos" class="w-[120px] h-[38px] px-[13px] rounded-md border-[0.5px] border-hairline font-sans text-body-sm text-ink bg-canvas">
                    <button type="button" wire:click="quitarEstudio({{ $indice }})" class="font-sans text-[11px] text-error underline">Quitar</button>
                </div>
                @error('estudiosActuales.'.$indice.'.numeroAlumnos')
                    <p class="font-sans text-[11px] text-error">{{ $message }}</p>
                @enderror
            @endforeach
            <button type="button" wire:click="agregarEstudio" class="font-sans text-[11px] text-primary underline">Agregar estudio</button>
        </fieldset>

        <x-ui.button-primary type="submit">Guardar y continuar</x-ui.button-primary>
    </form>
</div>
