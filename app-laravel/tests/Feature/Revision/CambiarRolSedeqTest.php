<?php

namespace Tests\Feature\Revision;

use App\Application\Archivo\RegistrarAccion;
use App\Application\Excepciones\DatosInvalidos;
use App\Application\Excepciones\PrecondicionIncumplida;
use App\Application\Revision\AsignarTramite;
use App\Application\Revision\CambiarRolSedeq;
use App\Application\Revision\RolesSedeq;
use App\Models\Revisor;
use App\Models\Solicitante;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\Concerns\ConTramitesEnRevision;
use Tests\TestCase;

class CambiarRolSedeqTest extends TestCase
{
    use ConTramitesEnRevision;
    use RefreshDatabase;

    private function admin(): User
    {
        (new RolesSeeder)->run();

        return User::factory()->create()->assignRole(RolesSedeq::ADMIN);
    }

    private function cambiar(User $actor, User $objetivo, ?string $rol): void
    {
        app(CambiarRolSedeq::class)->ejecutar($actor->id, $objetivo->id, $rol);
    }

    private function refusePrecondicion(User $actor, User $objetivo, ?string $rol, string $etapa): void
    {
        try {
            $this->cambiar($actor, $objetivo, $rol);
            $this->fail('Se esperaba PrecondicionIncumplida.');
        } catch (PrecondicionIncumplida $e) {
            $this->assertSame($etapa, $e->etapaFaltante);
        }
        $this->assertSame(0, DB::table('bitacora_acciones')->where('accion', RegistrarAccion::CAMBIAR_ROL)->count());
    }

    public function test_el_admin_cambia_revisor_a_supervisor_y_lo_registra(): void
    {
        $admin = $this->admin();
        $rev = $this->revisor();

        $this->cambiar($admin, $rev, RolesSedeq::SUPERVISOR);

        $rev = $rev->fresh();
        $this->assertTrue($rev->hasRole(RolesSedeq::SUPERVISOR));
        $this->assertFalse($rev->hasRole(RolesSedeq::REVISOR));
        $fila = DB::table('bitacora_acciones')->where('accion', RegistrarAccion::CAMBIAR_ROL)->first();
        $this->assertSame($admin->id, (int) $fila->actor_id);
        $this->assertSame('user', $fila->objeto_tipo);
        $this->assertSame($rev->id, (int) $fila->objeto_id);
        $detalle = json_decode($fila->detalle, true);
        $this->assertSame('revisor', $detalle['de']);
        $this->assertSame('supervisor', $detalle['a']);
    }

    public function test_revisor_a_otro_rol_libera_asignaciones_y_marca_revocado(): void
    {
        $admin = $this->admin();
        $sup = $this->supervisor();
        $rev = $this->revisor();
        $nivel = $this->nivelEnRevision();
        app(AsignarTramite::class)->ejecutar($sup->id, $nivel->id, $rev->id);

        $this->cambiar($admin, $rev, RolesSedeq::SUPERVISOR);

        $this->assertSame(0, DB::table('asignaciones_revision')->count());
        $this->assertNotNull(Revisor::findOrFail($rev->id)->revocado_at);
        $this->assertSame(1, DB::table('bitacora_asignaciones')->where('accion', 'desasignar_revocacion')->count());
    }

    public function test_otro_rol_a_revisor_crea_la_fila_y_reactiva_al_revocado(): void
    {
        $admin = $this->admin();
        $sup = $this->supervisor();

        $this->cambiar($admin, $sup, RolesSedeq::REVISOR);
        $this->assertTrue($sup->fresh()->hasRole(RolesSedeq::REVISOR));
        $this->assertNull(Revisor::findOrFail($sup->id)->revocado_at);

        $this->cambiar($admin, $sup, RolesSedeq::SUPERVISOR);
        $this->assertNotNull(Revisor::findOrFail($sup->id)->revocado_at);

        $this->cambiar($admin, $sup, RolesSedeq::REVISOR);
        $this->assertNull(Revisor::findOrFail($sup->id)->revocado_at);
        $this->assertSame(1, Revisor::where('user_id', $sup->id)->count());
    }

