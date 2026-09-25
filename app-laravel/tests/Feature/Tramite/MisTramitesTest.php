<?php

namespace Tests\Feature\Tramite;

use App\Models\Escuela;
use App\Models\Plantel;
use App\Models\Solicitante;
use App\Models\User;
use Database\Seeders\CatalogoMinimoSeeder;
use Database\Seeders\PasosCapturaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MisTramitesTest extends TestCase
{
    use RefreshDatabase;

    private function escuelaDe(Solicitante $solicitante, string $calle): Escuela
    {
        (new CatalogoMinimoSeeder)->run();
        (new PasosCapturaSeeder)->run();
        $plantel = Plantel::create(['calle' => $calle, 'colonia' => 'Centro', 'municipio' => 'Querétaro', 'codigo_postal' => '76000']);

        return Escuela::create(['plantel_id' => $plantel->id, 'solicitante_id' => $solicitante->id]);
    }

    public function test_lista_solo_las_escuelas_del_solicitante(): void
    {
        $solicitante = Solicitante::factory()->create();
        $mia = $this->escuelaDe($solicitante, 'Calle Propia');
        $this->escuelaDe(Solicitante::factory()->create(), 'Calle Ajena');

        $this->actingAs($solicitante->user)
            ->get(route('tramite.index'))
            ->assertOk()
            ->assertSee('Calle Propia')
            ->assertDontSee('Calle Ajena')
            ->assertSee('href="'.route('tramite.resumen', ['escuela' => $mia->id]).'"', false)
            ->assertSee('Ver trámite');
    }

    public function test_estado_vacio_explica_el_tramite_y_ofrece_iniciar(): void
    {
        $this->actingAs(Solicitante::factory()->create()->user)
            ->get(route('tramite.index'))
            ->assertOk()
            ->assertSee('Mis trámites')
            ->assertSee('Tu avance se guarda al terminar cada sección')
            ->assertSee('Iniciar nuevo trámite')
            ->assertSee('href="'.route('tramite.preregistro').'"', false);
    }

    public function test_ordena_del_mas_reciente_al_mas_antiguo(): void
    {
        $solicitante = Solicitante::factory()->create();
        $vieja = $this->escuelaDe($solicitante, 'Calle Vieja');
        $vieja->forceFill(['created_at' => now()->subDays(3)])->save();
        $this->escuelaDe($solicitante, 'Calle Nueva');

        $html = $this->actingAs($solicitante->user)->get(route('tramite.index'))->getContent();

        $this->assertLessThan(strpos($html, 'Calle Vieja'), strpos($html, 'Calle Nueva'));
    }

    public function test_usuario_sin_solicitante_ve_el_estado_vacio(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('tramite.index'))
            ->assertOk()
            ->assertSee('Iniciar nuevo trámite');
    }

    public function test_una_escuela_sin_niveles_esta_en_captura(): void
    {
        $solicitante = Solicitante::factory()->create();
        $this->escuelaDe($solicitante, 'Calle 1');

        $this->actingAs($solicitante->user)
            ->get(route('tramite.index'))
            ->assertSee('En captura')
            ->assertSee('Sin niveles seleccionados')
            ->assertDontSee('Captura inicial completa');
    }

    public function test_el_encabezado_enlaza_a_mis_tramites(): void
    {
        $this->actingAs(Solicitante::factory()->create()->user)
            ->get(route('tramite.preregistro'))
            ->assertSee('href="'.route('tramite.index').'"', false);
    }
}
