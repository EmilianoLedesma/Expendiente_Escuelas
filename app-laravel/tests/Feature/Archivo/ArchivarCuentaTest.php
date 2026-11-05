<?php

namespace Tests\Feature\Archivo;

use App\Application\Archivo\ArchivarCuenta;
use App\Application\Archivo\ArchivarTramite;
use App\Application\Excepciones\DatosInvalidos;
use App\Application\Excepciones\PrecondicionIncumplida;
use App\Application\Revision\AsignarTramite;
use App\Application\Revision\RolesSedeq;
use App\Models\Escuela;
use App\Models\EscuelaNivel;
use App\Models\ResponsableNivel;
use App\Models\Revisor;
use App\Models\Scopes\EscuelaNoArchivada;
use App\Models\Solicitante;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\ConTramitesEnRevision;
use Tests\TestCase;

class ArchivarCuentaTest extends TestCase
{
    use ConTramitesEnRevision;
    use RefreshDatabase;

    private function admin(): User
    {
        (new RolesSeeder)->run();

        return User::factory()->create()->assignRole(RolesSedeq::ADMIN);
    }

    private function escuela(int $id): Escuela
    {
        return Escuela::withoutGlobalScope(EscuelaNoArchivada::class)->findOrFail($id);
    }

    private function lote(User $usuario): ?string
    {
        return DB::table('users')->where('id', $usuario->id)->value('archivado_lote');
    }

    public function test_el_solicitante_con_borradores_archiva_cuenta_tramites_y_responsables_con_un_solo_lote(): void
    {
        $solicitante = Solicitante::factory()->create();
        $usuario = $solicitante->user;
        $primero = $this->nivelDe($solicitante);
        $segundo = $this->nivelDe($solicitante, 'secundaria');
        $responsable = $this->responsableDe($primero, $segundo);

        app(ArchivarCuenta::class)->ejecutar($usuario->id, $usuario->id);

        $lote = $this->lote($usuario);
        $this->assertNotNull($lote);
        $this->assertNotNull($usuario->fresh()->archivado_at);
        $this->assertNull(DB::table('users')->where('id', $usuario->id)->value('archivado_motivo'));
        foreach ([$primero, $segundo] as $nivel) {
            $escuela = $this->escuela($nivel->escuela_id);
            $this->assertNotNull($escuela->archivado_at);
            $this->assertSame($lote, $escuela->archivado_lote);
            $this->assertNull(Escuela::find($nivel->escuela_id));
        }
        $this->assertNotNull($responsable->fresh()->archivado_at);
        $this->assertSame($lote, $this->lote($responsable));
        $this->assertNotNull(Solicitante::find($solicitante->id), 'la fila de solicitante se conserva');
        $this->assertSame(2, ResponsableNivel::count(), 'los accesos se conservan para restaurar');
    }

    public function test_escribe_auditoria_de_la_cuenta_y_de_cada_tramite(): void
    {
        $solicitante = Solicitante::factory()->create();
        $nivel = $this->nivelDe($solicitante);

        app(ArchivarCuenta::class)->ejecutar($solicitante->user_id, $solicitante->user_id);

        $filas = DB::table('bitacora_acciones')->orderBy('id')->get();
        $this->assertEqualsCanonicalizing(['archivar_tramite', 'archivar_cuenta'], $filas->pluck('accion')->all());
        $cuenta = $filas->firstWhere('accion', 'archivar_cuenta');
        $this->assertSame('user', $cuenta->objeto_tipo);
        $this->assertSame($solicitante->user_id, (int) $cuenta->objeto_id);
        $this->assertSame($this->lote($solicitante->user), json_decode($cuenta->detalle, true)['lote']);
        $this->assertSame($nivel->escuela_id, (int) $filas->firstWhere('accion', 'archivar_tramite')->objeto_id);
        foreach ($filas as $fila) {
            $this->assertStringNotContainsString($solicitante->user->email, $fila->detalle);
            $this->assertStringNotContainsString($solicitante->user->name, $fila->detalle);
        }
    }

