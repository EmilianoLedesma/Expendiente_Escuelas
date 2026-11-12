<?php

namespace Tests\Feature\Archivo;

use App\Application\Archivo\ArchivarCuenta;
use App\Application\Archivo\ArchivarTramite;
use App\Application\Archivo\Purga;
use App\Application\Archivo\PurgarCuenta;
use App\Application\Archivo\PurgarTramite;
use App\Application\Excepciones\DatosInvalidos;
use App\Application\Excepciones\PrecondicionIncumplida;
use App\Application\Revision\RolesSedeq;
use App\Application\Tramite\EnviarTramite;
use App\Models\DocumentoEscuela;
use App\Models\DocumentoEscuelaNivel;
use App\Models\Escuela;
use App\Models\EscuelaNivel;
use App\Models\Plantel;
use App\Models\ResponsableNivel;
use App\Models\Scopes\EscuelaNoArchivada;
use App\Models\Scopes\NivelDeEscuelaNoArchivada;
use App\Models\Solicitante;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CapturaExpedienteConsistente;
use Tests\Concerns\ConTramitesEnRevision;
use Tests\TestCase;

class PurgarTest extends TestCase
{
    use CapturaExpedienteConsistente;
    use ConTramitesEnRevision;
    use RefreshDatabase;

    /** Todo lo que cuelga de una escuela; tras purgar el único trámite, cada una debe quedar vacía. */
    private const TABLAS_DEL_TRAMITE = [
        'escuelas', 'responsables_legales', 'escuela_niveles', 'documentos_escuela', 'documentos_escuela_nivel',
        'escuela_nivel_pasos', 'historial_estados_expediente', 'evaluaciones_validacion',
    ];

    private function admin(): User
    {
        (new RolesSeeder)->run();

        return User::factory()->create()->assignRole(RolesSedeq::ADMIN);
    }

    private function escuela(int $id): ?Escuela
    {
        return Escuela::withoutGlobalScope(EscuelaNoArchivada::class)->find($id);
    }

    /** Trámite completo con historial y reporte de validación guardado, archivado por el admin. */
    private function tramiteArchivado(User $admin): Escuela
    {
        $escuela = $this->crearEscuelaConPlantel();
        $nivel = $this->completarTramite($escuela);
        DB::table('historial_estados_expediente')->insert(['escuela_nivel_id' => $nivel->id, 'estado_id' => $nivel->estado_id, 'usuario_sedeq_id' => $admin->id]);
        $reporte = "validaciones/{$escuela->id}/reporte-prueba.pdf";
        Storage::disk('documentos')->put($reporte, '%PDF');
        DB::table('evaluaciones_validacion')->insert(['escuela_id' => $escuela->id, 'archivo_path' => $reporte, 'lista_para_envio' => true, 'resultados' => '[]']);

        app(ArchivarTramite::class)->ejecutar($escuela->id, $admin->id, 'Duplicado');

        return $escuela;
    }

    /** @return list<string> */
    private function archivosDelTramite(Escuela $escuela): array
    {
        $niveles = EscuelaNivel::withoutGlobalScope(NivelDeEscuelaNoArchivada::class)->where('escuela_id', $escuela->id)->pluck('id');

        return array_values(array_filter([
            ...DocumentoEscuela::where('escuela_id', $escuela->id)->pluck('archivo_path'),
            ...DocumentoEscuelaNivel::whereIn('escuela_nivel_id', $niveles)->pluck('archivo_path'),
            ...DB::table('evaluaciones_validacion')->where('escuela_id', $escuela->id)->pluck('archivo_path'),
        ]));
    }

    private function purgas(): int
    {
        return DB::table('bitacora_acciones')->whereIn('accion', ['purgar_tramite', 'purgar_cuenta'])->count();
    }

    // --- PurgarTramite ---

