<?php

namespace Tests\Feature\Livewire\Sedeq;

use App\Livewire\Sedeq\VisitasRealizadas;
use App\Models\EscuelaNivel;
use App\Models\User;
use Database\Seeders\PasosCapturaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Concerns\ConTramitesEnRevision;
use Tests\Concerns\ConVisitas;
use Tests\Concerns\GeneraPdfs;
use Tests\TestCase;

/** Página "Visitas realizadas" del supervisor: registro de solo lectura con filtros, enlaces a la visita y al acta. */
class VisitasRealizadasTest extends TestCase
{
    use ConTramitesEnRevision;
    use ConVisitas;
    use GeneraPdfs;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        (new PasosCapturaSeeder)->run();
    }

    /** @return array{0: EscuelaNivel, 1: int} */
    private function realizada(string $nombre, string $fecha = '2026-02-05', string $resultado = 'cumple', bool $acta = false): array
    {
        $nivel = $this->nivelEnRevision($resultado === 'cumple' ? 'visita_aprobada' : 'en_subsanacion');
        DB::table('escuelas')->where('id', $nivel->escuela_id)->update(['nombre_aprobado' => $nombre]);
        $id = $this->visitaDe($nivel, 1, 'realizada', $resultado, ['realizada_el' => $fecha]);
        if ($acta) {
            DB::table('visitas_inspeccion')->where('id', $id)->update(['acta_path' => 'visitas/1/acta.pdf', 'acta_nombre' => 'acta.pdf']);
        }

        return [$nivel, $id];
    }

    public function test_el_supervisor_ve_la_tarjeta_con_sus_datos_y_enlaces(): void
    {
        [$nivel, $id] = $this->realizada('Colegio Registro', '2026-02-05', 'con_observaciones', true);
        DB::table('visitas_inspeccion')->where('id', $id)->update(['subsanar_hasta' => '2026-02-20']);
        DB::table('observaciones_visita')->insert([['visita_id' => $id, 'orden' => 1, 'texto' => 'Uno'], ['visita_id' => $id, 'orden' => 2, 'texto' => 'Dos']]);

        $this->actingAs($this->supervisor())->get(route('sedeq.visitas'))
            ->assertOk()
            ->assertSee('Colegio Registro')
            ->assertSee('Visita 1 · 05/02/2026')
            ->assertSee('Inspector Uno')
            ->assertSee('Plazo aprox. de corrección: 20/02/2026')
            ->assertSee('2 observaciones · 0 respuestas')
            ->assertSee('En subsanación')
            ->assertSee('1 visita')
            ->assertSee('href="'.route('sedeq.visita', ['escuelaNivel' => $nivel->id]).'"', false)
            ->assertSee('href="'.route('visita.acta', ['escuelaNivel' => $nivel->id, 'visita' => $id]).'"', false);
    }

    public function test_sin_acta_no_hay_enlace_de_descarga_y_cumple_no_muestra_plazo(): void
    {
        $this->realizada('Colegio Sin Acta');

        $this->actingAs($this->supervisor())->get(route('sedeq.visitas'))
            ->assertOk()->assertSee('Colegio Sin Acta')
            ->assertDontSee('Descargar acta')->assertDontSee('Plazo aprox.');
    }

    public function test_orden_y_filtros_de_la_pagina(): void
    {
        $this->realizada('Colegio Antiguo', '2026-01-01');
        $this->realizada('Instituto Reciente', '2026-03-01', 'con_observaciones');
        $this->actingAs($this->supervisor());

        $html = Livewire::test(VisitasRealizadas::class)->html();
        $this->assertLessThan(strpos($html, 'Colegio Antiguo'), strpos($html, 'Instituto Reciente'));

        Livewire::test(VisitasRealizadas::class)
            ->set('buscar', 'antiguo')->assertSee('Colegio Antiguo')->assertDontSee('Instituto Reciente')
            ->set('buscar', '')->set('resultado', 'con_observaciones')->assertSee('Instituto Reciente')->assertDontSee('Colegio Antiguo')
            ->set('resultado', '')->set('desde', '2026-02-01')->assertSee('Instituto Reciente')->assertDontSee('Colegio Antiguo')
            ->set('desde', '')->set('hasta', '2026-01-01')->assertSee('Colegio Antiguo')->assertDontSee('Instituto Reciente')
            ->set('hasta', '')->assertSee('Colegio Antiguo')->assertSee('Instituto Reciente');
    }

    public function test_valores_invalidos_vuelven_al_predeterminado_sin_romper(): void
    {
        $this->realizada('Colegio Visible');
        $this->actingAs($this->supervisor());

        Livewire::test(VisitasRealizadas::class)
            ->set('resultado', 'rechazado')->assertOk()->assertSet('resultado', '')->assertSee('Colegio Visible')
            ->set('desde', 'basura')->assertOk()->assertSet('desde', '')->assertSee('Colegio Visible')
            ->set('hasta', '2026-99-99')->assertOk()->assertSet('hasta', '')
            ->set('desde', '2026-03-01')->set('hasta', '2026-01-01')->assertOk()->assertSet('desde', '')->assertSet('hasta', '')->assertSee('Colegio Visible');
    }

    public function test_estados_vacios(): void
    {
        $this->actingAs($this->supervisor());

        Livewire::test(VisitasRealizadas::class)->assertSee('Todavía no hay visitas realizadas.');

        $this->realizada('Colegio Existe');
        Livewire::test(VisitasRealizadas::class)->set('buscar', 'zzz')
            ->assertDontSee('Todavía no hay visitas realizadas.')->assertSee('Ninguna visita coincide con los filtros.');
    }

    public function test_el_nombre_hostil_se_escapa(): void
    {
        $this->realizada('<script>alert(1)</script>');
        $this->actingAs($this->supervisor());

        Livewire::test(VisitasRealizadas::class)->assertDontSeeHtml('<script>alert(1)</script>')->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
    }

    public function test_una_escuela_archivada_no_aparece(): void
    {
        [$nivel] = $this->realizada('Colegio Archivado');
        DB::table('escuelas')->where('id', $nivel->escuela_id)->update(['archivado_at' => now()]);
        $this->actingAs($this->supervisor());

        Livewire::test(VisitasRealizadas::class)->assertDontSee('Colegio Archivado')->assertSee('Todavía no hay visitas realizadas.');
    }

    public function test_revisor_y_solicitante_reciben_403_y_el_invitado_va_al_login(): void
    {
        $this->actingAs($this->revisor())->get(route('sedeq.visitas'))->assertForbidden();
        $this->actingAs(User::factory()->create())->get(route('sedeq.visitas'))->assertForbidden();
        auth()->logout();
        $this->get(route('sedeq.visitas'))->assertRedirect(route('login'));
    }

    public function test_la_barra_enlaza_visitas_solo_al_supervisor(): void
    {
        $enlace = 'href="'.route('sedeq.visitas').'"';

        $this->actingAs($this->supervisor())->get(route('sedeq.tablero'))->assertSee($enlace, false);
        $this->actingAs($this->revisor())->get(route('sedeq.tablero'))->assertDontSee($enlace, false);
    }

    public function test_el_numero_de_consultas_con_cinco_visitas_es_el_de_una(): void
    {
        $this->actingAs($this->supervisor());
        $contar = function (): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            Livewire::test(VisitasRealizadas::class);
            $n = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $n;
        };
        $this->realizada('Colegio 1');
        $contar(); // calienta cachés de una sola vez (roles, vistas) para medir solo lo que crece con las filas
        $una = $contar();
        foreach (range(2, 5) as $i) {
            $this->realizada("Colegio $i", acta: true);
        }

        $this->assertSame($una, $contar());
    }
}
