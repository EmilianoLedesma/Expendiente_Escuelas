<?php

namespace Tests\Feature\Tramite;

use App\Models\Escuela;
use App\Models\EscuelaNivel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CapturaExpedienteConsistente;
use Tests\Concerns\ConTramiteDevuelto;
use Tests\TestCase;

/** HUD devolución, Task 10: POST tramite.reenviar y el botón «Reenviar correcciones» del hub. */
class ReenviarCorreccionesRutaTest extends TestCase
{
    use CapturaExpedienteConsistente;
    use ConTramiteDevuelto;
    use RefreshDatabase;

    /** @return array{0: Escuela, 1: EscuelaNivel} */
    private function devuelto(): array
    {
        $escuela = $this->crearEscuelaConPlantel();
        $primaria = $this->completarTramite($escuela);
        $this->devolverTramite($escuela, [$primaria->id => ['plan_estudios' => 'Falta la modalidad']]);

        return [$escuela, $primaria];
    }

    private function estadoDe(EscuelaNivel $nivel): string
    {
        return (string) DB::table('estados_expediente')->where('id', $nivel->fresh()->estado_id)->value('clave');
    }

    private function formularioDeReenvio(Escuela $escuela): string
    {
        return '#<form method="POST" action="'.preg_quote(route('tramite.reenviar', ['escuela' => $escuela->id]), '#').'"[^>]*>\s*<input type="hidden" name="_token"#';
    }

    public function test_el_dueno_reenvia_y_vuelve_al_hub_con_aviso(): void
    {
        [$escuela, $primaria] = $this->devuelto();

        $this->actingAs($escuela->solicitante->user)->post(route('tramite.reenviar', ['escuela' => $escuela->id]))
            ->assertRedirect(route('tramite.resumen', ['escuela' => $escuela->id]))
            ->assertSessionHas('toast', fn (array $t) => $t['tipo'] === 'success' && $t['titulo'] === 'Correcciones reenviadas');

        $this->assertSame('en_revision', $this->estadoDe($primaria));
    }

    public function test_si_algo_falta_vuelve_al_hub_con_el_motivo(): void
    {
        [$escuela, $primaria] = $this->devuelto();
        DB::table('escuela_nivel_pasos')->where('escuela_nivel_id', $primaria->id)
            ->where('paso_captura_id', DB::table('pasos_captura')->where('clave', 'plan_estudios')->value('id'))
            ->update(['estado' => 'en_progreso']);

        $this->actingAs($escuela->solicitante->user)->post(route('tramite.reenviar', ['escuela' => $escuela->id]))
            ->assertRedirect(route('tramite.resumen', ['escuela' => $escuela->id]))
            ->assertSessionHas('toast', fn (array $t) => $t['tipo'] === 'error' && str_contains($t['descripcion'], 'Plan de estudios'));

        $this->assertSame('con_observaciones', $this->estadoDe($primaria));
    }

    public function test_quien_no_es_dueno_recibe_403(): void
    {
        [$escuela, $primaria] = $this->devuelto();

        $this->actingAs($this->responsableDe($primaria))->post(route('tramite.reenviar', ['escuela' => $escuela->id]))->assertForbidden();
        $this->actingAs($this->supervisor())->post(route('tramite.reenviar', ['escuela' => $escuela->id]))->assertForbidden();

        $this->assertSame('con_observaciones', $this->estadoDe($primaria));
    }

    public function test_el_hub_del_dueno_trae_el_formulario_con_csrf_solo_con_la_ronda_cerrada(): void
    {
        [$escuela, $primaria] = $this->devuelto();
        $url = route('tramite.resumen', ['escuela' => $escuela->id]);

        $this->actingAs($escuela->solicitante->user)->get($url)->assertOk()
            ->assertSee('Reenviar correcciones')
            ->assertSee('¿Reenviar las correcciones a SEDEQ?')
            ->assertSeeHtml('aria-labelledby="reenviar-correcciones-titulo"');
        $this->assertMatchesRegularExpression($this->formularioDeReenvio($escuela), $this->get($url)->getContent());

        // Ronda abierta (otro nivel aún en revisión): el hub se ve como enviado, sin botón.
        $this->nivelEn($escuela, 'secundaria', 'en_revision');
        $html = $this->actingAs($escuela->solicitante->user)->get($url)->assertOk()->assertDontSee('Reenviar correcciones')->getContent();
        $this->assertDoesNotMatchRegularExpression($this->formularioDeReenvio($escuela), $html);
    }

    public function test_el_hub_del_responsable_no_trae_el_boton(): void
    {
        [$escuela, $primaria] = $this->devuelto();

        $html = $this->actingAs($this->responsableDe($primaria))->get(route('tramite.resumen', ['escuela' => $escuela->id]))
            ->assertOk()->assertSee('Tu trámite tiene observaciones')->assertDontSee('Reenviar correcciones')->getContent();
        $this->assertDoesNotMatchRegularExpression($this->formularioDeReenvio($escuela), $html);
    }
}
