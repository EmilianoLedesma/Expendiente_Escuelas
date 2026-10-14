<?php

namespace App\Livewire\Tramite;

use App\Application\Captura\ReglasCaptura;
use App\Application\EscuelaNiveles\RegistrarNivelesSeleccionados;
use App\Application\EscuelaNiveles\ResumenEliminacionNivel;
use App\Application\Excepciones\DatosInvalidos;
use App\Application\Excepciones\PrecondicionIncumplida;
use App\Application\ResponsableLegal\DTO\DatosResponsableLegal;
use App\Application\ResponsableLegal\RegistrarResponsableLegal;
use App\Application\Tramite\EstadoPaso2;
use App\Application\Tramite\ResumenTramite;
use App\Livewire\Concerns\NotificaToast;
use App\Livewire\Concerns\ValidaEnConjunto;
use App\Livewire\Forms\GestorForm;
use App\Livewire\Forms\PersonaFisicaForm;
use App\Livewire\Forms\PersonaMoralForm;
use App\Models\Escuela;
use App\Models\Gestor;
use App\Models\NivelEducativo;
use App\Models\PersonaFisica;
use App\Models\PersonaMoral;
use App\Models\ResponsableLegal;
use App\Models\TernaNombre;
use Closure;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Presentación pura: dos fases ('responsable', 'niveles') sobre una sola
 * visita de /tramite/paso2/{escuela}. Ninguna regla de negocio ni acceso
 * a Eloquent de escritura vive aquí (ADR-001) — RegistrarResponsableLegal
 * y RegistrarNivelesSeleccionados son las únicas escrituras.
 * Desde WS-7b-1 el responsable se edita hasta el envío: con responsable la
 * página nunca redirige; `?seccion=responsable|niveles` elige la fase.
 */
#[Layout('layouts.tramite')]
class Paso2Responsable extends Component
{
    use NotificaToast;
    use ValidaEnConjunto;

    public Escuela $escuela;

    /** Estado del servidor, no del cliente: un $set('fase') forjado saltaba a 'niveles' (WS-1.2). */
    #[Locked]
    public string $fase = 'responsable';

    public string $tipoPersona = 'fisica';

    public string $domicilioNotificaciones = '';

    public string $personaAutorizadaRecoger = '';

    public string $nombrePropuesto1 = '';

    public string $nombrePropuesto2 = '';

    public string $nombrePropuesto3 = '';

    public PersonaFisicaForm $personaFisicaForm;

    public PersonaMoralForm $personaMoralForm;

    public GestorForm $gestorForm;

    public array $nivelesSeleccionados = [];

    /** WS-7b-1: niveles ya guardados (nivel_educativo_id); la diferencia con la selección es agregar/quitar. @var list<int> */
    #[Locked]
    public array $nivelesGuardados = [];

    /** Conteos a confirmar antes de quitar niveles con datos (ResumenEliminacionNivel). @var array<int, array<string, int>> */
    #[Locked]
    public array $porEliminar = [];

    /** Cambia con cada apertura del diálogo para que Alpine lo vuelva a abrir. */
    #[Locked]
    public int $confirmaciones = 0;

    /** WS-7b-1: `?seccion=responsable|niveles` (enlaces del hub y del resumen); sin él, mount() elige. */
    #[Url]
    public ?string $seccion = null;

    /** Ya existe responsable: guardar es una edición y no avanza el asistente. */
    #[Locked]
    public bool $editando = false;

    #[Locked]
    public ?string $tipoPersonaGuardado = null;

    /** D2: tras un cambio de tipo, aviso de revisar documentos. */
    public bool $avisoCambioTipo = false;

    /**
     * Resumen de solo lectura del responsable en la fase 'niveles', con enlace para editarlo.
     *
     * @var array{}|array{tipo: string, nombre: ?string, gestor: ?string, domicilio: ?string, autorizada: ?string, terna: list<string>}
     */
    public array $responsableCapturado = [];

