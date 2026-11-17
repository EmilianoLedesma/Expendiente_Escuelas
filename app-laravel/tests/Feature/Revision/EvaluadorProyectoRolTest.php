<?php

namespace Tests\Feature\Revision;

use App\Application\Revision\CambiarRolSedeq;
use App\Application\Revision\CrearCuentaSedeq;
use App\Application\Revision\RolesSedeq;
use App\Application\Revision\RutaInicio;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class EvaluadorProyectoRolTest extends TestCase
{
    use RefreshDatabase;

    private function usuario(string $rol): User
    {
        (new RolesSeeder)->run();

        return User::factory()->create()->assignRole($rol);
    }

    public function test_el_rol_se_llama_evaluador_proyecto_y_es_un_rol_de_sedeq(): void
    {
        $this->assertSame('evaluador_proyecto', RolesSedeq::EVALUADOR_PROYECTO);
        $this->assertContains(RolesSedeq::EVALUADOR_PROYECTO, RolesSedeq::TODOS);
    }

    public function test_el_seeder_crea_el_rol(): void
    {
        (new RolesSeeder)->run();

        $this->assertDatabaseHas('roles', ['name' => 'evaluador_proyecto', 'guard_name' => 'web']);
    }

    public function test_cuenta_protegida_y_operativa_pero_no_es_supervisor_ni_revisor(): void
    {
        $u = $this->usuario(RolesSedeq::EVALUADOR_PROYECTO);

        $this->assertTrue(RolesSedeq::cuentaProtegida($u));
        $this->assertTrue(RolesSedeq::esSedeqOperativo($u));
        $this->assertFalse($u->hasAnyRole([RolesSedeq::SUPERVISOR, RolesSedeq::REVISOR]));
    }

    public function test_su_inicio_es_la_cola_de_proyectos(): void
    {
        $this->assertSame('/sedeq/proyecto', RutaInicio::para($this->usuario(RolesSedeq::EVALUADOR_PROYECTO)));
    }

    public function test_tramite_y_dashboard_la_redirigen_a_su_inicio(): void
    {
        $u = $this->usuario(RolesSedeq::EVALUADOR_PROYECTO);

        $this->actingAs($u)->get('/tramite')->assertRedirect('/sedeq/proyecto');
        $this->actingAs($u)->get('/dashboard')->assertRedirect('/sedeq/proyecto');
    }

    public function test_solo_ella_entra_a_su_cola(): void
    {
        $this->actingAs($this->usuario(RolesSedeq::EVALUADOR_PROYECTO))->get('/sedeq/proyecto')->assertOk();
        $this->actingAs($this->usuario(RolesSedeq::SUPERVISOR))->get('/sedeq/proyecto')->assertForbidden();
        $this->actingAs($this->usuario(RolesSedeq::REVISOR))->get('/sedeq/proyecto')->assertForbidden();
        $this->actingAs(User::factory()->create())->get('/sedeq/proyecto')->assertForbidden();
    }

    public function test_ella_no_entra_a_las_pantallas_del_supervisor_ni_del_revisor(): void
    {
        $u = $this->usuario(RolesSedeq::EVALUADOR_PROYECTO);

        $this->actingAs($u)->get('/sedeq')->assertForbidden();
        $this->actingAs($u)->get('/sedeq/cola')->assertForbidden();
        $this->actingAs($u)->get('/sedeq/revisores')->assertForbidden();
    }

    public function test_el_admin_puede_crear_su_cuenta(): void
    {
        Notification::fake();
        $admin = $this->usuario(RolesSedeq::ADMIN);

        $nueva = app(CrearCuentaSedeq::class)->ejecutar($admin->id, 'Gabriela Ruiz Espinoza', 'gruiz@queretaro.gob.mx', RolesSedeq::EVALUADOR_PROYECTO);

        $this->assertTrue($nueva->fresh()->hasRole(RolesSedeq::EVALUADOR_PROYECTO));
    }

    public function test_el_admin_puede_asignarle_el_rol_a_una_cuenta_existente(): void
    {
        $admin = $this->usuario(RolesSedeq::ADMIN);
        $otra = User::factory()->create();

        app(CambiarRolSedeq::class)->ejecutar($admin->id, $otra->id, RolesSedeq::EVALUADOR_PROYECTO);

        $this->assertTrue($otra->fresh()->hasRole(RolesSedeq::EVALUADOR_PROYECTO));
    }
}
