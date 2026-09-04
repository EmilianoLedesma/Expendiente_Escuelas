<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared('CREATE TABLE gestores (
    responsable_legal_id    BIGINT PRIMARY KEY REFERENCES responsables_legales(id) ON DELETE CASCADE,
    nombre                  VARCHAR(200) NOT NULL,
    numero_poder            VARCHAR(50),
    notario_nombre          VARCHAR(150),
    notario_numero          VARCHAR(20),
    fecha_poder             DATE,
    created_at              TIMESTAMP NOT NULL DEFAULT now()
);');
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS gestores CASCADE');
    }
};
