<?php

namespace Tests\Feature\Tramite;

use App\Application\Tramite\ResumenTramite;
use App\Models\Solicitante;
use Database\Seeders\PasosCapturaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ConNivelParaResponsables;
use Tests\TestCase;

class ResumenResponsableTest extends TestCase
{
    use ConNivelParaResponsables;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        (new PasosCapturaSeeder)->run();
    }

    public function test_el_dto_del_responsable_se_acota_a_sus_niveles(): void
    {
        $dueno = Solicitante::factory()->create();
        $nivelA = $this->nivelDe($dueno, 'primaria');
        $this->nivelEn($nivelA->escuela, 'secundaria');
        $responsable = $this->responsableDe($nivelA);

        $propio = app(ResumenTramite::class)->paraEscuela($nivelA->escuela_id, $dueno->user->id);
        $acotado = app(ResumenTramite::class)->paraEscuela($nivelA->escuela_id, $responsable->id);
        $sinUsuario = app(ResumenTramite::class)->paraEscuela($nivelA->escuela_id);

        $this->assertCount(2, $propio->niveles);
        $this->assertCount(2, $sinUsuario->niveles);
        $this->assertCount(4, $propio->generales);
        $this->assertCount(1, $acotado->niveles);
        $this->assertSame($nivelA->id, $acotado->niveles[0]->escuelaNivelId);
        $this->assertSame([], $acotado->generales);
        $this->assertFalse($acotado->completo);
        $this->assertFalse($acotado->puedeEliminar);
        $this->assertTrue($propio->puedeEliminar);
    }

    public function test_la_pagina_del_responsable_muestra_solo_su_nivel_sin_informacion_general_ni_eliminar(): void
    {
        $dueno = Solicitante::factory()->create();
        $nivelA = $this->nivelDe($dueno, 'primaria');
        $this->nivelEn($nivelA->escuela, 'secundaria');
        $responsable = $this->responsableDe($nivelA);

        $this->actingAs($responsable)
            ->get(route('tramite.resumen', ['escuela' => $nivelA->escuela_id]))
            ->assertOk()
            ->assertSee('Primaria')
            ->assertDontSee('Secundaria')
            ->assertDontSee('Información general')
            ->assertDontSee('Datos generales')
            ->assertDontSee('Eliminar trámite')
            ->assertDontSee(route('tramite.paso2', ['escuela' => $nivelA->escuela_id]), false)
            ->assertDontSee(route('tramite.validacion', ['escuela' => $nivelA->escuela_id]), false);
    }

    public function test_el_dueno_sigue_viendo_todo(): void
    {
        $dueno = Solicitante::factory()->create();
        $nivel = $this->nivelDe($dueno);

        $this->actingAs($dueno->user)
            ->get(route('tramite.resumen', ['escuela' => $nivel->escuela_id]))
            ->assertOk()
            ->assertSee('Información general')
            ->assertSee('Eliminar');
    }
}
