<?php

namespace Tests\Feature\Livewire\Sedeq;

use App\Application\Revision\DecidirPaso;
use App\Application\Revision\PasosRevision;
use App\Application\Revision\ResultadosCambiados;
use App\Application\Validaciones\DTO\FilaValidacion;
use App\Application\Validaciones\DTO\ValidacionFinal;
use App\Application\Validaciones\EjecutarValidacionFinal;
use App\Livewire\Sedeq\RevisionNivel;
use App\Models\AsignacionRevision;
use App\Models\EscuelaNivel;
use App\Models\EvaluacionValidacion;
use App\Models\User;
use Database\Seeders\TiposDocumentosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\Concerns\ConTramitesEnRevision;
use Tests\TestCase;

class RevisionNivelTest extends TestCase
{
    use ConTramitesEnRevision;
    use RefreshDatabase;

    private EscuelaNivel $nivel;

    private User $revisor;

    protected function setUp(): void
    {
        parent::setUp();
        (new TiposDocumentosSeeder)->run();
        $this->nivel = $this->nivelEnRevision();
        $this->revisor = $this->revisor();
        AsignacionRevision::create(['escuela_nivel_id' => $this->nivel->id, 'revisor_id' => $this->revisor->id, 'asignado_por' => $this->supervisor()->id, 'asignado_at' => now()]);
    }

    private function pagina()
    {
        return Livewire::actingAs($this->revisor)->test(RevisionNivel::class, ['escuelaNivel' => $this->nivel->id]);
    }

    public function test_revisor_asignado_ve_la_lista_de_pasos_y_el_avance(): void
    {
        $this->actingAs($this->revisor)->get(route('sedeq.revision', ['escuelaNivel' => $this->nivel->id]))
            ->assertOk()
            ->assertSee('Avance: 0 de 10')
            ->assertSee('Datos del plantel')
            ->assertSee('Responsable legal');

        $this->pagina()->assertSet('pasoAbierto', 'plantel');
    }

    public function test_aceptar_registra_la_decision_y_avanza_el_conteo(): void
    {
        $this->pagina()->call('aceptar')->assertSee('Avance: 1 de 10')->assertDispatched('toast');

        $this->assertSame('aceptado', DB::table('decisiones_revision')->where('escuela_nivel_id', $this->nivel->id)->where('paso_clave', 'plantel')->value('estado'));
    }

    public function test_rechazar_exige_motivo_y_muestra_el_error(): void
    {
        $this->pagina()->call('rechazar')->assertHasErrors(['motivo'])->assertSee('Escribe el motivo del rechazo.');

        $this->assertSame(0, DB::table('decisiones_revision')->count());
    }

    public function test_rechazar_con_motivo_lo_registra_y_limpia_el_campo(): void
    {
        $this->pagina()->set('motivo', 'Falta el teléfono')->call('rechazar')->assertSet('motivo', '')->assertSee('Avance: 1 de 10');

        $this->assertSame('rechazado', DB::table('decisiones_revision')->where('paso_clave', 'plantel')->value('estado'));
    }

    public function test_revisor_ajeno_recibe_403(): void
    {
        $this->actingAs($this->revisor())->get(route('sedeq.revision', ['escuelaNivel' => $this->nivel->id]))->assertForbidden();
    }

    public function test_nivel_aprobado_no_muestra_los_botones(): void
    {
        $this->nivel->update(['estado_id' => DB::table('estados_expediente')->where('clave', 'aprobado')->value('id')]);

        $this->pagina()->assertDontSee('Aceptar paso')->assertDontSee('Rechazar paso');
    }

    public function test_paso_abierto_es_solo_un_paso_valido_del_nivel(): void
    {
        $this->pagina()
            ->call('abrir', 'responsable')->assertSet('pasoAbierto', 'responsable')
            ->call('abrir', 'mobiliario')->assertSet('pasoAbierto', 'responsable')
            ->call('abrir', 'no_existe')->assertSet('pasoAbierto', 'responsable');
    }

    public function test_el_id_del_nivel_no_se_puede_alterar_desde_el_cliente(): void
    {
        $this->expectException(CannotUpdateLockedPropertyException::class);

        $this->pagina()->set('escuelaNivelId', 999);
    }

    public function test_el_paso_abierto_no_se_puede_alterar_desde_el_cliente(): void
    {
        $this->expectException(CannotUpdateLockedPropertyException::class);

        $this->pagina()->set('pasoAbierto', 'mobiliario');
    }

