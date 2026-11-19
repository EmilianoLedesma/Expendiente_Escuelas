<?php

namespace Tests\Feature\Policies;

use App\Models\Solicitante;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ConTramitesEnRevision;
use Tests\TestCase;

class VisitaPolicyTest extends TestCase
{
    use ConTramitesEnRevision;
    use RefreshDatabase;

    public function test_supervisor_no_ve_un_nivel_en_captura(): void
    {
        $supervisor = $this->supervisor();

        $this->assertFalse($supervisor->can('verVisita', $this->nivelEnRevision('en_captura')));
        foreach (['aprobado', 'visita_programada', 'en_subsanacion', 'visita_aprobada'] as $estado) {
            $this->assertTrue($supervisor->can('verVisita', $this->nivelEnRevision($estado)), $estado);
        }
    }

    public function test_dueno_y_responsable_siguen_viendo_su_nivel_en_captura(): void
    {
        $dueno = Solicitante::factory()->create();
        $nivel = $this->nivelDe($dueno, 'primaria', 'en_captura');

        $this->assertTrue($dueno->user->can('verVisita', $nivel));
        $this->assertTrue($this->responsableDe($nivel)->can('verVisita', $nivel));
    }

    public function test_matriz_ver_visita_y_responder_visita(): void
    {
        $dueno = Solicitante::factory()->create();
        $nivelA = $this->nivelDe($dueno, 'primaria', 'en_subsanacion');
        $nivelB = $this->nivelEn($nivelA->escuela, 'secundaria', 'en_subsanacion');
        $respA = $this->responsableDe($nivelA);
        $ajeno = Solicitante::factory()->create()->user;
        $supervisor = $this->supervisor();

        $this->assertTrue($dueno->user->can('verVisita', $nivelA));
        $this->assertTrue($dueno->user->can('responderVisita', $nivelA));
        $this->assertTrue($supervisor->can('verVisita', $nivelA));
        $this->assertFalse($supervisor->can('responderVisita', $nivelA));
        $this->assertTrue($respA->can('verVisita', $nivelA));
        $this->assertFalse($respA->can('responderVisita', $nivelA));
        $this->assertFalse($respA->can('verVisita', $nivelB));
        $this->assertFalse($ajeno->can('verVisita', $nivelA));
        $this->assertFalse($ajeno->can('responderVisita', $nivelA));
    }
}
