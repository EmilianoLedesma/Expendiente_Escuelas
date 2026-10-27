<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Anexo 2 Instalaciones, D8 (dueño, 2026-10-22): Inicial deja de ofrecer cubículo,
 * cancha de usos múltiples y campo de fútbol (COMPENDIO 204 y 212). Solo toca la
 * matriz niveles_tipos_espacios; nunca las filas capturadas (instalaciones_espacios).
 * Los seeders solo agregan, por eso el retiro en bases ya sembradas es este paso explícito.
 */
return new class extends Migration
{
    private const CLAVES = ['cubiculo', 'cancha_usos_multiples', 'campo_futbol'];

    public function up(): void
    {
        DB::table('niveles_tipos_espacios')
            ->whereIn('nivel_educativo_id', DB::table('niveles_educativos')->where('clave', 'inicial')->select('id'))
            ->whereIn('tipo_espacio_id', DB::table('tipos_espacios')->whereIn('clave', self::CLAVES)->select('id'))
            ->delete();
    }

    public function down(): void
    {
        $nivel = DB::table('niveles_educativos')->where('clave', 'inicial')->value('id');
        $tipos = DB::table('tipos_espacios')->whereIn('clave', self::CLAVES)->pluck('id');

        if ($nivel === null) {
            return;
        }

        DB::table('niveles_tipos_espacios')->insertOrIgnore(
            $tipos->map(fn ($tipo) => ['nivel_educativo_id' => $nivel, 'tipo_espacio_id' => $tipo, 'obligatorio' => false])->all(),
        );
    }
};
