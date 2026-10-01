<?php

namespace App\Application\Personal;

use App\Application\EscuelaNiveles\MarcarPasoCompletado;
use App\Application\Excepciones\DatosInvalidos;
use App\Application\Personal\DTO\DatosPersona;
use App\Application\Tramite\CompuertaSubPaso3;
use App\Models\Asignatura;
use App\Models\CargoPuesto;
use App\Models\EscuelaNivel;
use App\Models\Personal;
use App\Models\Sala;
use Illuminate\Support\Facades\DB;

/**
 * Paso 3, sub-step 5 (Plantilla docente, Anexo 1). The submitted list
 * replaces the level's whole staff list: the form always sends every row,
 * so replace-all is the simplest correct write (personal_salas and
 * personal_asignaturas cascade). Every row must be complete; whether the
 * staff is *enough* is the capacity engine's question, not this one's.
 */
class RegistrarPlantillaDocente
{
    public function __construct(
        private readonly CompuertaSubPaso3 $compuerta,
        private readonly MarcarPasoCompletado $marcarPasoCompletado,
    ) {}

    /** @param list<DatosPersona> $personas */
    public function ejecutar(int $escuelaNivelId, array $personas): void
    {
        $escuelaNivel = EscuelaNivel::findOrFail($escuelaNivelId);
        $this->compuerta->verificar($escuelaNivel, 'plantilla_docente');

        if ($personas === []) {
            throw new DatosInvalidos(['personas' => 'Agrega al menos una persona a la plantilla.']);
        }

        $cargos = CargoPuesto::where('nivel_educativo_id', $escuelaNivel->nivel_educativo_id)->get()->keyBy('id');
        $salas = Sala::pluck('id')->all();
        $asignaturas = Asignatura::pluck('id')->all();

        $errores = [];
        foreach ($personas as $i => $persona) {
            $campo = fn (string $nombre) => "personas.{$i}.{$nombre}";
            $cargo = $persona->cargoPuestoId !== null ? $cargos->get($persona->cargoPuestoId) : null;

            if ($cargo === null) {
                $errores[$campo('cargoPuestoId')] = 'Elige un cargo del nivel.';
            }
            foreach (['nombre' => 200, 'nacionalidad' => 100, 'estudios' => 200, 'cedulaODocumento' => 100] as $propiedad => $maximo) {
                $valor = trim($persona->{$propiedad});
                if ($valor === '' || mb_strlen($valor) > $maximo) {
                    $errores[$campo($propiedad)] = $valor === '' ? 'Este dato es obligatorio.' : "Admite hasta {$maximo} caracteres.";
                }
            }
            if (! in_array($persona->sexo, ['M', 'F'], true)) {
                $errores[$campo('sexo')] = 'Elige M o F.';
            }
            if ($cargo?->requiere_sala && ! in_array($persona->salaId, $salas, true)) {
                $errores[$campo('salaId')] = 'Indica la sala a la que se asigna.';
            }
            if ($cargo?->requiere_asignatura && ! in_array($persona->asignaturaId, $asignaturas, true)) {
                $errores[$campo('asignaturaId')] = 'Indica la asignatura que imparte.';
            }
        }

        if ($errores !== []) {
            throw new DatosInvalidos($errores);
        }

        DB::transaction(function () use ($escuelaNivel, $personas, $cargos) {
            // WS-5b: the level's row lock serializes concurrent saves of the
            // delete-and-recreate; the gate re-runs on the locked row.
            $this->compuerta->verificar(EscuelaNivel::lockForUpdate()->findOrFail($escuelaNivel->id), 'plantilla_docente');

            Personal::where('escuela_nivel_id', $escuelaNivel->id)->delete();

            foreach ($personas as $persona) {
                /** @var CargoPuesto $cargo */
                $cargo = $cargos->get($persona->cargoPuestoId);
                $fila = Personal::create([
                    'escuela_nivel_id' => $escuelaNivel->id,
                    'cargo_puesto_id' => $cargo->id,
                    'nombre' => trim($persona->nombre),
                    'nacionalidad' => trim($persona->nacionalidad),
                    'sexo' => $persona->sexo,
                    'estudios' => trim($persona->estudios),
                    'cedula_o_documento' => trim($persona->cedulaODocumento),
                ]);

                if ($cargo->requiere_sala) {
                    DB::table('personal_salas')->insert(['personal_id' => $fila->id, 'sala_id' => $persona->salaId]);
                }
                if ($cargo->requiere_asignatura) {
                    DB::table('personal_asignaturas')->insert(['personal_id' => $fila->id, 'asignatura_id' => $persona->asignaturaId]);
                }
            }

            $this->marcarPasoCompletado->ejecutar($escuelaNivel->id, 'plantilla_docente');
        });
    }
}
