<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Sección 11 (Formación Docente), bloque "Formación Docente Continua": la planeación del curso o de las acciones es un PDF
 * de 1 a 2 páginas. Se guarda en la fila de la política del tipo 'continua' (ruta, nombre original, páginas: los tres o
 * ninguno). El archivo vive en el disco `documentos`; borrarlo al purgar el trámite o quitar el nivel es trabajo de
 * PurgarTramite y RegistrarNivelesSeleccionados (el cascade solo borra la fila).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared('ALTER TABLE proyecto_educativo_formacion_politica
    ADD COLUMN planeacion_path     VARCHAR(255) NULL,
    ADD COLUMN planeacion_nombre   VARCHAR(255) NULL,
    ADD COLUMN planeacion_paginas  SMALLINT NULL,
    ADD CONSTRAINT pe_formacion_politica_planeacion_completa CHECK (
        (planeacion_path IS NULL AND planeacion_nombre IS NULL AND planeacion_paginas IS NULL)
        OR (planeacion_path IS NOT NULL AND planeacion_nombre IS NOT NULL AND planeacion_paginas BETWEEN 1 AND 2));');
    }

    public function down(): void
    {
        DB::unprepared('ALTER TABLE proyecto_educativo_formacion_politica
    DROP CONSTRAINT IF EXISTS pe_formacion_politica_planeacion_completa,
    DROP COLUMN IF EXISTS planeacion_path,
    DROP COLUMN IF EXISTS planeacion_nombre,
    DROP COLUMN IF EXISTS planeacion_paginas;');
    }
};
