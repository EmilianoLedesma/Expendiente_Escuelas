<?php

namespace Tests\Feature\Application\Revision;

use App\Application\Excepciones\DatosInvalidos;
use App\Application\Excepciones\PrecondicionIncumplida;
use App\Application\ProyectoEducativo\SeccionesProyectoEducativo;
use App\Application\Revision\DecidirPaso;
use App\Application\Revision\DecidirSeccionProyecto;
use App\Application\Revision\PasosRevision;
use App\Application\Revision\RevisionAprobada;
use App\Application\Revision\RolesSedeq;
use App\Models\EscuelaNivel;
use App\Models\Solicitante;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\Concerns\ConTramitesEnRevision;
use Tests\TestCase;

class DecidirSeccionProyectoTest extends TestCase
{
    use ConTramitesEnRevision;
    use RefreshDatabase;

    private User $sup;

    private User $evaluador;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sup = $this->supervisor();
        $this->evaluador = User::factory()->create()->assignRole(RolesSedeq::EVALUADOR_PROYECTO);
    }

    private function nivelMedia(string $estado = 'en_revision'): EscuelaNivel
    {
        return $this->nivelDe(Solicitante::factory()->create(), 'media_superior', $estado);
    }

    /** @return list<string> */
    private function claves(): array
    {
        return SeccionesProyectoEducativo::clavesRevision();
    }

    private function decidirSeccion(int $actorId, EscuelaNivel $nivel, string $clave, string $decision = 'aceptado', ?string $motivo = null): void
    {
        app(DecidirSeccionProyecto::class)->ejecutar($actorId, $nivel->id, $clave, $decision, $motivo);
    }

    private function aceptarSecciones(EscuelaNivel $nivel, ?int $salvo = null): void
    {
        foreach ($this->claves() as $i => $clave) {
            if ($i !== $salvo) {
                $this->decidirSeccion($this->evaluador->id, $nivel, $clave);
            }
        }
    }

    private function aceptarPasosDelRevisor(EscuelaNivel $nivel): void
    {
        foreach (PasosRevision::paraNivel('media_superior') as $paso) {
            app(DecidirPaso::class)->ejecutar($this->sup->id, $nivel->id, $paso, 'aceptado');
        }
    }

    private function estado(EscuelaNivel $nivel): string
    {
        return (string) DB::table('estados_expediente')->where('id', DB::table('escuela_niveles')->where('id', $nivel->id)->value('estado_id'))->value('clave');
    }

    public function test_las_claves_de_revision_son_pe_01_a_pe_20_en_el_orden_del_indice(): void
    {
        $claves = $this->claves();

        $this->assertCount(20, $claves);
        $this->assertSame('pe_01', $claves[0]);
        $this->assertSame('pe_20', $claves[19]);
        $this->assertSame('pe_01', SeccionesProyectoEducativo::claveRevision(SeccionesProyectoEducativo::presentacion()));
        $this->assertSame('pe_20', SeccionesProyectoEducativo::claveRevision(SeccionesProyectoEducativo::bibliografia()));
        $this->assertSame(SeccionesProyectoEducativo::bibliografia(), SeccionesProyectoEducativo::slugDeRevision('pe_20'));
        $this->assertNull(SeccionesProyectoEducativo::slugDeRevision('pe_21'));
        $this->assertNull(SeccionesProyectoEducativo::slugDeRevision('plantel'));
    }

    public function test_el_revisor_nunca_ve_las_claves_del_proyecto_educativo(): void
    {
        $this->assertSame([], array_intersect($this->claves(), PasosRevision::TODOS));
    }

    public function test_acepta_y_registra_actual_e_historial_sin_tocar_el_progreso_del_revisor(): void
    {
        $nivel = $this->nivelMedia();

        $this->decidirSeccion($this->evaluador->id, $nivel, 'pe_01');

        $fila = DB::table('decisiones_revision')->where('escuela_nivel_id', $nivel->id)->where('paso_clave', 'pe_01')->first();
        $this->assertSame('aceptado', $fila->estado);
        $this->assertSame($this->evaluador->id, (int) $fila->decidido_por);
        $this->assertSame(1, DB::table('historial_decisiones_revision')->where('escuela_nivel_id', $nivel->id)->where('paso_clave', 'pe_01')->count());
        $this->assertSame(0, DB::table('progreso_revision')->where('escuela_nivel_id', $nivel->id)->count());
        $this->assertSame('en_revision', $this->estado($nivel));
    }

    public function test_se_puede_volver_a_decidir_mientras_siga_en_revision(): void
    {
        $nivel = $this->nivelMedia();

        $this->decidirSeccion($this->evaluador->id, $nivel, 'pe_02', 'rechazado', 'Falta detalle.');
        $this->decidirSeccion($this->evaluador->id, $nivel, 'pe_02');

        $this->assertSame('aceptado', DB::table('decisiones_revision')->where('paso_clave', 'pe_02')->value('estado'));
        $this->assertSame(2, DB::table('historial_decisiones_revision')->where('paso_clave', 'pe_02')->count());
        $this->assertSame(1, DB::table('decisiones_revision')->where('paso_clave', 'pe_02')->count());
    }

    public function test_rechazo_sin_motivo_es_datos_invalidos_y_no_escribe(): void
    {
        $nivel = $this->nivelMedia();

        foreach ([null, '', '   '] as $motivo) {
            try {
                $this->decidirSeccion($this->evaluador->id, $nivel, 'pe_01', 'rechazado', $motivo);
                $this->fail('Debió lanzar DatosInvalidos');
            } catch (DatosInvalidos $e) {
                $this->assertSame(['motivo' => 'Escribe el motivo del rechazo.'], $e->errores);
            }
        }
        $this->assertSame(0, DB::table('decisiones_revision')->count());
        $this->assertSame(0, DB::table('historial_decisiones_revision')->count());
    }

    public function test_clave_o_decision_invalidas_son_datos_invalidos(): void
    {
        $nivel = $this->nivelMedia();

        foreach ([['pe_21', 'aceptado'], ['plantel', 'aceptado'], ['pe_01', 'quizas']] as [$clave, $decision]) {
            try {
                $this->decidirSeccion($this->evaluador->id, $nivel, $clave, $decision);
                $this->fail('Debió lanzar DatosInvalidos');
            } catch (DatosInvalidos $e) {
                $this->assertArrayHasKey('pasoClave', $e->errores);
            }
        }
        $this->assertSame(0, DB::table('decisiones_revision')->count());
    }

    public function test_solo_el_evaluador_decide_secciones(): void
    {
        $nivel = $this->nivelMedia();

        foreach ([$this->sup, $this->revisor(), User::factory()->create(), Solicitante::factory()->create()->user] as $actor) {
            try {
                $this->decidirSeccion($actor->id, $nivel, 'pe_01');
                $this->fail('Debió rechazar al actor');
            } catch (PrecondicionIncumplida $e) {
                $this->assertSame('rol', $e->etapaFaltante);
            }
        }
        $this->assertSame(0, DB::table('decisiones_revision')->count());
    }

    public function test_decidir_paso_no_acepta_una_clave_del_proyecto(): void
    {
        $nivel = $this->nivelMedia();

        $this->expectException(DatosInvalidos::class);
        app(DecidirPaso::class)->ejecutar($this->sup->id, $nivel->id, 'pe_01', 'aceptado');
    }

    public function test_el_evaluador_no_puede_decidir_los_pasos_del_revisor(): void
    {
        $nivel = $this->nivelMedia();

        $this->expectException(AuthorizationException::class);
        app(DecidirPaso::class)->ejecutar($this->evaluador->id, $nivel->id, 'plantel', 'aceptado');
    }

    public function test_solo_aplica_a_media_superior(): void
    {
        $primaria = $this->nivelEnRevision();

        try {
            $this->decidirSeccion($this->evaluador->id, $primaria, 'pe_01');
            $this->fail('Debió rechazar el nivel');
        } catch (DatosInvalidos $e) {
            $this->assertArrayHasKey('pasoClave', $e->errores);
        }
        $this->assertSame(0, DB::table('decisiones_revision')->count());
    }

    public function test_solo_en_revision(): void
    {
        foreach (['en_captura', 'con_observaciones', 'aprobado'] as $estado) {
            $nivel = $this->nivelMedia($estado);

            try {
                $this->decidirSeccion($this->evaluador->id, $nivel, 'pe_01');
                $this->fail("Debió rechazar el estado {$estado}");
            } catch (PrecondicionIncumplida $e) {
                $this->assertSame('en_revision', $e->etapaFaltante);
            }
        }
        $this->assertSame(0, DB::table('decisiones_revision')->count());
    }

    public function test_las_veinte_aceptadas_no_cierran_el_nivel_si_el_revisor_no_termino(): void
    {
        Event::fake([RevisionAprobada::class]);
        $nivel = $this->nivelMedia();

        $this->aceptarSecciones($nivel);

        $this->assertSame('en_revision', $this->estado($nivel));
        Event::assertNotDispatched(RevisionAprobada::class);
    }

    public function test_los_pasos_del_revisor_aceptados_no_cierran_el_nivel_si_faltan_secciones(): void
    {
        Event::fake([RevisionAprobada::class]);
        $nivel = $this->nivelMedia();

        $this->aceptarPasosDelRevisor($nivel);

        $this->assertSame('en_revision', $this->estado($nivel));
        Event::assertNotDispatched(RevisionAprobada::class);
    }

    public function test_con_ambas_pistas_aceptadas_el_nivel_se_aprueba_sin_importar_el_orden(): void
    {
        Event::fake([RevisionAprobada::class]);
        $a = $this->nivelMedia();
        $this->aceptarSecciones($a);
        $this->aceptarPasosDelRevisor($a);

        $b = $this->nivelMedia();
        $this->aceptarPasosDelRevisor($b);
        $this->aceptarSecciones($b);

        $this->assertSame('aprobado', $this->estado($a));
        $this->assertSame('aprobado', $this->estado($b));
        $this->assertSame(1, DB::table('historial_estados_expediente')->where('escuela_nivel_id', $a->id)->where('comentario', 'Todos los pasos aceptados')->count());
        Event::assertDispatched(RevisionAprobada::class);
    }

    public function test_un_rechazo_de_seccion_devuelve_el_nivel_solo_cuando_ambas_pistas_terminaron(): void
    {
        Event::fake();
        $nivel = $this->nivelMedia();
        $this->decidirSeccion($this->evaluador->id, $nivel, 'pe_05', 'rechazado', 'Falta el modelo de evaluación.');
        $this->aceptarSecciones($nivel, salvo: 4);
        $this->assertSame('en_revision', $this->estado($nivel));

        $this->aceptarPasosDelRevisor($nivel);

        $this->assertSame('con_observaciones', $this->estado($nivel));
        $this->assertSame(1, DB::table('historial_estados_expediente')->where('escuela_nivel_id', $nivel->id)->where('comentario', 'Pasos con observaciones')->count());
    }

    public function test_un_rechazo_del_revisor_tambien_espera_a_las_secciones(): void
    {
        Event::fake();
        $nivel = $this->nivelMedia();
        $pasos = PasosRevision::paraNivel('media_superior');
        app(DecidirPaso::class)->ejecutar($this->sup->id, $nivel->id, $pasos[0], 'rechazado', 'Falta el plantel.');
        foreach (array_slice($pasos, 1) as $paso) {
            app(DecidirPaso::class)->ejecutar($this->sup->id, $nivel->id, $paso, 'aceptado');
        }
        $this->assertSame('en_revision', $this->estado($nivel));

        $this->aceptarSecciones($nivel);

        $this->assertSame('con_observaciones', $this->estado($nivel));
    }

    public function test_el_progreso_del_revisor_no_cuenta_las_secciones(): void
    {
        $nivel = $this->nivelMedia();
        $this->decidirSeccion($this->evaluador->id, $nivel, 'pe_01');

        app(DecidirPaso::class)->ejecutar($this->sup->id, $nivel->id, 'plantel', 'aceptado');

        $p = DB::table('progreso_revision')->where('escuela_nivel_id', $nivel->id)->first();
        $this->assertSame(1, (int) $p->secciones_revisadas);
        $this->assertSame(count(PasosRevision::paraNivel('media_superior')), (int) $p->secciones_total);
    }

    public function test_la_base_acepta_pe_01_a_pe_20_y_rechaza_otras_claves(): void
    {
        $nivel = $this->nivelMedia();
        $fila = fn (string $clave) => [
            'escuela_nivel_id' => $nivel->id, 'paso_clave' => $clave, 'estado' => 'aceptado', 'decidido_por' => $this->evaluador->id,
        ];

        foreach (['pe_01', 'pe_20'] as $clave) {
            DB::table('decisiones_revision')->insert($fila($clave));
            DB::table('historial_decisiones_revision')->insert($fila($clave));
        }
        $this->assertSame(2, DB::table('decisiones_revision')->count());

        foreach (['pe_00', 'pe_21', 'pe_1', 'PE_01'] as $clave) {
            foreach (['decisiones_revision', 'historial_decisiones_revision'] as $tabla) {
                try {
                    DB::transaction(fn () => DB::table($tabla)->insert($fila($clave)));
                    $this->fail("{$tabla} aceptó {$clave}");
                } catch (QueryException $e) {
                    $this->assertStringContainsString('paso_clave', $e->getMessage());
                }
            }
        }
    }
}
