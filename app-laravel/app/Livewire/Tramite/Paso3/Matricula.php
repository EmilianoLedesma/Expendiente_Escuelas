<?php

namespace App\Livewire\Tramite\Paso3;

use App\Application\Excepciones\DatosInvalidos;
use App\Application\Excepciones\PrecondicionIncumplida;
use App\Application\Matricula\DTO\DatosMatricula;
use App\Application\Matricula\MatriculaCapturada;
use App\Application\Matricula\RegistrarMatricula;
use App\Application\Tramite\ResumenTramite;
use App\Livewire\Tramite\Paso3\Concerns\CompuertaPaso3;
use App\Models\EscuelaNivel;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Paso 3, sub-step 6 and last: enrolment by sala (Inicial) or by grado and
 * grupo. Presentation only: RegistrarMatricula validates and writes. Saving
 * closes Paso 3 for the level and returns to the hub.
 */
#[Layout('layouts.tramite')]
class Matricula extends Component
{
    use CompuertaPaso3;

    public EscuelaNivel $escuelaNivel;

    public bool $porSala = false;

    /** @var array<int, string> sala_id => alumnos */
    public array $salas = [];

    /** @var list<array{gradoId: string, grupo: string, alumnos: string}> */
    public array $grupos = [];

    public function mount(EscuelaNivel $escuelaNivel, MatriculaCapturada $matriculaCapturada): void
    {
        $this->escuelaNivel = $escuelaNivel;

        if ($this->redirigirSiNoAlcanzable($escuelaNivel, 'matricula')) {
            return;
        }

        $this->porSala = $matriculaCapturada->porSala($escuelaNivel->id);

        if ($this->porSala) {
            $this->salas = $matriculaCapturada->salas($escuelaNivel->id);
        } else {
            $this->grupos = $matriculaCapturada->grupos($escuelaNivel->id) ?: [['gradoId' => '', 'grupo' => 'A', 'alumnos' => '']];
        }
    }

    public function agregarGrupo(): void
    {
        $this->grupos[] = ['gradoId' => '', 'grupo' => 'A', 'alumnos' => ''];
    }

    public function quitarGrupo(int $indice): void
    {
        unset($this->grupos[$indice]);
        $this->grupos = array_values($this->grupos);
    }

    public function guardar(RegistrarMatricula $registrarMatricula): void
    {
        $this->resetErrorBag();
        $entero = fn (string $valor): int => ctype_digit(trim($valor)) ? (int) trim($valor) : -1;

        $datos = $this->porSala
            ? new DatosMatricula(salas: array_map(
                fn (string $alumnos) => trim($alumnos) === '' ? 0 : $entero($alumnos),
                array_combine(array_map('intval', array_keys($this->salas)), array_values($this->salas)),
            ))
            : new DatosMatricula(grupos: array_map(fn (array $grupo) => [
                'gradoId' => ctype_digit($grupo['gradoId']) ? (int) $grupo['gradoId'] : 0,
                'grupo' => $grupo['grupo'],
                'alumnos' => $entero($grupo['alumnos']),
            ], $this->grupos));

        try {
            $registrarMatricula->ejecutar($this->escuelaNivel->id, $datos);
        } catch (DatosInvalidos $e) {
            foreach ($e->errores as $campo => $mensaje) {
                $this->addError($campo, $mensaje);
            }

            return;
        } catch (PrecondicionIncumplida) {
            $this->redirigirSiNoAlcanzable($this->escuelaNivel, 'matricula');

            return;
        }

        $this->redirectRoute('tramite.resumen', ['escuela' => $this->escuelaNivel->escuela_id]);
    }

    public function render(MatriculaCapturada $matriculaCapturada)
    {
        return view('livewire.tramite.paso3.matricula', [
            'encabezado' => ResumenTramite::encabezado('matricula', $this->escuelaNivel->nivelEducativo),
            'etiquetaAlumnos' => $this->escuelaNivel->tipo_tramite === 'reincorporacion' ? 'Alumnos inscritos' : 'Alumnos proyectados',
            'nombresSalas' => $matriculaCapturada->nombresSalas(),
            'grados' => $this->porSala ? [] : $matriculaCapturada->grados($this->escuelaNivel->id),
        ])->layoutData(['escuelaId' => $this->escuelaNivel->escuela_id, 'escuelaNivelId' => $this->escuelaNivel->id, 'seccionActual' => 'matricula']);
    }
}
