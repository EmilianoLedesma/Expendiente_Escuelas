<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Tabla confirmada vacía en dev antes de este cambio — sin riesgo
        // de pérdida de datos al reemplazar la columna directamente.
        DB::statement('ALTER TABLE historial_estados_expediente DROP COLUMN usuario_sedeq');
        DB::statement('ALTER TABLE historial_estados_expediente
            ADD COLUMN usuario_sedeq_id BIGINT NOT NULL REFERENCES users(id)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE historial_estados_expediente DROP COLUMN usuario_sedeq_id');
        DB::statement('ALTER TABLE historial_estados_expediente ADD COLUMN usuario_sedeq VARCHAR(150)');
    }
};
