<?php

namespace Tests\Feature\Livewire\Sedeq;

use App\Application\Tramite\ResumenTramite;
use App\Livewire\Sedeq\VisitaNivel;
use App\Models\Solicitante;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Concerns\ConTramitesEnRevision;
use Tests\Concerns\ConVisitas;
use Tests\Concerns\GeneraPdfs;
use Tests\TestCase;

class VisitaNivelTest extends TestCase
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

    private function estadoDe(int $nivelId): string
    {
        return DB::table('escuela_niveles')->join('estados_expediente as e', 'e.id', '=', 'escuela_niveles.estado_id')
            ->where('escuela_niveles.id', $nivelId)->value('e.clave');
    }

    private function manana(): string
    {
        return now()->addDay()->format('Y-m-d\TH:i');
    }

    public function test_supervisor_recibe_403_en_un_nivel_en_captura(): void
    {
        $nivel = $this->nivelEnRevision('en_captura');

        $this->actingAs($this->supervisor())->get('/sedeq/visita/'.$nivel->id)->assertForbidden();
    }

    public function test_supervisor_programa_la_visita_y_el_nivel_cambia_de_estado(): void
    {
        $nivel = $this->nivelEnRevision('aprobado');
        $this->actingAs($this->supervisor());

        Livewire::test(VisitaNivel::class, ['escuelaNivel' => $nivel->id])
            ->assertSee('Programar visita')
            ->set('fecha', $this->manana())->set('inspector', 'Ana Pérez')
            ->call('programar')->assertHasNoErrors()
            ->assertSee('Visita 1')->assertSee('Domicilio del plantel')->assertSee(ResumenTramite::domicilio($nivel->escuela->plantel))->assertSee('Ana Pérez');

        $this->assertSame('visita_programada', $this->estadoDe($nivel->id));
        $this->assertSame(1, DB::table('visitas_inspeccion')->where('escuela_nivel_id', $nivel->id)->count());
    }

    public function test_el_formulario_de_programar_no_pide_lugar(): void
    {
        $nivel = $this->nivelEnRevision('aprobado');
        $this->actingAs($this->supervisor());

        Livewire::test(VisitaNivel::class, ['escuelaNivel' => $nivel->id])
            ->assertSee('Programar visita')->assertDontSeeHtml('wire:model="lugar"')->assertDontSeeHtml('wire:model="editLugar"');
    }

    public function test_programar_muestra_los_errores_de_cada_campo(): void
    {
        $nivel = $this->nivelEnRevision('aprobado');
        $this->actingAs($this->supervisor());

        Livewire::test(VisitaNivel::class, ['escuelaNivel' => $nivel->id])
            ->set('fecha', now()->subDays(2)->format('Y-m-d\TH:i'))->set('inspector', '')
            ->call('programar')
            ->assertHasErrors(['fecha', 'inspector'])
            ->assertSee('La fecha de la visita no puede ser anterior a hoy.');

        $this->assertSame('aprobado', $this->estadoDe($nivel->id));
    }

    public function test_programar_con_fecha_ilegible_da_error_de_fecha(): void
    {
        $nivel = $this->nivelEnRevision('aprobado');
        $this->actingAs($this->supervisor());

        Livewire::test(VisitaNivel::class, ['escuelaNivel' => $nivel->id])
            ->set('fecha', 'no-es-fecha')->set('inspector', 'Y')
            ->call('programar')->assertHasErrors(['fecha']);
    }

    public function test_fecha_vacia_al_programar_es_error_y_no_programa_nada(): void
    {
        $nivel = $this->nivelEnRevision('aprobado');
        $this->actingAs($this->supervisor());

        foreach (['', '   '] as $vacio) {
            Livewire::test(VisitaNivel::class, ['escuelaNivel' => $nivel->id])
                ->set('fecha', $vacio)->set('inspector', 'Y')
                ->call('programar')->assertHasErrors(['fecha']);
        }

        $this->assertSame('aprobado', $this->estadoDe($nivel->id));
        $this->assertSame(0, DB::table('visitas_inspeccion')->where('escuela_nivel_id', $nivel->id)->count());
    }

    public function test_fecha_vacia_al_actualizar_es_error_y_no_mueve_la_visita(): void
    {
        $nivel = $this->nivelEnRevision('visita_programada');
        $id = $this->visitaDe($nivel);
        $antes = DB::table('visitas_inspeccion')->where('id', $id)->value('programada_para');
        $this->actingAs($this->supervisor());

        Livewire::test(VisitaNivel::class, ['escuelaNivel' => $nivel->id])
            ->set('editFecha', '')->call('actualizar')->assertHasErrors(['editFecha']);

        $this->assertSame($antes, DB::table('visitas_inspeccion')->where('id', $id)->value('programada_para'));
    }

    public function test_fecha_vacia_al_registrar_el_acta_es_error_y_no_cambia_nada(): void
    {
        $nivel = $this->nivelEnRevision('visita_programada');
        $id = $this->visitaDe($nivel);
        $this->actingAs($this->supervisor());

        Livewire::test(VisitaNivel::class, ['escuelaNivel' => $nivel->id])
            ->set('resultado', 'cumple')->set('realizada_el', '')->call('registrarActa')->assertHasErrors(['realizada_el']);

        $this->assertSame('visita_programada', $this->estadoDe($nivel->id));
        $this->assertSame('programada', DB::table('visitas_inspeccion')->where('id', $id)->value('estado'));
    }

    public function test_supervisor_actualiza_la_visita_programada(): void
    {
        $nivel = $this->nivelEnRevision('visita_programada');
        $id = $this->visitaDe($nivel);
        $this->actingAs($this->supervisor());

        Livewire::test(VisitaNivel::class, ['escuelaNivel' => $nivel->id])
            ->assertSet('editInspector', 'Inspector Uno')
            ->set('editInspector', 'Beto')
            ->call('actualizar')->assertHasNoErrors();

        $this->assertSame('Beto', DB::table('visitas_inspeccion')->where('id', $id)->value('inspector'));
    }

    public function test_actualizar_una_visita_con_fecha_pasada_muestra_el_error_de_la_accion(): void
    {
        $nivel = $this->nivelEnRevision('visita_programada');
        $this->visitaDe($nivel, 1, 'programada', 'cumple', ['programada_para' => now()->subDays(3)]);
        $this->actingAs($this->supervisor());

        Livewire::test(VisitaNivel::class, ['escuelaNivel' => $nivel->id])
            ->set('editInspector', 'Corregido')
            ->call('actualizar')
            ->assertHasErrors(['editFecha'])
            ->assertSee('La fecha de la visita no puede ser anterior a hoy.');
    }

    public function test_registra_el_acta_que_cumple_con_pdf(): void
    {
        $nivel = $this->nivelEnRevision('visita_programada');
        $id = $this->visitaDe($nivel);
        $this->actingAs($this->supervisor());

        Livewire::test(VisitaNivel::class, ['escuelaNivel' => $nivel->id])
            ->set('resultado', 'cumple')->set('realizada_el', now()->toDateString())
            ->set('acta', $this->archivoPdf(1, 'acta firmada.pdf'))
            ->call('registrarActa')->assertHasNoErrors()
            ->assertSee('Cumple')->assertSee('acta firmada.pdf');

        $this->assertSame('visita_aprobada', $this->estadoDe($nivel->id));
        $this->assertNotNull(DB::table('visitas_inspeccion')->where('id', $id)->value('acta_path'));
    }

    public function test_registra_el_acta_con_observaciones_y_las_muestra(): void
    {
        $nivel = $this->nivelEnRevision('visita_programada');
        $this->visitaDe($nivel);
        $this->actingAs($this->supervisor());

        Livewire::test(VisitaNivel::class, ['escuelaNivel' => $nivel->id])
            ->set('resultado', 'con_observaciones')->set('realizada_el', now()->toDateString())
            ->call('agregarObservacion')->set('observaciones.0', 'Falta extintor')->set('observaciones.1', 'Falta rampa')
            ->call('registrarActa')->assertHasNoErrors()
            ->assertSee('Falta extintor')->assertSee('Falta rampa')->assertSee('(aprox.)');

        $this->assertSame('en_subsanacion', $this->estadoDe($nivel->id));
    }

    public function test_agregar_y_quitar_observaciones(): void
    {
        $nivel = $this->nivelEnRevision('visita_programada');
        $this->visitaDe($nivel);
        $this->actingAs($this->supervisor());

        Livewire::test(VisitaNivel::class, ['escuelaNivel' => $nivel->id])
            ->assertSet('observaciones', [''])
            ->call('agregarObservacion')->assertCount('observaciones', 2)
            ->call('quitarObservacion', 0)->assertCount('observaciones', 1)
            ->call('quitarObservacion', 0)->assertSet('observaciones', ['']);
    }

    public function test_acta_con_observaciones_sin_ninguna_muestra_error(): void
    {
        $nivel = $this->nivelEnRevision('visita_programada');
        $this->visitaDe($nivel);
        $this->actingAs($this->supervisor());

        Livewire::test(VisitaNivel::class, ['escuelaNivel' => $nivel->id])
            ->set('resultado', 'con_observaciones')->set('realizada_el', now()->toDateString())
            ->call('registrarActa')
            ->assertHasErrors(['observaciones'])
            ->assertSee('Un acta con observaciones necesita al menos una observación.');

        $this->assertSame('visita_programada', $this->estadoDe($nivel->id));
    }

    public function test_acta_con_fecha_futura_y_pdf_invalido_muestran_errores(): void
    {
        $nivel = $this->nivelEnRevision('visita_programada');
        $this->visitaDe($nivel);
        $this->actingAs($this->supervisor());

        Livewire::test(VisitaNivel::class, ['escuelaNivel' => $nivel->id])
            ->set('resultado', 'cumple')->set('realizada_el', now()->addDays(3)->toDateString())
            ->call('registrarActa')->assertHasErrors(['realizada_el']);

        Livewire::test(VisitaNivel::class, ['escuelaNivel' => $nivel->id])
            ->set('resultado', 'cumple')->set('realizada_el', now()->toDateString())
            ->set('acta', UploadedFile::fake()->createWithContent('malo.pdf', 'no es un pdf'))
            ->call('registrarActa')->assertHasErrors(['acta']);
    }

    public function test_adjunta_el_acta_a_una_visita_realizada(): void
    {
        $nivel = $this->nivelEnRevision('visita_aprobada');
        $id = $this->visitaDe($nivel, 1, 'realizada');
        $this->actingAs($this->supervisor());

        Livewire::test(VisitaNivel::class, ['escuelaNivel' => $nivel->id])
            ->set('actaNueva', $this->archivoPdf(1, 'tarde.pdf'))
            ->call('adjuntarActa')->assertHasNoErrors()->assertSee('tarde.pdf');

        $this->assertSame('tarde.pdf', DB::table('visitas_inspeccion')->where('id', $id)->value('acta_nombre'));
    }

    public function test_acepta_la_subsanacion(): void
    {
        $nivel = $this->nivelEnRevision('en_subsanacion');
        $v = $this->visitaDe($nivel, 1, 'realizada', 'con_observaciones');
        DB::table('observaciones_visita')->insert(['visita_id' => $v, 'orden' => 1, 'texto' => 'Falta extintor']);
        $this->respuestaDe($v, 'evidencia.pdf', $this->archivoEnDisco());
        $this->actingAs($this->supervisor());

        Livewire::test(VisitaNivel::class, ['escuelaNivel' => $nivel->id])
            ->assertSee('evidencia.pdf')->assertSee('Respuesta recibida')
            ->call('aceptar')->assertHasNoErrors();

        $this->assertSame('visita_aprobada', $this->estadoDe($nivel->id));
    }

    public function test_el_nombre_de_archivo_se_escapa(): void
    {
        $nivel = $this->nivelEnRevision('en_subsanacion');
        $v = $this->visitaDe($nivel, 1, 'realizada', 'con_observaciones');
        $this->respuestaDe($v, '<script>alert(1)</script>.pdf', $this->archivoEnDisco());
        $this->actingAs($this->supervisor());

        Livewire::test(VisitaNivel::class, ['escuelaNivel' => $nivel->id])
            ->assertDontSeeHtml('<script>alert(1)</script>')->assertSee('<script>alert(1)</script>.pdf');
    }

    public function test_muestra_el_historial_de_visitas(): void
    {
        $nivel = $this->nivelEnRevision('visita_programada');
        $this->visitaDe($nivel, 1, 'realizada', 'con_observaciones');
        $this->visitaDe($nivel, 2);
        $this->actingAs($this->supervisor());

        Livewire::test(VisitaNivel::class, ['escuelaNivel' => $nivel->id])
            ->assertSee('Visita 1')->assertSee('Visita 2')->assertSee('Historial');
    }

    public function test_el_revisor_recibe_403_el_invitado_va_al_login_y_el_dueno_no_entra(): void
    {
        $nivel = $this->nivelEnRevision('aprobado');

        $this->actingAs($this->revisor())->get(route('sedeq.visita', ['escuelaNivel' => $nivel->id]))->assertForbidden();
        $this->actingAs(Solicitante::factory()->create()->user)->get(route('sedeq.visita', ['escuelaNivel' => $nivel->id]))->assertForbidden();
        auth()->logout();
        $this->get(route('sedeq.visita', ['escuelaNivel' => $nivel->id]))->assertRedirect(route('login'));
    }

    public function test_el_supervisor_abre_la_pagina(): void
    {
        $nivel = $this->nivelEnRevision('aprobado');

        $this->actingAs($this->supervisor())->get(route('sedeq.visita', ['escuelaNivel' => $nivel->id]))
            ->assertOk()->assertSee('Programar visita');
    }
}
