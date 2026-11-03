<?php

namespace Tests\Feature\Archivo;

use App\Application\Archivo\ArchivarTramite;
use App\Application\Excepciones\DatosInvalidos;
use App\Application\Excepciones\PrecondicionIncumplida;
use App\Application\Revision\AsignarTramite;
use App\Application\Revision\RolesSedeq;
use App\Models\DocumentoEscuela;
use App\Models\DocumentoEscuelaNivel;
use App\Models\Escuela;
use App\Models\EscuelaNivel;
use App\Models\Plantel;
use App\Models\Scopes\EscuelaNoArchivada;
use App\Models\Solicitante;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CapturaExpedienteConsistente;
use Tests\Concerns\ConTramitesEnRevision;
use Tests\TestCase;

class ArchivarTramiteTest extends TestCase
{
    use CapturaExpedienteConsistente;
    use ConTramitesEnRevision;
    use RefreshDatabase;

    private function borrador(): Escuela
    {
        $escuela = $this->crearEscuelaConPlantel();
        $this->completarTramite($escuela);

        return $escuela;
    }

    private function dueno(Escuela $escuela): User
    {
        return Solicitante::findOrFail($escuela->solicitante_id)->user;
    }

    private function admin(): User
    {
        (new RolesSeeder)->run();

        return User::factory()->create()->assignRole(RolesSedeq::ADMIN);
    }

    private function enviar(Escuela $escuela): void
    {
        EscuelaNivel::where('escuela_id', $escuela->id)->update(['estado_id' => DB::table('estados_expediente')->where('clave', 'en_revision')->value('id')]);
    }

    private function archivada(int $escuelaId): Escuela
    {
        return Escuela::withoutGlobalScope(EscuelaNoArchivada::class)->findOrFail($escuelaId);
    }

    /** @return list<string> */
    private function archivosDelTramite(Escuela $escuela): array
    {
        $niveles = DB::table('escuela_niveles')->where('escuela_id', $escuela->id)->pluck('id');

        return [
            ...DocumentoEscuela::where('escuela_id', $escuela->id)->pluck('archivo_path'),
            ...DocumentoEscuelaNivel::whereIn('escuela_nivel_id', $niveles)->pluck('archivo_path'),
        ];
    }

    public function test_el_dueno_archiva_un_borrador_sin_borrar_filas_ni_archivos(): void
    {
        $escuela = $this->borrador();
        $dueno = $this->dueno($escuela);
        $archivos = $this->archivosDelTramite($escuela);
        $this->assertNotEmpty($archivos);
        $niveles = DB::table('escuela_niveles')->where('escuela_id', $escuela->id)->count();

        app(ArchivarTramite::class)->ejecutar($escuela->id, $dueno->id);

        $this->assertNull(Escuela::find($escuela->id));
        $fila = $this->archivada($escuela->id);
        $this->assertNotNull($fila->archivado_at);
        $this->assertSame($dueno->id, $fila->archivado_por);
        $this->assertNull($fila->archivado_motivo);
        $this->assertNotNull($fila->archivado_lote);
        $this->assertSame($niveles, DB::table('escuela_niveles')->where('escuela_id', $escuela->id)->count());
        $this->assertSame($archivos, $this->archivosDelTramite($escuela));
        foreach ($archivos as $ruta) {
            Storage::disk('documentos')->assertExists($ruta);
        }
        $this->assertNotNull(Plantel::find($escuela->plantel_id));
    }

    public function test_escribe_la_fila_de_auditoria_con_el_lote_dado(): void
    {
        $escuela = $this->borrador();
        $dueno = $this->dueno($escuela);

        app(ArchivarTramite::class)->ejecutar($escuela->id, $dueno->id, null, '11111111-1111-1111-1111-111111111111');

        $this->assertSame('11111111-1111-1111-1111-111111111111', $this->archivada($escuela->id)->archivado_lote);
        $fila = DB::table('bitacora_acciones')->sole();
        $this->assertSame('archivar_tramite', $fila->accion);
        $this->assertSame('escuela', $fila->objeto_tipo);
        $this->assertSame($escuela->id, (int) $fila->objeto_id);
        $this->assertSame($dueno->id, (int) $fila->actor_id);
        $this->assertSame(['lote' => '11111111-1111-1111-1111-111111111111'], json_decode($fila->detalle, true));
    }

