@php
    $nombresCategoria = ['administrativo' => 'Espacios administrativos', 'cubiculo' => 'Cubículos', 'recreativo_deportivo' => 'Actividades físicas y recreativas', 'especial' => 'Instalaciones especiales'];
    $yaCapturado = $espaciosCapturados
        ->mapWithKeys(fn ($e) => [$e->tipoEspacio->nombre => ($e->cantidad ?? '—').' · '.($e->superficie_m2 ?? '—').' m²'])
        ->merge($sanitariosCapturados->mapWithKeys(fn ($s) => ['Sanitarios '.str_replace('_', ' ', $s->categoria) => ($s->cantidad_retretes ?? '—').' retretes · '.($s->cantidad_lavabos ?? '—').' lavabos']))
        ->all();
    $columnas = 'md:grid-cols-[minmax(0,2fr)_repeat(3,minmax(0,1fr))]';
    $etiqueta = 'block text-body-sm font-semibold text-ink';

    // Un sub-paso por bloque, en el orden en que se renderizan los paneles de abajo.
    $etiquetasCategoria = ['administrativo' => 'Administrativos', 'cubiculo' => 'Cubículos', 'recreativo_deportivo' => 'Actividades físicas', 'especial' => 'Instalaciones especiales'];
    $pasos = [];
    if ($yaCapturado !== []) {
        $pasos[] = 'Ya registrada';
    }
    foreach ($tipos->groupBy('categoria') as $categoria => $_) {
        $pasos[] = $etiquetasCategoria[$categoria] ?? $categoria;
    }
    if (count($categorias) > 0) {
        $pasos[] = 'Sanitarios';
    }
    $pasos[] = 'Aulas del nivel';
    $totalPasos = count($pasos);
    $indice = 0;
@endphp

