<?php

namespace Tests\Feature\Application\Tramite;

use App\Application\EscuelaNiveles\MarcarPasoCompletado;
use App\Application\ProyectoEducativo\SeccionesProyectoEducativo;
use App\Application\Revision\DecidirSeccionProyecto;
use App\Application\Revision\PasosRevision;
use App\Application\Revision\RolesSedeq;
use App\Application\Tramite\ObservacionesDeEscuela;
use App\Application\Tramite\PasosReabiertos;
use App\Application\Tramite\ReenviarCorrecciones;
use App\Application\Tramite\ResumenTramite;
use App\Application\Tramite\TramiteEditable;
use App\Models\Escuela;
use App\Models\EscuelaNivel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\Concerns\CapturaExpedienteConsistente;
use Tests\Concerns\ConTramiteDevuelto;
use Tests\TestCase;

/** Devolución del proyecto educativo: una sección rechazada (pe_NN) reabre el paso proyecto_educativo del nivel. */
class DevolucionProyectoEducativoTest extends TestCase
{
    use CapturaExpedienteConsistente;
    use ConTramiteDevuelto;
    use RefreshDatabase;

    private const MOTIVO_5 = 'Falta el modelo de evaluación.';

    private const MOTIVO_7 = 'Falta el currículum ampliado.';

    /** @return array{Escuela, EscuelaNivel} media superior completa y devuelta con pe_05 y pe_07 rechazadas */
    private function devuelto(): array
    {
        $escuela = $this->crearEscuelaConPlantel();
        $nivel = $this->completarTramite($escuela, nivelClave: 'media_superior');
        (new MarcarPasoCompletado)->ejecutar($nivel->id, SeccionesProyectoEducativo::CLAVE_PASO);

        $claves = SeccionesProyectoEducativo::clavesRevision();
        $this->devolverTramite(
            $escuela,
            [$nivel->id => ['pe_05' => self::MOTIVO_5, 'pe_07' => self::MOTIVO_7]],
            [$nivel->id => [...PasosRevision::paraNivel('media_superior'), ...array_values(array_diff($claves, ['pe_05', 'pe_07']))]],
        );

        return [$escuela, $nivel];
    }

    private function estadoDe(EscuelaNivel $nivel): string
    {
        return (string) DB::table('estados_expediente')->where('id', $nivel->fresh()->estado_id)->value('clave');
    }

    public function test_dos_secciones_rechazadas_reabren_un_solo_paso(): void
    {
        [$escuela, $nivel] = $this->devuelto();

        $this->assertSame(
            [['paso' => 'proyecto_educativo', 'escuelaNivelId' => $nivel->id]],
            app(PasosReabiertos::class)->reabiertos($escuela->id),
        );
        $this->assertTrue(app(PasosReabiertos::class)->estaReabierto($escuela->id, 'proyecto_educativo', $nivel->id));
        $this->assertTrue(app(TramiteEditable::class)->esEditable($escuela->id, 'proyecto_educativo', $nivel->id));
        $this->assertFalse(app(TramiteEditable::class)->esEditable($escuela->id, 'matricula', $nivel->id));
    }

    public function test_sin_secciones_rechazadas_el_paso_no_se_reabre(): void
    {
        [$escuela, $nivel] = $this->devuelto();
        DB::table('decisiones_revision')->where('escuela_nivel_id', $nivel->id)->where('estado', 'rechazado')->delete();

        $this->assertFalse(app(PasosReabiertos::class)->estaReabierto($escuela->id, 'proyecto_educativo', $nivel->id));
    }

    public function test_las_observaciones_juntan_los_motivos_de_las_secciones_en_el_paso(): void
    {
        [$escuela, $nivel] = $this->devuelto();

        $todas = app(ObservacionesDeEscuela::class)->todas($escuela->id);

        $this->assertCount(1, $todas);
        $this->assertSame('proyecto_educativo', $todas[0]['paso']);
        $this->assertSame('Presentación del Proyecto Educativo', $todas[0]['titulo']);
        $this->assertSame($nivel->id, $todas[0]['escuelaNivelId']);
        foreach ([SeccionesProyectoEducativo::nombre(SeccionesProyectoEducativo::evaluacion()), self::MOTIVO_5, SeccionesProyectoEducativo::nombre(SeccionesProyectoEducativo::curriculum()), self::MOTIVO_7] as $texto) {
            $this->assertStringContainsString($texto, $todas[0]['motivo']);
        }
    }

