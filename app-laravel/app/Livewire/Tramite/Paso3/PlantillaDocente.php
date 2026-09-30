<?php

namespace App\Livewire\Tramite\Paso3;

use App\Application\Excepciones\DatosInvalidos;
use App\Application\Excepciones\PrecondicionIncumplida;
use App\Application\Personal\DTO\DatosPersona;
use App\Application\Personal\PlantillaCapturada;
use App\Application\Personal\RegistrarPlantillaDocente;
use App\Application\Tramite\ResumenTramite;
use App\Livewire\Tramite\Paso3\Concerns\CompuertaPaso3;
use App\Models\EscuelaNivel;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Paso 3, sub-step 5 (Anexo 1): an editable table of people, submitted as a
 * whole. Presentation only: RegistrarPlantillaDocente validates each row
 * and replaces the level's list (ADR-001).
 */
#[Layout('layouts.tramite')]
class PlantillaDocente extends Component
{
    use CompuertaPaso3;

    private const FILA_VACIA = [
        'cargoPuestoId' => '', 'nombre' => '', 'nacionalidad' => 'Mexicana', 'sexo' => '',
        'estudios' => '', 'cedulaODocumento' => '', 'salaId' => '', 'asignaturaId' => '',
    ];

    public EscuelaNivel $escuelaNivel;

    /** @var list<array<string, string>> */
    public array $personas = [];

    public function mount(EscuelaNivel $escuelaNivel, PlantillaCapturada $plantillaCapturada): void
    {
        $this->escuelaNivel = $escuelaNivel;

        if ($this->redirigirSiNoAlcanzable($escuelaNivel, 'plantilla_docente')) {
            return;
        }

        $this->personas = $plantillaCapturada->personas($escuelaNivel->id) ?: [self::FILA_VACIA];
    }

    public function agregarPersona(): void
    {
        $this->personas[] = self::FILA_VACIA;
    }

    public function quitarPersona(int $indice): void
    {
        unset($this->personas[$indice]);
        $this->personas = array_values($this->personas);
    }

    public function guardar(RegistrarPlantillaDocente $registrarPlantillaDocente): void
    {
        $this->resetErrorBag();
        $entero = fn (string $valor): ?int => ctype_digit($valor) ? (int) $valor : null;

        $personas = array_map(fn (array $fila) => new DatosPersona(
            cargoPuestoId: $entero($fila['cargoPuestoId']),
            nombre: $fila['nombre'],
            nacionalidad: $fila['nacionalidad'],
            sexo: $fila['sexo'],
            estudios: $fila['estudios'],
            cedulaODocumento: $fila['cedulaODocumento'],
            salaId: $entero($fila['salaId']),
            asignaturaId: $entero($fila['asignaturaId']),
        ), $this->personas);

        try {
            $registrarPlantillaDocente->ejecutar($this->escuelaNivel->id, $personas);
        } catch (DatosInvalidos $e) {
            foreach ($e->errores as $campo => $mensaje) {
                $this->addError($campo, $mensaje);
            }

            return;
        } catch (PrecondicionIncumplida) {
            $this->redirigirSiNoAlcanzable($this->escuelaNivel, 'plantilla_docente');

            return;
        }

        $this->redirectRoute('tramite.paso3-matricula', ['escuelaNivel' => $this->escuelaNivel->id]);
    }

    public function render(PlantillaCapturada $plantillaCapturada)
    {
        return view('livewire.tramite.paso3.plantilla-docente', [
            'encabezado' => ResumenTramite::encabezado('plantilla_docente', $this->escuelaNivel->nivelEducativo),
            'cargos' => $plantillaCapturada->cargos($this->escuelaNivel->id),
            'salas' => $plantillaCapturada->salas(),
            'asignaturas' => $plantillaCapturada->asignaturas(),
        ])->layoutData(['escuelaId' => $this->escuelaNivel->escuela_id, 'escuelaNivelId' => $this->escuelaNivel->id, 'seccionActual' => 'plantilla_docente']);
    }
}
