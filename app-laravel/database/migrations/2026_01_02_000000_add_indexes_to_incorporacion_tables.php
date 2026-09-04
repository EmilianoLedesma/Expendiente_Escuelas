<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE INDEX idx_escuela_niveles_nivel_educativo_id ON escuela_niveles (nivel_educativo_id)');
        DB::statement('CREATE INDEX idx_escuela_niveles_estado_id ON escuela_niveles (estado_id)');

        DB::statement('CREATE INDEX idx_documentos_plantel_plantel_id ON documentos_plantel (plantel_id)');
        DB::statement('CREATE INDEX idx_documentos_plantel_tipo_documento_id ON documentos_plantel (tipo_documento_id)');

        DB::statement('CREATE INDEX idx_documentos_escuela_escuela_id ON documentos_escuela (escuela_id)');
        DB::statement('CREATE INDEX idx_documentos_escuela_tipo_documento_id ON documentos_escuela (tipo_documento_id)');

        DB::statement('CREATE INDEX idx_documentos_escuela_nivel_escuela_nivel_id ON documentos_escuela_nivel (escuela_nivel_id)');
        DB::statement('CREATE INDEX idx_documentos_escuela_nivel_tipo_documento_id ON documentos_escuela_nivel (tipo_documento_id)');

        DB::statement('CREATE INDEX idx_personal_escuela_nivel_id ON personal (escuela_nivel_id)');
        DB::statement('CREATE INDEX idx_personal_cargo_puesto_id ON personal (cargo_puesto_id)');

        DB::statement('CREATE INDEX idx_instalaciones_espacios_plantel_id ON instalaciones_espacios (plantel_id)');
        DB::statement('CREATE INDEX idx_instalaciones_espacios_tipo_espacio_id ON instalaciones_espacios (tipo_espacio_id)');

        DB::statement('CREATE INDEX idx_historial_estados_expediente_escuela_nivel_id ON historial_estados_expediente (escuela_nivel_id)');
        DB::statement('CREATE INDEX idx_historial_estados_expediente_estado_id ON historial_estados_expediente (estado_id)');

        DB::statement('CREATE INDEX idx_mobiliario_nivel_concepto_id ON mobiliario_nivel (concepto_id)');
        DB::statement('CREATE INDEX idx_matricula_grados_grado_id ON matricula_grados (grado_id)');
        DB::statement('CREATE INDEX idx_matricula_salas_sala_id ON matricula_salas (sala_id)');

        DB::statement('CREATE INDEX idx_perfiles_profesionales_cargo_puesto_id ON perfiles_profesionales (cargo_puesto_id)');
        DB::statement('CREATE INDEX idx_perfiles_profesionales_asignatura_id ON perfiles_profesionales (asignatura_id)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS idx_escuela_niveles_nivel_educativo_id');
        DB::statement('DROP INDEX IF EXISTS idx_escuela_niveles_estado_id');

        DB::statement('DROP INDEX IF EXISTS idx_documentos_plantel_plantel_id');
        DB::statement('DROP INDEX IF EXISTS idx_documentos_plantel_tipo_documento_id');

        DB::statement('DROP INDEX IF EXISTS idx_documentos_escuela_escuela_id');
        DB::statement('DROP INDEX IF EXISTS idx_documentos_escuela_tipo_documento_id');

        DB::statement('DROP INDEX IF EXISTS idx_documentos_escuela_nivel_escuela_nivel_id');
        DB::statement('DROP INDEX IF EXISTS idx_documentos_escuela_nivel_tipo_documento_id');

        DB::statement('DROP INDEX IF EXISTS idx_personal_escuela_nivel_id');
        DB::statement('DROP INDEX IF EXISTS idx_personal_cargo_puesto_id');

        DB::statement('DROP INDEX IF EXISTS idx_instalaciones_espacios_plantel_id');
        DB::statement('DROP INDEX IF EXISTS idx_instalaciones_espacios_tipo_espacio_id');

        DB::statement('DROP INDEX IF EXISTS idx_historial_estados_expediente_escuela_nivel_id');
        DB::statement('DROP INDEX IF EXISTS idx_historial_estados_expediente_estado_id');

        DB::statement('DROP INDEX IF EXISTS idx_mobiliario_nivel_concepto_id');
        DB::statement('DROP INDEX IF EXISTS idx_matricula_grados_grado_id');
        DB::statement('DROP INDEX IF EXISTS idx_matricula_salas_sala_id');

        DB::statement('DROP INDEX IF EXISTS idx_perfiles_profesionales_cargo_puesto_id');
        DB::statement('DROP INDEX IF EXISTS idx_perfiles_profesionales_asignatura_id');
    }
};
