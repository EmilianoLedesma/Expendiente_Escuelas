<div class="max-w-3xl">
    <x-ui.page-header :eyebrow="$encabezado" :title="$numero . '. ' . $nombre" :back-href="route('tramite.paso3-proyecto-educativo', ['escuelaNivel' => $escuelaNivel->id])" back-label="Presentación del Proyecto Educativo" />

    @if ($esPresentacion)

        {{-- La máscara es solo ayuda: escribe (442) 000-0000 y empuja ese valor a Livewire; el servidor normaliza y valida. Con +52/52 o más de 10 dígitos no recorta: deja el texto y el servidor explica el error. --}}
        <form novalidate wire:submit="guardar" x-data="{ mascara(v) { const d = v.replace(/\D/g, ''); if (/[^\d\s().-]/.test(v) || d.length > 10) return v; return d.replace(/^(\d{1,3})(\d{0,3})(\d{0,4})$/, (m, a, b, c) => (b ? '(' + a + ') ' + b : a) + (c ? '-' + c : '')); } }">
            <x-ui.error-summary />

            <x-ui.section title="Datos Generales">
                <x-ui.field id="fecha_entrega" label="Fecha de entrega">
                    <x-ui.input type="date" wire:model="fecha_entrega" />
                </x-ui.field>
                <x-ui.field id="nombre_institucion" label="Nombre de la institución">
                    <x-ui.input wire:model="nombre_institucion" maxlength="255" placeholder="Nombre completo de la institución educativa" />
                </x-ui.field>
                <x-ui.field id="fecha_apertura" label="Fecha de apertura">
                    <x-ui.input type="date" wire:model="fecha_apertura" max="{{ now()->toDateString() }}" />
                </x-ui.field>
                <x-ui.field id="domicilio_completo" label="Domicilio completo">
                    <x-ui.input wire:model="domicilio_completo" maxlength="500" placeholder="Calle, número, colonia, municipio, estado, C.P." />
                </x-ui.field>
                <x-ui.field id="representante_legal" label="Representante legal">
                    <x-ui.input wire:model="representante_legal" maxlength="255" placeholder="Nombre completo del representante" />
                </x-ui.field>
                <x-ui.field id="correo" label="Correo electrónico">
                    <x-ui.input type="email" wire:model="correo" maxlength="255" placeholder="correo@ejemplo.com" />
                </x-ui.field>
                <div class="grid gap-md sm:grid-cols-2">
                    <x-ui.field id="telefono" label="Teléfono">
                        <x-ui.input type="tel" inputmode="numeric" wire:model="telefono" x-on:input="$el.value = mascara($el.value); $wire.set('telefono', $el.value, false)" placeholder="(442) 000-0000" />
                    </x-ui.field>
                    <x-ui.field id="celular" label="Celular" optional>
                        <x-ui.input type="tel" inputmode="numeric" wire:model="celular" x-on:input="$el.value = mascara($el.value); $wire.set('celular', $el.value, false)" placeholder="(442) 000-0000" />
                    </x-ui.field>
                </div>
                <x-ui.field id="pagina_web" label="Página web" optional>
                    <x-ui.input type="url" wire:model="pagina_web" maxlength="255" placeholder="https://www.ejemplo.com" />
                </x-ui.field>
            </x-ui.section>

            <x-ui.section title="Modalidad, Ciclo Escolar y Duración">
                <x-ui.radio-group id="modalidad" legend="Modalidad" wire:model="modalidad" :opciones="['presencial' => 'Presencial', 'virtual' => 'Virtual', 'mixta' => 'Mixta']" />

                <div>
                    <x-ui.radio-group id="ciclo_escolar" legend="Ciclo escolar" wire:model.live="ciclo_escolar" :opciones="['semestral' => 'Semestral', 'cuatrimestral' => 'Cuatrimestral', 'trimestral' => 'Trimestral', 'bimestral' => 'Bimestral', 'mensual' => 'Mensual', 'otro' => 'Otro']" />
                    @if ($ciclo_escolar === 'otro')
                        <x-ui.field id="ciclo_escolar_otro" label="Especifica el ciclo escolar" class="mt-sm">
                            <x-ui.input wire:model="ciclo_escolar_otro" maxlength="100" />
                        </x-ui.field>
                    @endif
                </div>

                <div>
                    <x-ui.radio-group id="duracion" legend="Duración" wire:model.live="duracion" :opciones="['3_anios' => '3 años', '2_anios' => '2 años', 'otro' => 'Otro']" />
                    @if ($duracion === 'otro')
                        <x-ui.field id="duracion_otro" label="Especifica la duración" class="mt-sm">
                            <x-ui.input wire:model="duracion_otro" maxlength="100" />
                        </x-ui.field>
                    @endif
                </div>
            </x-ui.section>

            <x-ui.section title="Síntesis de los Resultados del Estudio de Factibilidad">
                {{-- El contador es solo una ayuda; el servidor cuenta de nuevo al guardar. --}}
                <div x-data="{ texto: $wire.entangle('sintesis_factibilidad') }">
                    <x-ui.field id="sintesis_factibilidad" label="Síntesis (500 a 1000 palabras)">
                        <x-ui.textarea rows="14" x-model="texto" />
                    </x-ui.field>
                    <p class="mt-xs text-body-sm text-muted" aria-live="polite">
                        <span x-text="(texto || '').trim().split(/\s+/).filter(Boolean).length">0</span> palabras
                    </p>
                </div>
            </x-ui.section>

            <x-ui.action-bar accion="guardar" label="Guardar sección" :back-href="route('tramite.paso3-proyecto-educativo', ['escuelaNivel' => $escuelaNivel->id])" back-label="Volver al índice" />
        </form>
    @else
        <p class="rounded-lg border border-dashed border-hairline p-lg text-body-md text-muted">Contenido por definir</p>
    @endif

    <nav aria-label="Secciones del proyecto educativo" class="mt-lg flex flex-wrap items-center justify-between gap-md">
        @if ($anterior)
            <a href="{{ route('tramite.paso3-proyecto-educativo-seccion', ['escuelaNivel' => $escuelaNivel->id, 'seccion' => $anterior['slug']]) }}" class="inline-flex min-h-11 items-center font-semibold text-primary">
                Anterior<span class="sr-only">: {{ $anterior['nombre'] }}</span>
            </a>
        @endif
        <a href="{{ route('tramite.paso3-proyecto-educativo', ['escuelaNivel' => $escuelaNivel->id]) }}" class="inline-flex min-h-11 items-center font-semibold text-primary">Índice de secciones</a>
        @if ($siguiente)
            <a href="{{ route('tramite.paso3-proyecto-educativo-seccion', ['escuelaNivel' => $escuelaNivel->id, 'seccion' => $siguiente['slug']]) }}" class="inline-flex min-h-11 items-center font-semibold text-primary">
                Siguiente<span class="sr-only">: {{ $siguiente['nombre'] }}</span>
            </a>
        @endif
    </nav>
</div>
