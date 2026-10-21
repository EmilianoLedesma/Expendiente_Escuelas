<?php

namespace Tests\Feature\Livewire\Sedeq;

use App\Application\Validaciones\DTO\FilaValidacion;
use App\Application\Validaciones\DTO\ValidacionFinal;
use App\Application\Validaciones\EjecutarValidacionFinal;
use App\Livewire\Sedeq\RevisionNivel;
use App\Models\AsignacionRevision;
use App\Models\EscuelaNivel;
use App\Models\User;
use Database\Seeders\TiposDocumentosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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
}
