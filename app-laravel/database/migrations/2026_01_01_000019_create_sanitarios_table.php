<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared('CREATE TABLE sanitarios (
    id                      BIGSERIAL PRIMARY KEY,
    plantel_id              BIGINT NOT NULL REFERENCES planteles(id) ON DELETE CASCADE,
    categoria               VARCHAR(30) NOT NULL
                                CHECK (categoria IN (
                                    \'alumnado_masculino\', \'alumnado_femenino\',
                                    \'personal_masculino\', \'personal_femenino\',
                                    \'alumnado_maternal\', \'personal\'
                                )),
    cantidad_retretes       SMALLINT,
    cantidad_mingitorios    SMALLINT,
    cantidad_lavabos        SMALLINT,
    superficie_m2           NUMERIC(8,2),
    ventilacion_natural     BOOLEAN,
    iluminacion_natural     BOOLEAN,
    created_at              TIMESTAMP NOT NULL DEFAULT now()
);');
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS sanitarios CASCADE');
    }
};
