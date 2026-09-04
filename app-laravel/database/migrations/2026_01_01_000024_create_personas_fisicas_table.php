<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared('CREATE TABLE personas_fisicas (
    responsable_legal_id    BIGINT PRIMARY KEY REFERENCES responsables_legales(id) ON DELETE CASCADE,
    nombre                  VARCHAR(200) NOT NULL,
    fecha_nacimiento        DATE,
    rfc                     VARCHAR(13),
    curp                    VARCHAR(18)
);');
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS personas_fisicas CASCADE');
    }
};
