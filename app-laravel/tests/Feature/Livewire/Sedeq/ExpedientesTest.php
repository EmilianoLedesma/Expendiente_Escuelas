<?php

namespace Tests\Feature\Livewire\Sedeq;

use App\Application\Revision\ListarExpedientes;
use App\Livewire\Sedeq\Expedientes;
use App\Models\EscuelaNivel;
use App\Models\Solicitante;
use Database\Seeders\PasosCapturaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Concerns\ConTramitesEnRevision;
use Tests\Concerns\ConVisitas;
use Tests\Concerns\GeneraPdfs;
use Tests\TestCase;

/** Expediente del supervisor (fase 0): lista de niveles con la revisión documental aprobada. */
class ExpedientesTest extends TestCase
{
    use ConTramitesEnRevision;
    use ConVisitas;
    use GeneraPdfs;
    use RefreshDatabase;

    private function nombrar(EscuelaNivel $nivel, string $nombre): void
    {
        DB::table('escuelas')->where('id', $nivel->escuela_id)->update(['nombre_aprobado' => $nombre]);
    }

    public function test_el_supervisor_ve_los_aprobados_con_enlace_al_tramite(): void
    {
        (new PasosCapturaSeeder)->run();
        $aprobado = $this->nivelEnRevision('aprobado');
        $this->nombrar($aprobado, 'Colegio Aprobado');
        $this->nombrar($this->nivelEnRevision('en_revision'), 'Colegio En Revision');

        $this->actingAs($this->supervisor())->get(route('sedeq.expedientes'))
            ->assertOk()
            ->assertSee('Expedientes')
            ->assertSee('Colegio Aprobado')
            ->assertDontSee('Colegio En Revision')
            ->assertSee('href="'.route('sedeq.tramite', ['escuela' => $aprobado->escuela_id]).'"', false);
    }

