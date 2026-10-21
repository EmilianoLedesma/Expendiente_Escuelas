<?php

namespace App\Application\Documentos;

use App\Models\DocumentoEscuela;
use App\Models\DocumentoEscuelaNivel;
use App\Models\DocumentoPlantel;
use App\Models\Escuela;
use Illuminate\Support\Facades\DB;

/**
 * Lectura de los datos estructurados capturados con cada documento (tablas de extensión de los refactors de
 * 2026-09-30 a 2026-10-20), para la vista de solo lectura de SEDEQ (spec docs/specs/2026-10-20-paso2-integracion-end-to-end.md §7.2).
 * No escribe, no evalúa reglas y no inventa valores: un campo NULL de un registro anterior a ese dato se muestra
 * «No capturado», nunca como incumplimiento. En la acreditación de la ocupación, los campos de las otras figuras no
 * aplican y se omiten.
 *
 * Cada fila: etiqueta, valor y, solo en la vigencia, un estado semántico (vigente | vencido | sin_fecha_expresa |
 * no_determinada | no_capturado). La vigencia se describe con la fecha que declara el documento o la que fija una
 * disposición (Protección Civil); la decisión de bloquear sigue en ValidarVigenciaDocumentos.
 */
class MetadatosDocumentales
{
    public const NO_CAPTURADO = 'No capturado';

    /**
     * clave => [tabla, columna de enlace, [columna => etiqueta], omitir vacíos].
     *
     * @var array<string, array{0: string, 1: string, 2: array<string, string>, 3: bool}>
     */
    private const CAMPOS = [
        'ine' => ['credenciales_ine', 'documento_escuela_id', ['nombre' => 'Nombre', 'curp' => 'CURP'], false],
        'constancia_curp' => ['constancias_curp', 'documento_escuela_id', ['nombre' => 'Nombre', 'curp' => 'CURP'], false],
        'constancia_situacion_fiscal' => ['constancias_situacion_fiscal', 'documento_escuela_id', ['nombre_razon_social' => 'Nombre o razón social', 'rfc' => 'RFC'], false],
        'certificado_numero_oficial' => ['certificados_numero_oficial', 'documento_plantel_id', [
            'calle' => 'Calle', 'numero_ext' => 'Número exterior', 'numero_int' => 'Número interior', 'colonia' => 'Colonia',
            'municipio' => 'Municipio', 'codigo_postal' => 'Código postal', 'autoridad_emisora' => 'Autoridad emisora',
            'folio_certificado' => 'Folio', 'clave_catastral' => 'Clave catastral',
        ], false],
        'dictamen_uso_suelo' => ['dictamenes_uso_suelo', 'documento_plantel_id', [
            'autoridad_expide' => 'Autoridad que lo expide', 'folio_dictamen' => 'Folio', 'giro_autorizado' => 'Giro autorizado',
            'condicionantes' => 'Condicionantes',
        ], false],
        'constancia_seguridad_estructural' => ['constancias_seguridad_estructural', 'documento_plantel_id', [
            'perito_nombre' => 'Profesional que firma', 'calidad_profesional' => 'Calidad en que firma', 'calidad_profesional_otra' => 'Calidad (otra)',
            'perito_cedula_profesional' => 'Cédula profesional', 'perito_registro_dro' => 'Número de registro profesional',
            'perito_registro_autoridad' => 'Autoridad del registro', 'perito_registro_fecha' => 'Fecha del registro',
            'perito_registro_vencimiento' => 'Vencimiento del registro', 'folio_constancia' => 'Folio',
        ], false],
        'visto_bueno_proteccion_civil' => ['vistos_buenos_proteccion_civil', 'documento_plantel_id', [
            'tipo_instrumento' => 'Tipo de instrumento', 'tipo_instrumento_otro' => 'Instrumento (otro)', 'autoridad_emisora' => 'Autoridad emisora',
            'jurisdiccion_emisora' => 'Jurisdicción', 'folio_documento' => 'Folio', 'clasificacion_riesgo' => 'Clasificación de riesgo',
            'ejercicio' => 'Año (ejercicio)', 'condicionantes' => 'Condicionantes',
        ], false],
        'escritura_inmueble' => ['acreditaciones_ocupacion_legal', 'documento_plantel_id', [
            'tipo' => 'Figura', 'numero_escritura' => 'Número de escritura', 'fecha_escritura' => 'Fecha de la escritura',
            'propietario_nombre' => 'Propietario', 'notario_nombre' => 'Notario', 'notario_numero' => 'Número de notaría',
            'notario_localidad' => 'Localidad de la notaría (histórico)', 'folio_rpp' => 'Folio del Registro Público', 'fecha_inscripcion_rpp' => 'Inscripción en el Registro Público',
            'arrendador_comodante' => 'Arrendador o comodante', 'arrendatario_comodatario' => 'Arrendatario o comodatario',
            'arrendador_es_propietario' => 'El arrendador es el propietario', 'facultades_referencia' => 'Facultades del arrendador',
            'fecha_contrato' => 'Fecha del contrato', 'fecha_inicio_vigencia' => 'Inicio de vigencia', 'uso_autorizado' => 'Uso autorizado',
            'ratificado_notario' => 'Ratificado ante notario', 'ratificacion_fecha' => 'Fecha de ratificación',
            'otro_especifique' => 'Instrumento', 'otro_referencia' => 'Referencia', 'otro_titular' => 'Titular',
            'otro_fecha_expedicion' => 'Fecha de expedición', 'otro_autoridad' => 'Autoridad', 'otro_derecho' => 'Derecho que otorga',
            'observaciones' => 'Observaciones',
        ], true],
        'recibo_pago_derechos' => ['recibos_pago_derechos', 'documento_escuela_nivel_id', ['folio' => 'Folio', 'monto' => 'Monto', 'fecha_pago' => 'Fecha de pago', 'portal_referencia' => 'Referencia del portal de pago'], false],
        'acervo_bibliografico_primaria' => ['relaciones_acervo_bibliografico', 'documento_escuela_nivel_id', ['numero_titulos' => 'Número de títulos'], false],
        'acervo_bibliografico_secundaria' => ['relaciones_acervo_bibliografico', 'documento_escuela_nivel_id', ['numero_titulos' => 'Número de títulos'], false],
    ];

