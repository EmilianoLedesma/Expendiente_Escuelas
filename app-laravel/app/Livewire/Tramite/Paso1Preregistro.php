<?php

namespace App\Livewire\Tramite;

use App\Application\Captura\Normalizacion;
use App\Application\Captura\Normalizar;
use App\Application\Captura\ReglasCaptura;
use App\Application\Excepciones\DatosInvalidos;
use App\Application\Geografia\ListarCatalogoGeografico;
use App\Application\Preregistro\DTO\DatosPreregistro;
use App\Application\Preregistro\IniciarTramiteNuevo;
use App\Application\Preregistro\ListarPlantelesDisponibles;
use App\Application\Preregistro\PlantelNoDisponible;
use App\Application\Tramite\ResumenTramite;
use App\Livewire\Concerns\NotificaToast;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Presentación pura: enlaza campos, valida forma, llama a
 * IniciarTramiteNuevo, reacciona al resultado. Ninguna regla de negocio ni
 * acceso a Eloquent/DB vive aquí (ADR-001). Con el catálogo geográfico importado
 * el municipio se elige de los de Querétaro (ADR-008/ADR-017); sin él se conserva
 * el campo de texto. IniciarTramiteNuevo vuelve a validar el id.
 */
#[Layout('layouts.tramite')]
class Paso1Preregistro extends Component
{
    use NotificaToast;

    public string $bifurcacion = 'nuevo';

    public ?int $plantelId = null;

    public string $calle = '';

    public string $numeroExt = '';

    public string $numeroInt = '';

    public string $colonia = '';

    public string $localidad = '';

    public string $municipio = '';

    /** municipios.id de Querétaro; '' = sin elegir. El nombre lo deriva el caso de uso. */
    public string $municipioId = '';

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
            ...$this->reglasMunicipio($siEsNuevo),
            'codigoPostal' => ReglasCaptura::codigoPostal(requerido: $siEsNuevo),
            'telefono' => ReglasCaptura::telefono(),
            'correoElectronico' => ReglasCaptura::correo(max: 150),
        ];
    }

    /** @return array<string, list<mixed>> */
    private function reglasMunicipio(string $siEsNuevo): array
    {
        $queretaro = app(ListarCatalogoGeografico::class)->idDeQueretaro();

        if ($queretaro === null) {
            return ['municipio' => ReglasCaptura::texto(requerido: $siEsNuevo, max: 150)];
        }

        return ['municipioId' => [$siEsNuevo, 'nullable', 'integer', Rule::exists('municipios', 'id')->where('entidad_federativa_id', $queretaro)]];
    }

    /** Mismo texto que PlantelNoDisponible: no revelar si el plantel existe para otro solicitante. */
    protected function messages(): array
    {
        return [
            'plantelId.exists' => (new PlantelNoDisponible)->getMessage(),
            'numeroInt.regex' => 'El número interior solo puede contener dígitos.',
            'municipioId.required_if' => 'Selecciona el municipio.',
            'municipioId.exists' => 'Selecciona un municipio de Querétaro de la lista.',
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
            municipioId: $this->bifurcacion === 'nuevo' && $this->municipioId !== '' ? (int) $this->municipioId : null,
        );

        try {
            $resultado = $iniciarTramiteNuevo->ejecutar($dto, auth()->user()->solicitante->getKey());
        } catch (PlantelNoDisponible $e) {
            $this->addError('plantelId', $e->getMessage());
            $this->toast('No se pudo guardar', $e->getMessage(), 'error');

            return;
        } catch (DatosInvalidos $e) {
            foreach ($e->errores as $campo => $mensaje) {
                $this->addError($campo, $mensaje);
            }
            $this->toastDatosInvalidos();

            return;
        } catch (InvalidArgumentException $e) {
            $this->addError('bifurcacion', $e->getMessage());
            $this->toast('No se pudo guardar', $e->getMessage(), 'error');

            return;
        }

        $this->toastAlRedirigir('Plantel guardado', 'Continúa con los datos del responsable legal.');
        $this->redirectRoute('tramite.paso2', ['escuela' => $resultado->escuelaId]);
    }

    public function render(ListarPlantelesDisponibles $listarPlantelesDisponibles, ListarCatalogoGeografico $catalogoGeografico)
    {
        return view('livewire.tramite.paso1-preregistro', [
            'planteles' => $this->bifurcacion === 'existente'
                ? $listarPlantelesDisponibles->ejecutar(auth()->user()->solicitante->getKey())
                : collect(),
            'encabezado' => ResumenTramite::encabezado('plantel'),
            'municipiosQueretaro' => $this->bifurcacion === 'nuevo' ? $catalogoGeografico->municipiosDeQueretaro() : [],
        ]);
    }
}