    public function mount(Escuela $escuela, EstadoPaso2 $estadoPaso2): void
    {
        $this->escuela = $escuela;
        $responsable = ResponsableLegal::where('escuela_id', $escuela->id)->first();

        // Sin responsable (EstadoPaso2::RESPONSABLE), el formulario vacío. Con responsable
        // (WS-7b-1) nunca se redirige: el formulario prellenado es alcanzable también con
        // documentos pendientes o vencidos y con niveles ya elegidos.
        if ($responsable === null) {
            $this->fase = 'responsable';

            return;
        }

        $this->prellenar($responsable);
        $this->nivelesGuardados = $escuela->escuelaNiveles()->orderBy('id')->pluck('nivel_educativo_id')->map(fn ($id) => (int) $id)->all();
        $this->nivelesSeleccionados = $this->nivelesGuardados;
        $this->responsableCapturado = $this->resumenResponsable($responsable);
        $sinNiveles = ! $escuela->escuelaNiveles()->exists();

        $this->fase = match (true) {
            $this->seccion === 'niveles' => 'niveles',
            $this->seccion === 'responsable' => 'responsable',
            // Primera vez con Paso 2 completo: el siguiente paso natural es elegir niveles.
            $sinNiveles && $estadoPaso2->etapaFaltante($escuela->id) === null => 'niveles',
            default => 'responsable',
        };
    }

    /** Lectura de presentación (ADR-006 §2): lo ya capturado, en los campos del formulario. */
    private function prellenar(ResponsableLegal $responsable): void
    {
        $this->editando = true;
        $this->tipoPersona = $responsable->tipo_persona;
        $this->tipoPersonaGuardado = $responsable->tipo_persona;
        $this->domicilioNotificaciones = (string) $responsable->domicilio_notificaciones;
        $this->personaAutorizadaRecoger = (string) $responsable->persona_autorizada_recoger;

        $terna = TernaNombre::where('escuela_id', $responsable->escuela_id)->pluck('nombre_propuesto', 'numero_propuesta');
        $this->nombrePropuesto1 = (string) ($terna[1] ?? '');
        $this->nombrePropuesto2 = (string) ($terna[2] ?? '');
        $this->nombrePropuesto3 = (string) ($terna[3] ?? '');

        $fisica = PersonaFisica::find($responsable->id);
        if ($fisica !== null) {
            $this->personaFisicaForm->nombre = (string) $fisica->nombre;
            $this->personaFisicaForm->fechaNacimiento = (string) $fisica->fecha_nacimiento;
            $this->personaFisicaForm->rfc = (string) $fisica->rfc;
            $this->personaFisicaForm->curp = (string) $fisica->curp;
        }

        $gestor = Gestor::find($responsable->id);
        if ($gestor !== null) {
            $this->gestorForm->nombre = (string) $gestor->nombre;
            $this->gestorForm->numeroPoder = (string) $gestor->numero_poder;
            $this->gestorForm->notarioNombre = (string) $gestor->notario_nombre;
            $this->gestorForm->notarioNumero = (string) $gestor->notario_numero;
            $this->gestorForm->fechaPoder = (string) $gestor->fecha_poder;
            $this->gestorForm->curp = (string) $gestor->curp;
        }

        $moral = PersonaMoral::find($responsable->id);
        if ($moral !== null) {
            $this->personaMoralForm->razonSocial = (string) $moral->razon_social;
            $this->personaMoralForm->numeroEscrituraConstitutiva = (string) $moral->numero_escritura_constitutiva;
            $this->personaMoralForm->fechaEscrituraConstitutiva = (string) $moral->fecha_escritura_constitutiva;
            $this->personaMoralForm->notarioNombre = (string) $moral->notario_nombre;
            $this->personaMoralForm->notarioNumero = (string) $moral->notario_numero;
            $this->personaMoralForm->notarioCiudad = (string) $moral->notario_ciudad;
            $this->personaMoralForm->folioRegistroPublico = (string) $moral->folio_registro_publico;
            $this->personaMoralForm->fechaInscripcionRpp = (string) $moral->fecha_inscripcion_rpp;
            $this->personaMoralForm->nombreRepresentanteLegal = (string) $moral->nombre_representante_legal;
        }
    }

