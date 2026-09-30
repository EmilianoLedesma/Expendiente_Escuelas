<?php

namespace Tests\Feature\Livewire\Tramite;

use App\Livewire\Tramite\Paso3\PlanEstudios;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\PreparaPaso3;
use Tests\TestCase;

class Paso3PlanEstudiosTest extends TestCase
{
    use PreparaPaso3;
    use RefreshDatabase;

    public function test_sin_completar_mobiliario_redirige_al_paso_pendiente(): void
    {
        $escuelaNivel = $this->nivelListoPara('inicial', 'mobiliario');
        $this->actingAs($escuelaNivel->escuela->solicitante->user);

        Livewire::test(PlanEstudios::class, ['escuelaNivel' => $escuelaNivel])
            ->assertRedirect(route('tramite.paso3-mobiliario', ['escuelaNivel' => $escuelaNivel->id]));
    }

    public function test_guarda_y_continua_a_la_plantilla(): void
    {
        $escuelaNivel = $this->nivelListoPara('primaria', 'plan_estudios');
        $this->actingAs($escuelaNivel->escuela->solicitante->user);

        Livewire::test(PlanEstudios::class, ['escuelaNivel' => $escuelaNivel])
            ->set('modalidad', 'escolarizada')
            ->set('planEstudiosReferencia', 'Plan de estudio 2022 (SEP)')
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertRedirect(route('tramite.paso3-plantilla', ['escuelaNivel' => $escuelaNivel->id]));

        $this->assertSame('Plan de estudio 2022 (SEP)', $escuelaNivel->refresh()->plan_estudios_referencia);
    }

    public function test_muestra_los_errores_del_caso_de_uso(): void
    {
        $escuelaNivel = $this->nivelListoPara('primaria', 'plan_estudios');
        $this->actingAs($escuelaNivel->escuela->solicitante->user);

        Livewire::test(PlanEstudios::class, ['escuelaNivel' => $escuelaNivel])
            ->set('modalidad', 'virtual')
            ->call('guardar')
            ->assertHasErrors(['plataformaEducativaTipo'])
            ->assertNoRedirect();
    }

    public function test_al_volver_muestra_lo_capturado(): void
    {
        $escuelaNivel = $this->nivelListoPara('primaria', 'plan_estudios');
        $escuelaNivel->update(['modalidad' => 'mixta', 'plataforma_educativa_tipo' => 'propia']);
        $this->actingAs($escuelaNivel->escuela->solicitante->user);

        Livewire::test(PlanEstudios::class, ['escuelaNivel' => $escuelaNivel])
            ->assertSet('modalidad', 'mixta')
            ->assertSet('plataformaEducativaTipo', 'propia');
    }

    public function test_la_pagina_responde_por_http(): void
    {
        $escuelaNivel = $this->nivelListoPara('primaria', 'plan_estudios');
        $this->actingAs($escuelaNivel->escuela->solicitante->user);

        $this->get(route('tramite.paso3-plan-estudios', ['escuelaNivel' => $escuelaNivel->id]))
            ->assertOk()
            ->assertSee('Plan de estudios y modalidad')
            ->assertSee('Escolarizada')
            // Captured in Paso 2.4 (WS-5b); shown here for reference, not editable.
            ->assertSee('Turno: Matutino')
            ->assertSee('Tipo de alumnado: Mixto')
            ->assertDontSee('name="turno"', false);
    }
}