    /** @var array<string, array<string, string>> valores de catálogo => texto */
    private const TEXTOS = [
        'calidad_profesional' => ['dro' => 'Director Responsable de Obra', 'corresponsable_seguridad_estructural' => 'Corresponsable en Seguridad Estructural', 'otra' => 'Otra'],
        'tipo_instrumento' => ['visto_bueno' => 'Visto Bueno', 'dictamen' => 'Dictamen', 'otro' => 'Otro'],
        'jurisdiccion_emisora' => ['municipio_queretaro' => 'Municipio de Querétaro', 'otra' => 'Otra autoridad'],
        'tipo' => ['escritura_publica' => 'Escritura pública', 'arrendamiento' => 'Arrendamiento', 'comodato' => 'Comodato', 'otro' => 'Otro instrumento'],
    ];

    /**
     * Documentos generales (escuela y plantel) de la escuela, por clave.
     *
     * @param  list<string>  $claves  las que aplican al tipo de persona (DocumentosCompletos::clavesAplicables)
     * @return array<string, list<array{etiqueta: string, valor: string, estado: string|null}>>
     */
    public function paraEscuela(int $escuelaId, array $claves): array
    {
        $plantelId = (int) Escuela::whereKey($escuelaId)->value('plantel_id');

        $documentos = [
            ...DocumentoEscuela::query()->join('tipos_documentos', 'tipos_documentos.id', '=', 'documentos_escuela.tipo_documento_id')
                ->where('documentos_escuela.escuela_id', $escuelaId)->whereIn('tipos_documentos.clave', $claves)
                ->get(['documentos_escuela.id', 'tipos_documentos.clave', 'documentos_escuela.fecha_emision'])->all(),
            ...DocumentoPlantel::query()->join('tipos_documentos', 'tipos_documentos.id', '=', 'documentos_plantel.tipo_documento_id')
                ->where('documentos_plantel.plantel_id', $plantelId)->whereIn('tipos_documentos.clave', $claves)
                ->get(['documentos_plantel.id', 'tipos_documentos.clave', 'documentos_plantel.fecha_emision'])->all(),
        ];

        $resultado = [];
        foreach ($documentos as $documento) {
            $resultado[(string) $documento->getAttribute('clave')] = $this->filas((string) $documento->getAttribute('clave'), (int) $documento->getKey(), $documento->getAttribute('fecha_emision'));
        }

        return $resultado;
    }