    /** @return array{tipo: string, nombre: ?string, gestor: ?string, domicilio: ?string, autorizada: ?string, terna: list<string>} */
    private function resumenResponsable(ResponsableLegal $responsableLegal): array
    {
        $nombre = match ($responsableLegal->tipo_persona) {
            'fisica', 'fisica_con_gestor' => PersonaFisica::find($responsableLegal->id)?->nombre,
            'moral' => PersonaMoral::find($responsableLegal->id)?->razon_social,
            default => null,
        };

        return [
            'tipo' => $responsableLegal->tipo_persona,
            'nombre' => $nombre,
            'gestor' => $responsableLegal->tipo_persona === 'fisica_con_gestor' ? Gestor::find($responsableLegal->id)?->nombre : null,
            'domicilio' => $responsableLegal->domicilio_notificaciones,
            'autorizada' => $responsableLegal->persona_autorizada_recoger,
            'terna' => TernaNombre::where('escuela_id', $responsableLegal->escuela_id)->orderBy('numero_propuesta')->pluck('nombre_propuesto')->map(fn ($n) => (string) $n)->all(),
        ];
    }

    /**
     * Reglas de las dos fases juntas: el hook de captura
     * (App\Livewire\Hooks\LimpiarYValidarAlCapturar) valida cada campo al
     * salir de él con estas reglas; cada acción valida solo las de su fase.
     */
    protected function rules(): array
    {
        return [...$this->reglasResponsable(), ...$this->reglasNiveles()];
    }

    /** @return array<string, list<mixed>> */
    private function reglasResponsable(): array
    {
        return [
            'tipoPersona' => ['required', 'in:fisica,fisica_con_gestor,moral'],
            'domicilioNotificaciones' => ReglasCaptura::texto(requerido: true, max: 250),
            'personaAutorizadaRecoger' => ReglasCaptura::nombrePersona(max: 200),
            'nombrePropuesto1' => ReglasCaptura::texto(requerido: true, max: 200),
            'nombrePropuesto2' => [...ReglasCaptura::texto(requerido: true, max: 200), $this->propuestaDistintaDe(['nombrePropuesto1'])],
            'nombrePropuesto3' => [...ReglasCaptura::texto(requerido: true, max: 200), $this->propuestaDistintaDe(['nombrePropuesto1', 'nombrePropuesto2'])],
        ];
    }

    /** @return array<string, list<string>> */
    private function reglasNiveles(): array
    {
        return [
            'nivelesSeleccionados' => ['required', 'array', 'min:1'],
            'nivelesSeleccionados.*' => ['integer', 'exists:niveles_educativos,id'],
        ];
    }

    /**
     * Una terna con dos nombres iguales (sin distinguir mayúsculas) no es
     * una terna: se compara cada propuesta con las anteriores.
     *
     * @param  list<string>  $anteriores
     */
    private function propuestaDistintaDe(array $anteriores): Closure
    {
        return function (string $atributo, mixed $valor, Closure $fallar) use ($anteriores): void {
            foreach ($anteriores as $anterior) {
                // Misma comparación que el caso de uso (una sola regla); aquí solo da aviso al salir del campo.
                if (RegistrarResponsableLegal::propuestaRepetida((string) $valor, (string) $this->{$anterior})) {
                    $fallar(RegistrarResponsableLegal::TERNA_REPETIDA);

                    return;
                }
            }
        };
    }

