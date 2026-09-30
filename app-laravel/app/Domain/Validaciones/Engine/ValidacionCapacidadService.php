<?php

namespace App\Domain\Validaciones\Engine;

use App\Domain\Validaciones\Regla\CalculadoraRequerimiento;
use App\Domain\Validaciones\Regla\ReglaCapacidad;
use App\Domain\Validaciones\Resultado\EstadoResultado;
use App\Domain\Validaciones\Resultado\ReporteValidacion;
use App\Domain\Validaciones\Resultado\ResultadoRegla;

/**
 * Motor de Validación de Capacidad Instalada (PRD; COMPENDIO §5, §5.2).
 *
 * For each reglas_validacion row: resolve which captured value is compared
 * and which magnitude feeds CalculadoraRequerimiento, then compare. The
 * magnitude is resolved here, by `clave` (option C of
 * docs/decisions/PENDIENTE-origen-de-magnitud.md, provisional), because the
 * catalog is small and closed. A missing input is no_evaluable, never a
 * pass or a fail.
 *
 * Composition: preescolar.superficie.espacio_maestro is not evaluated alone;
 * it adds 2 m² per aula to preescolar.superficie.aula (COMPENDIO: "1 m² por
 * educando + 2 m² adicionales para el espacio del maestro").
 */
final class ValidacionCapacidadService
{
    private const COMPUESTA = 'superficie.espacio_maestro';

    /** Rows whose declared value the wizard does not capture. */
    private const SIN_DATO = [
        'infraestructura.altura_aulas' => 'la altura de las aulas no se captura',
        'superficie.aula_usos_multiples' => 'la superficie del aula mayor no se captura',
        'superficie.aula_lactantes' => 'la superficie por sala no se captura (solo el total de aulas)',
        'superficie.aula_maternales' => 'la superficie por sala no se captura (solo el total de aulas)',
    ];

    public function __construct(private readonly CalculadoraRequerimiento $calculadora) {}

    /** @param list<ReglaCapacidad> $reglas */
    public function evaluar(array $reglas, DatosCapacidadNivel $datos): ReporteValidacion
    {
        $porSlug = [];
        foreach ($reglas as $regla) {
            $porSlug[$regla->slug()] = $regla;
        }

        $resultados = [];
        foreach ($reglas as $regla) {
            if ($regla->slug() !== self::COMPUESTA) {
                $resultados[] = $this->evaluarRegla($regla, $datos, $porSlug);
            }
        }

        return new ReporteValidacion($resultados);
    }

    /** @param array<string, ReglaCapacidad> $porSlug */
    private function evaluarRegla(ReglaCapacidad $regla, DatosCapacidadNivel $datos, array $porSlug): ResultadoRegla
    {
        $slug = $regla->slug();

        if (isset(self::SIN_DATO[$slug])) {
            return $this->noEvaluable($regla, 'No verificable: '.self::SIN_DATO[$slug].'.');
        }

        return match (true) {
            $slug === 'superficie.predio_total' => $this->superficie($regla, $datos->superficiePredio, $datos->matriculaPlantel, 'la superficie del predio'),
            $slug === 'superficie.construida_total' => $this->superficie($regla, $datos->superficieConstruida, $datos->matriculaNivel, 'la superficie construida'),
            $slug === 'superficie.aula' => $this->aulaConEspacioMaestro($regla, $datos, $porSlug[self::COMPUESTA] ?? null),
            $slug === 'superficie.aulas' => $this->superficie($regla, $datos->superficieAulas, $datos->matriculaNivel, 'la superficie de aulas'),
            in_array($slug, ['superficie.area_recreacion', 'superficie.area_recreativa', 'superficie.areas_recreativas_minimas'], true) => $this->superficie($regla, $datos->superficieRecreativa, $datos->matriculaNivel, 'la superficie de áreas recreativas'),
            $slug === 'superficie.sala_usos_multiples' => $this->superficie($regla, $datos->superficieUsosMultiples, $datos->matriculaNivel, 'la superficie de la sala de usos múltiples'),
            $slug === 'superficie.sanitarios' => $this->superficie($regla, $datos->superficieSanitariosAlumnos, $datos->matriculaNivel, 'la superficie de sanitarios de alumnos'),
            $slug === 'infraestructura.acervo_bibliografico' => $this->acervo($regla, $datos),
            $slug === 'personal.responsable_sala' => $this->responsablesDeSala($regla, $datos),
            in_array($slug, ['personal.asistente_lactantes', 'personal.asistente_maternales'], true) => $this->asistentes($regla, $datos, $slug === 'personal.asistente_lactantes' ? 'lactantes_' : 'maternal_'),
            $slug === 'personal.educacion_fisica' && $regla->cargoPuestoId === null => $this->personal($regla, $datos, $datos->docentesEducacionFisica),
            $regla->tipoRegla === 'personal' => $this->personal($regla, $datos, $regla->cargoPuestoId === null || $datos->personalPorCargo === null ? null : ($datos->personalPorCargo[$regla->cargoPuestoId] ?? 0)),
            default => $this->noEvaluable($regla, 'No verificable: el motor aún no tiene cómo evaluar esta regla.'),
        };
    }

    private function superficie(ReglaCapacidad $regla, ?float $declarado, ?int $matricula, string $que): ResultadoRegla
    {
        $usaMatricula = $regla->tipoCalculo !== 'minimo_fijo';

        if ($usaMatricula && $matricula === null) {
            return $this->noEvaluable($regla, 'No verificable: falta capturar la matrícula.');
        }
        if ($declarado === null) {
            return $this->noEvaluable($regla, "No verificable: falta capturar {$que}.");
        }

        return $this->comparar($regla, $this->requerido($regla, (float) ($matricula ?? 0)), $declarado, 'm²', $matricula);
    }

