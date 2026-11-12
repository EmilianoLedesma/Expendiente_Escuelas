<div class="max-w-3xl">
    <x-ui.page-header :eyebrow="$encabezado" :title="$numero . '. ' . $nombre" :back-href="route('tramite.paso3-proyecto-educativo', ['escuelaNivel' => $escuelaNivel->id])" back-label="Presentación del Proyecto Educativo" />

    @if ($esPresentacion)

        {{-- La máscara es solo ayuda: escribe (442) 000-0000 y empuja ese valor a Livewire; el servidor normaliza y valida. Con +52/52 o más de 10 dígitos no recorta: deja el texto y el servidor explica el error. --}}
        <form novalidate wire:submit="{{ $paso === count($pasos) ? 'guardar' : 'siguiente' }}" x-data="{ mascara(v) { const d = v.replace(/\D/g, ''); if (/[^\d\s().-]/.test(v) || d.length > 10) return v; return d.replace(/^(\d{1,3})(\d{0,3})(\d{0,4})$/, (m, a, b, c) => (b ? '(' + a + ') ' + b : a) + (c ? '-' + c : '')); } }">
            <x-ui.error-summary />

            <ol class="mb-sm flex flex-wrap gap-sm" aria-label="Pasos de la sección">
                @foreach ($pasos as $n => $p)
                    <li @if ($n === $paso) aria-current="step" @endif class="flex min-h-11 items-center gap-xs rounded-md border px-sm text-body-sm {{ $n === $paso ? 'border-primary bg-surface-soft font-semibold text-ink' : 'border-hairline text-muted' }}">
                        <span class="tabular-nums">{{ $n }}.</span> {{ $p['nombre'] }}
                    </li>
                @endforeach
            </ol>
            <p class="mb-md text-body-sm text-muted">Paso {{ $paso }} de {{ count($pasos) }}</p>

            <div wire:key="paso-{{ $paso }}">

            @if ($paso === 1)
            <x-ui.section title="Datos Generales">
                <x-ui.field id="fecha_entrega" label="Fecha de entrega">
                    <x-ui.input type="date" wire:model.blur="fecha_entrega" min="1900-01-01" required />
                </x-ui.field>
                <x-ui.field id="nombre_institucion" label="Nombre de la institución">
                    <x-ui.input wire:model.blur="nombre_institucion" maxlength="255" required autocomplete="organization" placeholder="Nombre completo de la institución educativa" />
                </x-ui.field>
                <x-ui.field id="fecha_apertura" label="Fecha de apertura">
                    <x-ui.input type="date" wire:model.blur="fecha_apertura" min="1900-01-01" max="{{ now()->toDateString() }}" required />
                </x-ui.field>
                <x-ui.field id="domicilio_completo" label="Domicilio completo">
                    <x-ui.input wire:model.blur="domicilio_completo" maxlength="500" required autocomplete="street-address" placeholder="Calle, número, colonia, municipio, estado, C.P." />
                </x-ui.field>
                <x-ui.field id="representante_legal" label="Representante legal">
                    <x-ui.input wire:model.blur="representante_legal" maxlength="255" required autocomplete="name" placeholder="Nombre completo del representante" />
                </x-ui.field>
                <x-ui.field id="correo" label="Correo electrónico">
                    <x-ui.input type="email" wire:model.blur="correo" maxlength="255" required autocomplete="email" placeholder="correo@ejemplo.com" />
                </x-ui.field>
                <div class="grid gap-md sm:grid-cols-2">
                    <x-ui.field id="telefono" label="Teléfono">
                        <x-ui.input type="tel" inputmode="tel" maxlength="20" required autocomplete="tel-national" wire:model.blur="telefono" x-on:input="$el.value = mascara($el.value); $wire.set('telefono', $el.value, false)" placeholder="(442) 000-0000" />
                    </x-ui.field>
                    <x-ui.field id="celular" label="Celular" optional>
                        <x-ui.input type="tel" inputmode="tel" maxlength="20" autocomplete="tel-national" wire:model.blur="celular" x-on:input="$el.value = mascara($el.value); $wire.set('celular', $el.value, false)" placeholder="(442) 000-0000" />
                    </x-ui.field>
                </div>
                <x-ui.field id="pagina_web" label="Página web" optional>
                    <x-ui.input type="url" wire:model.blur="pagina_web" maxlength="255" placeholder="https://www.ejemplo.com" />
                </x-ui.field>
            </x-ui.section>

            @elseif ($paso === 2)
            <x-ui.section title="Modalidad, Ciclo Escolar y Duración">
                <x-ui.radio-group id="modalidad" legend="Modalidad" wire:model="modalidad" :opciones="['presencial' => 'Presencial', 'virtual' => 'Virtual', 'mixta' => 'Mixta']" />

                <div>
                    <x-ui.radio-group id="ciclo_escolar" legend="Ciclo escolar" wire:model.live="ciclo_escolar" :opciones="['semestral' => 'Semestral', 'cuatrimestral' => 'Cuatrimestral', 'trimestral' => 'Trimestral', 'bimestral' => 'Bimestral', 'mensual' => 'Mensual', 'otro' => 'Otro']" />
                    @if ($ciclo_escolar === 'otro')
                        <x-ui.field id="ciclo_escolar_otro" label="Especifica el ciclo escolar" class="mt-sm">
                            <x-ui.input wire:model.blur="ciclo_escolar_otro" maxlength="100" required />
                        </x-ui.field>
                    @endif
                </div>

                <div>
                    <x-ui.radio-group id="duracion" legend="Duración" wire:model.live="duracion" :opciones="['3_anios' => '3 años', '2_anios' => '2 años', 'otro' => 'Otro']" />
                    @if ($duracion === 'otro')
                        <x-ui.field id="duracion_otro" label="Especifica la duración" class="mt-sm">
                            <x-ui.input wire:model.blur="duracion_otro" maxlength="100" required />
                        </x-ui.field>
                    @endif
                </div>
            </x-ui.section>

            @else
            <x-ui.section title="Síntesis de los Resultados del Estudio de Factibilidad">
                <x-ui.textarea-palabras id="sintesis_factibilidad" label="Síntesis (500 a 1000 palabras)" :min="500" :max="1000" :rows="14" />
            </x-ui.section>

            @endif
            </div>

            <div class="mt-xl flex flex-col-reverse gap-md border-t border-hairline bg-canvas py-md sm:sticky sm:bottom-0 sm:z-10 sm:flex-row sm:items-center sm:justify-between sm:bg-canvas/95 sm:backdrop-blur">
                @if ($paso > 1)
                    <x-ui.button-secondary type="button" wire:click="anterior">Anterior</x-ui.button-secondary>
                @else
                    <a href="{{ route('tramite.paso3-proyecto-educativo', ['escuelaNivel' => $escuelaNivel->id]) }}" class="inline-flex min-h-11 items-center justify-center text-body-md font-semibold text-primary">Volver al índice</a>
                @endif

                <x-ui.button-primary type="submit" wire:loading.attr="disabled">
                    <span wire:loading.remove>{{ $paso === count($pasos) ? 'Guardar' : 'Siguiente' }}</span>
                    <span wire:loading>Guardando…</span>
                </x-ui.button-primary>
            </div>
        </form>
    @elseif ($enfoque)
        {{-- Sección 4: un solo campo por pantalla. Siguiente valida ese campo y no escribe; Guardar (último campo) valida todo y guarda todo o nada. --}}
        @php
            $grupos = [1 => 'Propuesta Global del Proyecto', 2 => 'Apropiación del Enfoque Pedagógico-Didáctico', 3 => 'Estrategias y Actividades Didácticas Principales'];
            $esEstrategia = $enfoque['lista'] === 'estrategias_centrales';
        @endphp
        <form novalidate wire:submit="{{ $enfoque['ultima'] ? 'guardar' : 'siguienteEnfoque' }}">
            <x-ui.error-summary />

            <ol class="mb-sm flex flex-wrap gap-sm" aria-label="Grupos de la sección">
                @foreach ($grupos as $n => $titulo)
                    <li @if ($n === $enfoque['grupo']) aria-current="step" @endif class="flex min-h-11 items-center gap-xs rounded-md border px-sm text-body-sm {{ $n === $enfoque['grupo'] ? 'border-primary bg-surface-soft font-semibold text-ink' : 'border-hairline text-muted' }}">
                        <span class="tabular-nums">{{ $n }}.</span> {{ $titulo }}
                    </li>
                @endforeach
            </ol>
            <p class="mb-md text-body-sm text-muted">Campo {{ $enfoque['numero'] }} de {{ $enfoque['total'] }}</p>

            <div wire:key="enfoque-{{ $enfoque['clave'] }}">
                @if ($enfoque['clave'] === 'propuesta_global')
                    <x-ui.section title="Propuesta Global del Proyecto">
                        <x-ui.textarea-palabras id="propuesta_global" label="Propuesta Global del Proyecto Educativo (100 a 200 palabras)" :min="100" :max="200" placeholder="Describa de manera integral la propuesta educativa del proyecto..." />
                    </x-ui.section>
                @elseif ($enfoque['clave'] === 'apropiacion_enfoque')
                    <x-ui.section title="Apropiación del Enfoque Pedagógico-Didáctico">
                        <x-ui.textarea-palabras id="apropiacion_enfoque" label="Apropiación del Enfoque Pedagógico-Didáctico Constructivista-Humanista (450 a 600 palabras)" :min="450" :max="600" :rows="14" placeholder="Explique cómo la institución se apropia del enfoque constructivista-humanista en su práctica educativa..." />
                    </x-ui.section>
                @else
                    <x-ui.section title="Estrategias y Actividades Didácticas Principales (2 a 5 ejemplos)">
                        <p class="text-body-sm text-muted">{{ $esEstrategia ? 'Estrategias centrales' : 'Actividades sustantivas' }}: ejemplo {{ $enfoque['ejemplo'] }} de {{ $enfoque['ejemplos'] }}</p>
                        <x-ui.textarea-palabras :id="$enfoque['clave']" :label="($esEstrategia ? 'Estrategias centrales' : 'Actividades sustantivas') . ' (20 a 80 palabras)'" :min="20" :max="80" :rows="6" :placeholder="$esEstrategia ? 'Describa las estrategias didácticas principales que se implementarán...' : 'Describa las actividades sustantivas que darán soporte a las estrategias...'" />
                        <div class="flex flex-wrap gap-sm">
                            @if ($enfoque['puedeAgregar'])
                                <x-ui.button-secondary type="button" wire:click="agregarEjemplo">Añadir otro ejemplo</x-ui.button-secondary>
                            @endif
                            @if ($enfoque['puedeQuitar'])
                                <x-ui.button-secondary type="button" wire:click="quitarEjemplo">Quitar este ejemplo</x-ui.button-secondary>
                            @endif
                        </div>
                    </x-ui.section>
                @endif
            </div>

            <div class="mt-xl flex flex-col-reverse gap-md border-t border-hairline bg-canvas py-md sm:sticky sm:bottom-0 sm:z-10 sm:flex-row sm:items-center sm:justify-between sm:bg-canvas/95 sm:backdrop-blur">
                @if ($enfoque['numero'] > 1)
                    <x-ui.button-secondary type="button" wire:click="anteriorEnfoque">Anterior</x-ui.button-secondary>
                @else
                    <a href="{{ route('tramite.paso3-proyecto-educativo', ['escuelaNivel' => $escuelaNivel->id]) }}" class="inline-flex min-h-11 items-center justify-center text-body-md font-semibold text-primary">Volver al índice</a>
                @endif

                <x-ui.button-primary type="submit" wire:loading.attr="disabled">
                    <span wire:loading.remove>{{ $enfoque['ultima'] ? 'Guardar' : 'Siguiente' }}</span>
                    <span wire:loading>Guardando…</span>
                </x-ui.button-primary>
            </div>
        </form>
    @elseif ($evaluacion)
        {{-- Sección 5: un bloque por pantalla. Siguiente valida los campos del bloque y no escribe; Guardar (último bloque) valida todo y guarda todo o nada. --}}
        @php
            $campos = [
                'evaluacion_diagnostica' => ['Evaluación Diagnóstica', 'Describa el propósito, instrumentos y momentos de la evaluación diagnóstica...'],
                'evaluacion_formativa' => ['Evaluación Formativa', 'Describa cómo se implementará la evaluación formativa durante el proceso de enseñanza-aprendizaje...'],
                'evaluacion_sumativa' => ['Evaluación Sumativa', 'Describa los criterios y mecanismos de la evaluación sumativa...'],
                'heteroevaluacion' => ['Heteroevaluación', 'Describa los procesos de evaluación del docente hacia el alumno...'],
                'coevaluacion' => ['Coevaluación', 'Describa los procesos de evaluación entre pares...'],
                'autoevaluacion' => ['Autoevaluación', 'Describa los procesos de autoevaluación del estudiante...'],
                'elementos_porcentajes' => ['Elementos y porcentajes', 'Detalle los componentes que integran la calificación y sus porcentajes correspondientes...'],
            ];
        @endphp
        <form novalidate wire:submit="{{ $evaluacion['ultimo'] ? 'guardar' : 'siguienteEvaluacion' }}">
            <x-ui.error-summary />

            <ol class="mb-sm flex flex-wrap gap-sm" aria-label="Bloques de la sección">
                @foreach (\App\Application\ProyectoEducativo\ReglasEvaluacionProyecto::BLOQUES as $n => $b)
                    <li @if ($n === $evaluacion['bloque']) aria-current="step" @endif class="flex min-h-11 items-center gap-xs rounded-md border px-sm text-body-sm {{ $n === $evaluacion['bloque'] ? 'border-primary bg-surface-soft font-semibold text-ink' : 'border-hairline text-muted' }}">
                        <span class="tabular-nums">{{ $n }}.</span> {{ \Illuminate\Support\Str::before($b['titulo'], ' (') }}
                    </li>
                @endforeach
            </ol>
            <p class="mb-md text-body-sm text-muted">Bloque {{ $evaluacion['bloque'] }} de {{ $evaluacion['total'] }}</p>

            <div wire:key="evaluacion-{{ $evaluacion['bloque'] }}">
                <x-ui.section :title="$evaluacion['titulo']">
                    @foreach ($evaluacion['campos'] as $campo)
                        @php [$min, $max] = \App\Application\ProyectoEducativo\ReglasEvaluacionProyecto::RANGOS[$campo]; @endphp
                        <x-ui.textarea-palabras :id="$campo" :label="$campos[$campo][0]" :min="$min" :max="$max" :rows="$max > 300 ? 12 : 8" :placeholder="$campos[$campo][1]" />
                    @endforeach
                </x-ui.section>
            </div>

            <div class="mt-xl flex flex-col-reverse gap-md border-t border-hairline bg-canvas py-md sm:sticky sm:bottom-0 sm:z-10 sm:flex-row sm:items-center sm:justify-between sm:bg-canvas/95 sm:backdrop-blur">
                @if ($evaluacion['bloque'] > 1)
                    <x-ui.button-secondary type="button" wire:click="anteriorEvaluacion">Anterior</x-ui.button-secondary>
                @else
                    <a href="{{ route('tramite.paso3-proyecto-educativo', ['escuelaNivel' => $escuelaNivel->id]) }}" class="inline-flex min-h-11 items-center justify-center text-body-md font-semibold text-primary">Volver al índice</a>
                @endif

                <x-ui.button-primary type="submit" wire:loading.attr="disabled">
                    <span wire:loading.remove>{{ $evaluacion['ultimo'] ? 'Guardar' : 'Siguiente' }}</span>
                    <span wire:loading>Guardando…</span>
                </x-ui.button-primary>
            </div>
        </form>
    @elseif ($esTexto)
        <form novalidate wire:submit="guardar">
            <x-ui.error-summary />

            @if ($seccion === \App\Application\ProyectoEducativo\SeccionesProyectoEducativo::filosofia())
                <x-ui.section :title="$nombre">
                    <x-ui.textarea-palabras id="mision" label="Misión (50 a 150 palabras)" :min="50" :max="150" placeholder="Describa la razón de ser de la institución y su compromiso con la comunidad educativa..." />
                    <x-ui.textarea-palabras id="vision" label="Visión (50 a 150 palabras)" :min="50" :max="150" placeholder="Describa la proyección a futuro de la institución y las metas que aspira alcanzar..." />
                    <x-ui.textarea-palabras id="valores" label="Valores (50 a 250 palabras)" :min="50" :max="250" placeholder="Enumere y describa los valores fundamentales que guían el quehacer institucional..." />
                </x-ui.section>
            @else
                <x-ui.section :title="$nombre">
                    <x-ui.textarea-palabras id="objetivo_general" label="Objetivo General (50 a 100 palabras)" :min="50" :max="100" placeholder="Redacte el objetivo principal que orienta el proyecto educativo de la institución..." />
                    <x-ui.textarea-palabras id="objetivos_especificos" label="Objetivos Específicos (100 a 250 palabras)" :min="100" :max="250" placeholder="Desglose los objetivos puntuales derivados del objetivo general..." />
                </x-ui.section>
            @endif

            <x-ui.action-bar accion="guardar" label="Guardar" :back-href="route('tramite.paso3-proyecto-educativo', ['escuelaNivel' => $escuelaNivel->id])" back-label="Volver al índice" />
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