    public function guardarResponsable(RegistrarResponsableLegal $registrarResponsableLegal, EstadoPaso2 $estadoPaso2): void
    {
        $this->validarEnConjunto(
            fn () => $this->validate($this->reglasResponsable()),
            fn () => $this->tipoPersona === 'moral' ? $this->personaMoralForm->validate() : $this->personaFisicaForm->validate(),
            fn () => $this->tipoPersona === 'fisica_con_gestor' ? $this->gestorForm->validate() : null,
        );

        try {
            $registrarResponsableLegal->ejecutar($this->escuela->id, new DatosResponsableLegal(
                tipoPersona: $this->tipoPersona,
                domicilioNotificaciones: $this->domicilioNotificaciones !== '' ? $this->domicilioNotificaciones : null,
                personaAutorizadaRecoger: $this->personaAutorizadaRecoger !== '' ? $this->personaAutorizadaRecoger : null,
                nombrePropuesto1: $this->nombrePropuesto1 !== '' ? $this->nombrePropuesto1 : null,
                nombrePropuesto2: $this->nombrePropuesto2 !== '' ? $this->nombrePropuesto2 : null,
                nombrePropuesto3: $this->nombrePropuesto3 !== '' ? $this->nombrePropuesto3 : null,
                nombre: $this->personaFisicaForm->nombre !== '' ? $this->personaFisicaForm->nombre : null,
                fechaNacimiento: $this->personaFisicaForm->fechaNacimiento !== '' ? $this->personaFisicaForm->fechaNacimiento : null,
                rfc: $this->personaFisicaForm->rfc !== '' ? $this->personaFisicaForm->rfc : null,
                curp: $this->personaFisicaForm->curp !== '' ? $this->personaFisicaForm->curp : null,
                razonSocial: $this->personaMoralForm->razonSocial !== '' ? $this->personaMoralForm->razonSocial : null,
                numeroEscrituraConstitutiva: $this->personaMoralForm->numeroEscrituraConstitutiva !== '' ? $this->personaMoralForm->numeroEscrituraConstitutiva : null,
                fechaEscrituraConstitutiva: $this->personaMoralForm->fechaEscrituraConstitutiva !== '' ? $this->personaMoralForm->fechaEscrituraConstitutiva : null,
                notarioNombre: $this->personaMoralForm->notarioNombre !== '' ? $this->personaMoralForm->notarioNombre : null,
                notarioNumero: $this->personaMoralForm->notarioNumero !== '' ? $this->personaMoralForm->notarioNumero : null,
                notarioCiudad: $this->personaMoralForm->notarioCiudad !== '' ? $this->personaMoralForm->notarioCiudad : null,
                folioRegistroPublico: $this->personaMoralForm->folioRegistroPublico !== '' ? $this->personaMoralForm->folioRegistroPublico : null,
                fechaInscripcionRpp: $this->personaMoralForm->fechaInscripcionRpp !== '' ? $this->personaMoralForm->fechaInscripcionRpp : null,
                nombreRepresentanteLegal: $this->personaMoralForm->nombreRepresentanteLegal !== '' ? $this->personaMoralForm->nombreRepresentanteLegal : null,
                gestorNombre: $this->gestorForm->nombre !== '' ? $this->gestorForm->nombre : null,
                gestorNumeroPoder: $this->gestorForm->numeroPoder !== '' ? $this->gestorForm->numeroPoder : null,
                gestorNotarioNombre: $this->gestorForm->notarioNombre !== '' ? $this->gestorForm->notarioNombre : null,
                gestorNotarioNumero: $this->gestorForm->notarioNumero !== '' ? $this->gestorForm->notarioNumero : null,
                gestorFechaPoder: $this->gestorForm->fechaPoder !== '' ? $this->gestorForm->fechaPoder : null,
                gestorCurp: $this->gestorForm->curp !== '' ? $this->gestorForm->curp : null,
            ));
        } catch (DatosInvalidos $e) {
            foreach ($e->errores as $campo => $mensaje) {
                $this->addError($campo, $mensaje);
            }
            $this->toastDatosInvalidos();

            return;
        } catch (PrecondicionIncumplida $e) {
            // WS-7a: el trámite ya se envió; no se edita, se muestra el motivo sin redirigir.
            $this->addError('domicilioNotificaciones', $e->getMessage());
            $this->toast('No se guardó', $e->getMessage(), 'error');

            return;
        }

        $responsable = ResponsableLegal::where('escuela_id', $this->escuela->id)->firstOrFail();
        $this->responsableCapturado = $this->resumenResponsable($responsable);

        // WS-7b-1: una edición no avanza el asistente; se queda en la página con su aviso.
        if ($this->editando) {
            $cambioDeTipo = $this->tipoPersonaGuardado !== $this->tipoPersona;
            $this->tipoPersonaGuardado = $this->tipoPersona;
            $this->avisoCambioTipo = $this->avisoCambioTipo || $cambioDeTipo;
            $this->toast('Cambios guardados', $cambioDeTipo ? 'Revisa tus documentos.' : null);

            return;
        }

        $this->editando = true;
        $this->tipoPersonaGuardado = $this->tipoPersona;

        // Primera captura: mismo gate que antes (EstadoPaso2: completitud Y vigencia).
        if ($estadoPaso2->etapaFaltante($this->escuela->id) !== null) {
            $this->toastAlRedirigir('Responsable legal guardado', 'Ahora sube los documentos.');
            $this->redirectRoute('tramite.paso2-documentos', ['escuela' => $this->escuela->id]);

            return;
        }

        $this->fase = 'niveles';
        $this->toast('Responsable legal guardado', 'Ahora elige los niveles.');
    }