    /** @return array<string, list<array{etiqueta: string, valor: string, estado: string|null}>> */
    public function paraEscuelaNivel(int $escuelaNivelId): array
    {
        $resultado = [];
        $documentos = DocumentoEscuelaNivel::query()->join('tipos_documentos', 'tipos_documentos.id', '=', 'documentos_escuela_nivel.tipo_documento_id')
            ->where('documentos_escuela_nivel.escuela_nivel_id', $escuelaNivelId)
            ->get(['documentos_escuela_nivel.id', 'tipos_documentos.clave', 'documentos_escuela_nivel.fecha_emision']);

        foreach ($documentos as $documento) {
            $resultado[(string) $documento->getAttribute('clave')] = $this->filas((string) $documento->getAttribute('clave'), (int) $documento->getKey(), $documento->getAttribute('fecha_emision'));
        }

        return $resultado;
    }

    /** @return list<array{etiqueta: string, valor: string, estado: string|null}> */
    private function filas(string $clave, int $documentoId, mixed $fechaEmision): array
    {
        $filas = [];

        if (! isset(self::CAMPOS[$clave])) {
            return $fechaEmision === null ? [] : [self::fila('Fecha de emisión', self::fecha((string) $fechaEmision))];
        }

        [$tabla, $enlace, $campos, $omitirVacios] = self::CAMPOS[$clave];
        $registro = DB::table($tabla)->where($enlace, $documentoId)->first();

        if ($fechaEmision !== null || in_array($clave, ['dictamen_uso_suelo', 'constancia_seguridad_estructural', 'visto_bueno_proteccion_civil', 'certificado_numero_oficial'], true)) {
            $filas[] = self::fila($clave === 'certificado_numero_oficial' ? 'Fecha de expedición' : 'Fecha de emisión', $fechaEmision === null ? self::NO_CAPTURADO : self::fecha((string) $fechaEmision));
        }

        if ($registro === null) {
            // Documento sin su fila de datos (capturado antes de que existieran): nada que mostrar salvo eso.
            return [...$filas, self::fila('Datos del documento', self::NO_CAPTURADO)];
        }

        foreach ($campos as $columna => $etiqueta) {
            $valor = $registro->{$columna} ?? null;
            if ($valor === null && $omitirVacios) {
                continue;
            }
            $filas[] = self::fila($etiqueta, self::texto($columna, $valor));
        }

        return [...$filas, ...$this->filasGeograficas($clave, $registro), ...$this->vigencia($clave, $registro)];
    }

    /** Entidad y municipio del catálogo cuando el documento los referencia (ADR-017). @return list<array{etiqueta: string, valor: string, estado: string|null}> */
    private function filasGeograficas(string $clave, object $registro): array
    {
        [$entidad, $municipio, $prefijo] = match ($clave) {
            'certificado_numero_oficial' => [$registro->entidad_federativa_id ?? null, $registro->municipio_id ?? null, ''],
            'escritura_inmueble' => [$registro->notario_entidad_federativa_id ?? null, $registro->notario_municipio_id ?? null, ' de la notaría'],
            default => [null, null, ''],
        };

        if ($entidad === null && $municipio === null) {
            return [];
        }

        return [
            self::fila('Entidad federativa'.$prefijo.' (catálogo)', (string) (DB::table('entidades_federativas')->where('id', $entidad)->value('nombre') ?? self::NO_CAPTURADO)),
            self::fila('Municipio'.$prefijo.' (catálogo)', (string) (DB::table('municipios')->where('id', $municipio)->value('nombre') ?? self::NO_CAPTURADO)),
        ];
    }

