<?php

namespace Tests\Feature\Tramite;

use App\Application\Excepciones\PrecondicionIncumplida;
use App\Application\Tramite\EliminarTramite;
use App\Models\DocumentoEscuela;
use App\Models\DocumentoEscuelaNivel;
use App\Models\DocumentoPlantel;
use App\Models\Escuela;
use App\Models\EscuelaNivel;
use App\Models\NivelEducativo;
use App\Models\Plantel;
use App\Models\Solicitante;
use App\Models\TernaNombre;
use App\Models\User;
use Database\Seeders\MobiliarioConceptosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\Concerns\CapturaExpedienteConsistente;
use Tests\TestCase;

class EliminarTramiteTest extends TestCase
{
    use CapturaExpedienteConsistente;
    use RefreshDatabase;

    /** Todo lo que cuelga de una escuela; tras eliminar el único trámite, cada una debe quedar vacía. */
    private const TABLAS_DEL_TRAMITE = [
        'escuelas', 'ternas_nombres', 'responsables_legales', 'personas_fisicas', 'gestores',
        'escuela_niveles', 'aulas_nivel', 'mobiliario_nivel', 'personal', 'matricula_grados',
        'documentos_escuela', 'documentos_escuela_nivel', 'escuela_nivel_pasos',
        'historial_estados_expediente', 'evaluaciones_validacion',
    ];

    /** Trámite completo (con gestor) más terna, aulas, mobiliario, historial y un reporte de validación guardado. */
    private function tramiteCompleto(?int $plantelId = null): Escuela
    {
        $escuela = $this->crearEscuelaConPlantel($plantelId);
        $nivel = $this->completarTramite($escuela, 'fisica_con_gestor');
        (new MobiliarioConceptosSeeder)->run();

        TernaNombre::create(['escuela_id' => $escuela->id, 'numero_propuesta' => 1, 'nombre_propuesto' => 'Colegio Alfa']);
        DB::table('aulas_nivel')->insert(['escuela_nivel_id' => $nivel->id, 'numero_aulas' => 6]);
        DB::table('mobiliario_nivel')->insert(['escuela_nivel_id' => $nivel->id, 'concepto_id' => DB::table('mobiliario_conceptos')->value('id'), 'cantidad_declarada' => 10]);
        DB::table('historial_estados_expediente')->insert(['escuela_nivel_id' => $nivel->id, 'estado_id' => $nivel->estado_id, 'usuario_sedeq_id' => User::factory()->create()->id]);

        $reporte = "validaciones/{$escuela->id}/reporte-prueba.pdf";
        Storage::disk('documentos')->put($reporte, '%PDF');
        DB::table('evaluaciones_validacion')->insert(['escuela_id' => $escuela->id, 'archivo_path' => $reporte, 'lista_para_envio' => true, 'resultados' => '[]']);

        return $escuela;
    }

    /** @return list<string> */
    private function archivosDelTramite(Escuela $escuela): array
    {
        $niveles = EscuelaNivel::where('escuela_id', $escuela->id)->pluck('id');

        return [
            ...DocumentoEscuela::where('escuela_id', $escuela->id)->pluck('archivo_path'),
            ...DocumentoEscuelaNivel::whereIn('escuela_nivel_id', $niveles)->pluck('archivo_path'),
            ...DB::table('evaluaciones_validacion')->where('escuela_id', $escuela->id)->pluck('archivo_path'),
        ];
    }

    /** @return list<string> */
    private function archivosDelPlantel(Escuela $escuela): array
    {
        return DocumentoPlantel::where('plantel_id', $escuela->plantel_id)->pluck('archivo_path')->all();
    }

    private function cambiarEstado(EscuelaNivel $nivel, string $clave): void
    {
        $nivel->update(['estado_id' => DB::table('estados_expediente')->where('clave', $clave)->value('id')]);
    }

    private function dueno(Escuela $escuela): User
    {
        return Solicitante::findOrFail($escuela->solicitante_id)->user;
    }

