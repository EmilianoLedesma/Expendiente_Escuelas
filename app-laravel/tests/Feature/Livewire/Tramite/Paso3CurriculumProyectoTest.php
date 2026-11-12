<?php

namespace Tests\Feature\Livewire\Tramite;

use App\Application\ProyectoEducativo\DTO\DatosCurriculumProyecto;
use App\Application\ProyectoEducativo\GuardarCurriculumProyecto;
use App\Application\ProyectoEducativo\ReglasCurriculumProyecto;
use App\Livewire\Tramite\Paso3\ProyectoEducativoSeccion;
use App\Models\EscuelaNivel;
use App\Models\Solicitante;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Concerns\PreparaPaso3;
use Tests\TestCase;

class Paso3CurriculumProyectoTest extends TestCase
{
    use PreparaPaso3;
    use RefreshDatabase;

    private const SLUG = 'curriculum-ampliado';

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

    /** Llena los cinco ámbitos con lo mínimo válido (Sí). */
    private function llenar($componente)
    {
        $valores = [];
        foreach (array_keys(ReglasCurriculumProyecto::AMBITOS) as $ambito) {
            $valores["{$ambito}_trabaja"] = 'si';
            $valores["{$ambito}_justificacion"] = $this->palabras(100);
        }

        return $componente->set($valores);
    }

    public function test_el_primer_bloque_muestra_solo_el_primer_ambito_con_su_radio_y_placeholder(): void
    {
        $nivel = $this->nivel();

        $html = $this->get($this->ruta($nivel))->assertOk()
            ->assertSee('Práctica y Colaboración Ciudadana')
            ->assertSee('¿La trabajan?')
            ->assertSee('Justificación (100 a 250 palabras)')
            ->assertSee('Justifique la selección o no del ámbito de práctica y colaboración ciudadana...')
            ->assertSee('Siguiente')
            ->assertDontSee('Contenido por definir')
            ->assertDontSee('Justifique la selección o no del ámbito de educación para la salud...')
            ->getContent();

        $this->assertSame(1, substr_count($html, '<textarea'));
        $this->assertSame(2, substr_count($html, 'type="radio"'));
        $this->assertStringContainsString('wire:model="practica_ciudadana_trabaja"', $html);
        $this->assertStringNotContainsString('checked', $html);
    }

    public function test_cada_bloque_muestra_solo_su_ambito(): void
    {
        $c = $this->llenar($this->componente($this->nivel()));
        $esperado = [
            2 => ['Educación para la Salud', 'Justifique la selección o no del ámbito de educación para la salud...'],
            3 => ['Actividades Físicas y Deportivas', 'Justifique la selección o no del ámbito de actividades físicas y deportivas...'],
            4 => ['Educación Integral en Sexualidad y Género', 'Justifique la selección o no del ámbito de educación integral en sexualidad y género...'],
            5 => ['Actividades Artísticas y Culturales', 'Justifique la selección o no del ámbito de actividades artísticas y culturales...'],
        ];

        foreach ($esperado as $bloque => [$titulo, $placeholder]) {
            $c->call('siguienteCurriculum')->assertSet('bloque', $bloque)->assertSee($titulo)->assertSee($placeholder);
            $this->assertSame(1, substr_count($c->html(), '<textarea'));
            $this->assertSame(2, substr_count($c->html(), 'type="radio"'));
        }
    }

    public function test_solo_el_ultimo_bloque_ofrece_guardar(): void
    {
        $c = $this->llenar($this->componente($this->nivel()));
        $c->assertSeeHtml('wire:submit="siguienteCurriculum"');

        foreach ([2, 3, 4] as $bloque) {
            $c->call('siguienteCurriculum')->assertSet('bloque', $bloque)->assertSeeHtml('wire:submit="siguienteCurriculum"');
        }
        $c->call('siguienteCurriculum')->assertSet('bloque', 5)->assertSeeHtml('wire:submit="guardar"');
        $c->call('siguienteCurriculum')->assertSet('bloque', 5);
    }

