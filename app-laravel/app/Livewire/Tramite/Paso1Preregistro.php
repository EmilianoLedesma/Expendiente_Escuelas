<?php

namespace App\Livewire\Tramite;

use App\Application\Preregistro\DTO\DatosPreregistro;
use App\Application\Preregistro\IniciarTramiteNuevo;
use App\Application\Preregistro\ListarPlantelesDisponibles;
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

    public string $codigoPostal = '';

    public string $telefono = '';

    public string $correoElectronico = '';

    protected function rules(): array
    {
        return [
            'bifurcacion' => ['required', 'in:nuevo,existente'],
            'plantelId' => ['required_if:bifurcacion,existente', 'nullable', 'integer', 'exists:planteles,id'],
            'calle' => ['required_if:bifurcacion,nuevo', 'nullable', 'string', 'max:150'],
            'numeroExt' => ['nullable', 'string', 'max:20'],
            'numeroInt' => ['nullable', 'string', 'max:20'],
            'colonia' => ['required_if:bifurcacion,nuevo', 'nullable', 'string', 'max:150'],
            'localidad' => ['nullable', 'string', 'max:150'],
            'municipio' => ['required_if:bifurcacion,nuevo', 'nullable', 'string', 'max:150'],
            'codigoPostal' => ['required_if:bifurcacion,nuevo', 'nullable', 'string', 'max:10'],
            'telefono' => ['nullable', 'string', 'max:20'],
            'correoElectronico' => ['nullable', 'email', 'max:150'],
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
        } catch (InvalidArgumentException $e) {
            $this->addError('bifurcacion', $e->getMessage());

            return;
        }

        $this->redirectRoute('tramite.paso2-placeholder', ['escuela' => $resultado->escuelaId]);
    }

    public function render(ListarPlantelesDisponibles $listarPlantelesDisponibles)
    {
        return view('livewire.tramite.paso1-preregistro', [
            'planteles' => $this->bifurcacion === 'existente' ? $listarPlantelesDisponibles->ejecutar() : collect(),
        ]);
    }
}
