<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Datos del Visto Bueno / Dictamen de Protección Civil (tabla de extensión de
 * documentos_plantel, mismo patrón que dictamenes_uso_suelo y
 * constancias_seguridad_estructural) y retiro de la caducidad calculada a 30 días.
 *
 * Los 30 días eran una antigüedad administrativa sin fundamento: el Reglamento de PC del
 * Municipio de Querétaro de 2017 (art. 46) y las cédulas TR-140600-007/-008/-009 fijan año
 * calendario hasta el 31 de diciembre; los "30 días naturales" eran la Carta Compromiso del
 * reglamento de 2008, abrogado (COMPENDIO_MAESTRO §4 y §9, 2026-10-20).
 *
 * - `fecha_vencimiento_documento` es lo que declara el documento; `fecha_vencimiento_normativo`
 *   es la que fija una disposición, siempre con su `regimen_normativo` de origen.
 * - Los documentos ya capturados reciben una fila `no_capturada` y conservan su fecha calculada
 *   en `fecha_calculada_30_dias_historica` (NO verificada, no es un vencimiento). PDF, fecha de
 *   emisión y demás metadatos no se tocan. `down()` restaura los 30 días y esa fecha.
 */
return new class extends Migration
{
    private const CLAVE = 'visto_bueno_proteccion_civil';

    public function up(): void
    {
        DB::unprepared("CREATE TABLE vistos_buenos_proteccion_civil (
    documento_plantel_id               BIGINT PRIMARY KEY REFERENCES documentos_plantel(id) ON DELETE CASCADE,
    tipo_instrumento                   VARCHAR(20)
        CONSTRAINT vb_pc_tipo_instrumento_valido CHECK (tipo_instrumento IN ('visto_bueno', 'dictamen', 'otro')),
    tipo_instrumento_otro              VARCHAR(150),
    autoridad_emisora                  VARCHAR(200),
    jurisdiccion_emisora               VARCHAR(30)
        CONSTRAINT vb_pc_jurisdiccion_valida CHECK (jurisdiccion_emisora IN ('municipio_queretaro', 'otra')),
    folio_documento                    VARCHAR(50),
    clasificacion_riesgo               VARCHAR(20)
        CONSTRAINT vb_pc_riesgo_valido CHECK (clasificacion_riesgo IN ('alto', 'medio', 'bajo', 'no_indicado')),
    ejercicio                          SMALLINT
        CONSTRAINT vb_pc_ejercicio_valido CHECK (ejercicio BETWEEN 2017 AND 2100),
    tipo_vigencia                      VARCHAR(20) NOT NULL DEFAULT 'no_capturada'
        CONSTRAINT vb_pc_tipo_vigencia_valido CHECK (tipo_vigencia IN ('fecha_expresa', 'sin_fecha_expresa', 'no_capturada')),
    fecha_vencimiento_documento        DATE,
    regimen_normativo                  VARCHAR(40),
    fecha_vencimiento_normativo        DATE,
    condicionantes                     TEXT,
    fecha_calculada_30_dias_historica  DATE,
    CONSTRAINT vb_pc_vigencia_coherente CHECK ((tipo_vigencia = 'fecha_expresa') = (fecha_vencimiento_documento IS NOT NULL)),
    CONSTRAINT vb_pc_instrumento_otro_coherente CHECK (tipo_instrumento_otro IS NULL OR tipo_instrumento = 'otro'),
    CONSTRAINT vb_pc_normativo_con_regimen CHECK (fecha_vencimiento_normativo IS NULL OR regimen_normativo IS NOT NULL)
);");

        $documentos = DB::table('documentos_plantel')
            ->whereIn('tipo_documento_id', DB::table('tipos_documentos')->where('clave', self::CLAVE)->select('id'));

        foreach ((clone $documentos)->get(['id', 'fecha_vigencia']) as $documento) {
            DB::table('vistos_buenos_proteccion_civil')->insert([
                'documento_plantel_id' => $documento->id,
                'tipo_vigencia' => 'no_capturada',
                'fecha_calculada_30_dias_historica' => $documento->fecha_vigencia,
            ]);
        }

        $documentos->update(['fecha_vigencia' => null]);
        DB::table('tipos_documentos')->where('clave', self::CLAVE)->update(['vigencia_max_dias' => null, 'updated_at' => now()]);
    }

    public function down(): void
    {
        DB::table('tipos_documentos')->where('clave', self::CLAVE)->update(['vigencia_max_dias' => 30, 'updated_at' => now()]);

        DB::statement("UPDATE documentos_plantel SET fecha_vigencia = COALESCE(
                (SELECT v.fecha_calculada_30_dias_historica FROM vistos_buenos_proteccion_civil v WHERE v.documento_plantel_id = documentos_plantel.id),
                fecha_emision + INTERVAL '30 days')
            WHERE tipo_documento_id IN (SELECT id FROM tipos_documentos WHERE clave = ?)
              AND fecha_emision IS NOT NULL", [self::CLAVE]);

        DB::statement('DROP TABLE IF EXISTS vistos_buenos_proteccion_civil');
    }
};