    public function test_elimina_el_tramite_y_todo_lo_que_cuelga_de_el(): void
    {
        $escuela = $this->tramiteCompleto();
        foreach (self::TABLAS_DEL_TRAMITE as $tabla) {
            $this->assertGreaterThan(0, DB::table($tabla)->count(), "el fixture debe poblar {$tabla}");
        }

        app(EliminarTramite::class)->ejecutar($escuela->id);

        foreach (self::TABLAS_DEL_TRAMITE as $tabla) {
            $this->assertSame(0, DB::table($tabla)->count(), "{$tabla} debió vaciarse");
        }
    }

    public function test_conserva_el_plantel_y_sus_documentos(): void
    {
        $escuela = $this->tramiteCompleto();
        $archivosPlantel = $this->archivosDelPlantel($escuela);
        $this->assertNotEmpty($archivosPlantel);

        app(EliminarTramite::class)->ejecutar($escuela->id);

        $this->assertNotNull(Plantel::find($escuela->plantel_id));
        $this->assertSame($archivosPlantel, $this->archivosDelPlantel($escuela));
        foreach ($archivosPlantel as $ruta) {
            Storage::disk('documentos')->assertExists($ruta);
        }
    }

    public function test_borra_los_archivos_del_tramite_tras_el_commit(): void
    {
        $escuela = $this->tramiteCompleto();
        $archivos = $this->archivosDelTramite($escuela);
        $this->assertNotEmpty($archivos);
        foreach ($archivos as $ruta) {
            Storage::disk('documentos')->assertExists($ruta);
        }

        app(EliminarTramite::class)->ejecutar($escuela->id);

        foreach ($archivos as $ruta) {
            Storage::disk('documentos')->assertMissing($ruta);
        }
    }

    public function test_si_la_transaccion_se_revierte_conserva_filas_y_archivos(): void
    {
        $escuela = $this->tramiteCompleto();
        $archivos = $this->archivosDelTramite($escuela);

        try {
            DB::transaction(function () use ($escuela, $archivos) {
                app(EliminarTramite::class)->ejecutar($escuela->id);

                // Antes del commit ningún archivo se toca.
                foreach ($archivos as $ruta) {
                    Storage::disk('documentos')->assertExists($ruta);
                }

                throw new RuntimeException('fallo posterior');
            });
        } catch (RuntimeException) {
        }

        $this->assertNotNull(Escuela::find($escuela->id));
        $this->assertSame(1, EscuelaNivel::where('escuela_id', $escuela->id)->count());
        foreach ($archivos as $ruta) {
            Storage::disk('documentos')->assertExists($ruta);
        }
    }

    public function test_un_nivel_fuera_de_captura_impide_eliminar(): void
    {
        $escuela = $this->tramiteCompleto();
        $this->cambiarEstado(EscuelaNivel::where('escuela_id', $escuela->id)->sole(), 'en_revision');

        try {
            app(EliminarTramite::class)->ejecutar($escuela->id);
            $this->fail('debió lanzar PrecondicionIncumplida');
        } catch (PrecondicionIncumplida) {
        }

        $this->assertNotNull(Escuela::find($escuela->id));
        foreach ($this->archivosDelTramite($escuela) as $ruta) {
            Storage::disk('documentos')->assertExists($ruta);
        }
    }

    public function test_con_niveles_mixtos_tampoco_se_elimina(): void
    {
        $escuela = $this->tramiteCompleto();
        $otro = EscuelaNivel::create([
            'escuela_id' => $escuela->id,
            'nivel_educativo_id' => NivelEducativo::where('clave', 'secundaria')->value('id'),
            'estado_id' => DB::table('estados_expediente')->where('clave', 'en_captura')->value('id'),
            'tipo_tramite' => 'alta_nueva',
        ]);
        $this->cambiarEstado($otro, 'aprobado');

        $this->expectException(PrecondicionIncumplida::class);

        try {
            app(EliminarTramite::class)->ejecutar($escuela->id);
        } finally {
            $this->assertSame(2, EscuelaNivel::where('escuela_id', $escuela->id)->count());
        }
    }

