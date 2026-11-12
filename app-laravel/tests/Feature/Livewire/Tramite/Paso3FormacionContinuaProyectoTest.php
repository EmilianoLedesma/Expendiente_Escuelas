<?php

namespace Tests\Feature\Livewire\Tramite;

use App\Application\ProyectoEducativo\DTO\DatosFormacionContinuaProyecto;
use App\Application\ProyectoEducativo\GuardarFormacionContinuaProyecto;
use App\Application\ProyectoEducativo\ReglasFormacionProyecto;
use App\Livewire\Tramite\Paso3\ProyectoEducativoSeccion;
use App\Models\EscuelaNivel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Concerns\GeneraPdfs;
use Tests\Concerns\PreparaPaso3;
use Tests\TestCase;

class Paso3FormacionContinuaProyectoTest extends TestCase
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

    /** @param  array<string, mixed>  $cambios @return array<string, mixed> */
    private function accion(array $cambios = []): array
    {
        $base = [
            'nombre' => 'Curso de actualización', 'formato' => 'curso', 'formato_otro' => '', 'duracion_horas' => '20', 'dias' => ['sabado'],
            'hora_inicio' => '10:00', 'hora_fin' => '14:00', 'fecha_inicio' => '2027-01-09', 'fecha_fin' => '2027-03-27', 'otros' => '',
        ];
        foreach (ReglasFormacionProyecto::TEXTOS as $campo => [$min]) {
            $base[$campo] = $this->palabras($min);
        }

        return array_merge($base, $cambios);
    }

    /** Con una acción inicial vacía llegar a la pantalla de la política continua: 1 política inicial, 2 acción inicial, 3 política continua. */
    private function enPoliticaContinua()
    {
        return $this->componente($this->nivel())
            ->set('politica_inicial', $this->palabras(100))->set('acciones', [$this->accion()])
            ->call('siguienteFormacion')->call('siguienteFormacion')->assertSet('pantalla', 3);
    }

    /** Pantalla 4: la planeación, con la política continua ya válida. */
    private function enPlaneacion()
    {
        return $this->enPoliticaContinua()->set('politica_continua', $this->palabras(100))->call('siguienteFormacion')->assertSet('pantalla', 4);
    }

    /** Pantalla 5: la primera acción continua, con el PDF ya subido y válido. */
    private function enAccionContinua()
    {
        return $this->enPlaneacion()->set('planeacion_continua', $this->archivoPdf(1, 'planeacion.pdf'))->call('siguienteFormacion')->assertSet('pantalla', 5);
    }

    public function test_la_politica_continua_se_muestra_sola_con_la_etiqueta_y_el_placeholder_del_dueno(): void
    {
        $c = $this->enPoliticaContinua();

        $c->assertSee('Formación Docente Continua')
            ->assertSee('Política Global de Formación Continua (100 a 250 palabras)')
            ->assertSee('Describa la política institucional para la formación continua de los docentes...')
            ->assertSeeHtml('wire:submit="siguienteFormacion"')
            ->assertDontSee('Nombre de la acción')
            ->assertDontSee('Política Global de Formación Inicial');
        $this->assertSame(1, substr_count($c->html(), '<textarea'));
    }

    public function test_siguiente_en_la_politica_continua_la_exige(): void
    {
        $this->enPoliticaContinua()->call('siguienteFormacion')
            ->assertSet('pantalla', 3)
            ->assertHasErrors(['politica_continua'])
            ->assertHasNoErrors(['politica_inicial']);
    }

    public function test_la_planeacion_es_un_campo_de_archivo_pdf_con_la_etiqueta_y_el_placeholder_del_dueno(): void
    {
        $c = $this->enPlaneacion();

        $c->assertSee('Planeación del curso o las acciones de formación docente continua (1 a 2 cuartillas)')
            ->assertSee('Presente la planeación detallada de las acciones de formación continua...')
            ->assertSeeHtml('type="file"')
            ->assertSeeHtml('accept="application/pdf"')
            ->assertDontSee('Política Global de Formación Continua');
        $this->assertSame(0, substr_count($c->html(), '<textarea'));
    }

    public function test_siguiente_en_la_planeacion_exige_un_pdf_valido(): void
    {
        $c = $this->enPlaneacion();

        $c->call('siguienteFormacion')->assertSet('pantalla', 4)->assertHasErrors(['planeacion_continua']);
        $c->set('planeacion_continua', $this->archivoPdf(3))->call('siguienteFormacion')
            ->assertSet('pantalla', 4)->assertHasErrors(['planeacion_continua']);
        $c->set('planeacion_continua', $this->archivoPdf(2))->call('siguienteFormacion')
            ->assertSet('pantalla', 5)->assertHasNoErrors();
        $this->assertSame([], Storage::disk('documentos')->allFiles('proyecto_educativo'), 'avanzar no sube nada a disco');
    }

    public function test_la_accion_continua_usa_los_mismos_campos_nativos_con_rutas_de_la_continua(): void
    {
        $c = $this->enAccionContinua();
        $html = $c->html();

        $c->assertSee('Acción 1 de 1')->assertSee('Nombre de la acción')->assertSee('Describa la acción formativa')->assertSee('Temario');
        $this->assertSame(2, substr_count($html, 'type="time"'));
        $this->assertSame(2, substr_count($html, 'type="date"'));
        $this->assertSame(1, substr_count($html, 'type="number"'));
        $this->assertSame(7, substr_count($html, 'type="checkbox"'));
        $this->assertSame(5, substr_count($html, 'type="radio"'));
        $this->assertSame(8, substr_count($html, '<textarea'));
        $this->assertStringContainsString('wire:model.blur="acciones_continua.0.hora_inicio"', $html);
        $this->assertStringContainsString('wire:model.blur="acciones_continua.0.fecha_fin"', $html);
        $this->assertStringContainsString("\$wire.entangle('acciones_continua.0.proposito')", $html);
        $this->assertStringNotContainsString('wire:model.blur="acciones.0.', $html);
    }

    public function test_siguiente_valida_solo_la_accion_continua_actual(): void
    {
        $this->enAccionContinua()->call('siguienteFormacion')
            ->assertSet('pantalla', 5)
            ->assertHasErrors(['acciones_continua.0.nombre', 'acciones_continua.0.dias', 'acciones_continua.0.duracion_horas'])
            ->assertHasNoErrors(['acciones.0.nombre', 'politica_continua']);
    }

    public function test_agregar_y_quitar_acciones_continuas_no_tocan_las_iniciales(): void
    {
        $c = $this->enAccionContinua()->set('acciones_continua', [$this->accion()]);

        $c->call('agregarAccion')->assertCount('acciones_continua', 2)->assertCount('acciones', 1)->assertSet('pantalla', 6)->assertSee('Acción 2 de 2');
        $c->call('quitarAccion')->assertCount('acciones_continua', 1)->assertCount('acciones', 1)->assertSet('pantalla', 5);
        $c->call('quitarAccion')->assertCount('acciones_continua', 1);
    }

    public function test_el_maximo_de_acciones_continuas_es_veinte(): void
    {
        $this->enAccionContinua()->set('acciones_continua', array_fill(0, 20, $this->accion()))->call('agregarAccion')
            ->assertCount('acciones_continua', 20)->assertDontSee('Agregar acción');
    }

    public function test_anterior_desde_la_politica_continua_vuelve_a_la_ultima_accion_inicial(): void
    {
        $this->enPoliticaContinua()->call('anteriorFormacion')->assertSet('pantalla', 2)->assertSee('Acción 1 de 1');
    }

    public function test_la_ultima_pantalla_ofrece_guardar_no_guardar_y_continuar(): void
    {
        $c = $this->enAccionContinua();

        $c->assertSeeHtml('wire:submit="guardar"')->assertSee('Guardar')->assertDontSee('Guardar y continuar')->assertDontSeeHtml('wire:submit="siguienteFormacion"');
    }

    public function test_guardar_el_bloque_continuo_guarda_politica_archivo_y_acciones_y_avisa_bloque_guardado(): void
    {
        $nivel = $this->nivel();
        $c = $this->componente($nivel)->set('politica_inicial', $this->palabras(100))->set('acciones', [$this->accion()])
            ->call('siguienteFormacion')->call('siguienteFormacion')
            ->set('politica_continua', $this->palabras(100))->call('siguienteFormacion')
            ->set('planeacion_continua', $this->archivoPdf(1, 'planeacion.pdf'))->call('siguienteFormacion')
            ->set('acciones_continua', [$this->accion(), $this->accion(['nombre' => 'Otra'])]);

        $c->call('guardar')->assertHasNoErrors()->assertDispatched('toast', tipo: 'success', titulo: 'Bloque guardado')
            ->assertSet('planeacion_continua', null)->assertSet('formacionGuardado.planeacion_nombre', 'planeacion.pdf');

        $this->assertSame(2, DB::table('proyecto_educativo_formacion_acciones')->where('tipo', 'continua')->count());
        $this->assertSame(0, DB::table('proyecto_educativo_formacion_acciones')->where('tipo', 'inicial')->count(), 'el bloque inicial no se guardó');
        $this->assertSame('planeacion.pdf', DB::table('proyecto_educativo_formacion_politica')->where('tipo', 'continua')->value('planeacion_nombre'));
        $this->assertCount(1, Storage::disk('documentos')->allFiles('proyecto_educativo'));
    }

    public function test_con_los_dos_bloques_guardados_avisa_seccion_completa(): void
    {
        $nivel = $this->nivel();
        $this->componente($nivel)->set('politica_inicial', $this->palabras(100))->set('acciones', [$this->accion()])->call('guardar');

        $this->componente($nivel)->call('siguienteFormacion')->call('siguienteFormacion')
            ->set('politica_continua', $this->palabras(100))->call('siguienteFormacion')
            ->set('planeacion_continua', $this->archivoPdf(1))->call('siguienteFormacion')
            ->set('acciones_continua', [$this->accion()])
            ->call('guardar')->assertDispatched('toast', tipo: 'success', titulo: 'Sección completa');
    }

    public function test_guardar_incompleto_lleva_a_la_primera_pantalla_con_error_del_bloque_continuo(): void
    {
        $c = $this->enAccionContinua()->set('acciones_continua', [$this->accion(), $this->accion(['duracion_horas' => '0'])]);

        $c->call('guardar')->assertHasErrors(['acciones_continua.1.duracion_horas'])->assertSet('pantalla', 6)
            ->assertDispatched('toast', tipo: 'error', titulo: 'No se guardó');

        $c->set('politica_continua', '')->call('guardar')->assertHasErrors(['politica_continua'])->assertSet('pantalla', 3);

        $this->assertSame(0, DB::table('proyecto_educativo_formacion_politica')->count());
        $this->assertSame([], Storage::disk('documentos')->allFiles('proyecto_educativo'));
    }

    public function test_guardar_sin_archivo_nuevo_conserva_el_guardado_y_lo_muestra(): void
    {
        $nivel = $this->nivel();
        app(GuardarFormacionContinuaProyecto::class)->ejecutar($nivel->id, new DatosFormacionContinuaProyecto($this->palabras(100), $this->archivoPdf(2, 'guardada.pdf'), [$this->accion()]));
        $antes = DB::table('proyecto_educativo_formacion_politica')->where('tipo', 'continua')->value('planeacion_path');

        $c = $this->componente($nivel)->assertSet('formacionGuardado.planeacion_nombre', 'guardada.pdf')->assertSet('formacionGuardado.planeacion_paginas', 2)
            ->assertSet('politica_continua', $this->palabras(100))->assertCount('acciones_continua', 1)->assertSet('acciones_continua.0.hora_inicio', '10:00');
        // Sin nada guardado del bloque inicial se llega por la navegación a la planeación.
        $c->set('politica_inicial', $this->palabras(100))->set('acciones', [$this->accion()])
            ->call('siguienteFormacion')->call('siguienteFormacion')->call('siguienteFormacion')->assertSet('pantalla', 4)
            ->assertSee('Archivo guardado:')->assertSee('guardada.pdf')
            ->call('siguienteFormacion')->assertSet('pantalla', 5)->assertHasNoErrors()
            ->call('guardar')->assertHasNoErrors();

        $this->assertSame($antes, DB::table('proyecto_educativo_formacion_politica')->where('tipo', 'continua')->value('planeacion_path'));
    }

    public function test_guardar_en_el_bloque_inicial_no_guarda_el_continuo(): void
    {
        $nivel = $this->nivel();

        $this->componente($nivel)->set('politica_inicial', $this->palabras(100))->set('acciones', [$this->accion()])
            ->set('politica_continua', $this->palabras(100))->set('acciones_continua', [$this->accion()])
            ->call('guardar')->assertHasNoErrors();

        $this->assertSame(1, DB::table('proyecto_educativo_formacion_politica')->count());
        $this->assertSame(['inicial'], DB::table('proyecto_educativo_formacion_acciones')->pluck('tipo')->unique()->values()->all());
    }

    public function test_tramite_enviado_no_guarda_el_bloque_continuo(): void
    {
        $nivel = $this->nivel();
        $c = $this->componente($nivel)->set('politica_inicial', $this->palabras(100))->set('acciones', [$this->accion()])
            ->call('siguienteFormacion')->call('siguienteFormacion')
            ->set('politica_continua', $this->palabras(100))->call('siguienteFormacion')
            ->set('planeacion_continua', $this->archivoPdf(1))->call('siguienteFormacion')
            ->set('acciones_continua', [$this->accion()]);
        $nivel->update(['estado_id' => DB::table('estados_expediente')->where('clave', 'en_revision')->value('id')]);

        $c->call('guardar')->assertDispatched('toast', tipo: 'error', titulo: 'No se guardó', descripcion: 'El trámite ya se envió a revisión y no puede modificarse.');

        $this->assertSame(0, DB::table('proyecto_educativo_formacion_acciones')->count());
        $this->assertSame([], Storage::disk('documentos')->allFiles('proyecto_educativo'));
    }
}
