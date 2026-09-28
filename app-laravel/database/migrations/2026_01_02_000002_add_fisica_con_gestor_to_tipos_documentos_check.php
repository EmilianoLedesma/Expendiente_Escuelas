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
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE tipos_documentos DROP CONSTRAINT IF EXISTS tipos_documentos_aplica_persona_check');
        DB::statement("ALTER TABLE tipos_documentos ADD CONSTRAINT tipos_documentos_aplica_persona_check CHECK (aplica_persona IN ('fisica', 'moral', 'ambas'))");
    }
};
