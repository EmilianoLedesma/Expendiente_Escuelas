<?php

namespace Tests\Feature\Tramite;

use App\Livewire\Tramite\Paso3\DatosInmueble;
use App\Livewire\Tramite\Paso3\InfraestructuraNivel;
use App\Livewire\Tramite\Paso3\PlanEstudios;
use App\Models\EscuelaNivel;
use Database\Seeders\TiposEspaciosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Concerns\CapturaExpedienteConsistente;
use Tests\Concerns\ConTramiteDevuelto;
use Tests\TestCase;

/**
 * Devolución de punta a punta: el dueño guarda de verdad el paso reabierto desde su página (Livewire) y vuelve al
 * hub, porque el paso siguiente puede no estar reabierto. Un paso aceptado sigue cerrado (403).
 */
class CorregirPasoReabiertoTest extends TestCase
{
    use CapturaExpedienteConsistente;
    use ConTramiteDevuelto;
    use RefreshDatabase;

    /** @return array{0: \App\Models\Escuela, 1: EscuelaNivel} */
    private function devuelto(string $paso): array
    {
        $escuela = $this->crearEscuelaConPlantel();
        $nivel = $this->completarTramite($escuela);
        (new TiposEspaciosSeeder)->run();
        $this->devolverTramite($escuela, [$nivel->id => [$paso => 'Corrige esto']]);
        $this->actingAs($escuela->solicitante->user);

        return [$escuela, $nivel];
    }

    public function test_inmueble_reabierto_sin_datos_se_guarda_y_vuelve_al_hub(): void
    {
        [$escuela, $nivel] = $this->devuelto('inmueble');

        Livewire::test(DatosInmueble::class, ['escuelaNivel' => $nivel])
            ->assertSet('soloLectura', false)
            ->set('metrosTotales', 500)->set('metrosConstruidos', 200)
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertRedirect(route('tramite.resumen', ['escuela' => $escuela->id]));

        $this->assertEquals(500, (float) DB::table('planteles')->where('id', $escuela->plantel_id)->value('metros_totales'));
    }

    public function test_inmueble_reabierto_con_datos_se_precarga_sin_saltar_adelante(): void
    {
        [$escuela, $nivel] = $this->devuelto('inmueble');
        DB::table('planteles')->where('id', $escuela->plantel_id)->update(['metros_totales' => 300, 'metros_construidos' => 100]);

        Livewire::test(DatosInmueble::class, ['escuelaNivel' => $nivel])
            ->assertNoRedirect()
            ->assertSet('soloLectura', false)
            ->assertSet('metrosTotales', '300.00')
            ->set('metrosTotales', 450)
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertRedirect(route('tramite.resumen', ['escuela' => $escuela->id]));

        $this->assertEquals(450, (float) DB::table('planteles')->where('id', $escuela->plantel_id)->value('metros_totales'));
    }

    public function test_inmueble_aceptado_no_se_guarda(): void
    {
        [, $nivel] = $this->devuelto('plan_estudios');

        Livewire::test(DatosInmueble::class, ['escuelaNivel' => $nivel])
            ->assertSet('soloLectura', true)
            ->set('metrosTotales', 500)->set('metrosConstruidos', 200)
            ->call('guardar')
            ->assertForbidden();
    }

    public function test_infraestructura_reabierta_edita_lo_capturado_y_agrega_lo_nuevo(): void
    {
        [$escuela, $nivel] = $this->devuelto('infraestructura');
        DB::table('sanitarios')->insert(['plantel_id' => $escuela->plantel_id, 'categoria' => 'alumnado_masculino', 'cantidad_retretes' => 4, 'superficie_m2' => 12]);

        Livewire::test(InfraestructuraNivel::class, ['escuelaNivel' => $nivel])
            ->assertSet('puedeEditar', true)
            ->set('edicionSanitarios.alumnado_masculino.cantidadRetretes', 6)
            ->call('guardarEdicion')
            ->assertHasNoErrors()
            ->assertRedirect(route('tramite.paso3-infraestructura', ['escuelaNivel' => $nivel->id]));
        $this->assertSame(6, (int) DB::table('sanitarios')->where('plantel_id', $escuela->plantel_id)->value('cantidad_retretes'));

        Livewire::test(InfraestructuraNivel::class, ['escuelaNivel' => $nivel])
            ->set('numeroAulas', 6)->set('superficieAulasM2', 240)
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertRedirect(route('tramite.resumen', ['escuela' => $escuela->id]));
        $this->assertSame(6, (int) DB::table('aulas_nivel')->where('escuela_nivel_id', $nivel->id)->value('numero_aulas'));
    }

    public function test_infraestructura_aceptada_no_edita_lo_capturado(): void
    {
        [, $nivel] = $this->devuelto('plan_estudios');

        Livewire::test(InfraestructuraNivel::class, ['escuelaNivel' => $nivel])
            ->assertSet('puedeEditar', false)
            ->call('guardarEdicion')
            ->assertForbidden();
    }

    public function test_plan_de_estudios_reabierto_se_guarda_y_vuelve_al_hub(): void
    {
        [$escuela, $nivel] = $this->devuelto('plan_estudios');

        Livewire::test(PlanEstudios::class, ['escuelaNivel' => $nivel])
            ->set('modalidad', 'escolarizada')->set('planEstudiosReferencia', 'Plan 2022')
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertRedirect(route('tramite.resumen', ['escuela' => $escuela->id]));

        $this->assertSame('Plan 2022', EscuelaNivel::find($nivel->id)->plan_estudios_referencia);
    }

    public function test_en_captura_el_guardado_sigue_avanzando_al_siguiente_paso(): void
    {
        $escuela = $this->crearEscuelaConPlantel();
        $nivel = $this->completarTramite($escuela);
        $this->actingAs($escuela->solicitante->user);

        Livewire::test(PlanEstudios::class, ['escuelaNivel' => $nivel])
            ->set('modalidad', 'escolarizada')->set('planEstudiosReferencia', 'Plan 2022')
            ->call('guardar')
            ->assertRedirect(route('tramite.paso3-plantilla', ['escuelaNivel' => $nivel->id]));
    }
}
