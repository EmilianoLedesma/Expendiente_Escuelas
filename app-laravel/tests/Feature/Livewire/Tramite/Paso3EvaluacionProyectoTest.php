<?php

namespace Tests\Feature\Livewire\Tramite;

use App\Application\ProyectoEducativo\DTO\DatosEvaluacionProyecto;
use App\Application\ProyectoEducativo\GuardarEvaluacionProyecto;
use App\Livewire\Tramite\Paso3\ProyectoEducativoSeccion;
use App\Models\EscuelaNivel;
use App\Models\Solicitante;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Concerns\PreparaPaso3;
use Tests\TestCase;

class Paso3EvaluacionProyectoTest extends TestCase
{
    use PreparaPaso3;
    use RefreshDatabase;

    private const SLUG = 'modelo-de-evaluacion';

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

    private function ruta(EscuelaNivel $nivel, string $slug = self::SLUG): string
    {
        return route('tramite.paso3-proyecto-educativo-seccion', ['escuelaNivel' => $nivel->id, 'seccion' => $slug]);
    }

    /** Llena todo con lo mínimo válido. */
    private function llenar($componente)
    {
        return $componente->set([
            'evaluacion_diagnostica' => $this->palabras(100), 'evaluacion_formativa' => $this->palabras(100), 'evaluacion_sumativa' => $this->palabras(100),
            'heteroevaluacion' => $this->palabras(100), 'coevaluacion' => $this->palabras(100), 'autoevaluacion' => $this->palabras(100),
            'elementos_porcentajes' => $this->palabras(200),
        ]);
    }

    public function test_el_primer_bloque_muestra_solo_las_funciones_con_etiquetas_y_placeholders_del_dueno(): void
    {
        $nivel = $this->nivel();

        $html = $this->get($this->ruta($nivel))->assertOk()
            ->assertSee('Funciones del Proceso Evaluativo (100 a 300 palabras por función)')
            ->assertSee('Evaluación Diagnóstica')
            ->assertSee('Describa el propósito, instrumentos y momentos de la evaluación diagnóstica...')
            ->assertSee('Evaluación Formativa')
            ->assertSee('Describa cómo se implementará la evaluación formativa durante el proceso de enseñanza-aprendizaje...')
            ->assertSee('Evaluación Sumativa')
            ->assertSee('Describa los criterios y mecanismos de la evaluación sumativa...')
            ->assertSee('Siguiente')
            ->assertDontSee('Contenido por definir')
            ->assertDontSee('Heteroevaluación')
            ->assertDontSee('Elementos y porcentajes')
            ->getContent();

        $this->assertSame(3, substr_count($html, '<textarea'));
    }

    public function test_cada_bloque_muestra_solo_sus_campos(): void
    {
        $c = $this->llenar($this->componente($this->nivel()))->call('siguienteEvaluacion');

        $c->assertSet('bloque', 2)
            ->assertSee('Estrategias de Evaluación Auténtica (100 a 300 palabras por cada tipo)')
            ->assertSee('Heteroevaluación')
            ->assertSee('Describa los procesos de evaluación del docente hacia el alumno...')
            ->assertSee('Coevaluación')
            ->assertSee('Describa los procesos de evaluación entre pares...')
            ->assertSee('Autoevaluación')
            ->assertSee('Describa los procesos de autoevaluación del estudiante...')
            ->assertDontSee('Evaluación Diagnóstica');
        $this->assertSame(3, substr_count($c->html(), '<textarea'));

        $c->call('siguienteEvaluacion')->assertSet('bloque', 3)
            ->assertSee('Elementos y Porcentajes para la Calificación (200 a 500 palabras)')
            ->assertSee('Elementos y porcentajes')
            ->assertSee('Detalle los componentes que integran la calificación y sus porcentajes correspondientes...')
            ->assertDontSee('Heteroevaluación');
        $this->assertSame(1, substr_count($c->html(), '<textarea'));
    }

    public function test_solo_el_ultimo_bloque_ofrece_guardar(): void
    {
        $c = $this->llenar($this->componente($this->nivel()));
        $c->assertSeeHtml('wire:submit="siguienteEvaluacion"')->call('siguienteEvaluacion')->assertSeeHtml('wire:submit="siguienteEvaluacion"');

        $c->call('siguienteEvaluacion')->assertSet('bloque', 3)->assertSeeHtml('wire:submit="guardar"');
        $c->call('siguienteEvaluacion')->assertSet('bloque', 3);
    }