    private function aulaConEspacioMaestro(ReglaCapacidad $regla, DatosCapacidadNivel $datos, ?ReglaCapacidad $espacioMaestro): ResultadoRegla
    {
        if ($datos->matriculaNivel === null) {
            return $this->noEvaluable($regla, 'No verificable: falta capturar la matrícula.');
        }
        if ($datos->superficieAulas === null || $datos->numeroAulas === null) {
            return $this->noEvaluable($regla, 'No verificable: falta capturar la superficie de aulas.');
        }

        $requerido = $this->requerido($regla, (float) $datos->matriculaNivel);
        if ($espacioMaestro !== null) {
            $requerido += $this->requerido($espacioMaestro, 0) * $datos->numeroAulas;
        }

        return $this->comparar($regla, $requerido, $datos->superficieAulas, 'm²', $datos->matriculaNivel);
    }

    private function acervo(ReglaCapacidad $regla, DatosCapacidadNivel $datos): ResultadoRegla
    {
        if ($regla->tipoCalculo === 'ratio_por_grado' && $datos->gradosOfertados === null) {
            return $this->noEvaluable($regla, 'No verificable: falta capturar la matrícula por grado.');
        }
        if ($datos->acervoTitulos === null) {
            return $this->noEvaluable($regla, 'No verificable: falta capturar el acervo de la biblioteca.');
        }

        return $this->comparar($regla, $this->requerido($regla, (float) ($datos->gradosOfertados ?? 0)), $datos->acervoTitulos, 'títulos', $datos->gradosOfertados);
    }

    private function responsablesDeSala(ReglaCapacidad $regla, DatosCapacidadNivel $datos): ResultadoRegla
    {
        if ($datos->matriculaPorSala === null) {
            return $this->noEvaluable($regla, 'No verificable: falta capturar la matrícula por sala.');
        }
        if ($datos->personalPorCargo === null) {
            return $this->noEvaluable($regla, 'No verificable: falta capturar la plantilla de personal.');
        }

        $salas = count(array_filter($datos->matriculaPorSala, fn (int $alumnos) => $alumnos > 0));

        return $this->comparar($regla, $this->requerido($regla, (float) $salas), $datos->personalPorCargo[$regla->cargoPuestoId] ?? 0, 'personas', $salas);
    }

    /** Proportional per sala (COMPENDIO: "1 asistente por cada 5 menores en salas de lactantes"), rounded per sala, then summed. */
    private function asistentes(ReglaCapacidad $regla, DatosCapacidadNivel $datos, string $prefijoSala): ResultadoRegla
    {
        if ($datos->matriculaPorSala === null) {
            return $this->noEvaluable($regla, 'No verificable: falta capturar la matrícula por sala.');
        }
        if ($datos->personalPorCargo === null) {
            return $this->noEvaluable($regla, 'No verificable: falta capturar la plantilla de personal.');
        }

        $requerido = 0;
        $declarado = 0;
        $alumnosTotal = 0;
        foreach ($datos->matriculaPorSala as $sala => $alumnos) {
            if (! str_starts_with($sala, $prefijoSala)) {
                continue;
            }
            $alumnosTotal += $alumnos;
            $requerido += (int) $this->requerido($regla, (float) $alumnos);
            $declarado += $datos->personalPorCargoYSala[$regla->cargoPuestoId][$sala] ?? 0;
        }

        return $this->comparar($regla, $requerido, $declarado, 'personas', $alumnosTotal);
    }

    private function personal(ReglaCapacidad $regla, DatosCapacidadNivel $datos, ?int $declarado): ResultadoRegla
    {
        $usaMatricula = in_array($regla->tipoCalculo, ['personal_umbral', 'personal_proporcional'], true);

        if ($usaMatricula && $datos->matriculaNivel === null) {
            return $this->noEvaluable($regla, 'No verificable: falta capturar la matrícula.');
        }
        if ($declarado === null) {
            return $this->noEvaluable($regla, 'No verificable: falta capturar la plantilla de personal.');
        }

        return $this->comparar($regla, $this->requerido($regla, (float) ($datos->matriculaNivel ?? 0)), $declarado, 'personas', $datos->matriculaNivel);
    }

    private function requerido(ReglaCapacidad $regla, float $magnitud): int|float
    {
        return $this->calculadora->calcular($regla->tipoCalculo, $regla->valorNumerico, $regla->condicionMin, $regla->redondeo, $magnitud);
    }

    private function comparar(ReglaCapacidad $regla, int|float $requerido, int|float $declarado, string $unidad, ?int $magnitud): ResultadoRegla
    {
        $detalles = ['requerido' => $requerido, 'declarado' => $declarado, 'unidad' => $unidad, 'magnitud' => $magnitud];
        // Tolerate float noise from NUMERIC→float (e.g. 100 × 0.9).
        $cumple = $declarado + 1e-9 >= $requerido;

        return new ResultadoRegla(
            $regla->clave,
            $cumple ? EstadoResultado::Cumple : EstadoResultado::NoCumple,
            sprintf('%s: requerido %s %s, declarado %s %s.', $regla->concepto, self::numero($requerido), $unidad, self::numero($declarado), $unidad),
            $detalles,
        );
    }

    private function noEvaluable(ReglaCapacidad $regla, string $motivo): ResultadoRegla
    {
        return new ResultadoRegla($regla->clave, EstadoResultado::NoEvaluable, "{$regla->concepto}. {$motivo}");
    }

    private static function numero(int|float $valor): string
    {
        return is_int($valor) || fmod($valor, 1.0) === 0.0 ? (string) (int) round($valor) : number_format($valor, 2, '.', '');
    }
}