    public function test_el_solicitante_ve_que_secciones_se_rechazaron_y_por_que(): void
    {
        [$escuela, $nivel] = $this->devuelto();

        $this->assertSame(
            [SeccionesProyectoEducativo::evaluacion() => self::MOTIVO_5, SeccionesProyectoEducativo::curriculum() => self::MOTIVO_7],
            app(ObservacionesDeEscuela::class)->seccionesRechazadas($nivel->id),
        );
    }

    public function test_sin_ronda_cerrada_no_hay_secciones_rechazadas_visibles(): void
    {
        [$escuela, $nivel] = $this->devuelto();
        $nivel->update(['estado_id' => DB::table('estados_expediente')->where('clave', 'en_revision')->value('id')]);

        $this->assertSame([], app(ObservacionesDeEscuela::class)->seccionesRechazadas($nivel->id));
    }

    public function test_el_resumen_marca_el_paso_con_observaciones_y_ofrece_corregir(): void
    {
        [$escuela, $nivel] = $this->devuelto();

        $resumen = app(ResumenTramite::class)->paraEscuela($escuela->id, (int) $escuela->solicitante->user->id);
        $seccion = collect($resumen->niveles[0]->secciones)->firstWhere('clave', 'proyecto_educativo');

        $this->assertSame('con_observaciones', $seccion->estado);
        $this->assertSame('corregir', $seccion->accion);
        $this->assertStringContainsString(self::MOTIVO_5, (string) $seccion->observacion);
    }

    public function test_los_titulos_de_revision_cubren_las_secciones_del_proyecto(): void
    {
        $this->assertSame('Datos del plantel', PasosRevision::titulo('plantel'));
        $this->assertSame('Proyecto educativo · Modelo de Evaluación', PasosRevision::titulo('pe_05'));
        $this->assertSame('Proyecto educativo · Bibliografía del Proyecto', PasosRevision::titulo('pe_20'));
    }

    public function test_reenviar_borra_solo_las_secciones_rechazadas_y_deja_el_nivel_en_revision(): void
    {
        [$escuela, $nivel] = $this->devuelto();

        app(ReenviarCorrecciones::class)->ejecutar($escuela->id, (int) $escuela->solicitante->user->id);

        $this->assertSame('en_revision', $this->estadoDe($nivel));
        $quedan = DB::table('decisiones_revision')->where('escuela_nivel_id', $nivel->id)->pluck('paso_clave')->all();
        $this->assertNotContains('pe_05', $quedan);
        $this->assertNotContains('pe_07', $quedan);
        $this->assertContains('pe_01', $quedan);
        $this->assertCount(count(PasosRevision::paraNivel('media_superior')) + 18, $quedan);
        // El progreso del revisor sigue contando solo sus pasos.
        $p = DB::table('progreso_revision')->where('escuela_nivel_id', $nivel->id)->sole();
        $this->assertSame(count(PasosRevision::paraNivel('media_superior')), (int) $p->secciones_total);
        $this->assertSame(count(PasosRevision::paraNivel('media_superior')), (int) $p->secciones_revisadas);
    }

    public function test_tras_el_reenvio_el_evaluador_decide_solo_las_rechazadas_y_el_nivel_se_aprueba(): void
    {
        [$escuela, $nivel] = $this->devuelto();
        app(ReenviarCorrecciones::class)->ejecutar($escuela->id, (int) $escuela->solicitante->user->id);
        $evaluador = User::factory()->create()->assignRole(Role::findOrCreate(RolesSedeq::EVALUADOR_PROYECTO, 'web'));

        app(DecidirSeccionProyecto::class)->ejecutar($evaluador->id, $nivel->id, 'pe_05', 'aceptado');
        $this->assertSame('en_revision', $this->estadoDe($nivel));
        app(DecidirSeccionProyecto::class)->ejecutar($evaluador->id, $nivel->id, 'pe_07', 'aceptado');

        $this->assertSame('aprobado', $this->estadoDe($nivel));
    }
}
