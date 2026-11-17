<?php

namespace Tests\Feature\Livewire\Tramite;

use App\Application\ProyectoEducativo\DTO\DatosFormacionLaboralProyecto;
use App\Application\ProyectoEducativo\GuardarFormacionLaboralProyecto;
use App\Livewire\Tramite\Paso3\ProyectoEducativoSeccion;
use App\Models\EscuelaNivel;
use App\Models\Solicitante;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Concerns\PreparaPaso3;
use Tests\TestCase;

class Paso3FormacionLaboralProyectoTest extends TestCase
{
    use PreparaPaso3;
    use RefreshDatabase;

    private const SLUG = 'plan-de-estudios';

    private function palabras(int $n): string
    {
        return trim(str_repeat('palabra ', $n));
    }

    private function nivel(): EscuelaNivel
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');
        $this->actingAs($nivel->escuela->solicitante->user);

        return $nivel;
    }

    private function componente(EscuelaNivel $nivel)
    {
        return Livewire::test(ProyectoEducativoSeccion::class, ['escuelaNivel' => $nivel, 'seccion' => self::SLUG]);
    }

    private function ruta(EscuelaNivel $nivel): string
    {
        return route('tramite.paso3-proyecto-educativo-seccion', ['escuelaNivel' => $nivel->id, 'seccion' => self::SLUG]);
    }

    public function test_la_pagina_es_un_solo_bloque_con_los_tres_campos_del_dueno(): void
    {
        $this->get($this->ruta($this->nivel()))->assertOk()
            ->assertSee('Componente y Enfoque de Formación Laboral')
            ->assertSee('Formación 1 (100 a 250 palabras)')
            ->assertSee('Formación 2 (100 a 250 palabras)')
            ->assertSee('Otras formaciones (100 a 250 palabras)')
            ->assertSee('Describa la primera formación laboral ofrecida...')
            ->assertSee('Describa la segunda formación laboral ofrecida...')
            ->assertSee('Describa cualquier otra formación incluida en el plan de estudios...')
            ->assertSee('Guardar')
            ->assertDontSee('Contenido por definir')
            ->assertDontSee('Paso 1 de');
    }

    public function test_guardar_completo_avisa_y_escribe(): void
    {
        $nivel = $this->nivel();

        $this->componente($nivel)
            ->set(['formacion_laboral_1' => $this->palabras(100), 'formacion_laboral_2' => $this->palabras(120), 'otras_formaciones' => $this->palabras(110)])
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertDispatched('toast', tipo: 'success', titulo: 'Sección completa');

        $fila = DB::table('proyecto_educativo_formacion_laboral')->where('escuela_nivel_id', $nivel->id)->first();
        $this->assertSame($this->palabras(120), $fila->formacion_laboral_2);
        $this->assertSame($this->palabras(110), $fila->otras_formaciones);
    }

    public function test_otras_formaciones_se_puede_dejar_vacia(): void
    {
        $nivel = $this->nivel();

        $this->componente($nivel)
            ->set(['formacion_laboral_1' => $this->palabras(100), 'formacion_laboral_2' => $this->palabras(100)])
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertDispatched('toast', tipo: 'success', titulo: 'Sección completa');

        $this->assertNull(DB::table('proyecto_educativo_formacion_laboral')->where('escuela_nivel_id', $nivel->id)->value('otras_formaciones'));
    }

    public function test_guardar_con_errores_los_muestra_todos_y_no_escribe(): void
    {
        $this->componente($this->nivel())
            ->set(['formacion_laboral_1' => 'corto', 'formacion_laboral_2' => '', 'otras_formaciones' => $this->palabras(5)])
            ->call('guardar')
            ->assertHasErrors(['formacion_laboral_1', 'formacion_laboral_2', 'otras_formaciones'])
            ->assertDispatched('toast', tipo: 'error', titulo: 'No se guardó');

        $this->assertSame(0, DB::table('proyecto_educativo_formacion_laboral')->count());
    }

    public function test_abre_con_lo_guardado(): void
    {
        $nivel = $this->nivel();
        app(GuardarFormacionLaboralProyecto::class)->ejecutar($nivel->id, new DatosFormacionLaboralProyecto($this->palabras(101), $this->palabras(102), null));

        $this->componente($nivel)
            ->assertSet('formacion_laboral_1', $this->palabras(101))
            ->assertSet('formacion_laboral_2', $this->palabras(102))
            ->assertSet('otras_formaciones', '');
    }

    public function test_tramite_enviado_no_guarda(): void
    {
        $nivel = $this->nivel();
        $nivel->update(['estado_id' => DB::table('estados_expediente')->where('clave', 'en_revision')->value('id')]);

        $this->componente($nivel)
            ->set(['formacion_laboral_1' => $this->palabras(100), 'formacion_laboral_2' => $this->palabras(100)])
            ->call('guardar')
            ->assertDispatched('toast', tipo: 'error', titulo: 'No se guardó', descripcion: 'El trámite ya se envió a revisión y no puede modificarse.');

        $this->assertSame(0, DB::table('proyecto_educativo_formacion_laboral')->count());
    }

    public function test_el_indice_marca_completa_la_seccion_catorce(): void
    {
        $nivel = $this->nivel();
        $indice = route('tramite.paso3-proyecto-educativo', ['escuelaNivel' => $nivel->id]);

        $this->assertSame(20, substr_count($this->get($indice)->assertOk()->getContent(), 'Pendiente'));

        app(GuardarFormacionLaboralProyecto::class)->ejecutar($nivel->id, new DatosFormacionLaboralProyecto($this->palabras(100), $this->palabras(100), null));
        $html = $this->get($indice)->getContent();
        $this->assertSame(19, substr_count($html, 'Pendiente'));
        $this->assertSame(1, substr_count($html, 'Completa'));
    }

    public function test_otro_solicitante_recibe_403_y_primaria_404(): void
    {
        $nivel = $this->nivel();
        $this->actingAs(Solicitante::factory()->create()->user)->get($this->ruta($nivel))->assertForbidden();

        $primaria = $this->nivelListoPara('primaria', 'matricula');
        $this->actingAs($primaria->escuela->solicitante->user)->get(route('tramite.paso3-proyecto-educativo-seccion', ['escuelaNivel' => $primaria->id, 'seccion' => self::SLUG]))->assertNotFound();
    }
}
