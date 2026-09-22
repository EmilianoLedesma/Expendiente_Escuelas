<?php

namespace App\Livewire\Tramite;

use App\Application\Documentos\DocumentosCompletos;
use App\Application\Documentos\ValidarVigenciaDocumentos;
use App\Application\EscuelaNiveles\RegistrarNivelesSeleccionados;
use App\Application\ResponsableLegal\DTO\DatosResponsableLegal;
use App\Application\ResponsableLegal\RegistrarResponsableLegal;
use App\Livewire\Forms\GestorForm;
use App\Livewire\Forms\PersonaFisicaForm;
use App\Livewire\Forms\PersonaMoralForm;
use App\Models\Escuela;
use App\Models\EscuelaNivel;
use App\Models\NivelEducativo;
use App\Models\PersonaFisica;
use App\Models\PersonaMoral;
use App\Models\ResponsableLegal;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
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
    public Escuela $escuela;

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

    public function mount(Escuela $escuela, DocumentosCompletos $documentosCompletos, ValidarVigenciaDocumentos $validarVigencia): void
    {
        $this->escuela = $escuela;

        if ($escuela->escuelaNiveles()->exists()) {
            $this->redirectRoute('tramite.paso3-inmueble', [
                'escuelaNivel' => $escuela->escuelaNiveles()->orderBy('id')->first(),
            ]);

            return;
        }

        $responsableLegal = ResponsableLegal::where('escuela_id', $escuela->id)->first();

        if ($responsableLegal !== null) {
            // Una vigencia vencida es incompletitud, no un callejón sin salida:
            // 2.2 vuelve a pedir el documento infractor.
            if (! $documentosCompletos->paraEscuela($escuela->id, $responsableLegal->tipo_persona)
                || $validarVigencia->ejecutar($escuela->id) !== []) {
                $this->redirectRoute('tramite.paso2-documentos', ['escuela' => $escuela->id]);

                return;
            }

            $this->responsableCapturado = $this->resumenResponsable($responsableLegal);
            $this->fase = 'niveles';

            return;
        }

        $this->fase = 'responsable';
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

    public function guardarResponsable(RegistrarResponsableLegal $registrarResponsableLegal): void
    {
        $this->validate([
            'tipoPersona' => ['required', 'in:fisica,fisica_con_gestor,moral'],
            'domicilioNotificaciones' => ['required', 'string', 'max:250'],
            'personaAutorizadaRecoger' => ['nullable', 'string', 'max:200'],
            'nombrePropuesto1' => ['required', 'string', 'max:200'],
            'nombrePropuesto2' => ['required', 'string', 'max:200'],
            'nombrePropuesto3' => ['required', 'string', 'max:200'],
        ]);

        if ($this->tipoPersona === 'moral') {
            $this->personaMoralForm->validate();
        } else {
            $this->personaFisicaForm->validate();
            $this->personaFisicaForm->rfc = mb_strtoupper($this->personaFisicaForm->rfc);
            $this->personaFisicaForm->curp = mb_strtoupper($this->personaFisicaForm->curp);

            if ($this->tipoPersona === 'fisica_con_gestor') {
                $this->gestorForm->validate();
            }
        }

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

        $this->fase = 'niveles';
    }

    public function guardarNiveles(RegistrarNivelesSeleccionados $registrarNivelesSeleccionados): void
    {
        $this->validate([
            'nivelesSeleccionados' => ['required', 'array', 'min:1'],
            'nivelesSeleccionados.*' => ['integer', 'exists:niveles_educativos,id'],
        ]);

        try {
            $registrarNivelesSeleccionados->ejecutar($this->escuela->id, $this->nivelesSeleccionados);
        } catch (InvalidArgumentException $e) {
            $this->addError('nivelesSeleccionados', $e->getMessage());

            return;
        }

        $primerEscuelaNivel = EscuelaNivel::where('escuela_id', $this->escuela->id)->orderBy('id')->firstOrFail();

        $this->redirectRoute('tramite.paso3-inmueble', ['escuelaNivel' => $primerEscuelaNivel]);
    }

    public function render()
    {
        return view('livewire.tramite.paso2-responsable', [
            'nivelesDisponibles' => $this->fase === 'niveles' ? NivelEducativo::educacionBasica()->get() : collect(),
        ])->layoutData(['escuelaId' => $this->escuela->id]);
    }
}