    public function test_purga_un_tramite_archivado_con_sus_filas_y_archivos(): void
    {
        $admin = $this->admin();
        $escuela = $this->tramiteArchivado($admin);
        $archivos = $this->archivosDelTramite($escuela);
        $this->assertNotEmpty($archivos);
        foreach (self::TABLAS_DEL_TRAMITE as $tabla) {
            $this->assertGreaterThan(0, DB::table($tabla)->count(), "el fixture debe poblar {$tabla}");
        }

        app(PurgarTramite::class)->ejecutar($admin->id, $escuela->id, 'Solicitud de baja', Purga::CONFIRMACION);

        foreach (self::TABLAS_DEL_TRAMITE as $tabla) {
            $this->assertSame(0, DB::table($tabla)->count(), "{$tabla} debió vaciarse");
        }
        foreach ($archivos as $ruta) {
            Storage::disk('documentos')->assertMissing($ruta);
        }
        $this->assertNotNull(Plantel::find($escuela->plantel_id), 'el plantel se conserva');
        $fila = DB::table('bitacora_acciones')->where('accion', 'purgar_tramite')->sole();
        $this->assertSame($escuela->id, (int) $fila->objeto_id);
        $this->assertSame('escuela', $fila->objeto_tipo);
        $this->assertSame($admin->id, (int) $fila->actor_id);
        $this->assertSame('Solicitud de baja', $fila->motivo);
    }

    public function test_purgar_borra_tambien_los_pdf_del_paec(): void
    {
        $admin = $this->admin();
        $escuela = $this->tramiteArchivado($admin);
        $nivelId = EscuelaNivel::withoutGlobalScope(NivelDeEscuelaNoArchivada::class)->where('escuela_id', $escuela->id)->value('id');
        $rutas = ["proyecto_educativo/{$nivelId}/paec_hoja_ruta-x.pdf", "proyecto_educativo/{$nivelId}/paec_planeacion-x.pdf"];
        foreach ($rutas as $ruta) {
            Storage::disk('documentos')->put($ruta, '%PDF');
        }
        DB::table('proyecto_educativo_paec')->insert([
            'escuela_nivel_id' => $nivelId,
            'hoja_ruta_path' => $rutas[0], 'hoja_ruta_nombre' => 'hoja.pdf', 'hoja_ruta_paginas' => 1,
            'planeacion_path' => $rutas[1], 'planeacion_nombre' => 'planeacion.pdf', 'planeacion_paginas' => 2,
        ]);

        app(PurgarTramite::class)->ejecutar($admin->id, $escuela->id, 'Solicitud de baja', Purga::CONFIRMACION);

        $this->assertSame(0, DB::table('proyecto_educativo_paec')->count());
        foreach ($rutas as $ruta) {
            Storage::disk('documentos')->assertMissing($ruta);
        }
    }

    public function test_purgar_borra_tambien_el_pdf_de_planeacion_de_la_formacion_continua(): void
    {
        $admin = $this->admin();
        $escuela = $this->tramiteArchivado($admin);
        $nivelId = EscuelaNivel::withoutGlobalScope(NivelDeEscuelaNoArchivada::class)->where('escuela_id', $escuela->id)->value('id');
        $ruta = "proyecto_educativo/{$nivelId}/formacion_continua_planeacion-x.pdf";
        Storage::disk('documentos')->put($ruta, '%PDF');
        DB::table('proyecto_educativo_formacion_politica')->insert([
            'escuela_nivel_id' => $nivelId, 'tipo' => 'continua', 'politica' => 'texto',
            'planeacion_path' => $ruta, 'planeacion_nombre' => 'planeacion.pdf', 'planeacion_paginas' => 1,
        ]);

        app(PurgarTramite::class)->ejecutar($admin->id, $escuela->id, 'Solicitud de baja', Purga::CONFIRMACION);

        $this->assertSame(0, DB::table('proyecto_educativo_formacion_politica')->count());
        Storage::disk('documentos')->assertMissing($ruta);
    }

    public function test_un_tramite_no_archivado_no_se_purga(): void
    {
        $admin = $this->admin();
        $nivel = $this->nivelDe(Solicitante::factory()->create());

        try {
            app(PurgarTramite::class)->ejecutar($admin->id, $nivel->escuela_id, 'motivo', Purga::CONFIRMACION);
            $this->fail('Debió rechazar la purga.');
        } catch (PrecondicionIncumplida) {
        }

        $this->assertNotNull($this->escuela($nivel->escuela_id));
        $this->assertSame(0, $this->purgas());
    }