    public function test_null_quita_el_rol_de_sedeq(): void
    {
        $admin = $this->admin();
        $rev = $this->revisor();

        $this->cambiar($admin, $rev, null);

        $rev = $rev->fresh();
        $this->assertFalse($rev->hasAnyRole(RolesSedeq::TODOS));
        $this->assertNotNull(Revisor::findOrFail($rev->id)->revocado_at);
        $detalle = json_decode(DB::table('bitacora_acciones')->where('accion', RegistrarAccion::CAMBIAR_ROL)->value('detalle'), true);
        $this->assertSame('revisor', $detalle['de']);
        $this->assertNull($detalle['a']);
    }

    public function test_un_no_admin_no_puede(): void
    {
        $this->admin();
        $sup = $this->supervisor();
        $rev = $this->revisor();

        $this->refusePrecondicion($sup, $rev, RolesSedeq::SUPERVISOR, 'rol');
        $this->assertTrue($rev->fresh()->hasRole(RolesSedeq::REVISOR));
    }

    public function test_no_puede_cambiarse_a_si_mismo(): void
    {
        $admin = $this->admin();
        $otro = $this->admin();

        $this->refusePrecondicion($admin, $admin, RolesSedeq::SUPERVISOR, 'cuenta');
        $this->assertTrue($admin->fresh()->hasRole(RolesSedeq::ADMIN));
        $this->assertTrue($otro->fresh()->hasRole(RolesSedeq::ADMIN));
    }

    public function test_rechaza_cuentas_archivadas_y_solicitantes(): void
    {
        $admin = $this->admin();
        $archivado = $this->revisor();
        $archivado->forceFill(['archivado_at' => now()])->save();
        $solicitante = Solicitante::factory()->create()->user;

        $this->refusePrecondicion($admin, $archivado, RolesSedeq::SUPERVISOR, 'cuenta');
        $this->refusePrecondicion($admin, $solicitante, RolesSedeq::REVISOR, 'cuenta');
        $this->assertFalse($solicitante->fresh()->hasAnyRole(RolesSedeq::TODOS));
    }

    public function test_rechaza_una_cuenta_con_rol_ajeno_a_sedeq(): void
    {
        $admin = $this->admin();
        $resp = $this->responsableDe($this->nivelEnRevision());

        $this->refusePrecondicion($admin, $resp, RolesSedeq::ADMIN, 'cuenta');
        $this->assertFalse($resp->fresh()->hasAnyRole(RolesSedeq::TODOS));

        $otro = User::factory()->create()->assignRole(Role::findOrCreate('otro', 'web'));
        $this->refusePrecondicion($admin, $otro, RolesSedeq::REVISOR, 'cuenta');
    }

    public function test_rechaza_una_cuenta_con_acceso_de_responsable_aunque_no_tenga_rol(): void
    {
        $admin = $this->admin();
        $resp = $this->responsableDe($this->nivelEnRevision());
        $resp->syncRoles([]);

        $this->refusePrecondicion($admin, $resp, RolesSedeq::SUPERVISOR, 'cuenta');
        $this->assertFalse($resp->fresh()->hasAnyRole(RolesSedeq::TODOS));
    }

    public function test_rol_desconocido_es_dato_invalido(): void
    {
        $admin = $this->admin();
        $rev = $this->revisor();

        $this->expectException(DatosInvalidos::class);
        $this->cambiar($admin, $rev, 'dios');
    }

    public function test_no_baja_al_ultimo_admin_activo(): void
    {
        $actor = $this->admin();
        $unico = $this->admin();
        $actor->forceFill(['archivado_at' => now()])->save();

        $this->refusePrecondicion($actor, $unico, RolesSedeq::REVISOR, 'cuenta');
        $this->assertTrue($unico->fresh()->hasRole(RolesSedeq::ADMIN));

        $actor->forceFill(['archivado_at' => null])->save();
        $this->cambiar($actor, $unico, RolesSedeq::REVISOR);
        $this->assertTrue($unico->fresh()->hasRole(RolesSedeq::REVISOR));
    }

    public function test_mismo_rol_es_un_rechazo_sin_efecto(): void
    {
        $admin = $this->admin();
        $rev = $this->revisor();

        $this->refusePrecondicion($admin, $rev, RolesSedeq::REVISOR, 'cuenta');
        $this->assertTrue($rev->fresh()->hasRole(RolesSedeq::REVISOR));
    }
}
