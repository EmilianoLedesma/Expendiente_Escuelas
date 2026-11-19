<?php

namespace Tests\Feature\Livewire\Tramite;

use App\Application\Tramite\ResumenTramite;
use App\Livewire\Tramite\VisitaSolicitante;
use App\Models\EscuelaNivel;
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

/** Página de la visita para el solicitante: el dueño ve todo y responde; el responsable por nivel solo mira. */
class VisitaSolicitanteTest extends TestCase
{
    use ConTramitesEnRevision;
    use ConVisitas;
    use GeneraPdfs;
    use RefreshDatabase;

    private Solicitante $dueno;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('documentos');
        $this->dueno = Solicitante::factory()->create();
    }

    private function subsanacion(): EscuelaNivel
    {
        $nivel = $this->nivelDe($this->dueno, 'primaria', 'en_subsanacion');
        $visita = $this->visitaDe($nivel, 1, 'realizada', 'con_observaciones', ['inspector' => 'Ana Pérez']);
        DB::table('observaciones_visita')->insert([
            ['visita_id' => $visita, 'orden' => 1, 'texto' => 'Falta extintor'],
            ['visita_id' => $visita, 'orden' => 2, 'texto' => 'Rotular salidas'],
        ]);

        return $nivel;
    }

    private function visitaId(EscuelaNivel $nivel): int
    {
        return (int) DB::table('visitas_inspeccion')->where('escuela_nivel_id', $nivel->id)->value('id');
    }

    private function estadoDe(EscuelaNivel $nivel): string
    {
        return DB::table('escuela_niveles')->join('estados_expediente as e', 'e.id', '=', 'escuela_niveles.estado_id')
            ->where('escuela_niveles.id', $nivel->id)->value('e.clave');
    }

    private function pagina(EscuelaNivel $nivel, $usuario = null)
    {
        $this->actingAs($usuario ?? $this->dueno->user);

        return Livewire::test(VisitaSolicitante::class, ['escuelaNivel' => $nivel->id]);
    }

    public function test_el_dueno_ve_la_visita_las_observaciones_y_el_plazo(): void
    {
        $nivel = $this->subsanacion();
        $hasta = now()->addDays(5)->format('d/m/Y');

        $this->pagina($nivel)
            ->assertSee('Primaria')
            ->assertSee('Ana Pérez')
            ->assertSee('Domicilio del plantel')
            ->assertSee(ResumenTramite::domicilio($nivel->escuela->plantel))
            ->assertSee('Falta extintor')
            ->assertSee('Rotular salidas')
            ->assertSee('En subsanación: responde antes de '.$hasta.' (aprox.)')
            ->assertSee('Plazo aprox. para responder: '.$hasta.' (aprox.; 5 días hábiles, solo informativo)')
            ->assertSee('Todavía no cuentas con el acuerdo de incorporación');
    }

    public function test_el_texto_de_la_pagina_no_dice_sedeq_ni_rvoe_en_tramite_como_estado(): void
    {
        $nivel = $this->subsanacion();

        $html = $this->pagina($nivel)->html();

        $this->assertStringNotContainsString('SEDEQ', $html);
        $this->assertStringContainsString('autoridad educativa', $html);
    }

    public function test_el_dueno_responde_con_dos_pdf_y_el_estado_no_cambia(): void
    {
        $nivel = $this->subsanacion();

        $this->pagina($nivel)
            ->set('nota', 'Ya colocamos el extintor')
            ->set('archivos', [$this->archivoPdf(1, 'foto-uno.pdf'), $this->archivoPdf(1, 'foto-dos.pdf')])
            ->call('responder')
            ->assertHasNoErrors()
            ->assertSet('nota', '')
            ->assertSet('archivos', [])
            ->assertSee('Tu respuesta se envió; la autoridad educativa la revisará.')
            ->assertSee('Ya colocamos el extintor')
            ->assertSee('foto-uno.pdf');

        $respuesta = DB::table('respuestas_visita')->where('visita_id', $this->visitaId($nivel))->first();
        $this->assertSame('Ya colocamos el extintor', $respuesta->nota);
        $rutas = DB::table('respuestas_visita_archivos')->where('respuesta_id', $respuesta->id)->pluck('archivo_path');
        $this->assertCount(2, $rutas);
        foreach ($rutas as $ruta) {
            Storage::disk('documentos')->assertExists($ruta);
        }
        $this->assertSame('en_subsanacion', $this->estadoDe($nivel));
    }

    public function test_el_historial_da_al_dueno_enlaces_de_descarga_de_acta_y_respuestas(): void
    {
        $nivel = $this->subsanacion();
        $visita = $this->visitaId($nivel);
        DB::table('visitas_inspeccion')->where('id', $visita)->update(['acta_path' => $this->archivoEnDisco(), 'acta_nombre' => 'acta-uno.pdf']);
        [, $archivo] = $this->respuestaDe($visita, 'prueba.pdf', $this->archivoEnDisco('visitas/1/r.pdf'));

        $this->pagina($nivel)
            ->assertSee(route('visita.acta', ['escuelaNivel' => $nivel->id, 'visita' => $visita]), false)
            ->assertSee(route('visita.respuesta-archivo', ['escuelaNivel' => $nivel->id, 'visita' => $visita, 'archivo' => $archivo]), false);
    }

    public function test_nota_vacia_seis_archivos_o_no_pdf_dan_error_y_no_guardan_nada(): void
    {
        $nivel = $this->subsanacion();

        $this->pagina($nivel)->set('nota', '  ')->call('responder')
            ->assertHasErrors(['nota'])->assertSee('Escribe la nota de la respuesta.');

        $seis = array_map(fn ($i) => $this->archivoPdf(1, "d{$i}.pdf"), range(1, 6));
        $this->pagina($nivel)->set('nota', 'Listo')->set('archivos', $seis)->call('responder')
            ->assertHasErrors(['archivos'])->assertSee('Se admiten hasta 5 archivos PDF.');

        $this->pagina($nivel)->set('nota', 'Listo')->set('archivos', [UploadedFile::fake()->createWithContent('x.pdf', 'no es pdf')])
            ->call('responder')->assertHasErrors(['archivos']);

        $this->assertSame(0, DB::table('respuestas_visita')->count());
        $this->assertSame(0, DB::table('respuestas_visita_archivos')->count());
    }

    public function test_un_pdf_que_excede_el_limite_de_subida_muestra_su_error(): void
    {
        $nivel = $this->subsanacion();

        $this->pagina($nivel)
            ->set('archivos', [UploadedFile::fake()->create('grande.pdf', 13000, 'application/pdf')])
            ->assertSee('Cada archivo debe ser un PDF de máximo 10 MB.')
            ->assertSeeHtml('aria-invalid="true"')
            ->assertSeeHtml('id="archivos-error"');

        $this->assertSame(0, DB::table('respuestas_visita_archivos')->count());
    }

    public function test_quitar_un_archivo_elegido_lo_saca_de_la_lista(): void
    {
        $nivel = $this->subsanacion();

        $this->pagina($nivel)
            ->set('archivos', [$this->archivoPdf(1, 'uno.pdf'), $this->archivoPdf(1, 'dos.pdf')])
            ->call('quitarArchivo', 0)
            ->assertCount('archivos', 1);
    }

    public function test_sin_subsanacion_no_hay_formulario(): void
    {
        $aprobada = $this->nivelDe($this->dueno, 'primaria', 'visita_aprobada');
        $this->visitaDe($aprobada, 1, 'realizada', 'cumple');
        $programada2 = $this->nivelDe($this->dueno, 'secundaria', 'visita_programada');
        $this->visitaDe($programada2);

        $this->pagina($programada2)->assertSee('Visita programada para el')->assertDontSee('Enviar respuesta');
        $this->pagina($aprobada)->assertSee('Visita aprobada')->assertDontSee('Enviar respuesta');
        $this->pagina($this->subsanacion2())->assertSee('Enviar respuesta');
    }

    private function subsanacion2(): EscuelaNivel
    {
        $nivel = $this->nivelDe($this->dueno, 'preescolar', 'en_subsanacion');
        $this->visitaDe($nivel, 1, 'realizada', 'con_observaciones');

        return $nivel;
    }

    public function test_el_dueno_de_otra_escuela_recibe_403(): void
    {
        $nivel = $this->subsanacion();

        $this->pagina($nivel, Solicitante::factory()->create()->user)->assertForbidden();
    }

    public function test_el_supervisor_no_usa_la_pagina_del_solicitante(): void
    {
        $nivel = $this->subsanacion();

        $this->pagina($nivel, $this->supervisor())->assertForbidden();
    }

    public function test_el_revisor_recibe_403(): void
    {
        $nivel = $this->subsanacion();

        $this->pagina($nivel, $this->revisor())->assertForbidden();
    }

    public function test_el_responsable_del_nivel_solo_mira_sin_formulario_ni_descargas(): void
    {
        $nivel = $this->subsanacion();
        $visita = $this->visitaId($nivel);
        DB::table('visitas_inspeccion')->where('id', $visita)->update(['acta_path' => $this->archivoEnDisco(), 'acta_nombre' => 'acta-uno.pdf']);
        $this->respuestaDe($visita, 'prueba.pdf', $this->archivoEnDisco('visitas/1/r.pdf'));
        $responsable = $this->responsableDe($nivel);

        $this->pagina($nivel, $responsable)
            ->assertOk()
            ->assertSee('Falta extintor')
            ->assertDontSee('Enviar respuesta')
            ->assertDontSee('/visitas/', false)
            ->assertSee('prueba.pdf');
    }

    public function test_el_responsable_de_otro_nivel_recibe_403(): void
    {
        $nivel = $this->subsanacion();
        $otro = $this->nivelDe($this->dueno, 'secundaria', 'en_subsanacion');
        $responsable = $this->responsableDe($otro);

        $this->pagina($nivel, $responsable)->assertForbidden();
    }

    public function test_el_responsable_no_puede_responder_aunque_llame_al_metodo(): void
    {
        $nivel = $this->subsanacion();
        $responsable = $this->responsableDe($nivel);

        $this->pagina($nivel, $responsable)->set('nota', 'Intento')->call('responder')->assertForbidden();

        $this->assertSame(0, DB::table('respuestas_visita')->count());
    }

    public function test_un_invitado_va_al_login(): void
    {
        $nivel = $this->subsanacion();

        $this->get(route('tramite.visita', ['escuelaNivel' => $nivel->id]))->assertRedirect(route('login'));
    }

    public function test_la_ruta_responde_al_dueno_y_escapa_nombres_de_archivo_hostiles(): void
    {
        $nivel = $this->subsanacion();
        $visita = $this->visitaId($nivel);
        $this->respuestaDe($visita, '<script>alert(1)</script>.pdf', $this->archivoEnDisco());

        $this->actingAs($this->dueno->user)->get(route('tramite.visita', ['escuelaNivel' => $nivel->id]))
            ->assertOk()
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;.pdf', false);
    }
}
