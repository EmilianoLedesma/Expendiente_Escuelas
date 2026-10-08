<?php

namespace Tests\Feature\Database;

use App\Models\ResponsableNivel;
use App\Models\Solicitante;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\Concerns\ConNivelParaResponsables;
use Tests\TestCase;

class ResponsablesNivelTableTest extends TestCase
{
    use ConNivelParaResponsables;
    use RefreshDatabase;

    public function test_roles_seeder_crea_el_rol_responsable_nivel(): void
    {
        (new RolesSeeder)->run();

        $this->assertTrue(Role::where('name', 'responsable_nivel')->exists());
    }

    public function test_borrar_el_nivel_borra_los_accesos_pero_no_la_cuenta(): void
    {
        $nivel = $this->nivelDe(Solicitante::factory()->create());
        $usuario = $this->responsableDe($nivel);

        $nivel->delete();

        $this->assertDatabaseMissing('responsables_nivel', ['user_id' => $usuario->id]);
        $this->assertTrue(User::whereKey($usuario->id)->exists());
    }

    public function test_el_mismo_usuario_no_puede_tener_dos_veces_el_mismo_nivel(): void
    {
        $nivel = $this->nivelDe(Solicitante::factory()->create());
        $usuario = $this->responsableDe($nivel);

        $this->expectException(QueryException::class);

        ResponsableNivel::create([
            'user_id' => $usuario->id,
            'escuela_nivel_id' => $nivel->id,
            'invitado_por_solicitante_id' => $nivel->escuela->solicitante_id,
        ]);
    }
}