    public function test_un_archivo_ya_ausente_no_impide_borrar_los_demas(): void
    {
        $escuela = $this->tramiteCompleto();
        $demas = $this->archivosDelTramite($escuela);
        $ausente = array_shift($demas);
        Storage::disk('documentos')->delete($ausente);
        Exceptions::fake();

        app(EliminarTramite::class)->ejecutar($escuela->id);

        $this->assertNull(Escuela::find($escuela->id));
        $this->assertNotEmpty($demas);
        foreach ($demas as $ruta) {
            Storage::disk('documentos')->assertMissing($ruta);
        }
        Exceptions::assertNothingReported();
    }

    public function test_un_documento_sin_archivo_no_reporta_errores(): void
    {
        $escuela = $this->tramiteCompleto();
        $niveles = EscuelaNivel::where('escuela_id', $escuela->id)->pluck('id');
        DocumentoEscuela::where('escuela_id', $escuela->id)->limit(1)->update(['archivo_path' => null]);
        DocumentoEscuelaNivel::whereIn('escuela_nivel_id', $niveles)->limit(1)->update(['archivo_path' => null]);
        $archivos = array_filter($this->archivosDelTramite($escuela));
        Exceptions::fake();

        app(EliminarTramite::class)->ejecutar($escuela->id);

        $this->assertNull(Escuela::find($escuela->id));
        foreach ($archivos as $ruta) {
            Storage::disk('documentos')->assertMissing($ruta);
        }
        Exceptions::assertNothingReported();
    }

    public function test_otro_tramite_del_mismo_plantel_no_se_toca(): void
    {
        $escuela = $this->tramiteCompleto();
        $ajena = Escuela::create(['plantel_id' => $escuela->plantel_id, 'solicitante_id' => Solicitante::factory()->create()->id]);
        TernaNombre::create(['escuela_id' => $ajena->id, 'numero_propuesta' => 1, 'nombre_propuesto' => 'Colegio Beta']);
        $nivelAjeno = EscuelaNivel::create([
            'escuela_id' => $ajena->id,
            'nivel_educativo_id' => NivelEducativo::where('clave', 'primaria')->value('id'),
            'estado_id' => DB::table('estados_expediente')->where('clave', 'en_captura')->value('id'),
            'tipo_tramite' => 'alta_nueva',
        ]);
        $archivoAjeno = "escuela/{$ajena->id}/ine-ajena.pdf";
        Storage::disk('documentos')->put($archivoAjeno, '%PDF');
        DocumentoEscuela::create([
            'escuela_id' => $ajena->id,
            'tipo_documento_id' => DB::table('tipos_documentos')->where('ambito', 'escuela')->value('id'),
            'archivo_path' => $archivoAjeno,
        ]);

        app(EliminarTramite::class)->ejecutar($escuela->id);

        $this->assertNotNull(Escuela::find($ajena->id));
        $this->assertSame(1, TernaNombre::where('escuela_id', $ajena->id)->count());
        $this->assertNotNull(EscuelaNivel::find($nivelAjeno->id));
        $this->assertSame(1, DocumentoEscuela::where('escuela_id', $ajena->id)->count());
        Storage::disk('documentos')->assertExists($archivoAjeno);
    }