    /** @return array<string, array{string, string}> */
    public static function gatesInvalidos(): array
    {
        return [
            'confirmación equivocada' => ['motivo', 'purgar'],
            'confirmación vacía' => ['motivo', ''],
            'motivo vacío' => ['', Purga::CONFIRMACION],
            'motivo en blanco' => ['   ', Purga::CONFIRMACION],
        ];
    }

    #[DataProvider('gatesInvalidos')]
    public function test_sin_confirmacion_o_motivo_no_purga_el_tramite(string $motivo, string $confirmacion): void
    {
        $admin = $this->admin();
        $escuela = $this->tramiteArchivado($admin);

        $this->expectException(DatosInvalidos::class);

        try {
            app(PurgarTramite::class)->ejecutar($admin->id, $escuela->id, $motivo, $confirmacion);
        } finally {
            $this->assertNotNull($this->escuela($escuela->id));
            $this->assertSame(0, $this->purgas());
        }
    }

    public function test_quien_no_es_admin_no_purga_un_tramite(): void
    {
        $admin = $this->admin();
        $escuela = $this->tramiteArchivado($admin);
        $dueno = Solicitante::findOrFail($escuela->solicitante_id)->user;

        foreach ([$dueno, $this->supervisor()] as $actor) {
            try {
                app(PurgarTramite::class)->ejecutar($actor->id, $escuela->id, 'motivo', Purga::CONFIRMACION);
                $this->fail('Debió rechazar la purga.');
            } catch (PrecondicionIncumplida) {
            }
        }

        $this->assertNotNull($this->escuela($escuela->id));
        $this->assertSame(0, $this->purgas());
    }

    // --- PurgarCuenta ---

    public function test_purga_la_cuenta_de_un_solicitante_con_sus_tramites_archivados_y_responsables_del_lote(): void
    {
        Storage::fake('documentos');
        $admin = $this->admin();
        $solicitante = Solicitante::factory()->create();
        $usuario = $solicitante->user;
        $suelto = $this->nivelDe($solicitante);
        $enCascada = $this->nivelDe($solicitante, 'secundaria');
        $responsable = $this->responsableDe($enCascada);
        $compartido = $this->responsableDe($enCascada, $this->nivelDe(Solicitante::factory()->create()));
        app(ArchivarTramite::class)->ejecutar($suelto->escuela_id, $admin->id, 'Aparte');
        app(ArchivarCuenta::class)->ejecutar($usuario->id, $admin->id, 'Baja');

        app(PurgarCuenta::class)->ejecutar($admin->id, $usuario->id, 'Derecho de cancelación', Purga::CONFIRMACION);

        $this->assertNull(User::find($usuario->id));
        $this->assertNull(Solicitante::find($solicitante->id));
        $this->assertNull($this->escuela($suelto->escuela_id));
        $this->assertNull($this->escuela($enCascada->escuela_id));
        $this->assertNull(User::find($responsable->id), 'el responsable archivado en el lote se purga');
        $this->assertNotNull(User::find($compartido->id), 'quien tiene otro acceso no estaba en el lote');

        $cuenta = DB::table('bitacora_acciones')->where('accion', 'purgar_cuenta')->sole();
        $this->assertSame($usuario->id, (int) $cuenta->objeto_id);
        $this->assertSame('user', $cuenta->objeto_tipo);
        $this->assertSame('Derecho de cancelación', $cuenta->motivo);
        $this->assertSame(['tramites' => 2, 'responsables' => 1], json_decode($cuenta->detalle, true));
        $this->assertSame(2, DB::table('bitacora_acciones')->where('accion', 'purgar_tramite')->count());
        foreach (DB::table('bitacora_acciones')->get() as $fila) {
            foreach ([$usuario->email, $usuario->name, $responsable->email, $responsable->name] as $dato) {
                $this->assertStringNotContainsString($dato, $fila->detalle);
                $this->assertStringNotContainsString($dato, (string) $fila->motivo);
            }
        }
    }

