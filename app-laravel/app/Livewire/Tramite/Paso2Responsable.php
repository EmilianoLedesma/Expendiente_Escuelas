<?php

namespace App\Livewire\Tramite;

use App\Application\Captura\ReglasCaptura;
use App\Application\EscuelaNiveles\RegistrarNivelesSeleccionados;
use App\Application\Excepciones\DatosInvalidos;
use App\Application\Excepciones\PrecondicionIncumplida;
use App\Application\ResponsableLegal\DTO\DatosResponsableLegal;
use App\Application\ResponsableLegal\RegistrarResponsableLegal;
use App\Application\Tramite\EstadoPaso2;
use App\Application\Tramite\ResumenTramite;
use App\Livewire\Concerns\ValidaEnConjunto;
use App\Livewire\Forms\GestorForm;
use App\Livewire\Forms\PersonaFisicaForm;
use App\Livewire\Forms\PersonaMoralForm;
use App\Models\Escuela;
use App\Models\NivelEducativo;
use App\Models\PersonaFisica;
use App\Models\PersonaMoral;
use App\Models\ResponsableLegal;
use Closure;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Presentación pura: dos fases ('responsable', 'niveles') sobre una sola
 * visita de /tramite/paso2/{escuela}. Ninguna regla de negocio ni acceso
 * a Eloquent de escritura vive aquí (ADR-001) — RegistrarResponsableLegal
 * y RegistrarNivelesSeleccionados (Task 10) son las únicas escrituras.
 */
#[Layout('layouts.tramite')]
class Paso2Responsable extends Component
{
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

    /**
     * Resumen de solo lectura de lo que ya se envió, para cuando fase es
     * 'niveles' por un responsable ya capturado (por ejemplo, al volver por
     * el enlace de navegación). No hay edición: RegistrarResponsableLegal
     * es un no-op si escuela_id ya tiene fila (guardián de idempotencia),
     * así que prellenar el formulario de captura sería un formulario que
     * silenciosamente no guarda los cambios. Mismo patrón que
     * InfraestructuraNivel: mostrar, no re-capturar; cambiarlo es una
     * decisión normativa aparte (¿requiere autorización de SEDEQ?), no
     * resuelta aquí.
     *
     * @var array{}|array{tipo: string, nombre: ?string, domicilio: ?string}
     */
    public array $responsableCapturado = [];

    public function mount(Escuela $escuela, EstadoPaso2 $estadoPaso2): void
    {
        $this->escuela = $escuela;

        // EstadoPaso2 va ANTES del salto al resumen: una escuela_niveles creada
        // sin Paso 2 completo (bypass previo a WS-1.2) no debe atorar al
        // usuario en Paso 3 — vuelve a la etapa que le falta. Una vigencia
        // vencida también es incompletitud: 2.2 vuelve a pedir el documento.
        $etapaFaltante = $estadoPaso2->etapaFaltante($escuela->id);

        if ($etapaFaltante === EstadoPaso2::RESPONSABLE) {
            $this->fase = 'responsable';

            return;
        }

        if ($etapaFaltante === EstadoPaso2::DOCUMENTOS) {
            $this->redirectRoute('tramite.paso2-documentos', ['escuela' => $escuela->id]);

            return;
        }

        if ($escuela->escuelaNiveles()->exists()) {
            // WS-5b (decisión del dueño): con niveles ya elegidos, al hub; su
            // "Siguiente paso" es Documentos del nivel del primer nivel.
            $this->redirectRoute('tramite.resumen', ['escuela' => $escuela->id]);

            return;
        }

        $this->responsableCapturado = $this->resumenResponsable(ResponsableLegal::where('escuela_id', $escuela->id)->firstOrFail());
        $this->fase = 'niveles';
    }

    /** @return array{tipo: string, nombre: ?string, domicilio: ?string} */
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
            'domicilio' => $responsableLegal->domicilio_notificaciones,
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
            $propuesta = mb_strtolower((string) $valor);

            foreach ($anteriores as $anterior) {
                if ($propuesta !== '' && $propuesta === mb_strtolower($this->{$anterior})) {
                    $fallar('Las tres propuestas de nombre deben ser distintas.');

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
            ));
        } catch (DatosInvalidos $e) {
            foreach ($e->errores as $campo => $mensaje) {
                $this->addError($campo, $mensaje);
            }

            return;
        }

        // Mismo gate que mount() (EstadoPaso2: completitud Y vigencia) — aplicado
        // aquí también, porque antes guardarResponsable() saltaba directo a
        // 'niveles' sin pasar por Documentos (Paso 2.2) en la misma visita.
        if ($estadoPaso2->etapaFaltante($this->escuela->id) !== null) {
            $this->redirectRoute('tramite.paso2-documentos', ['escuela' => $this->escuela->id]);

            return;
        }

        $this->fase = 'niveles';
    }

    public function guardarNiveles(RegistrarNivelesSeleccionados $registrarNivelesSeleccionados): void
    {
        $this->validate($this->reglasNiveles());

        try {
            $registrarNivelesSeleccionados->ejecutar($this->escuela->id, $this->nivelesSeleccionados);
        } catch (PrecondicionIncumplida $e) {
            // mount() de /tramite/paso2 re-deriva la etapa faltante y manda al
            // formulario de responsable o a Documentos según corresponda.
            $this->redirectRoute($e->etapaFaltante === EstadoPaso2::DOCUMENTOS ? 'tramite.paso2-documentos' : 'tramite.paso2', ['escuela' => $this->escuela->id]);

            return;
        } catch (InvalidArgumentException $e) {
            $this->addError('nivelesSeleccionados', $e->getMessage());

            return;
        }

        $this->redirectRoute('tramite.resumen', ['escuela' => $this->escuela->id]);
    }

    public function render()
    {
        return view('livewire.tramite.paso2-responsable', [
            'nivelesDisponibles' => $this->fase === 'niveles' ? NivelEducativo::educacionBasica()->get() : collect(),
            'encabezado' => ResumenTramite::encabezado($this->fase === 'niveles' ? 'niveles' : 'responsable'),
        ])->layoutData(['escuelaId' => $this->escuela->id, 'seccionActual' => $this->fase === 'niveles' ? 'niveles' : 'responsable']);
    }
}
