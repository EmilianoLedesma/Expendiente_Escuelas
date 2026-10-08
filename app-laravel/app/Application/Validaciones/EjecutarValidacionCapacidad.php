<?php

namespace App\Application\Validaciones;

use App\Domain\Validaciones\Engine\EvaluadorMobiliario;
use App\Domain\Validaciones\Engine\ValidacionCapacidadService;
use App\Domain\Validaciones\Regla\ReglaCapacidad;
use App\Domain\Validaciones\Resultado\ReporteValidacion;
use App\Models\EscuelaNivel;
use Illuminate\Support\Facades\DB;

/**
 * Runs the capacity engine for one escuela_nivel: its reglas_validacion
 * rows against what Paso 3 captured, plus Inicial furniture. Since WS-7a
 * (ADR-010 P3) a no_cumple, or a no_evaluable caused by a missing capture,
 * blocks the send (ValidacionFinal::bloqueantes); rules whose input the
 * wizard never captures are marked sin_dato and do not.
 */
class EjecutarValidacionCapacidad
{
    public function __construct(
        private readonly ConstruirDatosCapacidad $construirDatos,
        private readonly ValidacionCapacidadService $servicio,
        private readonly EvaluadorMobiliario $evaluadorMobiliario,
    ) {}

    public function ejecutar(int $escuelaNivelId): ReporteValidacion
    {
        $escuelaNivel = EscuelaNivel::with('nivelEducativo')->findOrFail($escuelaNivelId);
        $datos = $this->construirDatos->paraNivel($escuelaNivelId);
        $resultados = $this->servicio->evaluar($this->reglas($escuelaNivel->nivel_educativo_id), $datos)->resultados;

        if ($escuelaNivel->nivelEducativo->clave === 'inicial') {
            $resultados[] = $this->evaluadorMobiliario->evaluar($datos->matriculaPorSala, $datos->mobiliario);
        }

        return new ReporteValidacion($resultados);
    }

    /** @return array<string, string> clave => concepto, for presenting results */
    public function conceptos(int $escuelaNivelId): array
    {
        $nivelId = EscuelaNivel::whereKey($escuelaNivelId)->value('nivel_educativo_id');

        return DB::table('reglas_validacion')->where('nivel_educativo_id', $nivelId)->pluck('concepto', 'clave')->all()
            + [EvaluadorMobiliario::CLAVE => 'Mobiliario y equipo por sala'];
    }

    /** @return list<ReglaCapacidad> */
    private function reglas(int $nivelEducativoId): array
    {
        return DB::table('reglas_validacion')->where('nivel_educativo_id', $nivelEducativoId)->orderBy('id')->get()
            ->map(fn ($fila) => new ReglaCapacidad(
                clave: $fila->clave,
                tipoRegla: $fila->tipo_regla,
                tipoCalculo: $fila->tipo_calculo,
                ambito: $fila->ambito,
                redondeo: $fila->redondeo,
                concepto: $fila->concepto,
                cargoPuestoId: $fila->cargo_puesto_id !== null ? (int) $fila->cargo_puesto_id : null,
                condicionMin: $fila->condicion_min !== null ? (float) $fila->condicion_min : null,
                valorNumerico: (float) $fila->valor_numerico,
                condicionMax: $fila->condicion_max !== null ? (float) $fila->condicion_max : null,
            ))
            ->values()
            ->all();
    }
}
