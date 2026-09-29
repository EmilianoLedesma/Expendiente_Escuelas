<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Extiende tipos_documentos.aplica_persona para permitir 'fisica_con_gestor'
 * — responsables_legales.tipo_persona ya acepta ese valor (D2, WS-5a); esta
 * tabla nunca pudo expresarlo. Sin esto, el documento del gestor
 * (poder_gestor) no tiene forma de aplicarse solo a escuelas con gestor.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE tipos_documentos DROP CONSTRAINT IF EXISTS tipos_documentos_aplica_persona_check');
        DB::statement("ALTER TABLE tipos_documentos ADD CONSTRAINT tipos_documentos_aplica_persona_check CHECK (aplica_persona IN ('fisica', 'moral', 'ambas', 'fisica_con_gestor'))");

        // D2: TiposDocumentosSeeder usa insertOrIgnore por clave, así que una BD ya
        // sembrada conservaría acta_nacimiento como 'fisica'. No-op en BD nueva.
        DB::table('tipos_documentos')->where('clave', 'acta_nacimiento')->update(['aplica_persona' => 'ambas']);
    }

    public function down(): void
    {
        // Falla si ya existe alguna fila 'fisica_con_gestor' (Postgres revalida el
        // CHECK viejo): para revertir, borra esas filas primero.
        DB::statement('ALTER TABLE tipos_documentos DROP CONSTRAINT IF EXISTS tipos_documentos_aplica_persona_check');
        DB::statement("ALTER TABLE tipos_documentos ADD CONSTRAINT tipos_documentos_aplica_persona_check CHECK (aplica_persona IN ('fisica', 'moral', 'ambas'))");
    }
};
