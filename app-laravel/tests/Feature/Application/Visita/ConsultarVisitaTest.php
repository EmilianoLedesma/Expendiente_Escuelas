<?php

namespace Tests\Feature\Application\Visita;

use App\Application\Tramite\ResumenTramite;
use App\Application\Visita\ConsultarVisita;
use App\Models\Solicitante;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\ConTramitesEnRevision;
use Tests\Concerns\ConVisitas;
use Tests\Concerns\GeneraPdfs;
use Tests\TestCase;

class ConsultarVisitaTest extends TestCase
{
    use ConTramitesEnRevision;
    use ConVisitas;
    use GeneraPdfs;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('documentos');
    }

    public function test_nivel_aprobado_sin_visitas_permite_programar(): void
    {
        $nivel = $this->nivelEnRevision('aprobado');
        $d = app(ConsultarVisita::class)->ejecutar($this->supervisor(), $nivel->id);

        $this->assertSame(ResumenTramite::domicilio($nivel->escuela->plantel), $d['domicilioPlantel']);
        $this->assertSame('aprobado', $d['estado']['clave']);
        $this->assertNull($d['visita']);
        $this->assertSame([], $d['visitas']);
        $this->assertTrue($d['puedeProgramar']);
        $this->assertFalse($d['puedeRegistrarActa']);
        $this->assertFalse($d['hayRespuestaNueva']);
    }

    public function test_visita_programada_permite_registrar_y_actualizar_pero_no_programar(): void
    {
        $nivel = $this->nivelEnRevision('visita_programada');
        $this->visitaDe($nivel);
        $d = app(ConsultarVisita::class)->ejecutar($this->supervisor(), $nivel->id);

        $this->assertSame(1, $d['visita']['numero']);
        $this->assertTrue($d['puedeRegistrarActa']);
        $this->assertTrue($d['puedeActualizar']);
        $this->assertFalse($d['puedeProgramar']);
        $this->assertFalse($d['puedeAceptar']);
    }

    public function test_subsanacion_con_observaciones_respuestas_e_historial(): void
    {
        $nivel = $this->nivelEnRevision('en_subsanacion');
        $v1 = $this->visitaDe($nivel, 1, 'realizada', 'con_observaciones');
        DB::table('observaciones_visita')->insert([['visita_id' => $v1, 'orden' => 1, 'texto' => 'Falta extintor'], ['visita_id' => $v1, 'orden' => 2, 'texto' => 'Falta rampa']]);
        $this->respuestaDe($v1, 'evidencia.pdf', $this->archivoEnDisco());
        $d = app(ConsultarVisita::class)->ejecutar($this->supervisor(), $nivel->id);

        $this->assertSame(['Falta extintor', 'Falta rampa'], $d['visita']['observaciones']);
        $this->assertSame('evidencia.pdf', $d['visita']['respuestas'][0]['archivos'][0]['nombre']);
        $this->assertTrue($d['puedeAceptar']);
        $this->assertTrue($d['hayRespuestaNueva']);
        $this->assertTrue($d['puedeProgramar']);
        $this->assertCount(1, $d['visitas']);
    }

    public function test_programar_no_se_ofrece_con_una_programada_en_subsanacion(): void
    {
        $nivel = $this->nivelEnRevision('en_subsanacion');
        $this->visitaDe($nivel, 1, 'realizada', 'con_observaciones');
        $this->visitaDe($nivel, 2);
        $d = app(ConsultarVisita::class)->ejecutar($this->supervisor(), $nivel->id);

        $this->assertSame(2, $d['visita']['numero']);
        $this->assertFalse($d['puedeProgramar']);
        $this->assertCount(2, $d['visitas']);
    }

    public function test_respuesta_anterior_al_cambio_de_estado_no_es_nueva(): void
    {
        $nivel = $this->nivelEnRevision('visita_aprobada');
        $v1 = $this->visitaDe($nivel, 1, 'realizada', 'con_observaciones');
        $this->respuestaDe($v1, 'a.pdf', $this->archivoEnDisco(), now()->subHour());
        DB::table('historial_estados_expediente')->insert(['escuela_nivel_id' => $nivel->id, 'estado_id' => $nivel->estado_id,
            'comentario' => 'Subsanación aceptada', 'usuario_sedeq_id' => $this->supervisor()->id, 'fecha' => now()]);
        $d = app(ConsultarVisita::class)->ejecutar($this->supervisor(), $nivel->id);

        $this->assertFalse($d['hayRespuestaNueva']);
        $this->assertFalse($d['puedeAceptar']);
    }

    public function test_el_dueno_lee_sin_banderas_de_supervisor(): void
    {
        $sol = Solicitante::factory()->create();
        $nivel = $this->nivelDe($sol, 'primaria', 'visita_programada');
        $this->visitaDe($nivel);
        $d = app(ConsultarVisita::class)->ejecutar($sol->user, $nivel->id);

        $this->assertSame(1, $d['visita']['numero']);
        $this->assertFalse($d['puedeProgramar']);
        $this->assertFalse($d['puedeRegistrarActa']);
        $this->assertFalse($d['puedeActualizar']);
        $this->assertFalse($d['puedeAceptar']);
    }

    public function test_revisor_y_extrano_reciben_authorization_exception(): void
    {
        $nivel = $this->nivelEnRevision('aprobado');
        foreach ([$this->revisor(), Solicitante::factory()->create()->user] as $quien) {
            try {
                app(ConsultarVisita::class)->ejecutar($quien, $nivel->id);
                $this->fail('debió rechazar');
            } catch (AuthorizationException) {
                $this->assertTrue(true);
            }
        }
    }
}