    public function test_una_escuela_con_varios_niveles_es_una_sola_tarjeta(): void
    {
        (new PasosCapturaSeeder)->run();
        $primaria = $this->nivelDe(Solicitante::factory()->create(), 'primaria', 'aprobado');
        $this->nivelEn($primaria->escuela, 'secundaria', 'aprobado');
        $this->nombrar($primaria, 'Colegio Doble');
        $this->nombrar($this->nivelEnRevision('aprobado'), 'Colegio Simple');
        $enlace = 'href="'.route('sedeq.tramite', ['escuela' => $primaria->escuela_id]).'"';

        $html = $this->actingAs($this->supervisor())->get(route('sedeq.expedientes'))->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, $enlace), 'un solo enlace por escuela');
        $this->assertSame(1, substr_count($html, '>Colegio Doble</h2>'), 'una sola tarjeta para la escuela');
        $this->assertStringContainsString('Primaria', $html);
        $this->assertStringContainsString('Secundaria', $html);
        $this->assertStringContainsString('2 escuelas', $html);
        $this->assertStringContainsString('3 niveles', $html);
    }

    public function test_la_busqueda_filtra_la_lista(): void
    {
        (new PasosCapturaSeeder)->run();
        $this->nombrar($this->nivelEnRevision('aprobado'), 'Instituto Cumbres');
        $this->nombrar($this->nivelEnRevision('aprobado'), 'Colegio Del Valle');
        $this->actingAs($this->supervisor());

        Livewire::test(Expedientes::class)
            ->assertSee('Instituto Cumbres')->assertSee('Colegio Del Valle')
            ->set('buscar', 'cumbres')
            ->assertSee('Instituto Cumbres')->assertDontSee('Colegio Del Valle');
    }

    public function test_sin_aprobados_lo_dice(): void
    {
        (new PasosCapturaSeeder)->run();
        $this->actingAs($this->supervisor());

        Livewire::test(Expedientes::class)->assertSee('Todavía no hay expedientes aprobados');
    }

    public function test_el_filtro_de_estado_funciona_y_empieza_en_aprobados(): void
    {
        (new PasosCapturaSeeder)->run();
        $this->nombrar($this->nivelEnRevision('aprobado'), 'Colegio Aprobado');
        $this->nombrar($this->nivelEnRevision('en_revision'), 'Colegio En Revision');
        $this->nombrar($this->nivelEnRevision('con_observaciones'), 'Colegio Con Observaciones');
        $this->actingAs($this->supervisor());

        Livewire::test(Expedientes::class)
            ->assertSeeHtml('value="aprobado"')->assertSeeHtml('value="en_revision"')
            ->assertSeeHtml('value="con_observaciones"')->assertSeeHtml('value="rechazado"')
            ->assertDontSeeHtml('value="en_captura"')
            ->assertSee('Colegio Aprobado')->assertDontSee('Colegio En Revision')
            ->set('estado', 'en_revision')
            ->assertSee('Colegio En Revision')->assertDontSee('Colegio Aprobado')
            ->set('estado', 'con_observaciones')
            ->assertSee('Colegio Con Observaciones')->assertDontSee('Colegio En Revision');
    }

    public function test_un_estado_invalido_vuelve_a_aprobados_sin_romper(): void
    {
        (new PasosCapturaSeeder)->run();
        $this->nombrar($this->nivelEnRevision('aprobado'), 'Colegio Aprobado');
        $this->actingAs($this->supervisor());

        Livewire::test(Expedientes::class)->set('estado', 'en_captura')->assertOk()->assertSee('Colegio Aprobado')->assertSet('estado', 'aprobado');
    }

    public function test_el_revisor_recibe_403(): void
    {
        (new PasosCapturaSeeder)->run();
        $this->actingAs($this->revisor())->get(route('sedeq.expedientes'))->assertForbidden();
    }

    public function test_un_invitado_va_al_login(): void
    {
        $this->get(route('sedeq.expedientes'))->assertRedirect(route('login'));
    }

    public function test_la_barra_del_supervisor_enlaza_expedientes(): void
    {
        (new PasosCapturaSeeder)->run();
        $this->actingAs($this->supervisor())->get(route('sedeq.tablero'))
            ->assertSee('href="'.route('sedeq.expedientes').'"', false);
    }

    public function test_enlace_de_visita_segun_el_estado_del_nivel(): void
    {
        (new PasosCapturaSeeder)->run();
        $casos = ['aprobado' => 'Programar visita', 'visita_programada' => 'Registrar acta', 'en_subsanacion' => 'Ver visita', 'visita_aprobada' => 'Ver visita'];
        $this->actingAs($this->supervisor());

        foreach ($casos as $estado => $texto) {
            $nivel = $this->nivelEnRevision($estado);
            $this->nombrar($nivel, 'Colegio '.$estado);

            Livewire::test(Expedientes::class)->set('estado', $estado)
                ->assertSee($texto)
                ->assertSeeHtml('href="'.route('sedeq.visita', ['escuelaNivel' => $nivel->id]).'"');
        }
    }

    public function test_los_estados_sin_visita_no_llevan_enlace_de_visita(): void
    {
        (new PasosCapturaSeeder)->run();
        $this->nombrar($this->nivelEnRevision('en_revision'), 'Colegio En Revision');
        $this->actingAs($this->supervisor());

        Livewire::test(Expedientes::class)->set('estado', 'en_revision')
            ->assertSee('Colegio En Revision')->assertDontSee('Programar visita')->assertDontSee('Ver visita')->assertDontSee('Registrar acta');
    }

    public function test_respuesta_recibida_solo_si_es_mas_reciente_que_el_ultimo_cambio(): void
    {
        (new PasosCapturaSeeder)->run();
        Storage::fake('documentos');
        $conRespuesta = $this->nivelEnRevision('en_subsanacion');
        $this->nombrar($conRespuesta, 'Colegio Con Respuesta');
        $visita = $this->visitaDe($conRespuesta, 1, 'realizada', 'con_observaciones');
        DB::table('historial_estados_expediente')->insert(['escuela_nivel_id' => $conRespuesta->id, 'estado_id' => $conRespuesta->estado_id,
            'comentario' => 'Acta', 'usuario_sedeq_id' => $this->supervisor()->id, 'fecha' => now()->subHours(2)]);
        $this->respuestaDe($visita, 'a.pdf', $this->archivoEnDisco(), now()->subHour());

        $vieja = $this->nivelEnRevision('en_subsanacion');
        $this->nombrar($vieja, 'Colegio Sin Respuesta Nueva');
        $v2 = $this->visitaDe($vieja, 1, 'realizada', 'con_observaciones');
        $this->respuestaDe($v2, 'b.pdf', $this->archivoEnDisco(), now()->subHours(3));
        DB::table('historial_estados_expediente')->insert(['escuela_nivel_id' => $vieja->id, 'estado_id' => $vieja->estado_id,
            'comentario' => 'Acta', 'usuario_sedeq_id' => $this->supervisor()->id, 'fecha' => now()->subHour()]);

        $filas = collect(app(ListarExpedientes::class)->ejecutar($this->supervisor()->id, 'en_subsanacion'))->keyBy('escuelaNivelId');
        $this->assertTrue($filas[$conRespuesta->id]->respuestaPendiente);
        $this->assertFalse($filas[$vieja->id]->respuestaPendiente);
        $this->assertSame('en_subsanacion', $filas[$vieja->id]->estado);

        $this->actingAs($this->supervisor());
        $html = Livewire::test(Expedientes::class)->set('estado', 'en_subsanacion')->assertSee('Respuesta recibida')->html();
        $this->assertSame(1, substr_count($html, 'Respuesta recibida'));
    }

    public function test_el_numero_de_consultas_no_crece_con_las_escuelas(): void
    {
        (new PasosCapturaSeeder)->run();
        $sup = $this->supervisor();
        $contar = function () use ($sup): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            app(ListarExpedientes::class)->ejecutar($sup->id, 'en_subsanacion');
            $n = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $n;
        };
        $this->nivelEnRevision('en_subsanacion');
        $una = $contar();
        $this->nivelEnRevision('en_subsanacion');
        $this->nivelEnRevision('en_subsanacion');

        $this->assertSame($una, $contar());
    }
}
