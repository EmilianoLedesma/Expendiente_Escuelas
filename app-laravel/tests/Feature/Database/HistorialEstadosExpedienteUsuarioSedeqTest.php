<?php

namespace Tests\Feature\Database;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class HistorialEstadosExpedienteUsuarioSedeqTest extends TestCase
{
    use RefreshDatabase;

    public function test_usuario_sedeq_id_es_un_fk_obligatorio_a_users(): void
    {
        $columns = collect(DB::select(
            "SELECT column_name, is_nullable FROM information_schema.columns
             WHERE table_name = 'historial_estados_expediente'"
        ))->keyBy('column_name');

        $this->assertFalse($columns->has('usuario_sedeq'), 'La columna vieja usuario_sedeq no debería existir.');
        $this->assertTrue($columns->has('usuario_sedeq_id'), 'Falta la columna usuario_sedeq_id.');
        $this->assertSame('NO', $columns['usuario_sedeq_id']->is_nullable);
    }

    public function test_usuario_sedeq_id_rechaza_un_id_de_usuario_inexistente(): void
    {
        $this->expectException(QueryException::class);

        DB::table('historial_estados_expediente')->insert([
            'escuela_nivel_id' => 999999,
            'estado_id' => 1,
            'usuario_sedeq_id' => 999999,
            'fecha' => now(),
        ]);
    }
}
