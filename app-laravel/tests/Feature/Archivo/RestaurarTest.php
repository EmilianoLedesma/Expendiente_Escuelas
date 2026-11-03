<?php

namespace Tests\Feature\Archivo;

use App\Application\Archivo\ArchivarCuenta;
use App\Application\Archivo\ArchivarTramite;
use App\Application\Archivo\RestaurarCuenta;
use App\Application\Archivo\RestaurarTramite;
use App\Application\Excepciones\PrecondicionIncumplida;
use App\Application\Revision\RolesSedeq;
use App\Models\Escuela;
use App\Models\Scopes\EscuelaNoArchivada;
use App\Models\Solicitante;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\ConTramitesEnRevision;
use Tests\TestCase;

class RestaurarTest extends TestCase
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

    public function test_restaura_un_tramite_y_vuelve_a_ser_visible(): void
    {
        $solicitante = Solicitante::factory()->create();
        $nivel = $this->nivelDe($solicitante);
        $admin = $this->admin();
        app(ArchivarTramite::class)->ejecutar($nivel->escuela_id, $admin->id, 'Duplicado');

        app(RestaurarTramite::class)->ejecutar($admin->id, $nivel->escuela_id);

        $this->assertNotNull(Escuela::find($nivel->escuela_id));
        $fila = DB::table('escuelas')->where('id', $nivel->escuela_id)->first();
        $this->assertNull($fila->archivado_at);
        $this->assertNull($fila->archivado_por);
        $this->assertNull($fila->archivado_motivo);
        $this->assertNull($fila->archivado_lote);
        $this->assertSame(1, DB::table('bitacora_acciones')->where('accion', 'restaurar_tramite')->where('objeto_id', $nivel->escuela_id)->count());
    }

    public function test_no_restaura_un_tramite_no_archivado(): void
    {
        $nivel = $this->nivelDe(Solicitante::factory()->create());

        $this->expectException(PrecondicionIncumplida::class);

        app(RestaurarTramite::class)->ejecutar($this->admin()->id, $nivel->escuela_id);
    }

    public function test_no_restaura_un_tramite_cuyo_dueno_esta_archivado(): void
    {
        $solicitante = Solicitante::factory()->create();
        $nivel = $this->nivelDe($solicitante);
        $admin = $this->admin();
        app(ArchivarCuenta::class)->ejecutar($solicitante->user_id, $admin->id, 'Baja');

        try {
            app(RestaurarTramite::class)->ejecutar($admin->id, $nivel->escuela_id);
            $this->fail('Debió rechazar.');
        } catch (PrecondicionIncumplida) {
        }

        $this->assertNotNull($this->escuela($nivel->escuela_id)->archivado_at);
    }

    public function test_un_actor_que_no_es_admin_no_restaura(): void
    {
        $solicitante = Solicitante::factory()->create();
        $nivel = $this->nivelDe($solicitante);
        app(ArchivarTramite::class)->ejecutar($nivel->escuela_id, $solicitante->user_id);

        try {
            app(RestaurarTramite::class)->ejecutar($solicitante->user_id, $nivel->escuela_id);
            $this->fail('Debió rechazar.');
        } catch (PrecondicionIncumplida) {
        }
        try {
            app(RestaurarCuenta::class)->ejecutar($solicitante->user_id, $solicitante->user_id);
            $this->fail('Debió rechazar.');
        } catch (PrecondicionIncumplida) {
        }

        $this->assertNotNull($this->escuela($nivel->escuela_id)->archivado_at);
    }

    public function test_restaurar_la_cuenta_devuelve_solo_lo_archivado_en_su_cascada(): void
    {
        $solicitante = Solicitante::factory()->create();
        $usuario = $solicitante->user;
        $suelto = $this->nivelDe($solicitante);
        $enCascada = $this->nivelDe($solicitante, 'secundaria');
        $responsable = $this->responsableDe($enCascada);
        $admin = $this->admin();
        app(ArchivarTramite::class)->ejecutar($suelto->escuela_id, $admin->id, 'Aparte');
        app(ArchivarCuenta::class)->ejecutar($usuario->id, $admin->id, 'Baja');

        app(RestaurarCuenta::class)->ejecutar($admin->id, $usuario->id);

        $this->assertNull($usuario->fresh()->archivado_at);
        $this->assertNull(DB::table('users')->where('id', $usuario->id)->value('archivado_lote'));
        $this->assertNotNull(Escuela::find($enCascada->escuela_id));
        $this->assertNull($responsable->fresh()->archivado_at);
        $this->assertNull(Escuela::find($suelto->escuela_id), 'el archivado aparte sigue archivado');
        $this->assertSame('Aparte', $this->escuela($suelto->escuela_id)->archivado_motivo);
    }

    /** El responsable arrastrado comparte el lote del solicitante: restaurarlo solo no debe reactivar la cuenta del dueño. */
    public function test_no_restaura_por_separado_a_un_responsable_archivado_en_cascada(): void
    {
        $solicitante = Solicitante::factory()->create();
        $nivel = $this->nivelDe($solicitante);
        $responsable = $this->responsableDe($nivel);
        $admin = $this->admin();
        app(ArchivarCuenta::class)->ejecutar($solicitante->user_id, $admin->id, 'Baja');
        $this->assertNotNull($responsable->fresh()->archivado_at);

        try {
            app(RestaurarCuenta::class)->ejecutar($admin->id, $responsable->id);
            $this->fail('Debió rechazar.');
        } catch (PrecondicionIncumplida $e) {
            $this->assertSame('archivo', $e->etapaFaltante);
        }

        $this->assertNotNull($responsable->fresh()->archivado_at);
        $this->assertNotNull($solicitante->user->fresh()->archivado_at);
        $this->assertNotNull($this->escuela($nivel->escuela_id)->archivado_at);
        $this->assertSame(0, DB::table('bitacora_acciones')->whereIn('accion', ['restaurar_cuenta', 'restaurar_tramite'])->count());
    }

    public function test_la_auditoria_no_lleva_nombre_ni_correo(): void
    {
        $solicitante = Solicitante::factory()->create();
        $this->nivelDe($solicitante);
        $admin = $this->admin();
        app(ArchivarCuenta::class)->ejecutar($solicitante->user_id, $admin->id, 'Baja');

        app(RestaurarCuenta::class)->ejecutar($admin->id, $solicitante->user_id);

        $filas = DB::table('bitacora_acciones')->whereIn('accion', ['restaurar_cuenta', 'restaurar_tramite'])->get();
        $this->assertEqualsCanonicalizing(['restaurar_cuenta', 'restaurar_tramite'], $filas->pluck('accion')->all());
        foreach ($filas as $fila) {
            $this->assertStringNotContainsString($solicitante->user->email, $fila->detalle);
            $this->assertStringNotContainsString($solicitante->user->name, $fila->detalle);
        }
    }

    public function test_restaurar_una_cuenta_no_archivada_falla(): void
    {
        $solicitante = Solicitante::factory()->create();

        $this->expectException(PrecondicionIncumplida::class);

        app(RestaurarCuenta::class)->ejecutar($this->admin()->id, $solicitante->user_id);
    }
}
