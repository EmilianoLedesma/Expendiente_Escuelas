<?php

namespace Tests\Feature\Livewire;

use App\Livewire\Tramite\Paso24DocumentosNivel;
use App\Livewire\Tramite\Paso3\DatosInmueble;
use App\Livewire\Tramite\Paso3\InfraestructuraNivel;
use App\Models\Solicitante;
use Database\Seeders\CatalogoMinimoSeeder;
use Database\Seeders\PasosCapturaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\Concerns\ConNivelParaResponsables;
use Tests\Concerns\PreparaPaso3;
use Tests\TestCase;

class ResponsableNivelPasosTest extends TestCase
{
    use ConNivelParaResponsables;
    use PreparaPaso3;
    use RefreshDatabase;

    public function test_inmueble_no_capturado_es_solo_lectura_para_el_responsable(): void
    {
        $nivel = $this->nivelListoPara('primaria', 'inmueble');
        $responsable = $this->responsableDe($nivel);

        Livewire::actingAs($responsable)->test(DatosInmueble::class, ['escuelaNivel' => $nivel])
            ->assertSet('soloLectura', true)
            ->assertSee('El solicitante debe capturar los datos del inmueble')
            ->assertSeeHtml('href="'.route('tramite.resumen', ['escuela' => $nivel->escuela_id]).'"')
            ->assertSee('Volver al resumen')
            ->assertDontSee('Superficie del predio')
            ->call('guardar')
            ->assertForbidden();
    }

    public function test_solo_lectura_no_se_puede_alterar_desde_el_cliente(): void
    {
        $nivel = $this->nivelListoPara('primaria', 'inmueble');
        $responsable = $this->responsableDe($nivel);

        $this->expectException(CannotUpdateLockedPropertyException::class);

        Livewire::actingAs($responsable)->test(DatosInmueble::class, ['escuelaNivel' => $nivel])
            ->set('soloLectura', false);
    }

    public function test_el_dueno_si_puede_capturar_el_inmueble(): void
    {
        $nivel = $this->nivelListoPara('primaria', 'inmueble');
        $dueno = Solicitante::find($nivel->escuela->solicitante_id)->user;

        Livewire::actingAs($dueno)->test(DatosInmueble::class, ['escuelaNivel' => $nivel])
            ->assertSet('soloLectura', false)
            ->assertSee('Superficie del predio');
    }

    public function test_si_falta_paso2_el_responsable_va_al_hub_no_a_paso2(): void
    {
        (new CatalogoMinimoSeeder)->run();
        (new PasosCapturaSeeder)->run();
        $nivel = $this->nivelDe(Solicitante::factory()->create());
        $responsable = $this->responsableDe($nivel);
        $hub = route('tramite.resumen', ['escuela' => $nivel->escuela_id]);

        Livewire::actingAs($responsable)->test(InfraestructuraNivel::class, ['escuelaNivel' => $nivel])->assertRedirect($hub);
        Livewire::actingAs($responsable)->test(Paso24DocumentosNivel::class, ['escuelaNivel' => $nivel])->assertRedirect($hub);
    }

    public function test_si_falta_paso2_el_dueno_sigue_yendo_a_paso2(): void
    {
        (new CatalogoMinimoSeeder)->run();
        (new PasosCapturaSeeder)->run();
        $dueno = Solicitante::factory()->create();
        $nivel = $this->nivelDe($dueno);

        Livewire::actingAs($dueno->user)->test(InfraestructuraNivel::class, ['escuelaNivel' => $nivel])
            ->assertRedirect(route('tramite.paso2', ['escuela' => $nivel->escuela_id]));
    }
}
