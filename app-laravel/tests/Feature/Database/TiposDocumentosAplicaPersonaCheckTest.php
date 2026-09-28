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
}