    public function test_un_tramite_archivado_antes_no_entra_en_el_lote(): void
    {
        $solicitante = Solicitante::factory()->create();
        $previo = $this->nivelDe($solicitante);
        $this->nivelDe($solicitante, 'secundaria');
        app(ArchivarTramite::class)->ejecutar($previo->escuela_id, $solicitante->user_id);
        $lotePrevio = $this->escuela($previo->escuela_id)->archivado_lote;

        app(ArchivarCuenta::class)->ejecutar($solicitante->user_id, $solicitante->user_id);

        $this->assertSame($lotePrevio, $this->escuela($previo->escuela_id)->archivado_lote);
        $this->assertNotSame($lotePrevio, $this->lote($solicitante->user));
    }

    public function test_el_solicitante_que_ya_envio_un_tramite_no_archiva_nada(): void
    {
        $solicitante = Solicitante::factory()->create();
        $borrador = $this->nivelDe($solicitante);
        $this->nivelDe($solicitante, 'secundaria', 'en_revision');

        try {
            app(ArchivarCuenta::class)->ejecutar($solicitante->user_id, $solicitante->user_id);
            $this->fail('Debió rechazar el archivo.');
        } catch (PrecondicionIncumplida $e) {
            $this->assertSame('No puedes archivar tu cuenta: ya enviaste un trámite a revisión.', $e->getMessage());
        }

        $this->assertNull($solicitante->user->fresh()->archivado_at);
        $this->assertNotNull(Escuela::find($borrador->escuela_id));
        $this->assertSame(0, DB::table('bitacora_acciones')->count());
    }

    public function test_el_responsable_por_nivel_archiva_solo_su_cuenta_aunque_el_tramite_se_haya_enviado(): void
    {
        $nivel = $this->nivelEnRevision();
        $responsable = $this->responsableDe($nivel);

        app(ArchivarCuenta::class)->ejecutar($responsable->id, $responsable->id);

        $this->assertNotNull($responsable->fresh()->archivado_at);
        $this->assertNotNull(EscuelaNivel::find($nivel->id));
    }

    /** @return array<string, array{string}> */
    public static function rolesSedeq(): array
    {
        return array_combine(RolesSedeq::TODOS, array_map(fn ($r) => [$r], RolesSedeq::TODOS));
    }

    #[DataProvider('rolesSedeq')]
    public function test_una_cuenta_de_sedeq_no_se_archiva_sola(string $rol): void
    {
        (new RolesSeeder)->run();
        $usuario = User::factory()->create()->assignRole($rol);

        try {
            app(ArchivarCuenta::class)->ejecutar($usuario->id, $usuario->id);
            $this->fail('Debió rechazar el archivo.');
        } catch (PrecondicionIncumplida) {
        }

        $this->assertNull($usuario->fresh()->archivado_at);
    }

    public function test_un_revisor_revocado_no_se_archiva_solo(): void
    {
        $usuario = User::factory()->create();
        Revisor::create(['user_id' => $usuario->id, 'revocado_at' => now()]);

        $this->expectException(PrecondicionIncumplida::class);

        app(ArchivarCuenta::class)->ejecutar($usuario->id, $usuario->id);
    }

    public function test_quien_no_es_admin_no_archiva_la_cuenta_de_otro(): void
    {
        $ajeno = Solicitante::factory()->create();
        $otro = Solicitante::factory()->create();

        $this->expectException(PrecondicionIncumplida::class);

        try {
            app(ArchivarCuenta::class)->ejecutar($ajeno->user_id, $otro->user_id);
        } finally {
            $this->assertNull($ajeno->user->fresh()->archivado_at);
        }
    }

    public function test_el_admin_archiva_un_solicitante_con_tramites_enviados(): void
    {
        $solicitante = Solicitante::factory()->create();
        $enviado = $this->nivelDe($solicitante, 'primaria', 'en_revision');
        $admin = $this->admin();

        app(ArchivarCuenta::class)->ejecutar($solicitante->user_id, $admin->id, 'Cuenta duplicada');

        $usuario = $solicitante->user->fresh();
        $this->assertNotNull($usuario->archivado_at);
        $this->assertSame('Cuenta duplicada', DB::table('users')->where('id', $usuario->id)->value('archivado_motivo'));
        $this->assertSame($this->lote($usuario), $this->escuela($enviado->escuela_id)->archivado_lote);
        $this->assertSame('Cuenta duplicada', $this->escuela($enviado->escuela_id)->archivado_motivo);
    }

