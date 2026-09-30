<?php

namespace App\Application\Validaciones;

use App\Application\Validaciones\DTO\FilaValidacion;
use App\Domain\Validaciones\Resultado\ReporteValidacion;
use App\Domain\Validaciones\Resultado\ResultadoRegla;
use App\Models\TipoDocumento;

/**
 * Turns the engine's report into rows a person can act on: rule title,
 * the documents to fix (by their catalog name) and one line per compared
 * value. Shared by the final-validation page and its PDF.
 */
class PresentadorValidacion
{
    private const TITULOS = [
        'documentos_requeridos_presentes' => 'Documentos requeridos',
        'nombre_identidad_coincide' => 'Nombre en la identificación y la Constancia de CURP',
        'curp_coincide' => 'CURP',
        'nombre_fiscal_coincide' => 'Nombre o razón social en la Constancia de Situación Fiscal',
        'rfc_coincide' => 'RFC',
        'domicilio_coincide' => 'Domicilio del plantel',
    ];

    private const ESTADOS_DOCUMENTO = [
        'coincide' => 'coincide',
        'difiere' => 'no coincide',
        'formato_invalido' => 'formato no válido',
        'sin_referencia' => 'sin dato declarado para comparar',
    ];

    private const PARTES_DOMICILIO = [
        'domicilio_calle' => 'Calle',
        'domicilio_numero_ext' => 'Número exterior',
        'domicilio_colonia' => 'Colonia',
        'domicilio_municipio' => 'Municipio',
        'domicilio_codigo_postal' => 'Código postal',
    ];

    /** @return list<FilaValidacion> */
    public function filas(ReporteValidacion $reporte): array
    {
        $nombres = TipoDocumento::pluck('nombre', 'clave')->all();
        $nombre = fn (string $clave): string => (string) ($nombres[$clave] ?? $clave);

        return array_map(fn (ResultadoRegla $resultado) => new FilaValidacion(
            clave: $resultado->clave,
            titulo: self::TITULOS[$resultado->clave] ?? $resultado->clave,
            estado: $resultado->estado->value,
            mensaje: $resultado->mensaje,
            documentos: array_combine($resultado->documentos, array_map($nombre, $resultado->documentos)),
            lineas: $this->lineas($resultado, $nombre),
        ), $reporte->resultados);
    }

    /**
     * Capacity engine rows. The title is the rule's concepto; the line says
     * what was required against what was declared; pasoCorreccion points at
     * the Paso 3 sub-step where the declared value lives.
     *
     * @param  array<string, string>  $conceptos  reglas_validacion clave => concepto
     * @return list<FilaValidacion>
     */
    public function filasCapacidad(ReporteValidacion $reporte, array $conceptos): array
    {
        return array_map(function (ResultadoRegla $resultado) use ($conceptos) {
            $d = $resultado->detalles;
            $lineas = [];

            if (isset($d['requerido'], $d['declarado'])) {
                $lineas[] = sprintf('Requerido: %s · Declarado: %s', self::cantidad($d['requerido'], $d['unidad']), self::cantidad($d['declarado'], $d['unidad']));
            }
            foreach ($d['faltantes'] ?? [] as $faltante) {
                $lineas[] = sprintf('%s (%s): se requieren %d, se declararon %d', $faltante['concepto'], self::SALAS[$faltante['sala']] ?? $faltante['sala'], $faltante['requerido'], $faltante['declarado']);
            }
            if (($d['no_evaluados'] ?? []) !== []) {
                $lineas[] = 'No verificados (sala de usos múltiples): '.implode(', ', $d['no_evaluados']);
            }

            return new FilaValidacion(
                clave: $resultado->clave,
                titulo: $conceptos[$resultado->clave] ?? $resultado->clave,
                estado: $resultado->estado->value,
                mensaje: $resultado->mensaje,
                documentos: [],
                lineas: $lineas,
                pasoCorreccion: self::pasoCorreccion($resultado->clave),
            );
        }, $reporte->resultados);
    }

    private const SALAS = [
        'lactantes_a' => 'Lactantes A', 'lactantes_b' => 'Lactantes B', 'lactantes_c' => 'Lactantes C',
        'maternal_a' => 'Maternal A', 'maternal_b' => 'Maternal B',
    ];

    private static function pasoCorreccion(string $clave): string
    {
        return match (true) {
            str_contains($clave, '.personal.') => 'plantilla_docente',
            str_ends_with($clave, '.mobiliario') => 'mobiliario',
            str_ends_with($clave, '.predio_total') || str_ends_with($clave, '.construida_total') => 'inmueble',
            default => 'infraestructura',
        };
    }

    private static function cantidad(int|float $valor, string $unidad): string
    {
        $singular = ['personas' => 'persona', 'títulos' => 'título'];

        return self::numero($valor).' '.((float) $valor === 1.0 ? ($singular[$unidad] ?? $unidad) : $unidad);
    }

    private static function numero(int|float $valor): string
    {
        return is_int($valor) || fmod((float) $valor, 1.0) === 0.0 ? (string) (int) round($valor) : number_format((float) $valor, 2, '.', '');
    }

    /**
     * @param  callable(string): string  $nombre
     * @return list<string>
     */
    private function lineas(ResultadoRegla $resultado, callable $nombre): array
    {
        $detalles = $resultado->detalles;

        if (isset($detalles['faltantes'])) {
            return array_map(fn (string $clave) => 'Falta: '.$nombre($clave), $detalles['faltantes']);
        }

        if (isset($detalles['partes'])) {
            $lineas = [];
            foreach ($detalles['partes'] as $parte => $comparacion) {
                $lineas[] = sprintf(
                    '%s: plantel «%s», certificado «%s» — %s',
                    self::PARTES_DOMICILIO[$parte] ?? $parte,
                    $comparacion['declarado'],
                    $comparacion['documento'] ?? 'sin dato',
                    $comparacion['estado'] === 'coincide' ? 'coincide' : 'no coincide',
                );
            }

            return $lineas;
        }

        $lineas = [];
        if (($detalles['declarado'] ?? null) !== null) {
            $lineas[] = 'Declarado: '.$detalles['declarado'].(($detalles['declarado_invalido'] ?? false) ? ' (formato no válido, no se usó para comparar)' : '');
        }
        foreach ($detalles['documentos'] ?? [] as $clave => $documento) {
            $lineas[] = $documento['estado'] === 'sin_datos'
                ? $nombre($clave).': sin datos capturados — vuelve a subirlo con sus datos'
                : sprintf('%s: %s — %s', $nombre($clave), $documento['valor'], self::ESTADOS_DOCUMENTO[$documento['estado']] ?? $documento['estado']);
        }

        return $lineas;
    }
}
