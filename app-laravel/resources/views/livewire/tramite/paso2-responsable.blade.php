<div class="max-w-3xl">
    @if ($fase === 'responsable')
        <x-ui.page-header :eyebrow="$encabezado" title="Responsable legal">
            <x-slot:intro>Persona física o moral que solicita la incorporación, su domicilio para notificaciones y la terna de nombres propuestos para la escuela.</x-slot:intro>
        </x-ui.page-header>

        <form novalidate wire:submit="guardarResponsable">
            <x-ui.error-summary />

            <x-ui.radio-group
                id="tipoPersona"
                legend="Tipo de persona"
                wire:model.live="tipoPersona"
                :opciones="['fisica' => 'Persona física', 'fisica_con_gestor' => 'Persona física con gestor', 'moral' => 'Persona moral']"
            />

            @if ($tipoPersona !== 'moral')
                <x-ui.section title="Datos personales">
                    <x-ui.field id="personaFisicaForm.nombre" label="Nombre completo">
                        <x-ui.input maxlength="200" wire:model.blur="personaFisicaForm.nombre" autocomplete="name" />
                    </x-ui.field>
                    <x-ui.field id="personaFisicaForm.fechaNacimiento" label="Fecha de nacimiento" optional>
                        <x-ui.input type="date" wire:model.blur="personaFisicaForm.fechaNacimiento" />
                    </x-ui.field>
                    <x-ui.field id="personaFisicaForm.rfc" label="RFC" optional>
                        <x-ui.input maxlength="13" autocapitalize="characters" spellcheck="false" wire:model.blur="personaFisicaForm.rfc" class="uppercase" />
                    </x-ui.field>
                    <x-ui.field id="personaFisicaForm.curp" label="CURP" optional>
                        <x-ui.input maxlength="18" autocapitalize="characters" spellcheck="false" wire:model.blur="personaFisicaForm.curp" class="uppercase" />
                    </x-ui.field>
                </x-ui.section>

                @if ($tipoPersona === 'fisica_con_gestor')
                    <x-ui.section title="Datos del gestor">
                        <x-ui.field id="gestorForm.nombre" label="Nombre del gestor">
                            <x-ui.input maxlength="200" wire:model.blur="gestorForm.nombre" />
                        </x-ui.field>
                        <x-ui.field id="gestorForm.curp" label="CURP del gestor" hint="La credencial de elector y la Constancia de CURP que subas en Documentos deben ser las del gestor.">
                            <x-ui.input wire:model.blur="gestorForm.curp" class="uppercase" maxlength="18" />
                        </x-ui.field>
                        <x-ui.field id="gestorForm.numeroPoder" label="Número de poder" optional>
                            <x-ui.input maxlength="50" wire:model.blur="gestorForm.numeroPoder" />
                        </x-ui.field>
                        <x-ui.field id="gestorForm.notarioNombre" label="Nombre del notario" optional>
                            <x-ui.input maxlength="150" wire:model.blur="gestorForm.notarioNombre" />
                        </x-ui.field>
                        <x-ui.field id="gestorForm.notarioNumero" label="Número de notaría" optional>
                            <x-ui.input maxlength="20" wire:model.blur="gestorForm.notarioNumero" />
                        </x-ui.field>
                        <x-ui.field id="gestorForm.fechaPoder" label="Fecha del poder" optional>
                            <x-ui.input type="date" wire:model.blur="gestorForm.fechaPoder" />
                        </x-ui.field>
                    </x-ui.section>
                @endif
            @else
                <x-ui.section title="Datos de la persona moral">
                    <x-ui.field id="personaMoralForm.razonSocial" label="Razón social">
                        <x-ui.input maxlength="200" wire:model.blur="personaMoralForm.razonSocial" autocomplete="organization" />
                    </x-ui.field>
                    <x-ui.field id="personaMoralForm.nombreRepresentanteLegal" label="Representante legal">
                        <x-ui.input maxlength="200" wire:model.blur="personaMoralForm.nombreRepresentanteLegal" />
                    </x-ui.field>
                </x-ui.section>

                <x-ui.section title="Datos notariales">
                    <x-ui.field id="personaMoralForm.numeroEscrituraConstitutiva" label="Número de escritura constitutiva" optional>
                        <x-ui.input maxlength="50" wire:model.blur="personaMoralForm.numeroEscrituraConstitutiva" />
                    </x-ui.field>
                    <x-ui.field id="personaMoralForm.fechaEscrituraConstitutiva" label="Fecha de escritura constitutiva" optional>
                        <x-ui.input type="date" wire:model.blur="personaMoralForm.fechaEscrituraConstitutiva" />
                    </x-ui.field>
                    <x-ui.field id="personaMoralForm.notarioNombre" label="Nombre del notario" optional>
                        <x-ui.input maxlength="150" wire:model.blur="personaMoralForm.notarioNombre" />
                    </x-ui.field>
                    <x-ui.field id="personaMoralForm.notarioNumero" label="Número de notaría" optional>
                        <x-ui.input maxlength="20" wire:model.blur="personaMoralForm.notarioNumero" />
                    </x-ui.field>
                    <x-ui.field id="personaMoralForm.notarioCiudad" label="Ciudad de la notaría" optional>
                        <x-ui.input maxlength="100" wire:model.blur="personaMoralForm.notarioCiudad" />
                    </x-ui.field>
                    <x-ui.field id="personaMoralForm.folioRegistroPublico" label="Folio del Registro Público" optional>
                        <x-ui.input maxlength="50" wire:model.blur="personaMoralForm.folioRegistroPublico" />
                    </x-ui.field>
                    <x-ui.field id="personaMoralForm.fechaInscripcionRpp" label="Fecha de inscripción en el Registro Público" optional>
                        <x-ui.input type="date" wire:model.blur="personaMoralForm.fechaInscripcionRpp" />
                    </x-ui.field>
                </x-ui.section>
            @endif

            <x-ui.section title="Domicilio para notificaciones">
                <x-ui.field id="domicilioNotificaciones" label="Domicilio para notificaciones">
                    <x-ui.input maxlength="250" wire:model.blur="domicilioNotificaciones" autocomplete="street-address" />
                </x-ui.field>
                <x-ui.field id="personaAutorizadaRecoger" label="Persona autorizada para recoger notificaciones" optional>
                    <x-ui.input maxlength="200" wire:model.blur="personaAutorizadaRecoger" />
                </x-ui.field>
            </x-ui.section>

            <x-ui.section title="Terna de nombres">
                @foreach ([1, 2, 3] as $n)
                    <x-ui.field id="nombrePropuesto{{ $n }}" label="Propuesta de nombre {{ $n }}">
                        <x-ui.input maxlength="200" wire:model.blur="nombrePropuesto{{ $n }}" />
                    </x-ui.field>
                @endforeach
            </x-ui.section>

            <x-ui.action-bar accion="guardarResponsable" :back-href="route('tramite.resumen', ['escuela' => $escuela->id])" />
        </form>
    @endif

    @if ($fase === 'niveles')
        <x-ui.page-header :eyebrow="$encabezado" title="Niveles educativos">
            <x-slot:intro>Selecciona los niveles de Educación Básica que solicitas incorporar en este plantel.</x-slot:intro>
        </x-ui.page-header>

        @if ($responsableCapturado !== [])
            <x-ui.section title="Responsable legal (ya registrado)">
                <p class="text-body-sm text-muted">Estos datos ya se capturaron. Cambiarlos requiere autorización previa de la Dirección de Educación.</p>
                <x-ui.summary-list :filas="[
                    'Tipo' => ['fisica' => 'Persona física', 'fisica_con_gestor' => 'Persona física con gestor', 'moral' => 'Persona moral'][$responsableCapturado['tipo']] ?? $responsableCapturado['tipo'],
                    'Nombre' => $responsableCapturado['nombre'],
                    'Domicilio para notificaciones' => $responsableCapturado['domicilio'],
                ]" />
            </x-ui.section>
        @endif

        <form novalidate wire:submit="guardarNiveles" class="mt-xl">
            <x-ui.error-summary />

            <fieldset id="nivelesSeleccionados" @error('nivelesSeleccionados') aria-describedby="nivelesSeleccionados-error" @enderror>
                <legend class="text-body-md font-semibold text-ink">Niveles educativos</legend>

                @error('nivelesSeleccionados')
                    <p id="nivelesSeleccionados-error" class="mt-xxs flex items-start gap-xxs text-body-sm font-semibold text-error-ink">
                        <x-ui.icon nombre="exclamation-circle" class="mt-px h-5 w-5" />
                        <span><span class="sr-only">Error:</span> {{ $message }}</span>
                    </p>
                @enderror

                <div class="mt-xs space-y-xxs">
                    @foreach ($nivelesDisponibles as $nivel)
                        <x-ui.checkbox id="nivel-{{ $nivel->id }}" wire:model="nivelesSeleccionados" value="{{ $nivel->id }}">
                            <x-tramite.nivel :clave="$nivel->clave" como="franja">{{ $nivel->nombre }}</x-tramite.nivel>
                        </x-ui.checkbox>
                    @endforeach
                </div>
            </fieldset>

            <x-ui.action-bar accion="guardarNiveles" :back-href="route('tramite.resumen', ['escuela' => $escuela->id])" />
        </form>
    @endif
</div>
