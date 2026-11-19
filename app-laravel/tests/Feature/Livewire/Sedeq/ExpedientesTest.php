<?php

namespace Tests\Feature\Livewire\Sedeq;

use App\Livewire\Sedeq\Expedientes;
use App\Models\EscuelaNivel;
use App\Models\Solicitante;
use Database\Seeders\PasosCapturaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Concerns\ConTramitesEnRevision;
use Tests\TestCase;

/** Expediente del supervisor (fase 0): lista de niveles con la revisión documental aprobada. */
class ExpedientesTest extends TestCase
{
    use ConTramitesEnRevision;
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
}