    /** @return list<array{etiqueta: string, valor: string, estado: string|null}> */
    private function vigencia(string $clave, object $registro): array
    {
        return match ($clave) {
            'dictamen_uso_suelo' => [self::declarada($registro->tipo_vigencia ?? null, $registro->fecha_vencimiento_documento ?? null)],
            'constancia_seguridad_estructural' => [self::declarada($registro->tipo_vigencia ?? null, $registro->vigencia_constancia ?? null)],
            'visto_bueno_proteccion_civil' => $this->vigenciaProteccionCivil($registro),
            'escritura_inmueble' => in_array($registro->tipo ?? null, ['arrendamiento', 'comodato'], true) ? [$this->vigenciaContrato($registro)] : [],
            default => [],
        };
    }

    /** @return list<array{etiqueta: string, valor: string, estado: string|null}> */
    private function vigenciaProteccionCivil(object $registro): array
    {
        $filas = [self::declarada($registro->tipo_vigencia ?? null, $registro->fecha_vencimiento_documento ?? null)];

        if (($registro->fecha_vencimiento_normativo ?? null) !== null) {
            $fecha = (string) $registro->fecha_vencimiento_normativo;
            $filas[] = self::fila('Vencimiento normativo', self::fecha($fecha).' ('.($registro->regimen_normativo ?? 'régimen no indicado').')', $fecha < now()->toDateString() ? 'vencido' : 'vigente');
        }

        return $filas;
    }

    /** @return array{etiqueta: string, valor: string, estado: string|null} */
    private function vigenciaContrato(object $registro): array
    {
        if (($registro->vigencia_indeterminada ?? null) === true) {
            return self::fila('Vigencia del contrato', 'Indeterminada', 'sin_fecha_expresa');
        }

        $fin = $registro->vigencia_contrato ?? null;
        if ($fin === null) {
            return self::fila('Vigencia del contrato', self::NO_CAPTURADO, 'no_capturado');
        }

        return self::fila('Vigencia del contrato', 'Hasta el '.self::fecha((string) $fin), (string) $fin < now()->toDateString() ? 'vencido' : 'vigente');
    }

    /** Vigencia tal como la declara el documento (modelo tipo_vigencia de los refactors de 2026-10-19/20). @return array{etiqueta: string, valor: string, estado: string|null} */
    private static function declarada(?string $tipo, ?string $fecha): array
    {
        return match (true) {
            $tipo === 'fecha_expresa' && $fecha !== null => self::fila('Vigencia declarada', 'Hasta el '.self::fecha($fecha), $fecha < now()->toDateString() ? 'vencido' : 'vigente'),
            $tipo === 'sin_fecha_expresa' => self::fila('Vigencia declarada', 'El documento no indica fecha de vencimiento', 'sin_fecha_expresa'),
            default => self::fila('Vigencia declarada', 'No capturada (registro anterior a este dato)', 'no_determinada'),
        };
    }

    private static function texto(string $columna, mixed $valor): string
    {
        return match (true) {
            $valor === null || $valor === '' => self::NO_CAPTURADO,
            is_bool($valor) => $valor ? 'Sí' : 'No',
            $columna === 'monto' && is_numeric($valor) => '$'.number_format((float) $valor, 2),
            isset(self::TEXTOS[$columna][(string) $valor]) => self::TEXTOS[$columna][(string) $valor],
            preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $valor) === 1 => self::fecha((string) $valor),
            default => (string) $valor,
        };
    }

    private static function fecha(string $fecha): string
    {
        return date('d/m/Y', (int) strtotime($fecha));
    }

    /** @return array{etiqueta: string, valor: string, estado: string|null} */
    private static function fila(string $etiqueta, string $valor, ?string $estado = null): array
    {
        return ['etiqueta' => $etiqueta, 'valor' => $valor, 'estado' => $estado];
    }
}
