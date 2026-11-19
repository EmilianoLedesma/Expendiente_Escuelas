<?php

namespace Tests\Feature\Tramite;

use App\Models\Solicitante;
use Database\Seeders\PasosCapturaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ConTramiteDevuelto;
use Tests\TestCase;

/** Lo que el solicitante ve cuando la revisión documental de su trámite ya se aprobó (Expediente, fase 0). */
class TramiteAprobadoVistaTest extends TestCase
{
    use ConTramiteDevuelto;
    use RefreshDatabase;

    public function test_mis_tramites_marca_el_tramite_aprobado_y_no_lo_llama_en_revision(): void
    {
        (new PasosCapturaSeeder)->run();
        $dueno = Solicitante::factory()->create();
        $this->nivelDe($dueno, 'primaria', 'aprobado');

        $this->actingAs($dueno->user)->get(route('tramite.index'))
            ->assertOk()
            ->assertSee('Revisión documental aprobada')
            ->assertDontSee('En revisión');
    }

    public function test_el_hub_explica_que_aprobado_no_es_incorporado_y_cual_es_el_siguiente_paso(): void
    {
        (new PasosCapturaSeeder)->run();
        $dueno = Solicitante::factory()->create();
        $nivel = $this->nivelDe($dueno, 'primaria', 'aprobado');

        $this->actingAs($dueno->user)->get(route('tramite.resumen', ['escuela' => $nivel->escuela_id]))
            ->assertOk()
            ->assertSee('Revisión documental aprobada')
            ->assertSee('visita de inspección')
            ->assertSee('Todavía no cuentas con el acuerdo de incorporación')
            ->assertDontSee('— en revisión');
    }

    public function test_con_un_nivel_aun_en_revision_el_hub_nombra_el_aprobado_y_sigue_en_revision(): void
    {
        (new PasosCapturaSeeder)->run();
        $dueno = Solicitante::factory()->create();
        $primaria = $this->nivelDe($dueno, 'primaria', 'aprobado');
        $this->nivelEn($primaria->escuela, 'secundaria', 'en_revision');

        $this->actingAs($dueno->user)->get(route('tramite.resumen', ['escuela' => $primaria->escuela_id]))
            ->assertOk()
            ->assertSee('— en revisión')
            ->assertSee('Revisión documental aprobada en: Primaria');
    }

    public function test_el_hub_y_mis_tramites_enlazan_a_la_visita_solo_en_los_estados_de_visita(): void
    {
        (new PasosCapturaSeeder)->run();
        $dueno = Solicitante::factory()->create();
        $programada = $this->nivelDe($dueno, 'primaria', 'visita_programada');
        $subsanacion = $this->nivelEn($programada->escuela, 'secundaria', 'en_subsanacion');
        $aprobada = $this->nivelEn($programada->escuela, 'preescolar', 'visita_aprobada');
        $documental = $this->nivelEn($programada->escuela, 'inicial', 'aprobado');

        foreach ([route('tramite.resumen', ['escuela' => $programada->escuela_id]), route('tramite.index')] as $url) {
            $this->actingAs($dueno->user)->get($url)->assertOk()
                ->assertSee(route('tramite.visita', ['escuelaNivel' => $programada->id]), false)
                ->assertSee(route('tramite.visita', ['escuelaNivel' => $subsanacion->id]), false)
                ->assertSee(route('tramite.visita', ['escuelaNivel' => $aprobada->id]), false)
                ->assertDontSee(route('tramite.visita', ['escuelaNivel' => $documental->id]), false);
        }
        $this->actingAs($dueno->user)->get(route('tramite.resumen', ['escuela' => $programada->escuela_id]))
            ->assertSee('Responder observaciones');
    }

    public function test_el_responsable_ve_el_enlace_de_visita_solo_de_su_nivel(): void
    {
        (new PasosCapturaSeeder)->run();
        $dueno = Solicitante::factory()->create();
        $propio = $this->nivelDe($dueno, 'primaria', 'visita_programada');
        $ajeno = $this->nivelEn($propio->escuela, 'secundaria', 'visita_programada');
        $responsable = $this->responsableDe($propio);

        $this->actingAs($responsable)->get(route('tramite.resumen', ['escuela' => $propio->escuela_id]))->assertOk()
            ->assertSee(route('tramite.visita', ['escuelaNivel' => $propio->id]), false)
            ->assertDontSee(route('tramite.visita', ['escuelaNivel' => $ajeno->id]), false);
    }

    public function test_el_aviso_de_que_aun_no_hay_acuerdo_aparece_en_mis_tramites_y_en_el_hub(): void
    {
        (new PasosCapturaSeeder)->run();
        $dueno = Solicitante::factory()->create();
        $nivel = $this->nivelDe($dueno, 'primaria', 'en_revision');
        $aviso = 'no puedes inscribir alumnos, cobrar cuotas ni anunciar «RVOE en trámite»';

        $this->actingAs($dueno->user)->get(route('tramite.index'))->assertOk()->assertSee($aviso);
        $this->actingAs($dueno->user)->get(route('tramite.resumen', ['escuela' => $nivel->escuela_id]))->assertOk()->assertSee($aviso);
    }
}