    public function guardarNiveles(RegistrarNivelesSeleccionados $registrarNivelesSeleccionados, ResumenEliminacionNivel $resumenEliminacion): void
    {
        $this->validate($this->reglasNiveles());
        [$agregar, $quitar] = $this->cambiosDeNiveles();

        if ($agregar === [] && $quitar === []) {
            $this->redirectRoute('tramite.resumen', ['escuela' => $this->escuela->id]);

            return;
        }

        $conteos = $quitar === [] ? [] : $resumenEliminacion->ejecutar($this->escuela->id, $quitar);

        // Spec U3: con datos capturados se confirma primero; sin datos, sin diálogo.
        if (ResumenEliminacionNivel::conDatos($conteos)) {
            $this->abrirConfirmacion($conteos);

            return;
        }

        $this->aplicarNiveles($registrarNivelesSeleccionados, $resumenEliminacion, $agregar, $quitar, $conteos);
    }

    public function confirmarQuitarNiveles(RegistrarNivelesSeleccionados $registrarNivelesSeleccionados, ResumenEliminacionNivel $resumenEliminacion): void
    {
        [$agregar, $quitar] = $this->cambiosDeNiveles();

        $this->aplicarNiveles($registrarNivelesSeleccionados, $resumenEliminacion, $agregar, $quitar, $this->porEliminar);
    }

    public function cancelarQuitarNiveles(): void
    {
        $this->porEliminar = [];
    }

    /** @return array{0: list<int>, 1: list<int>} agregar, quitar (nivel_educativo_id) */
    private function cambiosDeNiveles(): array
    {
        $seleccion = array_map('intval', $this->nivelesSeleccionados);

        return [
            array_values(array_diff($seleccion, $this->nivelesGuardados)),
            array_values(array_diff($this->nivelesGuardados, $seleccion)),
        ];
    }

    /** @param array<int, array<string, int>> $conteos */
    private function abrirConfirmacion(array $conteos): void
    {
        $this->porEliminar = $conteos;
        $this->confirmaciones++;
    }

    /**
     * @param  list<int>  $agregar
     * @param  list<int>  $quitar
     * @param  array<int, array<string, int>>  $confirmado
     */
    private function aplicarNiveles(RegistrarNivelesSeleccionados $registrar, ResumenEliminacionNivel $resumenEliminacion, array $agregar, array $quitar, array $confirmado): void
    {
        try {
            $registrar->ejecutar($this->escuela->id, agregar: $agregar, quitar: $quitar, confirmado: $confirmado);
        } catch (PrecondicionIncumplida $e) {
            if ($e->etapaFaltante === RegistrarNivelesSeleccionados::CONFIRMACION) {
                // Los datos crecieron desde la confirmación: se vuelve a preguntar con conteos frescos.
                $this->abrirConfirmacion($resumenEliminacion->ejecutar($this->escuela->id, $quitar));
                $this->addError('nivelesSeleccionados', $e->getMessage());

                return;
            }

            // Paso 2 pendiente (p. ej. agregar y quitar a la vez) o trámite enviado:
            // no se aplica nada y se explica el motivo sin salir de la página.
            $this->porEliminar = [];
            $this->addError('nivelesSeleccionados', $e->getMessage());
            $this->toast('No se guardó', $e->getMessage(), 'error');

            return;
        } catch (InvalidArgumentException $e) {
            $this->porEliminar = [];
            $this->addError('nivelesSeleccionados', $e->getMessage());
            $this->toast('No se guardó', $e->getMessage(), 'error');

            return;
        }

        $this->porEliminar = [];
        $this->toastAlRedirigir('Niveles guardados', $quitar === [] ? 'Ya puedes capturar los datos de cada nivel.' : 'Se quitaron los niveles que desmarcaste.');
        $this->redirectRoute('tramite.resumen', ['escuela' => $this->escuela->id]);
    }

    public function render()
    {
        return view('livewire.tramite.paso2-responsable', [
            'nivelesDisponibles' => $this->fase === 'niveles' ? NivelEducativo::educacionBasica()->get() : collect(),
            'encabezado' => ResumenTramite::encabezado($this->fase === 'niveles' ? 'niveles' : 'responsable'),
            'tieneNiveles' => $this->escuela->escuelaNiveles()->exists(),
            'etiquetasEliminacion' => ResumenEliminacionNivel::ETIQUETAS,
        ])->layoutData(['escuelaId' => $this->escuela->id, 'seccionActual' => $this->fase === 'niveles' ? 'niveles' : 'responsable']);
    }
}
