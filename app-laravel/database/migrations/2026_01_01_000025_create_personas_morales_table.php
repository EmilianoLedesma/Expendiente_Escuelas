<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared('CREATE TABLE personas_morales (
    responsable_legal_id            BIGINT PRIMARY KEY REFERENCES responsables_legales(id) ON DELETE CASCADE,
    razon_social                    VARCHAR(200) NOT NULL,
    numero_escritura_constitutiva   VARCHAR(50),
    fecha_escritura_constitutiva    DATE,
    notario_nombre                  VARCHAR(150),
    notario_numero                  VARCHAR(20),
    notario_ciudad                  VARCHAR(100),
    folio_registro_publico          VARCHAR(50),
    fecha_inscripcion_rpp           DATE,
    nombre_representante_legal      VARCHAR(200) NOT NULL
);');
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS personas_morales CASCADE');
    }
};
