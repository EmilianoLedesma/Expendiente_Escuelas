<?php

namespace Tests\Feature\Livewire\Tramite;

use App\Application\ProyectoEducativo\DTO\DatosFormacionContinuaProyecto;
use App\Application\ProyectoEducativo\DTO\DatosFormacionInicialProyecto;
use App\Application\ProyectoEducativo\GuardarFormacionContinuaProyecto;
use App\Application\ProyectoEducativo\GuardarFormacionInicialProyecto;
use App\Application\ProyectoEducativo\ReglasFormacionProyecto;
use App\Livewire\Tramite\Paso3\ProyectoEducativoSeccion;
use App\Models\EscuelaNivel;
use App\Models\Solicitante;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Concerns\GeneraPdfs;
use Tests\Concerns\PreparaPaso3;
use Tests\TestCase;

class Paso3FormacionInicialProyectoTest extends TestCase
{
    use GeneraPdfs;
    use PreparaPaso3;
    use RefreshDatabase;

    private const SLUG = 'formacion-docente';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('documentos');
    }

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

    /** @param  array<string, mixed>  $cambios @return array<string, mixed> */
    private function accion(array $cambios = []): array
    {
        $base = [
            'nombre' => 'Taller de didáctica', 'formato' => 'taller', 'formato_otro' => '', 'duracion_horas' => '40', 'dias' => ['lunes', 'viernes'],
            'hora_inicio' => '09:00', 'hora_fin' => '13:00', 'fecha_inicio' => '2026-08-03', 'fecha_fin' => '2026-12-18', 'otros' => '',
        ];
        foreach (ReglasFormacionProyecto::TEXTOS as $campo => [$min]) {
            $base[$campo] = $this->palabras($min);
        }

        return array_merge($base, $cambios);
    }

    private function llenar($componente, int $acciones = 1)
    {
        return $componente->set('politica_inicial', $this->palabras(100))->set('acciones', array_fill(0, $acciones, $this->accion()));
    }

    public function test_la_primera_pantalla_muestra_solo_la_politica(): void
    {
        $html = $this->get($this->ruta($this->nivel()))->assertOk()
            ->assertSee('Formación Docente Inicial')
            ->assertSee('Política Global de Formación Inicial (100 a 250 palabras)')
            ->assertSee('Describa la política institucional para la formación inicial de los docentes...')
            ->assertSee('Siguiente')
            ->assertDontSee('Contenido por definir')
            ->assertDontSee('Nombre de la acción')
            ->getContent();

        $this->assertSame(1, substr_count($html, '<textarea'));
    }

    public function test_siguiente_exige_la_politica_y_no_avanza_sin_ella(): void
    {
        $this->componente($this->nivel())->call('siguienteFormacion')
            ->assertSet('pantalla', 1)
            ->assertHasErrors(['politica_inicial'])
            ->assertDispatched('toast', tipo: 'error')
            ->set('politica_inicial', $this->palabras(100))->call('siguienteFormacion')
            ->assertSet('pantalla', 2)->assertHasNoErrors();
    }

    public function test_la_pantalla_de_accion_usa_entradas_nativas_para_horas_fechas_y_duracion(): void
    {
        $c = $this->componente($this->nivel())->set('politica_inicial', $this->palabras(100))->call('siguienteFormacion');
        $html = $c->html();

        $c->assertSee('Acción 1 de 1')
            ->assertSee('Nombre de la acción')->assertSee('Describa la acción formativa')
            ->assertSee('Propósito')->assertSee('Propósito de la acción')
            ->assertSee('Participantes')->assertSee('¿A quién va dirigida?')
            ->assertSee('Formato')->assertSee('Taller')->assertSee('Curso')->assertSee('Seminario')->assertSee('Diplomado')->assertSee('Otro')
            ->assertSee('Duración')->assertSee('horas')
            ->assertSee('Horario')->assertSee('Lunes')->assertSee('Domingo')
            ->assertSee('Periodo')
            ->assertSee('Temario')->assertSee('Describa los temas principales')
            ->assertSee('Evaluación')->assertSee('¿Cómo se evaluará a los participantes?')
            ->assertSee('Recursos')->assertSee('Recursos necesarios')
            ->assertSee('Perfil de ponente(s) o encargado(s)')->assertSee('Perfil profesional requerido')
            ->assertSee('Reconocimiento')->assertSee('Tipo de reconocimiento que se otorgará')
            ->assertSee('Otros')->assertSee('Información adicional')
            ->assertDontSee('Política Global de Formación Inicial');
        $this->assertSame(2, substr_count($html, 'type="time"'));
        $this->assertSame(2, substr_count($html, 'type="date"'));
        $this->assertSame(1, substr_count($html, 'type="number"'));
        $this->assertSame(7, substr_count($html, 'type="checkbox"'));
        $this->assertSame(5, substr_count($html, 'type="radio"'));
        $this->assertSame(8, substr_count($html, '<textarea'));
        $this->assertSame(2, substr_count($html, 'wire:model.blur="acciones.0.hora_'));
        $this->assertStringContainsString('wire:model.blur="acciones.0.fecha_inicio"', $html);
        $this->assertStringContainsString('wire:model.blur="acciones.0.duracion_horas"', $html);
    }

    public function test_otro_muestra_el_campo_para_especificar_solo_cuando_se_elige(): void
    {
        $c = $this->componente($this->nivel())->set('politica_inicial', $this->palabras(100))->call('siguienteFormacion')->assertDontSee('Especifique el formato');

        $c->set('acciones.0.formato', 'otro')->assertSee('Especifique el formato');
        $c->set('acciones.0.formato', 'curso')->assertDontSee('Especifique el formato');
    }

    public function test_siguiente_valida_solo_la_accion_actual(): void
    {
        $c = $this->componente($this->nivel())->set('politica_inicial', $this->palabras(100))->call('siguienteFormacion');

        $c->call('siguienteFormacion')
            ->assertSet('pantalla', 2)
            ->assertHasErrors(['acciones.0.nombre', 'acciones.0.dias', 'acciones.0.hora_inicio', 'acciones.0.fecha_inicio', 'acciones.0.duracion_horas'])
            ->assertHasNoErrors(['politica_inicial', 'acciones.0.otros']);
    }

    public function test_agregar_y_quitar_acciones(): void
    {
        $c = $this->llenar($this->componente($this->nivel()))->call('siguienteFormacion');

        $c->assertSet('pantalla', 2)->assertCount('acciones', 1)->assertDontSee('Quitar esta acción');
        $c->call('agregarAccion')->assertCount('acciones', 2)->assertSet('pantalla', 3)->assertSee('Acción 2 de 2')->assertSee('Quitar esta acción');
        $c->call('quitarAccion')->assertCount('acciones', 1)->assertSet('pantalla', 2)->assertSee('Acción 1 de 1');
        $c->call('quitarAccion')->assertCount('acciones', 1);
    }

    public function test_el_maximo_de_acciones_es_veinte(): void
    {
        $c = $this->llenar($this->componente($this->nivel()), 20)->call('siguienteFormacion');

        $c->assertSet('pantalla', 2)->call('agregarAccion')->assertCount('acciones', 20)->assertDontSee('Agregar acción');
    }

    public function test_anterior_vuelve_sin_validar_ni_perder_lo_tecleado(): void
    {
        $this->componente($this->nivel())->set('politica_inicial', $this->palabras(100))->call('siguienteFormacion')->set('acciones.0.nombre', 'Borrador')
            ->call('anteriorFormacion')->assertSet('pantalla', 1)->assertHasNoErrors()->assertSet('acciones.0.nombre', 'Borrador');
    }

    public function test_solo_la_ultima_pantalla_ofrece_guardar(): void
    {
        $c = $this->llenar($this->componente($this->nivel()), 2);
        $c->assertSeeHtml('wire:submit="siguienteFormacion"')->call('siguienteFormacion')->assertSeeHtml('wire:submit="siguienteFormacion"');
        $c->call('siguienteFormacion')->assertSet('pantalla', 3)->assertSeeHtml('wire:submit="guardar"');
    }

    public function test_la_ultima_pantalla_del_bloque_inicial_ofrece_guardar_y_continuar(): void
    {
        $c = $this->llenar($this->componente($this->nivel()))->call('siguienteFormacion');

        $c->assertSet('pantalla', 2)->assertSee('Guardar y continuar')->assertSeeHtml('wire:submit="guardar"')->assertDontSeeHtml('wire:submit="siguienteFormacion"');
    }

    public function test_guardar_completo_guarda_el_bloque_y_pasa_al_bloque_continuo(): void
    {
        $nivel = $this->nivel();

        $this->llenar($this->componente($nivel), 2)->call('guardar')
            ->assertHasNoErrors()
            ->assertSet('pantalla', 4)
            ->assertSee('Formación Docente Continua')
            ->assertDispatched('toast', tipo: 'success', titulo: 'Bloque guardado');

        $this->assertSame(1, DB::table('proyecto_educativo_formacion_politica')->where('escuela_nivel_id', $nivel->id)->count());
        $this->assertSame(2, DB::table('proyecto_educativo_formacion_acciones')->where('escuela_nivel_id', $nivel->id)->count());
    }

    public function test_guardar_incompleto_no_escribe_y_lleva_a_la_primera_pantalla_con_error(): void
    {
        $nivel = $this->nivel();
        $acciones = [$this->accion(), $this->accion(['duracion_horas' => '0'])];

        $this->componente($nivel)->set('politica_inicial', $this->palabras(100))->set('acciones', $acciones)->call('guardar')
            ->assertHasErrors(['acciones.1.duracion_horas'])
            ->assertSet('pantalla', 3)
            ->assertDispatched('toast', tipo: 'error', titulo: 'No se guardó');

        $this->componente($nivel)->set('politica_inicial', '')->set('acciones', $acciones)->call('guardar')
            ->assertHasErrors(['politica_inicial', 'acciones.1.duracion_horas'])->assertSet('pantalla', 1);

        $this->assertSame(0, DB::table('proyecto_educativo_formacion_acciones')->count());
    }

    public function test_abre_con_lo_guardado_y_una_accion_vacia_si_no_hay_nada(): void
    {
        $nivel = $this->nivel();
        $this->componente($nivel)->assertCount('acciones', 1)->assertSet('acciones.0.nombre', '')->assertSet('pantalla', 1);

        app(GuardarFormacionInicialProyecto::class)->ejecutar($nivel->id, new DatosFormacionInicialProyecto($this->palabras(120), [$this->accion(['hora_inicio' => '08:30']), $this->accion(['nombre' => 'Otra'])]));

        $this->componente($nivel)->assertSet('politica_inicial', $this->palabras(120))->assertCount('acciones', 2)
            ->assertSet('acciones.0.hora_inicio', '08:30')->assertSet('acciones.0.fecha_inicio', '2026-08-03')->assertSet('acciones.0.duracion_horas', '40')
            ->assertSet('acciones.1.nombre', 'Otra')->assertSet('acciones.0.dias', ['lunes', 'viernes']);
    }

    public function test_los_textos_llevan_las_restricciones_del_lado_del_cliente(): void
    {
        $html = $this->componente($this->nivel())->set('politica_inicial', $this->palabras(100))->call('siguienteFormacion')->html();

        $this->assertStringContainsString("\$wire.entangle('acciones.0.proposito')", $html);
        $this->assertStringContainsString('x-on:input="recortar()"', $html);
        $this->assertStringContainsString('max: 150', $html);
        $this->assertStringContainsString('min: 20', $html);
    }

    public function test_tramite_enviado_no_guarda(): void
    {
        $nivel = $this->nivel();
        $nivel->update(['estado_id' => DB::table('estados_expediente')->where('clave', 'en_revision')->value('id')]);

        $this->llenar($this->componente($nivel))->call('guardar')
            ->assertDispatched('toast', tipo: 'error', titulo: 'No se guardó', descripcion: 'El trámite ya se envió a revisión y no puede modificarse.');

        $this->assertSame(0, DB::table('proyecto_educativo_formacion_acciones')->count());
    }

    public function test_otro_solicitante_recibe_403_y_primaria_404(): void
    {
        $nivel = $this->nivel();
        $this->actingAs(Solicitante::factory()->create()->user)->get($this->ruta($nivel))->assertForbidden();

        $primaria = $this->nivelListoPara('primaria', 'matricula');
        $this->actingAs($primaria->escuela->solicitante->user)->get(route('tramite.paso3-proyecto-educativo-seccion', ['escuelaNivel' => $primaria->id, 'seccion' => self::SLUG]))->assertNotFound();
    }

    public function test_indice_marca_completa_la_seccion_once_solo_con_los_dos_bloques(): void
    {
        $nivel = $this->nivel();
        $indice = route('tramite.paso3-proyecto-educativo', ['escuelaNivel' => $nivel->id]);

        $this->assertSame(20, substr_count($this->get($indice)->assertOk()->getContent(), 'Pendiente'));

        $this->llenar($this->componente($nivel))->call('guardar');
        $this->assertSame(20, substr_count($this->get($indice)->getContent(), 'Pendiente'), 'con solo el bloque inicial sigue pendiente');

        app(GuardarFormacionContinuaProyecto::class)->ejecutar($nivel->id, new DatosFormacionContinuaProyecto($this->palabras(100), $this->archivoPdf(1), [$this->accion()]));
        $html = $this->get($indice)->getContent();
        $this->assertSame(19, substr_count($html, 'Pendiente'));
        $this->assertSame(1, substr_count($html, 'Completa'));
    }
}
