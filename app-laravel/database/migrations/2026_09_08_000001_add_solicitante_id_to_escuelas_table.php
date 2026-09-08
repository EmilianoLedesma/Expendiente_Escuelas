<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('escuelas', function (Blueprint $table) {
            $table->foreignId('solicitante_id')
                ->after('plantel_id')
                // RESTRICT, no CASCADE: una escuela es un expediente real,
                // no metadata desechable — eliminar un solicitante nunca
                // debe borrar en silencio los trámites que posee.
                ->constrained('solicitantes')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('escuelas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('solicitante_id');
        });
    }
};