    /** Un responsable del lote que después obtuvo acceso en otro solicitante no se borra en silencio: la purga entera se rechaza. */
    public function test_un_responsable_del_lote_con_acceso_en_otro_solicitante_impide_la_purga(): void
    {
        $admin = $this->admin();
        $solicitante = Solicitante::factory()->create();
        $nivel = $this->nivelDe($solicitante);
        $responsable = $this->responsableDe($nivel);
        app(ArchivarCuenta::class)->ejecutar($solicitante->user_id, $admin->id, 'Baja');
        $otroNivel = $this->nivelDe(Solicitante::factory()->create());
        $acceso = ResponsableNivel::create([
            'user_id' => $responsable->id,
            'escuela_nivel_id' => $otroNivel->id,
            'invitado_por_solicitante_id' => $otroNivel->escuela->solicitante_id,
        ]);

        try {
            app(PurgarCuenta::class)->ejecutar($admin->id, $solicitante->user_id, 'motivo', Purga::CONFIRMACION);
            $this->fail('Debió rechazar la purga.');
        } catch (PrecondicionIncumplida) {
        }

        $this->assertNotNull(ResponsableNivel::find($acceso->id));
        $this->assertNotNull(User::find($responsable->id));
        $this->assertNotNull(User::find($solicitante->user_id));
        $this->assertNotNull(Solicitante::find($solicitante->id));
        $this->assertNotNull($this->escuela($nivel->escuela_id));
        $this->assertSame(1, ResponsableNivel::where('escuela_nivel_id', $nivel->id)->count());
        $this->assertSame(0, $this->purgas());
    }

    /** EnviarTramite escribe el users.id del solicitante en historial_estados_expediente; la cascada lo borra, no debe impedir la purga. */
    public function test_purga_a_un_solicitante_que_envio_un_tramite(): void
    {
        Storage::fake('documentos');
        $admin = $this->admin();
        $escuela = $this->crearEscuelaConPlantel();
        $this->completarTramite($escuela);
        $usuario = $escuela->solicitante->user;
        app(EnviarTramite::class)->ejecutar($escuela->id, $usuario->id);
        $this->assertTrue(DB::table('historial_estados_expediente')->where('usuario_sedeq_id', $usuario->id)->exists());
        app(ArchivarCuenta::class)->ejecutar($usuario->id, $admin->id, 'Baja');

        app(PurgarCuenta::class)->ejecutar($admin->id, $usuario->id, 'motivo', Purga::CONFIRMACION);

        $this->assertNull(User::find($usuario->id));
        $this->assertNull(Solicitante::find($escuela->solicitante_id));
        foreach (self::TABLAS_DEL_TRAMITE as $tabla) {
            $this->assertSame(0, DB::table($tabla)->count(), "{$tabla} debió vaciarse");
        }
    }

    /** El responsable arrastrado comparte el lote del solicitante: purgarlo solo se rechaza en vez de chocar con la FK del solicitante. */
    public function test_no_purga_por_separado_a_un_responsable_archivado_en_cascada(): void
    {
        $admin = $this->admin();
        $solicitante = Solicitante::factory()->create();
        $nivel = $this->nivelDe($solicitante);
        $responsable = $this->responsableDe($nivel);
        app(ArchivarCuenta::class)->ejecutar($solicitante->user_id, $admin->id, 'Baja');

        try {
            app(PurgarCuenta::class)->ejecutar($admin->id, $responsable->id, 'motivo', Purga::CONFIRMACION);
            $this->fail('Debió rechazar la purga.');
        } catch (PrecondicionIncumplida $e) {
            $this->assertSame('archivo', $e->etapaFaltante);
        }

        $this->assertNotNull(User::find($responsable->id));
        $this->assertNotNull(User::find($solicitante->user_id));
        $this->assertNotNull(Solicitante::find($solicitante->id));
        $this->assertNotNull($this->escuela($nivel->escuela_id));
        $this->assertSame(0, $this->purgas());
    }

