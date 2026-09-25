<div class="max-w-3xl">
    <x-ui.page-header :eyebrow="$encabezado" title="Datos del inmueble">
        <x-slot:intro>Dimensiones, colindancias y servicios del inmueble donde operará la escuela.</x-slot:intro>
    </x-ui.page-header>

    {{-- Contexto de solo lectura (Paso 1 y Paso 2): nunca inputs sobre el domicilio. --}}
    <x-ui.section title="Ya capturado en pasos anteriores">
        <x-ui.summary-list :filas="array_filter([
            'Domicilio del plantel' => $domicilio,
            'Terna de nombres' => $terna->isNotEmpty() ? $terna->pluck('nombre_propuesto')->implode(' · ') : null,
            'Acreditación de ocupación legal' => $acreditacion ? str_replace('_', ' ', $acreditacion->tipo) : null,
            'Constancia de seguridad estructural' => $constancia ? 'Perito: '.$constancia->perito_nombre : null,
        ], fn ($valor) => $valor !== null)" />
    </x-ui.section>

    <form wire:submit="guardar" class="mt-xl">
        <x-ui.error-summary />

        <x-ui.section title="Dimensiones">
            <div class="grid grid-cols-1 gap-md sm:grid-cols-2">
                <x-ui.field id="metrosTotales" label="Superficie del predio (m²)">
                    <x-ui.input type="number" inputmode="decimal" step="0.01" min="0" unit="m²" wire:model.blur="metrosTotales" />
                </x-ui.field>
                <x-ui.field id="metrosConstruidos" label="Superficie construida (m²)" optional>
                    <x-ui.input type="number" inputmode="decimal" step="0.01" min="0" unit="m²" wire:model.blur="metrosConstruidos" />
                </x-ui.field>
                <x-ui.field id="areaCivicaM2" label="Área cívica (m²)" optional>
                    <x-ui.input type="number" inputmode="decimal" step="0.01" min="0" unit="m²" wire:model.blur="areaCivicaM2" />
                </x-ui.field>
            </div>
            <x-ui.checkbox id="tieneAstaBandera" wire:model.blur="tieneAstaBandera">Cuenta con asta bandera</x-ui.checkbox>
        </x-ui.section>

        <x-ui.section title="Colindancias">
            <div class="grid grid-cols-1 gap-md sm:grid-cols-2">
                @foreach (['Norte' => 'colindanciaNorte', 'Sur' => 'colindanciaSur', 'Este' => 'colindanciaEste', 'Oeste' => 'colindanciaOeste'] as $etiqueta => $propiedad)
                    <x-ui.field :id="$propiedad" :label="$etiqueta" optional>
                        <x-ui.input wire:model.blur="{{ $propiedad }}" />
                    </x-ui.field>
                @endforeach
            </div>
        </x-ui.section>

        <x-ui.section title="Ubicación geográfica">
            <div class="grid grid-cols-1 gap-md sm:grid-cols-2">
                <x-ui.field id="latitud" label="Latitud" hint="En grados decimales." optional>
                    <x-ui.input type="number" inputmode="decimal" step="0.0000001" wire:model.blur="latitud" />
                </x-ui.field>
                <x-ui.field id="longitud" label="Longitud" hint="En grados decimales." optional>
                    <x-ui.input type="number" inputmode="decimal" step="0.0000001" wire:model.blur="longitud" />
                </x-ui.field>
            </div>
        </x-ui.section>

        <x-ui.section title="Instituciones de salud y emergencia cercanas">
            @foreach ($serviciosCercanos as $indice => $servicio)
                <fieldset wire:key="servicio-{{ $indice }}" class="rounded-md border border-hairline p-md">
                    <legend class="px-xxs text-body-sm font-semibold text-ink">Institución {{ $indice + 1 }}</legend>
                    <div class="grid grid-cols-1 gap-md sm:grid-cols-2">
                        <x-ui.field id="serviciosCercanos.{{ $indice }}.nombre" label="Nombre" class="sm:col-span-2">
                            <x-ui.input wire:model.blur="serviciosCercanos.{{ $indice }}.nombre" />
                        </x-ui.field>
                        <x-ui.field id="serviciosCercanos.{{ $indice }}.tipo" label="Tipo">
                            <x-ui.select wire:model.blur="serviciosCercanos.{{ $indice }}.tipo">
                                <option value="salud">Salud</option>
                                <option value="emergencia">Emergencia</option>
                            </x-ui.select>
                        </x-ui.field>
                        <x-ui.field id="serviciosCercanos.{{ $indice }}.distanciaValor" label="Distancia" optional>
                            <div class="flex gap-xs">
                                <x-ui.input type="number" inputmode="decimal" step="0.01" min="0" wire:model.blur="serviciosCercanos.{{ $indice }}.distanciaValor" />
                                <x-ui.select id="serviciosCercanos.{{ $indice }}.distanciaUnidad" aria-label="Unidad de distancia" wire:model.blur="serviciosCercanos.{{ $indice }}.distanciaUnidad" class="w-24">
                                    <option value="m">m</option>
                                    <option value="km">km</option>
                                </x-ui.select>
                            </div>
                        </x-ui.field>
                        <x-ui.checkbox id="serviciosCercanos.{{ $indice }}.esPublico" wire:model.blur="serviciosCercanos.{{ $indice }}.esPublico">Institución pública</x-ui.checkbox>
                    </div>
                    <button type="button" wire:click="quitarServicio({{ $indice }})" class="mt-sm inline-flex min-h-11 items-center gap-xxs font-semibold text-error-ink underline underline-offset-4 hover:no-underline">
                        <x-ui.icon nombre="x-mark" class="h-5 w-5" />
                        Quitar<span class="sr-only"> institución {{ $indice + 1 }}</span>
                    </button>
                </fieldset>
            @endforeach
            <x-ui.button-secondary type="button" wire:click="agregarServicio">
                <x-ui.icon nombre="plus" class="h-5 w-5" />
                Agregar institución
            </x-ui.button-secondary>
        </x-ui.section>

        <x-ui.section title="Estudios que el inmueble ya imparte">
            @foreach ($estudiosActuales as $indice => $estudio)
                <fieldset wire:key="estudio-{{ $indice }}" class="rounded-md border border-hairline p-md">
                    <legend class="px-xxs text-body-sm font-semibold text-ink">Estudio {{ $indice + 1 }}</legend>
                    <div class="grid grid-cols-1 gap-md sm:grid-cols-2">
                        <x-ui.field id="estudiosActuales.{{ $indice }}.nivelEducativoId" label="Nivel">
                            <x-ui.select wire:model.blur="estudiosActuales.{{ $indice }}.nivelEducativoId">
                                <option value="">Otro (especificar)</option>
                                @foreach ($niveles as $nivel)
                                    <option value="{{ $nivel->id }}">{{ $nivel->nombre }}</option>
                                @endforeach
                            </x-ui.select>
                        </x-ui.field>
                        <x-ui.field id="estudiosActuales.{{ $indice }}.otroNivelTexto" label="Otro nivel" optional>
                            <x-ui.input wire:model.blur="estudiosActuales.{{ $indice }}.otroNivelTexto" />
                        </x-ui.field>
                        <x-ui.field id="estudiosActuales.{{ $indice }}.numeroAlumnos" label="Número de alumnos">
                            <x-ui.input type="number" inputmode="numeric" min="0" wire:model.blur="estudiosActuales.{{ $indice }}.numeroAlumnos" />
                        </x-ui.field>
                    </div>
                    <button type="button" wire:click="quitarEstudio({{ $indice }})" class="mt-sm inline-flex min-h-11 items-center gap-xxs font-semibold text-error-ink underline underline-offset-4 hover:no-underline">
                        <x-ui.icon nombre="x-mark" class="h-5 w-5" />
                        Quitar<span class="sr-only"> estudio {{ $indice + 1 }}</span>
                    </button>
                </fieldset>
            @endforeach
            <x-ui.button-secondary type="button" wire:click="agregarEstudio">
                <x-ui.icon nombre="plus" class="h-5 w-5" />
                Agregar estudio
            </x-ui.button-secondary>
        </x-ui.section>

        <x-ui.action-bar accion="guardar" :back-href="route('tramite.resumen', ['escuela' => $escuelaNivel->escuela_id])" />
    </form>
</div>
