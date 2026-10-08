<?php

namespace Tests\Feature\Tramite;

use App\Models\Solicitante;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ConNivelParaResponsables;
use Tests\TestCase;

class MisNivelesResponsableTest extends TestCase
{
    use ConNivelParaResponsables;
    use RefreshDatabase;

    public function test_el_responsable_ve_sus_niveles_con_enlace_al_resumen(): void
    {
        $nivel = $this->nivelDe(Solicitante::factory()->create(), 'primaria');
        $this->nivelEn($nivel->escuela, 'secundaria');
        $responsable = $this->responsableDe($nivel);

        $this->actingAs($responsable)
            ->get(route('tramite.index'))
            ->assertOk()
            ->assertSee('Mis niveles asignados')
            ->assertSee('Primaria')
            ->assertDontSee('Secundaria')
            ->assertDontSee('Iniciar nuevo trámite')
            ->assertSee('href="'.route('tramite.resumen', ['escuela' => $nivel->escuela_id]).'"', false);
    }

    public function test_el_responsable_no_entra_a_crear_tramites(): void
    {
        $responsable = $this->responsableDe($this->nivelDe(Solicitante::factory()->create()));

        $this->actingAs($responsable)
            ->get(route('tramite.preregistro'))
            ->assertRedirect(route('tramite.index'));
    }

    public function test_usuario_sin_solicitante_ni_niveles_ve_el_estado_vacio_sin_iniciar_tramite(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('tramite.index'))
            ->assertOk()
            ->assertSee('Este sistema te guía')
            ->assertDontSee('Iniciar nuevo trámite');
    }

    public function test_el_solicitante_sigue_viendo_mis_tramites_y_preregistro(): void
    {
        $solicitante = Solicitante::factory()->create();

        $this->actingAs($solicitante->user)->get(route('tramite.index'))->assertOk()->assertSee('Iniciar nuevo trámite');
        $this->actingAs($solicitante->user)->get(route('tramite.preregistro'))->assertOk();
    }
}
