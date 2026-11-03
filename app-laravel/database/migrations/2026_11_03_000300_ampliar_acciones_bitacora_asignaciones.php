<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Añade 'desasignar_archivo' (trámite archivado libera su asignación) al CHECK de bitacora_asignaciones.accion. */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared("ALTER TABLE bitacora_asignaciones DROP CONSTRAINT bitacora_asignaciones_accion_check;
ALTER TABLE bitacora_asignaciones ADD CONSTRAINT bitacora_asignaciones_accion_check
    CHECK (accion IN ('asignar','reasignar','desasignar_sobrecarga','desasignar_revocacion','desasignar_archivo'));");
    }

    public function down(): void
    {
        DB::unprepared("DELETE FROM bitacora_asignaciones WHERE accion = 'desasignar_archivo';
ALTER TABLE bitacora_asignaciones DROP CONSTRAINT bitacora_asignaciones_accion_check;
ALTER TABLE bitacora_asignaciones ADD CONSTRAINT bitacora_asignaciones_accion_check
    CHECK (accion IN ('asignar','reasignar','desasignar_sobrecarga','desasignar_revocacion'));");
    }
};