    /** Sin lote, nadie más se purga: otra cuenta archivada sin lote sigue en pie. */
    public function test_una_cuenta_archivada_sin_lote_solo_se_purga_a_si_misma(): void
    {
        $admin = $this->admin();
        $usuario = User::factory()->create();
        $ajeno = User::factory()->create();
        foreach ([$usuario, $ajeno] as $u) {
            $u->forceFill(['archivado_at' => now(), 'archivado_por' => $admin->id])->save();
        }

        app(PurgarCuenta::class)->ejecutar($admin->id, $usuario->id, 'motivo', Purga::CONFIRMACION);

        $this->assertNull(User::find($usuario->id));
        $this->assertNotNull(User::find($ajeno->id));
        $this->assertSame(['tramites' => 0, 'responsables' => 0], json_decode(DB::table('bitacora_acciones')->where('accion', 'purgar_cuenta')->value('detalle'), true));
    }

    public function test_una_cuenta_con_un_tramite_sin_archivar_no_se_purga(): void
    {
        $admin = $this->admin();
        $solicitante = Solicitante::factory()->create();
        $archivado = $this->nivelDe($solicitante);
        app(ArchivarCuenta::class)->ejecutar($solicitante->user_id, $admin->id, 'Baja');
        $activo = $this->nivelDe($solicitante, 'secundaria');

        try {
            app(PurgarCuenta::class)->ejecutar($admin->id, $solicitante->user_id, 'motivo', Purga::CONFIRMACION);
            $this->fail('Debió rechazar la purga.');
        } catch (PrecondicionIncumplida) {
        }

        $this->assertNotNull(User::find($solicitante->user_id));
        $this->assertNotNull($this->escuela($archivado->escuela_id));
        $this->assertNotNull($this->escuela($activo->escuela_id));
        $this->assertSame(0, $this->purgas());
    }

    public function test_una_cuenta_no_archivada_no_se_purga(): void
    {
        $admin = $this->admin();
        $solicitante = Solicitante::factory()->create();

        $this->expectException(PrecondicionIncumplida::class);

        try {
            app(PurgarCuenta::class)->ejecutar($admin->id, $solicitante->user_id, 'motivo', Purga::CONFIRMACION);
        } finally {
            $this->assertNotNull(User::find($solicitante->user_id));
        }
    }

    #[DataProvider('gatesInvalidos')]
    public function test_sin_confirmacion_o_motivo_no_purga_la_cuenta(string $motivo, string $confirmacion): void
    {
        $admin = $this->admin();
        $solicitante = Solicitante::factory()->create();
        app(ArchivarCuenta::class)->ejecutar($solicitante->user_id, $admin->id, 'Baja');

        $this->expectException(DatosInvalidos::class);

        try {
            app(PurgarCuenta::class)->ejecutar($admin->id, $solicitante->user_id, $motivo, $confirmacion);
        } finally {
            $this->assertNotNull(User::find($solicitante->user_id));
            $this->assertSame(0, $this->purgas());
        }
    }

    public function test_quien_no_es_admin_no_purga_una_cuenta(): void
    {
        $admin = $this->admin();
        $solicitante = Solicitante::factory()->create();
        app(ArchivarCuenta::class)->ejecutar($solicitante->user_id, $admin->id, 'Baja');

        $this->expectException(PrecondicionIncumplida::class);

        try {
            app(PurgarCuenta::class)->ejecutar($this->supervisor()->id, $solicitante->user_id, 'motivo', Purga::CONFIRMACION);
        } finally {
            $this->assertNotNull(User::find($solicitante->user_id));
        }
    }

    public function test_un_revisor_con_decisiones_no_se_purga_y_nada_se_borra(): void
    {
        $admin = $this->admin();
        $revisor = $this->revisor();
        $nivel = $this->nivelEnRevision();
        DB::table('decisiones_revision')->insert(['escuela_nivel_id' => $nivel->id, 'paso_clave' => 'plantel', 'estado' => 'aceptado', 'decidido_por' => $revisor->id]);
        app(ArchivarCuenta::class)->ejecutar($revisor->id, $admin->id, 'Dejó SEDEQ');

        try {
            app(PurgarCuenta::class)->ejecutar($admin->id, $revisor->id, 'motivo', Purga::CONFIRMACION);
            $this->fail('Debió rechazar la purga.');
        } catch (PrecondicionIncumplida) {
        }

        $this->assertNotNull(User::find($revisor->id));
        $this->assertSame(1, DB::table('decisiones_revision')->count());
        $this->assertSame(0, $this->purgas());
    }

