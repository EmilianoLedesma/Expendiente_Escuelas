<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Entidad federativa y municipio del certificado de número oficial como referencias al catálogo geográfico
 * local (ADR-017), el mismo que usa la notaría de la escritura. La columna de texto `municipio` se conserva
 * (la usan el motor de validación y las filas anteriores) y desde ahora se deriva del catálogo en el servidor.
 * Columnas nuevas y nullable: los certificados ya capturados no se tocan. Colonia y código postal siguen
 * siendo texto: no hay un catálogo oficial de INEGI para ellos (los códigos postales son de Correos de México).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE certificados_numero_oficial ADD COLUMN entidad_federativa_id BIGINT REFERENCES entidades_federativas(id) ON DELETE SET NULL');
        DB::statement('ALTER TABLE certificados_numero_oficial ADD COLUMN municipio_id BIGINT REFERENCES municipios(id) ON DELETE SET NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE certificados_numero_oficial DROP COLUMN municipio_id');
        DB::statement('ALTER TABLE certificados_numero_oficial DROP COLUMN entidad_federativa_id');
    }
};
