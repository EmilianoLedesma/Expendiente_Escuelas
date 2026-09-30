<?php

namespace App\Application\Matricula;

use App\Application\EscuelaNiveles\MarcarPasoCompletado;
use App\Application\Excepciones\DatosInvalidos;
use App\Application\Matricula\DTO\DatosMatricula;
use App\Application\Tramite\CompuertaSubPaso3;
use App\Models\EscuelaNivel;
use App\Models\Grado;
use App\Models\MatriculaGrado;
use App\Models\MatriculaSala;
use App\Models\Sala;
use Illuminate\Support\Facades\DB;

/**
 * Paso 3, sub-step 6 (Matrícula), the magnitude the capacity engine reads.
 * Replaces the level's enrolment on every save. At least one student is
 * required: a level with no students has nothing to validate.
 */
class RegistrarMatricula
{
    public function __construct(
        private readonly CompuertaSubPaso3 $compuerta,
        private readonly MarcarPasoCompletado $marcarPasoCompletado,
    ) {}

    public function ejecutar(int $escuelaNivelId, DatosMatricula $datos): void
    {
        $escuelaNivel = EscuelaNivel::with('nivelEducativo')->findOrFail($escuelaNivelId);
        $this->compuerta->verificar($escuelaNivel, 'matricula');

        $porSala = $escuelaNivel->nivelEducativo->clave === 'inicial';

        if ($porSala ? $datos->grupos !== [] : $datos->salas !== []) {
            throw new DatosInvalidos(['matricula' => $porSala ? 'Educación Inicial se captura por sala.' : 'Este nivel se captura por grado y grupo.']);
        }

        $porSala ? $this->validarSalas($datos->salas) : $this->validarGrupos($escuelaNivel, $datos->grupos);

        $total = $porSala ? array_sum($datos->salas) : array_sum(array_column($datos->grupos, 'alumnos'));
        if ($total <= 0) {
            throw new DatosInvalidos(['matricula' => 'Declara al menos un alumno.']);
        }

        DB::transaction(function () use ($escuelaNivel, $datos, $porSala) {
            if ($porSala) {
                MatriculaSala::where('escuela_nivel_id', $escuelaNivel->id)->delete();
                foreach ($datos->salas as $salaId => $alumnos) {
                    MatriculaSala::create(['escuela_nivel_id' => $escuelaNivel->id, 'sala_id' => $salaId, 'cantidad_alumnos' => $alumnos]);
                }
            } else {
                MatriculaGrado::where('escuela_nivel_id', $escuelaNivel->id)->delete();
                foreach ($datos->grupos as $grupo) {
                    MatriculaGrado::create([
                        'escuela_nivel_id' => $escuelaNivel->id,
                        'grado_id' => $grupo['gradoId'],
                        'grupo' => self::grupo($grupo['grupo']),
                        'cantidad_alumnos' => $grupo['alumnos'],
                    ]);
                }
            }

            $this->marcarPasoCompletado->ejecutar($escuelaNivel->id, 'matricula');
        });
    }

    /** @param array<int, int> $salas */
    private function validarSalas(array $salas): void
    {
        $validas = Sala::pluck('id')->all();
        $errores = [];

        foreach ($salas as $salaId => $alumnos) {
            if (! in_array($salaId, $validas, true)) {
                $errores["salas.{$salaId}"] = 'Sala desconocida.';
            } elseif ($alumnos < 0 || $alumnos > 32767) {
                $errores["salas.{$salaId}"] = 'Captura un número de alumnos válido.';
            }
        }

        if ($errores !== []) {
            throw new DatosInvalidos($errores);
        }
    }

    /** @param list<array{gradoId: int, grupo: string, alumnos: int}> $grupos */
    private function validarGrupos(EscuelaNivel $escuelaNivel, array $grupos): void
    {
        $grados = Grado::where('nivel_educativo_id', $escuelaNivel->nivel_educativo_id)->pluck('id')->all();
        $vistos = [];
        $errores = [];

        foreach (array_values($grupos) as $i => $grupo) {
            if (! in_array($grupo['gradoId'], $grados, true)) {
                $errores["grupos.{$i}.gradoId"] = 'Elige un grado del nivel.';
            }

            $etiqueta = self::grupo($grupo['grupo']);
            if ($etiqueta === '' || mb_strlen($etiqueta) > 5) {
                $errores["grupos.{$i}.grupo"] = 'El grupo es una etiqueta de hasta 5 caracteres (A, B…).';
            } elseif (isset($vistos["{$grupo['gradoId']}|{$etiqueta}"])) {
                $errores["grupos.{$i}.grupo"] = 'Ese grupo ya está capturado para el grado.';
            }
            $vistos["{$grupo['gradoId']}|{$etiqueta}"] = true;

            if ($grupo['alumnos'] < 0 || $grupo['alumnos'] > 32767) {
                $errores["grupos.{$i}.alumnos"] = 'Captura un número de alumnos válido.';
            }
        }

        if ($errores !== []) {
            throw new DatosInvalidos($errores);
        }
    }

    private static function grupo(string $grupo): string
    {
        return mb_strtoupper(trim($grupo));
    }
}