<div class="max-w-3xl">
    <x-ui.page-header :eyebrow="$encabezado" title="Infraestructura">
        <x-slot:intro>Declara los espacios del plantel y las aulas del nivel que se pretende incorporar.</x-slot:intro>
    </x-ui.page-header>

    <form novalidate wire:submit="guardar">
        <x-ui.error-summary />

        <x-ui.stepper :pasos="$pasos">
        @if ($yaCapturado !== [])
            <x-ui.stepper-panel :indice="$indice++" :total="$totalPasos" titulo="Infraestructura del plantel (ya registrada)">
            <x-ui.section title="Infraestructura del plantel (ya registrada)">
                <p class="text-body-sm text-muted">Estos datos son del plantel y ya fueron capturados. Cambiarlos requiere autorización previa de la Dirección de Educación.</p>
                <x-ui.summary-list :filas="$yaCapturado" />
            </x-ui.section>
            </x-ui.stepper-panel>
        @endif

        @foreach ($tipos->groupBy('categoria') as $categoria => $tiposCategoria)
            <x-ui.stepper-panel :indice="$indice++" :total="$totalPasos" :titulo="$nombresCategoria[$categoria] ?? $categoria">
            <x-ui.section :title="$nombresCategoria[$categoria] ?? $categoria">
                <div class="hidden gap-sm text-body-sm font-semibold text-muted md:grid {{ $columnas }}" aria-hidden="true">
                    <span>Espacio</span><span>Cantidad</span><span>Superficie (m²)</span><span>Capacidad</span>
                </div>

                @foreach ($tiposCategoria as $tipo)
                    <div wire:key="tipo-{{ $tipo->id }}" class="rounded-md border border-hairline p-md md:rounded-none md:border-0 md:border-t md:px-0">
                        <div class="grid grid-cols-1 gap-sm md:items-start {{ $columnas }}">
                            <p class="text-body-md font-semibold text-ink">{{ $tipo->nombre }}</p>
                            @foreach (['cantidad' => ['Cantidad', 'numeric', null], 'superficieM2' => ['Superficie (m²)', 'decimal', '0.01'], 'capacidadPromedio' => ['Capacidad', 'numeric', null]] as $campo => [$texto, $modo, $paso])
                                <div>
                                    <label for="espacios.{{ $tipo->id }}.{{ $campo }}" class="{{ $etiqueta }} md:sr-only">{{ $texto }}<span class="sr-only"> — {{ $tipo->nombre }}</span></label>
                                    <x-ui.input id="espacios.{{ $tipo->id }}.{{ $campo }}" type="number" inputmode="{{ $modo }}" min="0" :step="$paso" wire:model.blur="espacios.{{ $tipo->id }}.{{ $campo }}" />
                                </div>
                            @endforeach
                        </div>

                        <div class="mt-sm grid grid-cols-1 gap-sm md:grid-cols-2">
                            <div>
                                <label for="espacios.{{ $tipo->id }}.destinadoA" class="{{ $etiqueta }}">Destinado a<span class="sr-only"> — {{ $tipo->nombre }}</span> <span class="font-normal text-muted">(opcional)</span></label>
                                @if ($tipo->clave === 'bodega')
                                    <x-ui.select id="espacios.{{ $tipo->id }}.destinadoA" wire:model.blur="espacios.{{ $tipo->id }}.destinadoA">
                                        <option value="">Selecciona…</option>
                                        <option value="limpieza">Limpieza</option>
                                        <option value="general">General</option>
                                        <option value="otro">Otro</option>
                                    </x-ui.select>
                                @else
                                    <x-ui.input id="espacios.{{ $tipo->id }}.destinadoA" wire:model.blur="espacios.{{ $tipo->id }}.destinadoA" />
                                @endif
                            </div>
                            <div class="flex flex-wrap gap-x-md">
                                <x-ui.checkbox id="espacios.{{ $tipo->id }}.ventilacionNatural" wire:model.blur="espacios.{{ $tipo->id }}.ventilacionNatural">Ventilación natural<span class="sr-only"> — {{ $tipo->nombre }}</span></x-ui.checkbox>
                                <x-ui.checkbox id="espacios.{{ $tipo->id }}.iluminacionNatural" wire:model.blur="espacios.{{ $tipo->id }}.iluminacionNatural">Iluminación natural<span class="sr-only"> — {{ $tipo->nombre }}</span></x-ui.checkbox>
                            </div>
                        </div>

                        @if ($tipo->permite_campo_futbol)
                            <div class="mt-sm grid grid-cols-1 gap-sm md:grid-cols-2">
                                <div>
                                    <label for="espacios.{{ $tipo->id }}.campoFutbolTipoSuperficie" class="{{ $etiqueta }}">Tipo de superficie <span class="font-normal text-muted">(opcional)</span></label>
                                    <x-ui.input id="espacios.{{ $tipo->id }}.campoFutbolTipoSuperficie" wire:model.blur="espacios.{{ $tipo->id }}.campoFutbolTipoSuperficie" />
                                </div>
                                <div>
                                    <label for="espacios.{{ $tipo->id }}.campoFutbolFormato" class="{{ $etiqueta }}">Formato <span class="font-normal text-muted">(opcional)</span></label>
                                    <x-ui.select id="espacios.{{ $tipo->id }}.campoFutbolFormato" wire:model.blur="espacios.{{ $tipo->id }}.campoFutbolFormato">
                                        <option value="">Selecciona…</option>
                                        <option value="11">11</option>
                                        <option value="7">7</option>
                                        <option value="5">5</option>
                                        <option value="baby_fut">Baby fut</option>
                                    </x-ui.select>
                                </div>
                            </div>
                        @endif

                        @if ($tipo->permite_material_biblioteca)
                            <fieldset id="materialesBiblioteca" class="mt-md border-l-4 border-hairline pl-md">
                                <legend class="text-body-sm font-semibold text-ink">Material de la biblioteca</legend>
                                <div class="hidden gap-sm text-body-sm font-semibold text-muted md:grid md:grid-cols-[minmax(0,2fr)_repeat(2,minmax(0,1fr))]" aria-hidden="true">
                                    <span>Material</span><span>Títulos</span><span>Volúmenes</span>
                                </div>
                                @foreach ($materiales as $material)
                                    <div wire:key="material-{{ $material->id }}" class="grid grid-cols-2 gap-sm py-xs md:grid-cols-[minmax(0,2fr)_repeat(2,minmax(0,1fr))] md:items-center">
                                        <p class="col-span-2 text-body-md text-ink md:col-span-1">{{ $material->nombre }}</p>
                                        @foreach (['numeroTitulos' => 'Títulos', 'numeroVolumenes' => 'Volúmenes'] as $campo => $texto)
                                            <div>
                                                <label for="materialesBiblioteca.{{ $material->id }}.{{ $campo }}" class="{{ $etiqueta }} md:sr-only">{{ $texto }}<span class="sr-only"> — {{ $material->nombre }}</span></label>
                                                <x-ui.input id="materialesBiblioteca.{{ $material->id }}.{{ $campo }}" type="number" inputmode="numeric" min="0" wire:model.blur="materialesBiblioteca.{{ $material->id }}.{{ $campo }}" />
                                            </div>
                                        @endforeach
                                    </div>
                                @endforeach
                            </fieldset>
                        @endif
                    </div>
                @endforeach
            </x-ui.section>
            </x-ui.stepper-panel>
        @endforeach

        @if (count($categorias) > 0)
            <x-ui.stepper-panel :indice="$indice++" :total="$totalPasos" titulo="Sanitarios">
            <x-ui.section title="Sanitarios">
                @foreach ($categorias as $categoria)
                    <div wire:key="sanitario-{{ $categoria }}" class="rounded-md border border-hairline p-md md:rounded-none md:border-0 md:border-t md:px-0">
                        <p class="text-body-md font-semibold text-ink">{{ ucfirst(str_replace('_', ' ', $categoria)) }}</p>
                        <div class="mt-sm grid grid-cols-2 gap-sm md:grid-cols-4">
                            @foreach (array_filter([
                                'cantidadRetretes' => ['Retretes', 'numeric', null],
                                'cantidadMingitorios' => ['Mingitorios', 'numeric', null],
                                'cantidadLavabos' => ['Lavabos', 'numeric', null],
                                'superficieM2' => ['Superficie (m²)', 'decimal', '0.01'],
                                'cantidadBacinicas' => $categoria === 'alumnado_maternal' ? ['Bacinicas', 'numeric', null] : null,
                            ]) as $campo => [$texto, $modo, $paso])
                                <div>
                                    <label for="sanitarios.{{ $categoria }}.{{ $campo }}" class="{{ $etiqueta }}">{{ $texto }}<span class="sr-only"> — {{ str_replace('_', ' ', $categoria) }}</span></label>
                                    <x-ui.input id="sanitarios.{{ $categoria }}.{{ $campo }}" type="number" inputmode="{{ $modo }}" min="0" :step="$paso" wire:model.blur="sanitarios.{{ $categoria }}.{{ $campo }}" />
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </x-ui.section>
            </x-ui.stepper-panel>
        @endif

        <x-ui.stepper-panel :indice="$indice++" :total="$totalPasos" titulo="Aulas del nivel">
        <x-ui.section title="Aulas del nivel">
            <div class="grid grid-cols-1 gap-md sm:grid-cols-2">
                <x-ui.field id="numeroAulas" label="Número de aulas">
                    <x-ui.input type="number" inputmode="numeric" min="1" wire:model.blur="numeroAulas" />
                </x-ui.field>
                <x-ui.field id="superficieAulasM2" label="Superficie total (m²)">
                    <x-ui.input type="number" inputmode="decimal" step="0.01" min="0" unit="m²" wire:model.blur="superficieAulasM2" />
                </x-ui.field>
            </div>
        </x-ui.section>

        <x-ui.action-bar accion="guardar" :back-href="route('tramite.resumen', ['escuela' => $escuelaNivel->escuela_id])" />
        </x-ui.stepper-panel>
        </x-ui.stepper>
    </form>
</div>
