<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * 3FN: el domicilio de la Presentación del proyecto educativo ya vive, por partes, en `planteles` (calle, números,
 * colonia, localidad, municipio, código postal). La sección lo lee de ahí; guardarlo otra vez como un solo texto
 * duplicaba el dato y podía desfasarse. Se descarta la columna `domicilio_completo`.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared('ALTER TABLE proyecto_educativo_presentacion DROP COLUMN IF EXISTS domicilio_completo');
    }

    public function down(): void
    {
        DB::unprepared('ALTER TABLE proyecto_educativo_presentacion ADD COLUMN IF NOT EXISTS domicilio_completo VARCHAR(500) NULL');
    }
};
