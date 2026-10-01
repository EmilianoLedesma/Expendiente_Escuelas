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
        'documentos_nivel_presentes' => 'Documentos del nivel',
        'recibo_no_reutilizado' => 'Recibo de pago no usado en otro nivel',
        'acervo_coincide' => 'Títulos del acervo bibliográfico',
        'inventario_con_laboratorio' => 'Inventario y laboratorio declarado',
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
     * @param  callable(string): string  $nombre
     * @return list<string>
     */
    private function lineas(ResultadoRegla $resultado, callable $nombre): array
    {
        $detalles = $resultado->detalles;

        if (isset($detalles['faltantes'])) {
            return array_map(fn (string $clave) => 'Falta: '.$nombre($clave), $detalles['faltantes']);
        }

        if (isset($detalles['folio'])) {
            return ['Folio del recibo: '.$detalles['folio']];
        }

        if (isset($detalles['laboratorios'])) {
            return ['Laboratorios declarados en la infraestructura: '.$detalles['laboratorios']];
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
