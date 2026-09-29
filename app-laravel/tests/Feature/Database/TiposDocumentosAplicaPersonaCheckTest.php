<?php

namespace Tests\Feature\Database;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TiposDocumentosAplicaPersonaCheckTest extends TestCase
{
    use RefreshDatabase;

    public function test_acepta_fisica_con_gestor(): void
    {
        DB::table('tipos_documentos')->insert([
            'clave' => 'test_fisica_con_gestor',
            'nombre' => 'Test',
            'aplica_persona' => 'fisica_con_gestor',
            'ambito' => 'plantel',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertDatabaseHas('tipos_documentos', ['clave' => 'test_fisica_con_gestor', 'aplica_persona' => 'fisica_con_gestor']);
    }

    public function test_sigue_rechazando_un_valor_invalido(): void
    {
        $this->expectException(QueryException::class);

        DB::table('tipos_documentos')->insert([
            'clave' => 'test_invalido',
            'nombre' => 'Test',
            'aplica_persona' => 'no_es_un_valor_valido',
            'ambito' => 'plantel',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    // D2: el seeder usa insertOrIgnore por clave, así que una BD ya sembrada
    // conserva acta_nacimiento como 'fisica'; la migración la corrige.
    public function test_la_migracion_corrige_acta_nacimiento_a_ambas_en_una_bd_ya_sembrada(): void
    {
        DB::table('tipos_documentos')->insert([
            'clave' => 'acta_nacimiento',
            'nombre' => 'Acta de nacimiento',
            'aplica_persona' => 'fisica',
            'ambito' => 'escuela',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        (require database_path('migrations/2026_01_02_000002_add_fisica_con_gestor_to_tipos_documentos_check.php'))->up();

        $this->assertDatabaseHas('tipos_documentos', ['clave' => 'acta_nacimiento', 'aplica_persona' => 'ambas']);
    }
}