    public function test_las_pistas_se_calculan_una_sola_vez_por_pagina(): void
    {
        $fila = new FilaValidacion('rfc_coincide', 'RFC', 'no_cumple', 'El RFC no coincide', [], []);
        $this->mock(EjecutarValidacionFinal::class)->shouldReceive('evaluar')->once()
            ->andReturn(new ValidacionFinal(null, now(), false, [$fila]));

        $this->pagina()->call('abrir', 'documentos')->assertSee('El RFC no coincide')
            ->call('abrir', 'plantel')->call('abrir', 'documentos')->set('motivo', 'x')->assertSee('El RFC no coincide');
    }

    public function test_las_pistas_persistidas_son_arreglos_y_no_objetos(): void
    {
        $fila = new FilaValidacion('rfc_coincide', 'RFC', 'no_cumple', 'El RFC no coincide', [], []);
        $this->mock(EjecutarValidacionFinal::class)->shouldReceive('evaluar')
            ->andReturn(new ValidacionFinal(null, now(), false, [$fila]));

        $pistas = $this->pagina()->call('abrir', 'documentos')->instance()->pistas;

        $this->assertIsArray($pistas['documentos'][0]);
        $this->assertSame('rfc_coincide', $pistas['documentos'][0]['clave']);
    }

    public function test_si_el_motor_falla_la_pagina_carga_sin_pistas(): void
    {
        $this->mock(EjecutarValidacionFinal::class)->shouldReceive('evaluar')->andThrow(new \RuntimeException('motor roto'));

        $this->pagina()->call('abrir', 'documentos')->assertOk()->assertDontSee('Pistas del sistema')->assertSee('Documentos del plantel');
    }

    public function test_el_aviso_de_resultado_cambiado_sale_en_el_paso_aceptado_y_no_en_el_pendiente(): void
    {
        $this->mock(ResultadosCambiados::class)->shouldReceive('ejecutar')
            ->andReturn(['documentos' => ['RFC: antes cumplía y ahora «No cumple».'], 'plantel' => ['Regla de plantel: antes cumplía y ahora «No cumple».']]);
        // Solo «documentos» está aceptado; «plantel» sigue pendiente aunque el caso de uso lo devolviera.
        DB::table('decisiones_revision')->insert(['escuela_nivel_id' => $this->nivel->id, 'paso_clave' => 'documentos', 'estado' => 'aceptado', 'decidido_por' => $this->revisor->id, 'decidido_at' => now()]);

        $this->pagina()
            ->call('abrir', 'documentos')
            ->assertSee('Resultado cambiado desde que se aceptó')
            ->assertSee('RFC: antes cumplía y ahora «No cumple».')
            ->assertSeeHtml('data-resultado-cambiado-paso="documentos"')
            ->assertDontSeeHtml('data-resultado-cambiado-paso="plantel"')
            ->call('abrir', 'plantel')
            ->assertDontSee('Resultado cambiado desde que se aceptó')
            ->assertDontSee('Regla de plantel');
    }

    public function test_sin_resultados_cambiados_no_hay_aviso(): void
    {
        $this->mock(ResultadosCambiados::class)->shouldReceive('ejecutar')->andReturn([]);

        $this->pagina()->call('abrir', 'documentos')->assertDontSee('Resultado cambiado');
    }

    public function test_los_resultados_cambiados_persistidos_son_arreglos_de_textos(): void
    {
        $this->mock(ResultadosCambiados::class)->shouldReceive('ejecutar')->andReturn(['documentos' => ['RFC cambió']]);

        $this->assertSame(['documentos' => ['RFC cambió']], $this->pagina()->instance()->resultadosCambiados);
    }

    public function test_el_aviso_se_recalcula_tras_decidir(): void
    {
        $this->mock(ResultadosCambiados::class)->shouldReceive('ejecutar')->twice()->andReturn([], ['plantel' => ['Cambió']]);

        $this->pagina()->call('abrir', 'plantel')->assertDontSee('Resultado cambiado desde que se aceptó')
            ->call('aceptar')->assertSee('Resultado cambiado desde que se aceptó');
    }