    public function test_el_dueno_no_archiva_un_tramite_enviado(): void
    {
        $escuela = $this->borrador();
        $this->enviar($escuela);

        try {
            app(ArchivarTramite::class)->ejecutar($escuela->id, $this->dueno($escuela)->id);
            $this->fail('Debió lanzar PrecondicionIncumplida.');
        } catch (PrecondicionIncumplida $e) {
            $this->assertSame('en_captura', $e->etapaFaltante);
        }

        $this->assertNotNull(Escuela::find($escuela->id));
        $this->assertSame(0, DB::table('bitacora_acciones')->count());
    }

    public function test_con_niveles_mixtos_el_dueno_tampoco_archiva(): void
    {
        $escuela = $this->borrador();
        $this->nivelEn($escuela, 'secundaria', 'aprobado');

        $this->expectException(PrecondicionIncumplida::class);

        try {
            app(ArchivarTramite::class)->ejecutar($escuela->id, $this->dueno($escuela)->id);
        } finally {
            $this->assertNotNull(Escuela::find($escuela->id));
        }
    }

    public function test_el_admin_archiva_un_tramite_en_revision_y_libera_su_asignacion(): void
    {
        $nivel = $this->nivelEnRevision();
        app(AsignarTramite::class)->asignarSeleccion($this->supervisor()->id, [$nivel->id], $this->revisor()->id);
        $admin = $this->admin();

        app(ArchivarTramite::class)->ejecutar($nivel->escuela_id, $admin->id, 'Duplicado');

        $fila = $this->archivada($nivel->escuela_id);
        $this->assertSame('Duplicado', $fila->archivado_motivo);
        $this->assertSame($admin->id, $fila->archivado_por);
        $this->assertSame(0, DB::table('asignaciones_revision')->count());
        $log = DB::table('bitacora_asignaciones')->where('accion', 'desasignar_archivo')->sole();
        $this->assertSame($nivel->id, (int) $log->escuela_nivel_id);
        $this->assertSame($admin->id, (int) $log->actor_id);
        $this->assertSame('Duplicado', DB::table('bitacora_acciones')->sole()->motivo);
    }

    public function test_el_admin_sin_motivo_recibe_datos_invalidos(): void
    {
        $nivel = $this->nivelEnRevision();
        $admin = $this->admin();

        foreach ([null, '   '] as $motivo) {
            try {
                app(ArchivarTramite::class)->ejecutar($nivel->escuela_id, $admin->id, $motivo);
                $this->fail('Debió lanzar DatosInvalidos.');
            } catch (DatosInvalidos $e) {
                $this->assertArrayHasKey('motivo', $e->errores);
            }
        }

        $this->assertNotNull(Escuela::find($nivel->escuela_id));
    }

    public function test_archivar_dos_veces_se_rechaza(): void
    {
        $escuela = $this->borrador();
        $dueno = $this->dueno($escuela);
        app(ArchivarTramite::class)->ejecutar($escuela->id, $dueno->id);
        $lote = $this->archivada($escuela->id)->archivado_lote;

        try {
            app(ArchivarTramite::class)->ejecutar($escuela->id, $this->admin()->id, 'otra vez');
            $this->fail('Debió lanzar PrecondicionIncumplida.');
        } catch (PrecondicionIncumplida) {
        }

        $this->assertSame($lote, $this->archivada($escuela->id)->archivado_lote);
        $this->assertSame(1, DB::table('bitacora_acciones')->count());
    }

    public function test_mis_tramites_busca_el_estado_en_captura_una_sola_vez(): void
    {
        $escuela = $this->borrador();
        $dueno = $this->dueno($escuela);
        foreach (['Calle 2', 'Calle 3'] as $calle) {
            $plantel = Plantel::create(['calle' => $calle, 'colonia' => 'Centro', 'municipio' => 'Querétaro', 'codigo_postal' => '76000']);
            Escuela::create(['plantel_id' => $plantel->id, 'solicitante_id' => $escuela->solicitante_id]);
        }
        $consultas = 0;
        DB::listen(function ($query) use (&$consultas) {
            if (str_contains($query->sql, 'from "estados_expediente"')) {
                $consultas++;
            }
        });

        $this->actingAs($dueno)->get(route('tramite.index'))->assertOk();

        $this->assertSame(1, $consultas);
    }

    // --- Ruta DELETE /tramite/{escuela} (nombre histórico; ahora archiva) ---