    public function test_mis_tramites_busca_el_estado_en_captura_una_sola_vez(): void
    {
        $escuela = $this->tramiteCompleto();
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

    // --- Ruta DELETE /tramite/{escuela} ---

    public function test_el_dueno_elimina_y_vuelve_a_mis_tramites(): void
    {
        $escuela = $this->tramiteCompleto();

        $this->actingAs($this->dueno($escuela))
            ->delete(route('tramite.eliminar', ['escuela' => $escuela->id]))
            ->assertRedirect(route('tramite.index'))
            ->assertSessionHas('status', 'Trámite eliminado.');

        $this->assertNull(Escuela::find($escuela->id));
    }

    public function test_eliminar_dos_veces_no_truena(): void
    {
        $escuela = $this->tramiteCompleto();
        $dueno = $this->dueno($escuela);
        $this->actingAs($dueno)->delete(route('tramite.eliminar', ['escuela' => $escuela->id]));

        $this->actingAs($dueno)
            ->delete(route('tramite.eliminar', ['escuela' => $escuela->id]))
            ->assertRedirect(route('tramite.index'))
            ->assertSessionHas('status', 'El trámite ya no existe.');
    }

    public function test_otro_solicitante_recibe_403(): void
    {
        $escuela = $this->tramiteCompleto();

        $this->actingAs(Solicitante::factory()->create()->user)
            ->delete(route('tramite.eliminar', ['escuela' => $escuela->id]))
            ->assertForbidden();

        $this->assertNotNull(Escuela::find($escuela->id));
    }

    public function test_usuario_sin_solicitante_recibe_403(): void
    {
        $escuela = $this->tramiteCompleto();

        $this->actingAs(User::factory()->create())
            ->delete(route('tramite.eliminar', ['escuela' => $escuela->id]))
            ->assertForbidden();
    }

    public function test_invitado_va_al_login(): void
    {
        $escuela = $this->tramiteCompleto();

        $this->delete(route('tramite.eliminar', ['escuela' => $escuela->id]))
            ->assertRedirect(route('login'));

        $this->assertNotNull(Escuela::find($escuela->id));
    }

    public function test_fuera_de_captura_la_ruta_avisa_y_no_elimina(): void
    {
        $escuela = $this->tramiteCompleto();
        $this->cambiarEstado(EscuelaNivel::where('escuela_id', $escuela->id)->sole(), 'en_revision');

        $this->actingAs($this->dueno($escuela))
            ->delete(route('tramite.eliminar', ['escuela' => $escuela->id]))
            ->assertRedirect(route('tramite.index'))
            ->assertSessionHas('error');

        $this->assertNotNull(Escuela::find($escuela->id));
    }

    // --- UI ---

    public function test_mis_tramites_y_resumen_ofrecen_eliminar_con_confirmacion(): void
    {
        $escuela = $this->tramiteCompleto();
        $dueno = $this->dueno($escuela);
        $accion = 'action="'.route('tramite.eliminar', ['escuela' => $escuela->id]).'"';

        foreach ([route('tramite.index'), route('tramite.resumen', ['escuela' => $escuela->id])] as $pagina) {
            $this->actingAs($dueno)->get($pagina)
                ->assertOk()
                ->assertSee('Eliminar trámite')
                ->assertSee('<dialog', false)
                ->assertSee('.showModal()', false)
                ->assertSee($accion, false)
                ->assertSee('name="_method" value="DELETE"', false)
                ->assertSee('Se borrará el trámite, sus datos y archivos. El domicilio del plantel no se elimina.')
                ->assertSee('Sí, eliminar trámite')
                ->assertSee('Cancelar');
        }
    }

    public function test_fuera_de_captura_no_se_ofrece_eliminar(): void
    {
        $escuela = $this->tramiteCompleto();
        $this->cambiarEstado(EscuelaNivel::where('escuela_id', $escuela->id)->sole(), 'en_revision');
        $dueno = $this->dueno($escuela);

        foreach ([route('tramite.index'), route('tramite.resumen', ['escuela' => $escuela->id])] as $pagina) {
            $this->actingAs($dueno)->get($pagina)
                ->assertOk()
                ->assertDontSee('Eliminar trámite');
        }
    }

    public function test_mis_tramites_muestra_el_aviso_de_eliminado(): void
    {
        $escuela = $this->tramiteCompleto();

        $this->actingAs($this->dueno($escuela))
            ->followingRedirects()
            ->delete(route('tramite.eliminar', ['escuela' => $escuela->id]))
            ->assertOk()
            ->assertSee('Trámite eliminado.');
    }
}