    /** @return array<string, array{string, string}> */
    public static function historial(): array
    {
        return [
            'asignaciones_revision.revisor_id' => ['asignaciones_revision', 'revisor_id'],
            'asignaciones_revision.asignado_por' => ['asignaciones_revision', 'asignado_por'],
            'bitacora_asignaciones.revisor_id' => ['bitacora_asignaciones', 'revisor_id'],
            'bitacora_asignaciones.actor_id' => ['bitacora_asignaciones', 'actor_id'],
            'decisiones_revision.decidido_por' => ['decisiones_revision', 'decidido_por'],
            'historial_decisiones_revision.decidido_por' => ['historial_decisiones_revision', 'decidido_por'],
            'historial_estados_expediente.usuario_sedeq_id' => ['historial_estados_expediente', 'usuario_sedeq_id'],
            'prioridades_revision.actualizado_por' => ['prioridades_revision', 'actualizado_por'],
            'revisores.user_id' => ['revisores', 'user_id'],
        ];
    }

    /** Una cuenta sin rol alguno pero referida por el historial de revisión tampoco se purga. */
    #[DataProvider('historial')]
    public function test_una_cuenta_referida_por_el_historial_de_revision_no_se_purga(string $tabla, string $columna): void
    {
        $admin = $this->admin();
        $usuario = User::factory()->create();
        $nivel = $this->nivelEnRevision();
        $otro = $admin->id;
        $filas = [
            'asignaciones_revision' => ['escuela_nivel_id' => $nivel->id, 'revisor_id' => $otro, 'asignado_por' => $otro],
            'bitacora_asignaciones' => ['escuela_nivel_id' => $nivel->id, 'revisor_id' => $otro, 'accion' => 'asignar', 'actor_id' => $otro],
            'decisiones_revision' => ['escuela_nivel_id' => $nivel->id, 'paso_clave' => 'plantel', 'estado' => 'aceptado', 'decidido_por' => $otro],
            'historial_decisiones_revision' => ['escuela_nivel_id' => $nivel->id, 'paso_clave' => 'plantel', 'estado' => 'aceptado', 'decidido_por' => $otro],
            'historial_estados_expediente' => ['escuela_nivel_id' => $nivel->id, 'estado_id' => $nivel->estado_id, 'usuario_sedeq_id' => $otro],
            'prioridades_revision' => ['escuela_nivel_id' => $nivel->id, 'actualizado_por' => $otro],
            'revisores' => ['user_id' => $otro, 'revocado_at' => now()],
        ];
        DB::table($tabla)->insert([...$filas[$tabla], $columna => $usuario->id]);
        $usuario->forceFill(['archivado_at' => now(), 'archivado_por' => $admin->id])->save();

        try {
            app(PurgarCuenta::class)->ejecutar($admin->id, $usuario->id, 'motivo', Purga::CONFIRMACION);
            $this->fail('Debió rechazar la purga.');
        } catch (PrecondicionIncumplida) {
        }

        $this->assertNotNull(User::find($usuario->id));
        $this->assertSame(1, DB::table($tabla)->where($columna, $usuario->id)->count());
    }

    public function test_el_admin_no_se_purga_a_si_mismo(): void
    {
        $admin = $this->admin();
        $this->admin();
        $admin->forceFill(['archivado_at' => now()])->save();

        $this->expectException(PrecondicionIncumplida::class);

        try {
            app(PurgarCuenta::class)->ejecutar($admin->id, $admin->id, 'motivo', Purga::CONFIRMACION);
        } finally {
            $this->assertNotNull(User::find($admin->id));
        }
    }

    public function test_no_se_purga_una_cuenta_de_admin_ni_la_del_ultimo_admin(): void
    {
        $actor = $this->admin();
        $otro = $this->admin();
        $otro->forceFill(['archivado_at' => now()])->save();

        $this->expectException(PrecondicionIncumplida::class);

        try {
            app(PurgarCuenta::class)->ejecutar($actor->id, $otro->id, 'motivo', Purga::CONFIRMACION);
        } finally {
            $this->assertNotNull(User::find($otro->id));
        }
    }
}