    public function test_el_dueno_archiva_por_la_ruta_y_vuelve_a_mis_tramites(): void
    {
        $escuela = $this->borrador();
        $dueno = $this->dueno($escuela);

        $this->actingAs($dueno)
            ->delete(route('tramite.eliminar', ['escuela' => $escuela->id]))
            ->assertRedirect(route('tramite.index'))
            ->assertSessionHas('toast.titulo', 'Trámite archivado');

        $this->assertNull(Escuela::find($escuela->id));
        $this->assertSame($dueno->id, $this->archivada($escuela->id)->archivado_por);
    }

    public function test_archivar_dos_veces_por_la_ruta_no_truena(): void
    {
        $escuela = $this->borrador();
        $dueno = $this->dueno($escuela);
        $this->actingAs($dueno)->delete(route('tramite.eliminar', ['escuela' => $escuela->id]));

        $this->actingAs($dueno)
            ->delete(route('tramite.eliminar', ['escuela' => $escuela->id]))
            ->assertRedirect(route('tramite.index'))
            ->assertSessionHas('toast.titulo', 'El trámite ya no existe');
    }

    public function test_otro_solicitante_recibe_403(): void
    {
        $escuela = $this->borrador();

        $this->actingAs(Solicitante::factory()->create()->user)
            ->delete(route('tramite.eliminar', ['escuela' => $escuela->id]))
            ->assertForbidden();

        $this->assertNotNull(Escuela::find($escuela->id));
    }

    public function test_usuario_sin_solicitante_recibe_403(): void
    {
        $escuela = $this->borrador();

        $this->actingAs(User::factory()->create())
            ->delete(route('tramite.eliminar', ['escuela' => $escuela->id]))
            ->assertForbidden();
    }

    public function test_invitado_va_al_login(): void
    {
        $escuela = $this->borrador();

        $this->delete(route('tramite.eliminar', ['escuela' => $escuela->id]))
            ->assertRedirect(route('login'));

        $this->assertNotNull(Escuela::find($escuela->id));
    }

    public function test_fuera_de_captura_la_ruta_avisa_y_no_archiva(): void
    {
        $escuela = $this->borrador();
        $this->enviar($escuela);

        $this->actingAs($this->dueno($escuela))
            ->delete(route('tramite.eliminar', ['escuela' => $escuela->id]))
            ->assertRedirect(route('tramite.index'))
            ->assertSessionHas('toast.tipo', 'error')
            ->assertSessionHas('toast.titulo', 'No se pudo archivar el trámite');

        $this->assertNotNull(Escuela::find($escuela->id));
    }

    // --- UI ---

    public function test_mis_tramites_y_resumen_ofrecen_archivar_con_confirmacion(): void
    {
        $escuela = $this->borrador();
        $dueno = $this->dueno($escuela);
        $accion = 'action="'.route('tramite.eliminar', ['escuela' => $escuela->id]).'"';

        foreach ([route('tramite.index'), route('tramite.resumen', ['escuela' => $escuela->id])] as $pagina) {
            $this->actingAs($dueno)->get($pagina)
                ->assertOk()
                ->assertSee('Archivar trámite')
                ->assertSee('<dialog', false)
                ->assertSee('.showModal()', false)
                ->assertSee($accion, false)
                ->assertSee('name="_method" value="DELETE"', false)
                ->assertSee('El trámite deja de aparecer en tus trámites. Sus datos y archivos se conservan; para recuperarlo, contacta a SEDEQ.')
                ->assertSee('Sí, archivar trámite')
                ->assertSee('Cancelar')
                ->assertDontSee('Eliminar trámite');
        }
    }

    public function test_fuera_de_captura_no_se_ofrece_archivar(): void
    {
        $escuela = $this->borrador();
        $this->enviar($escuela);
        $dueno = $this->dueno($escuela);

        foreach ([route('tramite.index'), route('tramite.resumen', ['escuela' => $escuela->id])] as $pagina) {
            $this->actingAs($dueno)->get($pagina)
                ->assertOk()
                ->assertDontSee('Archivar trámite');
        }
    }

    public function test_mis_tramites_muestra_el_aviso_de_archivado(): void
    {
        $escuela = $this->borrador();

        $this->actingAs($this->dueno($escuela))
            ->followingRedirects()
            ->delete(route('tramite.eliminar', ['escuela' => $escuela->id]))
            ->assertOk()
            ->assertSee('Trámite archivado', false)
            ->assertDontSee('se borraron', false);
    }
}
