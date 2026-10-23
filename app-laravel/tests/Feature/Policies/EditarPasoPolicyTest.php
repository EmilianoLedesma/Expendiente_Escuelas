<?php

namespace Tests\Feature\Policies;

use App\Models\Solicitante;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ConTramiteDevuelto;
use Tests\TestCase;

/** Devolución: `editarPaso` abre solo los pasos reabiertos; con todo en captura equivale a `update`. */
class EditarPasoPolicyTest extends TestCase
{
    use ConTramiteDevuelto;
    use RefreshDatabase;

    public function test_responsable_de_nivel_corrige_su_paso_pero_no_uno_general(): void
    {
        $dueno = Solicitante::factory()->create();
        $nivel = $this->nivelDe($dueno);
        $responsable = $this->responsableDe($nivel);
        $this->devolverTramite($nivel->escuela, [$nivel->id => ['matricula' => 'x', 'plantel' => 'x', 'inmueble' => 'x']]);
        $nivel = $nivel->fresh();

        $this->assertTrue($responsable->can('editarPaso', [$nivel, 'matricula']));
        $this->assertFalse($responsable->can('editarPaso', [$nivel, 'plantel']), 'general: solo dueño');
        $this->assertFalse($responsable->can('editarPaso', [$nivel, 'inmueble']), 'ámbito plantel: solo dueño');
        $this->assertFalse($responsable->can('editarPaso', [$nivel, 'plan_estudios']), 'no reabierto');
        $this->assertTrue($dueno->user->can('editarPaso', [$nivel, 'inmueble']));
        $this->assertTrue($dueno->user->can('editarPaso', [$nivel->escuela, 'plantel']));
        $this->assertTrue($dueno->user->can('editarPaso', [$nivel->escuela, 'responsable', 'plantel']), 'basta un paso reabierto');
        $this->assertFalse($responsable->can('editarPaso', [$nivel->escuela, 'plantel']));
    }

    public function test_ronda_abierta_da_403(): void
    {
        $dueno = Solicitante::factory()->create();
        $nivel = $this->nivelDe($dueno);
        $this->nivelEn($nivel->escuela, 'secundaria', 'en_revision');
        $this->devolverTramite($nivel->escuela, [$nivel->id => ['matricula' => 'x']]);

        $this->assertFalse($dueno->user->can('editarPaso', [$nivel->fresh(), 'matricula']));
        $this->assertFalse($dueno->user->can('editarPaso', [$nivel->escuela, 'matricula']));
    }

    public function test_en_captura_equivale_a_update(): void
    {
        $dueno = Solicitante::factory()->create();
        $nivel = $this->nivelDe($dueno);
        $responsable = $this->responsableDe($nivel);

        $this->assertTrue($responsable->can('editarPaso', [$nivel, 'matricula']));
        $this->assertTrue($dueno->user->can('editarPaso', [$nivel->escuela, 'plantel']));
        $this->assertFalse(Solicitante::factory()->create()->user->can('editarPaso', [$nivel, 'matricula']));
    }
}
