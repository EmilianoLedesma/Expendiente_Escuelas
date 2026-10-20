<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Datos que el Certificado de Número Oficial puede traer además del domicilio: autoridad que lo expide,
 * folio, número interior y clave catastral. La cédula municipal TR-170200-013 no publica los campos
 * impresos del certificado, así que todos son nullable y opcionales: los registros ya capturados
 * conservan su domicilio y quedan con NULL. La fecha de expedición usa documentos_plantel.fecha_emision
 * (ya existe). No hay vencimiento: el certificado del Municipio de Querétaro es permanente.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE certificados_numero_oficial ADD COLUMN numero_int VARCHAR(20)');
        DB::statement('ALTER TABLE certificados_numero_oficial ADD COLUMN autoridad_emisora VARCHAR(200)');
        DB::statement('ALTER TABLE certificados_numero_oficial ADD COLUMN folio_certificado VARCHAR(50)');
        DB::statement('ALTER TABLE certificados_numero_oficial ADD COLUMN clave_catastral VARCHAR(40)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE certificados_numero_oficial DROP COLUMN clave_catastral');
        DB::statement('ALTER TABLE certificados_numero_oficial DROP COLUMN folio_certificado');
        DB::statement('ALTER TABLE certificados_numero_oficial DROP COLUMN autoridad_emisora');
        DB::statement('ALTER TABLE certificados_numero_oficial DROP COLUMN numero_int');
    }
};