    public function test_un_fallo_del_aviso_no_rompe_la_pagina(): void
    {
        $this->mock(EjecutarValidacionFinal::class)->shouldReceive('evaluar')->andThrow(new \RuntimeException('motor roto'));
        DB::table('decisiones_revision')->insert(['escuela_nivel_id' => $this->nivel->id, 'paso_clave' => 'documentos', 'estado' => 'aceptado', 'decidido_por' => $this->revisor->id, 'decidido_at' => now()]);
        // Con una evaluación guardada ResultadosCambiados llega al motor (sin ella vuelve antes y no prueba nada).
        EvaluacionValidacion::create(['escuela_id' => $this->nivel->escuela_id, 'archivo_path' => 'validaciones/x.pdf', 'lista_para_envio' => true,
            'resultados' => ['documental' => [], 'capacidad' => [], 'niveles' => [], 'regla_envio' => EjecutarValidacionFinal::REGLA_ENVIO]]);
        Exceptions::fake();

        $this->pagina()->call('abrir', 'documentos')->assertOk()->assertDontSee('Resultado cambiado desde que se aceptó');
        Exceptions::assertReported(fn (\RuntimeException $e) => $e->getMessage() === 'motor roto');
    }

    public function test_la_pista_aparece_en_el_paso_y_no_bloquea_la_decision(): void
    {
        $fila = new FilaValidacion('rfc_coincide', 'RFC', 'no_cumple', 'El RFC no coincide', [], ['RFC: ABC · no coincide']);
        $this->mock(EjecutarValidacionFinal::class)->shouldReceive('evaluar')
            ->andReturn(new ValidacionFinal(null, now(), false, [$fila]));

        $this->pagina()
            ->call('abrir', 'documentos')
            ->assertSee('Pistas del sistema')
            ->assertSee('No cumple')
            ->assertSee('El RFC no coincide')
            ->assertSee('RFC: ABC · no coincide')
            ->call('abrir', 'plantel')
            ->assertDontSee('Pistas del sistema')
            ->call('abrir', 'documentos')
            ->call('aceptar')
            ->assertSee('Avance: 1 de 10');

        $this->assertSame('aceptado', DB::table('decisiones_revision')->where('paso_clave', 'documentos')->value('estado'));
    }

    /** Deja `responsable` rechazado y reenviado (corregido) y vuelve el nivel a en_revision. */
    private function conPasoCorregido(string $motivo = 'Falta la identificación'): void
    {
        app(DecidirPaso::class)->ejecutar($this->revisor->id, $this->nivel->id, 'responsable', 'rechazado', $motivo);
        DB::table('decisiones_revision')->where('escuela_nivel_id', $this->nivel->id)->where('estado', 'rechazado')->delete();
        $this->nivel->update(['estado_id' => DB::table('estados_expediente')->where('clave', 'en_revision')->value('id')]);
    }

    public function test_la_vista_lista_primero_los_corregidos(): void
    {
        $this->conPasoCorregido();

        $this->pagina()
            ->assertSeeInOrder(['Avance: 0 de 10', 'Responsable legal', 'Corregido', 'Datos del plantel'])
            ->assertSet('pasoAbierto', 'responsable')
            ->assertSee('Motivo anterior')
            ->assertSee('Falta la identificación');
    }

    public function test_el_revisor_decide_solo_los_corregidos_y_el_nivel_sube_a_aprobado(): void
    {
        foreach (['plantel', 'documentos'] as $p) {
            app(DecidirPaso::class)->ejecutar($this->revisor->id, $this->nivel->id, $p, 'aceptado', null);
        }
        $this->conPasoCorregido();
        foreach (PasosRevision::paraNivel('primaria') as $p) {
            if (! in_array($p, ['plantel', 'documentos', 'responsable'], true)) {
                app(DecidirPaso::class)->ejecutar($this->revisor->id, $this->nivel->id, $p, 'aceptado', null);
            }
        }

        $this->pagina()->call('aceptar')->assertSee('Avance: 10 de 10');

        $this->assertSame('aprobado', DB::table('estados_expediente')->where('id', $this->nivel->fresh()->estado_id)->value('clave'));
    }

    public function test_con_observaciones_no_muestra_botones_y_explica_por_que(): void
    {
        $this->nivel->update(['estado_id' => DB::table('estados_expediente')->where('clave', 'con_observaciones')->value('id')]);

        $this->pagina()
            ->assertDontSee('Aceptar paso')->assertDontSee('Rechazar paso')
            ->assertSee('Este trámite está con el solicitante para corregir; podrás decidir cuando lo reenvíe.')
            ->assertSee('Datos del plantel');
    }

    public function test_en_revision_muestra_botones_y_no_la_nota(): void
    {
        $this->pagina()->assertSee('Aceptar paso')->assertDontSee('está con el solicitante');
    }
}
