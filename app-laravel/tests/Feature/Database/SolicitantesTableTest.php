<?php

namespace Tests\Feature\Database;

use App\Models\Solicitante;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SolicitantesTableTest extends TestCase
{
    use RefreshDatabase;

    public function test_crea_un_solicitante_ligado_a_un_user(): void
    {
        $user = User::factory()->create();

        $solicitante = Solicitante::create(['user_id' => $user->id]);

        $this->assertDatabaseHas('solicitantes', ['user_id' => $user->id]);
        $this->assertTrue($solicitante->user->is($user));
        $this->assertTrue($user->fresh()->solicitante->is($solicitante));
    }

    public function test_user_id_es_unico_en_solicitantes(): void
    {
        $user = User::factory()->create();
        Solicitante::create(['user_id' => $user->id]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        Solicitante::create(['user_id' => $user->id]);
    }

    public function test_el_backfill_crea_solicitante_para_un_user_preexistente(): void
    {
        // Inserta directo a la tabla `users`, simulando un usuario creado
        // antes de que este código (o el futuro listener de Registered)
        // existiera — sin pasar por Eloquent ni por ningún código de esta
        // feature. Independiente de qué migración corrió más reciente.
        $preexistenteId = DB::table('users')->insertGetId([
            'name' => 'Usuario Preexistente',
            'email' => 'preexistente@example.com',
            'password' => bcrypt('password'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Solicitante::backfillDesdeUsers();

        $this->assertDatabaseHas('solicitantes', ['user_id' => $preexistenteId]);
    }

    public function test_el_backfill_no_duplica_solicitantes_ya_existentes(): void
    {
        $solicitante = Solicitante::factory()->create();

        Solicitante::backfillDesdeUsers();

        $this->assertDatabaseCount('solicitantes', 1);
    }
}