    public function test_el_admin_sin_motivo_recibe_datos_invalidos(): void
    {
        $solicitante = Solicitante::factory()->create();

        try {
            app(ArchivarCuenta::class)->ejecutar($solicitante->user_id, $this->admin()->id);
            $this->fail('Debió lanzar DatosInvalidos.');
        } catch (DatosInvalidos $e) {
            $this->assertArrayHasKey('motivo', $e->errores);
        }

        $this->assertNull($solicitante->user->fresh()->archivado_at);
    }

    public function test_el_admin_archiva_un_revisor_y_sus_tramites_vuelven_a_la_cola(): void
    {
        $sup = $this->supervisor();
        $rev = $this->revisor();
        $otroRev = $this->revisor();
        $ids = [$this->nivelEnRevision()->id, $this->nivelEnRevision()->id];
        $ajeno = $this->nivelEnRevision()->id;
        app(AsignarTramite::class)->asignarSeleccion($sup->id, $ids, $rev->id);
        app(AsignarTramite::class)->asignarSeleccion($sup->id, [$ajeno], $otroRev->id);
        $admin = $this->admin();

        app(ArchivarCuenta::class)->ejecutar($rev->id, $admin->id, 'Dejó SEDEQ');

        $this->assertNotNull($rev->fresh()->archivado_at);
        $this->assertSame([$ajeno], DB::table('asignaciones_revision')->pluck('escuela_nivel_id')->map(fn ($id) => (int) $id)->all());
        $logs = DB::table('bitacora_asignaciones')->where('accion', 'desasignar_archivo')->get();
        $this->assertCount(2, $logs);
        $this->assertEqualsCanonicalizing($ids, $logs->pluck('escuela_nivel_id')->map(fn ($id) => (int) $id)->all());
        $this->assertSame([$rev->id], $logs->pluck('revisor_id')->map(fn ($id) => (int) $id)->unique()->values()->all());
        $this->assertSame([$admin->id], $logs->pluck('actor_id')->map(fn ($id) => (int) $id)->unique()->values()->all());
    }

    public function test_el_admin_no_se_archiva_a_si_mismo(): void
    {
        $admin = $this->admin();
        $this->admin();

        try {
            app(ArchivarCuenta::class)->ejecutar($admin->id, $admin->id, 'me voy');
            $this->fail('Debió rechazar el archivo.');
        } catch (PrecondicionIncumplida) {
        }

        $this->assertNull($admin->fresh()->archivado_at);
    }

    public function test_no_se_archiva_al_ultimo_admin_activo(): void
    {
        // El actor quedó archivado por otra transacción (carrera entre dos admins): el objetivo es el único activo.
        $actor = $this->admin();
        $actor->forceFill(['archivado_at' => now()])->save();
        $ultimo = $this->admin();

        try {
            app(ArchivarCuenta::class)->ejecutar($ultimo->id, $actor->id, 'carrera');
            $this->fail('Debió rechazar el archivo.');
        } catch (PrecondicionIncumplida) {
        }

        $this->assertNull($ultimo->fresh()->archivado_at);
    }

    public function test_el_admin_archiva_a_otro_admin_si_hay_dos(): void
    {
        $actor = $this->admin();
        $otro = $this->admin();

        app(ArchivarCuenta::class)->ejecutar($otro->id, $actor->id, 'Rotación');

        $this->assertNotNull($otro->fresh()->archivado_at);
        $this->assertSame($actor->id, (int) DB::table('users')->where('id', $otro->id)->value('archivado_por'));
    }

    public function test_una_cuenta_ya_archivada_se_rechaza(): void
    {
        $solicitante = Solicitante::factory()->create();
        $admin = $this->admin();
        app(ArchivarCuenta::class)->ejecutar($solicitante->user_id, $admin->id, 'uno');

        $this->expectException(PrecondicionIncumplida::class);

        app(ArchivarCuenta::class)->ejecutar($solicitante->user_id, $admin->id, 'dos');
    }

    public function test_un_responsable_con_acceso_a_otro_solicitante_no_se_archiva_en_cascada(): void
    {
        $solicitante = Solicitante::factory()->create();
        $nivel = $this->nivelDe($solicitante);
        $ajeno = $this->nivelDe(Solicitante::factory()->create());
        $compartido = $this->responsableDe($nivel, $ajeno);

        app(ArchivarCuenta::class)->ejecutar($solicitante->user_id, $solicitante->user_id);

        $this->assertNull($compartido->fresh()->archivado_at);
    }
}
