<?php

namespace App\Livewire\Tramite;

use App\Application\Captura\Normalizacion;
use App\Application\Captura\Normalizar;
use App\Application\Captura\ReglasCaptura;
use App\Application\Excepciones\DatosInvalidos;
use App\Application\Preregistro\DTO\DatosPreregistro;
use App\Application\Preregistro\IniciarTramiteNuevo;
use App\Application\Preregistro\ListarPlantelesDisponibles;
use App\Application\Preregistro\PlantelNoDisponible;
use App\Application\Tramite\ResumenTramite;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Presentación pura: enlaza campos, valida forma, llama a
 * IniciarTramiteNuevo, reacciona al resultado. Ninguna regla de negocio ni
 * acceso a Eloquent/DB vive aquí (ADR-001).
 */
#[Layout('layouts.tramite')]
class Paso1Preregistro extends Component
{
    public string $bifurcacion = 'nuevo';

    public ?int $plantelId = null;

    public string $calle = '';

    public string $numeroExt = '';

    public string $numeroInt = '';

    public string $colonia = '';

    public string $localidad = '';

    public string $municipio = '';

    #[Normalizar(Normalizacion::Digitos)]
    public string $codigoPostal = '';

    #[Normalizar(Normalizacion::Telefono)]
    public string $telefono = '';

    #[Normalizar(Normalizacion::Correo)]
    public string $correoElectronico = '';

    protected function rules(): array
    {
        $siEsNuevo = 'required_if:bifurcacion,nuevo';

        return [
            'bifurcacion' => ['required', 'in:nuevo,existente'],
            'plantelId' => ['required_if:bifurcacion,existente', 'nullable', 'integer', 'exists:planteles,id'],
            'calle' => ReglasCaptura::texto(requerido: $siEsNuevo, max: 150),
            'numeroExt' => ReglasCaptura::texto(max: 20),
            'numeroInt' => [...ReglasCaptura::texto(max: 20), 'regex:/^\d+$/'],
            'colonia' => ReglasCaptura::texto(requerido: $siEsNuevo, max: 150),
            'localidad' => ReglasCaptura::texto(max: 150),
            'municipio' => ReglasCaptura::texto(requerido: $siEsNuevo, max: 150),
            'codigoPostal' => ReglasCaptura::codigoPostal(requerido: $siEsNuevo),
            'telefono' => ReglasCaptura::telefono(),
            'correoElectronico' => ReglasCaptura::correo(max: 150),
        ];
    }

    /** Mismo texto que PlantelNoDisponible: no revelar si el plantel existe para otro solicitante. */
    protected function messages(): array
    {
        return [
            'plantelId.exists' => (new PlantelNoDisponible)->getMessage(),
            'numeroInt.regex' => 'El número interior solo puede contener dígitos.',
        ];
    }

    public function guardar(IniciarTramiteNuevo $iniciarTramiteNuevo): void
    {
        $this->validate();

        $dto = new DatosPreregistro(
            bifurcacion: $this->bifurcacion,
            plantelId: $this->bifurcacion === 'existente' ? $this->plantelId : null,
            calle: $this->calle !== '' ? $this->calle : null,
            numeroExt: $this->numeroExt !== '' ? $this->numeroExt : null,
            numeroInt: $this->numeroInt !== '' ? $this->numeroInt : null,
            colonia: $this->colonia !== '' ? $this->colonia : null,
            localidad: $this->localidad !== '' ? $this->localidad : null,
            municipio: $this->municipio !== '' ? $this->municipio : null,
            codigoPostal: $this->codigoPostal !== '' ? $this->codigoPostal : null,
            telefono: $this->telefono !== '' ? $this->telefono : null,
            correoElectronico: $this->correoElectronico !== '' ? $this->correoElectronico : null,
        );

        try {
            $resultado = $iniciarTramiteNuevo->ejecutar($dto, auth()->user()->solicitante->getKey());
        } catch (PlantelNoDisponible $e) {
            $this->addError('plantelId', $e->getMessage());

            return;
        } catch (DatosInvalidos $e) {
            foreach ($e->errores as $campo => $mensaje) {
                $this->addError($campo, $mensaje);
            }

            return;
        } catch (InvalidArgumentException $e) {
            $this->addError('bifurcacion', $e->getMessage());

            return;
        }

        $this->redirectRoute('tramite.paso2', ['escuela' => $resultado->escuelaId]);
    }

    public function render(ListarPlantelesDisponibles $listarPlantelesDisponibles)
    {
        return view('livewire.tramite.paso1-preregistro', [
            'planteles' => $this->bifurcacion === 'existente'
                ? $listarPlantelesDisponibles->ejecutar(auth()->user()->solicitante->getKey())
                : collect(),
            'encabezado' => ResumenTramite::encabezado('plantel'),
        ]);
    }
}
