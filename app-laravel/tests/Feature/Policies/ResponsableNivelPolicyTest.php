<?php

namespace Tests\Feature\Policies;

use App\Models\Solicitante;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ConNivelParaResponsables;
use Tests\TestCase;

class ResponsableNivelPolicyTest extends TestCase
{
    use ConNivelParaResponsables;
    use RefreshDatabase;

    public function test_matriz_de_habilidades_sobre_el_nivel(): void
    {
        $dueno = Solicitante::factory()->create();
        $nivelA = $this->nivelDe($dueno, 'primaria');
        $nivelB = $this->nivelEn($nivelA->escuela, 'secundaria');
        $responsableA = $this->responsableDe($nivelA);
        $ajeno = Solicitante::factory()->create()->user;

        $esperado = [
            'view' => ['dueno' => true, 'responsableA' => true, 'ajeno' => false],
            'update' => ['dueno' => true, 'responsableA' => true, 'ajeno' => false],
            'updateInmueble' => ['dueno' => true, 'responsableA' => false, 'ajeno' => false],
            'gestionarResponsables' => ['dueno' => true, 'responsableA' => false, 'ajeno' => false],
        ];
        $actores = ['dueno' => $dueno->user, 'responsableA' => $responsableA, 'ajeno' => $ajeno];

        foreach ($esperado as $habilidad => $porActor) {
            foreach ($porActor as $actor => $permitido) {
                $this->assertSame($permitido, $actores[$actor]->can($habilidad, $nivelA), "{$actor} {$habilidad} nivel A");
            }
            $this->assertFalse($responsableA->can($habilidad, $nivelB), "responsableA {$habilidad} nivel B");
        }
    }

    public function test_verresumen_abre_al_responsable_pero_view_update_delete_siguen_siendo_del_dueno(): void
    {
        $dueno = Solicitante::factory()->create();
        $nivel = $this->nivelDe($dueno);
        $responsable = $this->responsableDe($nivel);
        $escuela = $nivel->escuela;

        $this->assertTrue($dueno->user->can('verResumen', $escuela));
        $this->assertTrue($responsable->can('verResumen', $escuela));
        $this->assertFalse(Solicitante::factory()->create()->user->can('verResumen', $escuela));
        $this->assertFalse(User::factory()->create()->can('verResumen', $escuela));
        $responsableAjeno = $this->responsableDe($this->nivelDe(Solicitante::factory()->create()));
        $this->assertFalse($responsableAjeno->can('verResumen', $escuela), 'responsable de otra escuela');

        foreach (['view', 'update', 'delete'] as $habilidad) {
            $this->assertFalse($responsable->can($habilidad, $escuela), "responsable {$habilidad} escuela");
        }
    }

    public function test_el_responsable_recibe_403_en_las_rutas_de_la_escuela_y_del_otro_nivel(): void
    {
        $dueno = Solicitante::factory()->create();
        $nivelA = $this->nivelDe($dueno, 'primaria');
        $nivelB = $this->nivelEn($nivelA->escuela, 'secundaria');
        $responsable = $this->responsableDe($nivelA);
        $escuela = $nivelA->escuela;

        $this->actingAs($responsable);

        $this->get(route('tramite.paso2', ['escuela' => $escuela->id]))->assertForbidden();
        $this->get(route('tramite.paso2-documentos', ['escuela' => $escuela->id]))->assertForbidden();
        $this->get(route('tramite.validacion', ['escuela' => $escuela->id]))->assertForbidden();
        $this->delete(route('tramite.eliminar', ['escuela' => $escuela->id]))->assertForbidden();
        $this->get(route('tramite.paso2-nivel-documentos', ['escuelaNivel' => $nivelB->id]))->assertForbidden();
        $this->get(route('tramite.paso3-matricula', ['escuelaNivel' => $nivelB->id]))->assertForbidden();

        $this->assertNotSame(403, $this->get(route('tramite.resumen', ['escuela' => $escuela->id]))->getStatusCode());
        $this->assertNotSame(403, $this->get(route('tramite.paso2-nivel-documentos', ['escuelaNivel' => $nivelA->id]))->getStatusCode());
    }
}