    public function test_siguiente_valida_solo_los_campos_del_bloque_actual_y_no_avanza_si_fallan(): void
    {
        $nivel = $this->nivel();

        $this->componente($nivel)->set(['evaluacion_diagnostica' => $this->palabras(100), 'evaluacion_formativa' => $this->palabras(10)])->call('siguienteEvaluacion')
            ->assertSet('bloque', 1)
            ->assertHasErrors(['evaluacion_formativa', 'evaluacion_sumativa'])
            ->assertHasNoErrors(['evaluacion_diagnostica', 'heteroevaluacion', 'elementos_porcentajes'])
            ->set(['evaluacion_formativa' => $this->palabras(100), 'evaluacion_sumativa' => $this->palabras(100)])->call('siguienteEvaluacion')
            ->assertSet('bloque', 2)->assertHasNoErrors();

        $this->assertSame(0, DB::table('proyecto_educativo_evaluacion')->count());
    }

    public function test_anterior_vuelve_sin_validar_ni_perder_lo_tecleado(): void
    {
        $this->llenar($this->componente($this->nivel()))->call('siguienteEvaluacion')->set('heteroevaluacion', 'corto')
            ->call('anteriorEvaluacion')->assertSet('bloque', 1)->assertHasNoErrors()->assertSet('heteroevaluacion', 'corto');
    }

    public function test_abre_con_lo_guardado_y_en_el_primer_bloque(): void
    {
        $nivel = $this->nivel();
        app(GuardarEvaluacionProyecto::class)->ejecutar($nivel->id, new DatosEvaluacionProyecto(
            ...array_fill_keys(DatosEvaluacionProyecto::campos(), $this->palabras(250)),
        ));

        $this->componente($nivel)->assertSet('bloque', 1)->assertSet('evaluacion_diagnostica', $this->palabras(250))->assertSet('elementos_porcentajes', $this->palabras(250));
    }

    public function test_guardar_completo_guarda_y_avisa_que_la_seccion_esta_completa(): void
    {
        $nivel = $this->nivel();

        $this->llenar($this->componente($nivel))->call('guardar')
            ->assertHasNoErrors()
            ->assertDispatched('toast', tipo: 'success', titulo: 'Sección completa');

        $this->assertSame(1, DB::table('proyecto_educativo_evaluacion')->where('escuela_nivel_id', $nivel->id)->count());
    }

    public function test_guardar_incompleto_no_escribe_y_lleva_al_primer_bloque_con_error(): void
    {
        $nivel = $this->nivel();

        $this->llenar($this->componente($nivel))->set('coevaluacion', $this->palabras(301))->set('elementos_porcentajes', '')
            ->call('guardar')
            ->assertHasErrors(['coevaluacion', 'elementos_porcentajes'])
            ->assertSet('bloque', 2)
            ->assertDispatched('toast', tipo: 'error', titulo: 'No se guardó');

        $this->assertSame(0, DB::table('proyecto_educativo_evaluacion')->count());
    }

    public function test_tramite_enviado_no_guarda(): void
    {
        $nivel = $this->nivel();
        $nivel->update(['estado_id' => DB::table('estados_expediente')->where('clave', 'en_revision')->value('id')]);

        $this->llenar($this->componente($nivel))->call('guardar')
            ->assertDispatched('toast', tipo: 'error', titulo: 'No se guardó', descripcion: 'El trámite ya se envió a revisión y no puede modificarse.');

        $this->assertSame(0, DB::table('proyecto_educativo_evaluacion')->count());
    }

    public function test_otro_solicitante_recibe_403_y_primaria_404(): void
    {
        $nivel = $this->nivel();
        $this->actingAs(Solicitante::factory()->create()->user)->get($this->ruta($nivel))->assertForbidden();

        $primaria = $this->nivelListoPara('primaria', 'matricula');
        $this->actingAs($primaria->escuela->solicitante->user)->get($this->ruta($primaria))->assertNotFound();
    }

    public function test_indice_marca_completa_la_seccion_cinco_cuando_lo_esta(): void
    {
        $nivel = $this->nivel();
        $indice = route('tramite.paso3-proyecto-educativo', ['escuelaNivel' => $nivel->id]);

        $this->assertSame(20, substr_count($this->get($indice)->assertOk()->getContent(), 'Pendiente'));

        $this->llenar($this->componente($nivel))->call('guardar');
        $html = $this->get($indice)->getContent();
        $this->assertSame(19, substr_count($html, 'Pendiente'));
        $this->assertSame(1, substr_count($html, 'Completa'));
    }
}
