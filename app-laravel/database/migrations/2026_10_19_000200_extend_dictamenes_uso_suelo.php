<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Vigencia jurídica del Dictamen de Uso de Suelo tal como la declara el
 * documento (la cédula municipal TR-170100-017 dice "la que indique el
 * documento") y condicionantes urbanísticas. `no_capturada` es el estado
 * explícito de los dictámenes ya registrados: ni vigentes ni vencidos.
 * Columnas nuevas; las filas anteriores conservan sus datos y quedan en
 * `no_capturada`.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE dictamenes_uso_suelo ADD COLUMN tipo_vigencia VARCHAR(20) NOT NULL DEFAULT 'no_capturada'
            CONSTRAINT dictamenes_tipo_vigencia_valido CHECK (tipo_vigencia IN ('fecha_expresa', 'sin_fecha_expresa', 'no_capturada'))");
        DB::statement('ALTER TABLE dictamenes_uso_suelo ADD COLUMN fecha_vencimiento_documento DATE');
        DB::statement('ALTER TABLE dictamenes_uso_suelo ADD COLUMN condicionantes TEXT');
        DB::statement("ALTER TABLE dictamenes_uso_suelo ADD CONSTRAINT dictamenes_vigencia_coherente
            CHECK ((tipo_vigencia = 'fecha_expresa') = (fecha_vencimiento_documento IS NOT NULL))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE dictamenes_uso_suelo DROP CONSTRAINT dictamenes_vigencia_coherente');
        DB::statement('ALTER TABLE dictamenes_uso_suelo DROP COLUMN condicionantes');
        DB::statement('ALTER TABLE dictamenes_uso_suelo DROP COLUMN fecha_vencimiento_documento');
        DB::statement('ALTER TABLE dictamenes_uso_suelo DROP COLUMN tipo_vigencia');
    }
};
