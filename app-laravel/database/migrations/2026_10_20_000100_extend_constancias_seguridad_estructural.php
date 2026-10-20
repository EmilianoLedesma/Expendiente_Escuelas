<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Captura fiel de la Constancia de Seguridad Estructural: calidad en que firma el
 * profesional (DRO, Corresponsable en Seguridad Estructural u otra), folio, vigencia
 * tal como la declara el documento (mismo modelo que dictamenes_uso_suelo) y las
 * fechas del registro profesional como columnas explícitas.
 *
 * - `perito_registro_vigencia` (histórica) NO se reinterpreta ni se toca: su
 *   semántica (vencimiento, refrendo o año de registro) nunca se definió y ninguna
 *   regla la evalúa ya. Los datos nuevos van a `perito_registro_fecha` y
 *   `perito_registro_vencimiento`.
 * - `perito_registro_dro` conserva el nombre pero guarda el número de registro
 *   profesional sea cual sea la calidad (renombrarla se difiere: otras ramas pueden leerla).
 * - `vigencia_constancia` ya significaba "fecha que declara la constancia"
 *   (migración 2026_10_12_000200): las filas con fecha pasan a `fecha_expresa`; el
 *   resto queda `no_capturada`, el estado explícito de "no se sabe".
 * Columnas nuevas nullable; ninguna fila existente pierde datos.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE constancias_seguridad_estructural ADD COLUMN calidad_profesional VARCHAR(40)
            CONSTRAINT constancias_calidad_profesional_valida CHECK (calidad_profesional IN (\'dro\', \'corresponsable_seguridad_estructural\', \'otra\'))');
        DB::statement('ALTER TABLE constancias_seguridad_estructural ADD COLUMN calidad_profesional_otra VARCHAR(150)');
        DB::statement('ALTER TABLE constancias_seguridad_estructural ADD CONSTRAINT constancias_calidad_otra_coherente
            CHECK (calidad_profesional_otra IS NULL OR calidad_profesional = \'otra\')');
        DB::statement('ALTER TABLE constancias_seguridad_estructural ADD COLUMN folio_constancia VARCHAR(50)');
        DB::statement('ALTER TABLE constancias_seguridad_estructural ADD COLUMN perito_registro_fecha DATE');
        DB::statement('ALTER TABLE constancias_seguridad_estructural ADD COLUMN perito_registro_vencimiento DATE');

        DB::statement("ALTER TABLE constancias_seguridad_estructural ADD COLUMN tipo_vigencia VARCHAR(20) NOT NULL DEFAULT 'no_capturada'
            CONSTRAINT constancias_tipo_vigencia_valido CHECK (tipo_vigencia IN ('fecha_expresa', 'sin_fecha_expresa', 'no_capturada'))");
        DB::statement("UPDATE constancias_seguridad_estructural SET tipo_vigencia = 'fecha_expresa' WHERE vigencia_constancia IS NOT NULL");
        DB::statement("ALTER TABLE constancias_seguridad_estructural ADD CONSTRAINT constancias_vigencia_coherente
            CHECK ((tipo_vigencia = 'fecha_expresa') = (vigencia_constancia IS NOT NULL))");

        DB::statement("COMMENT ON COLUMN constancias_seguridad_estructural.perito_registro_dro IS 'Número de registro profesional del firmante (DRO o Corresponsable); el nombre de la columna es histórico.'");
        DB::statement("COMMENT ON COLUMN constancias_seguridad_estructural.perito_registro_vigencia IS 'Histórica: semántica no determinada (vencimiento, refrendo o año de registro). Ninguna regla la evalúa; usar perito_registro_fecha y perito_registro_vencimiento.'");
    }

    public function down(): void
    {
        DB::statement('COMMENT ON COLUMN constancias_seguridad_estructural.perito_registro_vigencia IS NULL');
        DB::statement('COMMENT ON COLUMN constancias_seguridad_estructural.perito_registro_dro IS NULL');
        DB::statement('ALTER TABLE constancias_seguridad_estructural DROP CONSTRAINT constancias_vigencia_coherente');
        DB::statement('ALTER TABLE constancias_seguridad_estructural DROP COLUMN tipo_vigencia');
        DB::statement('ALTER TABLE constancias_seguridad_estructural DROP COLUMN perito_registro_vencimiento');
        DB::statement('ALTER TABLE constancias_seguridad_estructural DROP COLUMN perito_registro_fecha');
        DB::statement('ALTER TABLE constancias_seguridad_estructural DROP COLUMN folio_constancia');
        DB::statement('ALTER TABLE constancias_seguridad_estructural DROP CONSTRAINT constancias_calidad_otra_coherente');
        DB::statement('ALTER TABLE constancias_seguridad_estructural DROP COLUMN calidad_profesional_otra');
        DB::statement('ALTER TABLE constancias_seguridad_estructural DROP COLUMN calidad_profesional');
    }
};