    public function test_siguiente_exige_respuesta_y_justificacion_del_bloque_y_no_avanza(): void
    {
        $nivel = $this->nivel();

        $this->componente($nivel)->call('siguienteCurriculum')
            ->assertSet('bloque', 1)
            ->assertHasErrors(['practica_ciudadana_trabaja', 'practica_ciudadana_justificacion'])
            ->assertHasNoErrors(['salud_trabaja', 'salud_justificacion'])
            ->set(['practica_ciudadana_trabaja' => 'no', 'practica_ciudadana_justificacion' => $this->palabras(100)])->call('siguienteCurriculum')
            ->assertSet('bloque', 2)->assertHasNoErrors();

        $this->assertSame(0, DB::table('proyecto_educativo_curriculum')->count());
    }

    public function test_anterior_vuelve_sin_validar_ni_perder_lo_tecleado(): void
    {
        $this->llenar($this->componente($this->nivel()))->call('siguienteCurriculum')->set('salud_justificacion', 'corto')
            ->call('anteriorCurriculum')->assertSet('bloque', 1)->assertHasNoErrors()->assertSet('salud_justificacion', 'corto');
    }

    public function test_abre_con_lo_guardado_y_en_el_primer_bloque(): void
    {
        $nivel = $this->nivel();
        $valores = [];
        foreach (array_keys(ReglasCurriculumProyecto::AMBITOS) as $ambito) {
            $valores["{$ambito}_trabaja"] = 'no';
            $valores["{$ambito}_justificacion"] = $this->palabras(120);
        }
        app(GuardarCurriculumProyecto::class)->ejecutar($nivel->id, new DatosCurriculumProyecto(...$valores));

        $this->componente($nivel)->assertSet('bloque', 1)->assertSet('practica_ciudadana_trabaja', 'no')->assertSet('artistico_cultural_justificacion', $this->palabras(120));
    }

    public function test_guardar_completo_guarda_y_avisa_que_la_seccion_esta_completa(): void
    {
        $nivel = $this->nivel();

        $this->llenar($this->componente($nivel))->set('salud_trabaja', 'no')->call('guardar')
            ->assertHasNoErrors()
            ->assertDispatched('toast', tipo: 'success', titulo: 'Sección completa');

        $this->assertSame(1, DB::table('proyecto_educativo_curriculum')->where('escuela_nivel_id', $nivel->id)->count());
        $this->assertFalse((bool) DB::table('proyecto_educativo_curriculum')->value('salud_trabaja'));
    }

    public function test_guardar_incompleto_no_escribe_y_lleva_al_primer_bloque_con_error(): void
    {
        $nivel = $this->nivel();

        $this->llenar($this->componente($nivel))->set('actividad_fisica_justificacion', $this->palabras(251))->set('artistico_cultural_trabaja', '')
            ->call('guardar')
            ->assertHasErrors(['actividad_fisica_justificacion', 'artistico_cultural_trabaja'])
            ->assertSet('bloque', 3)
            ->assertDispatched('toast', tipo: 'error', titulo: 'No se guardó');

        $this->assertSame(0, DB::table('proyecto_educativo_curriculum')->count());
    }

    public function test_los_campos_llevan_las_restricciones_del_lado_del_cliente(): void
    {
        $html = $this->componente($this->nivel())->html();

        $this->assertStringContainsString('$wire.entangle(\'practica_ciudadana_justificacion\')', $html);
        $this->assertStringContainsString('x-on:input="recortar()"', $html);
        $this->assertStringContainsString('max: 250', $html);
        $this->assertStringContainsString('min: 100', $html);
    }

    public function test_tramite_enviado_no_guarda(): void
    {
        $nivel = $this->nivel();
        $nivel->update(['estado_id' => DB::table('estados_expediente')->where('clave', 'en_revision')->value('id')]);

        $this->llenar($this->componente($nivel))->call('guardar')
            ->assertDispatched('toast', tipo: 'error', titulo: 'No se guardó', descripcion: 'El trámite ya se envió a revisión y no puede modificarse.');

        $this->assertSame(0, DB::table('proyecto_educativo_curriculum')->count());
    }

    public function test_otro_solicitante_recibe_403_y_primaria_404(): void
    {
        $nivel = $this->nivel();
        $this->actingAs(Solicitante::factory()->create()->user)->get($this->ruta($nivel))->assertForbidden();

        $primaria = $this->nivelListoPara('primaria', 'matricula');
        $this->actingAs($primaria->escuela->solicitante->user)->get($this->ruta($primaria))->assertNotFound();
    }

    public function test_indice_marca_completa_la_seccion_siete_cuando_lo_esta(): void
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
