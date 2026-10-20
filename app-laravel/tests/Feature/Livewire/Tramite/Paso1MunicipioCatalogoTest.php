<?php

namespace Tests\Feature\Livewire\Tramite;

use App\Livewire\Tramite\Paso1Preregistro;
use App\Models\Plantel;
use App\Models\Solicitante;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\ConCatalogoGeografico;
use Tests\TestCase;

/** Paso 1 con el catálogo geográfico importado: selector limitado a Querétaro (ADR-008), sin listas en la vista. */
class Paso1MunicipioCatalogoTest extends TestCase
{
    use ConCatalogoGeografico;
    use RefreshDatabase;

    private function pagina()
    {
        $this->actingAs(Solicitante::factory()->create()->user);

        return Livewire::test(Paso1Preregistro::class)
            ->set('bifurcacion', 'nuevo')
            ->set('calle', 'Av. Hidalgo')
            ->set('colonia', 'Centro')
            ->set('codigoPostal', '76000');
    }

    public function test_con_catalogo_el_municipio_es_un_selector_de_queretaro(): void
    {
        $this->sembrarCatalogoGeografico();

        $this->pagina()
            ->assertSeeHtml('wire:model.blur="municipioId"')
            ->assertSee('SAN JUAN DEL RÍO')
            ->assertDontSee('CELAYA')
            ->assertDontSeeHtml('wire:model.blur="municipio"');
    }

    public function test_guarda_el_municipio_elegido_del_catalogo(): void
    {
        $this->sembrarCatalogoGeografico();
        $corregidora = $this->municipio('CORREGIDORA');

        $this->pagina()->set('municipioId', (string) $corregidora->id)->call('guardar')->assertHasNoErrors();

        $plantel = Plantel::sole();
        $this->assertSame($corregidora->id, $plantel->municipio_id);
        $this->assertSame('CORREGIDORA', $plantel->municipio);
    }

    public function test_con_catalogo_el_municipio_es_obligatorio_y_debe_ser_de_queretaro(): void
    {
        $this->sembrarCatalogoGeografico();

        $this->pagina()->call('guardar')->assertHasErrors(['municipioId' => 'required_if']);
        $this->pagina()->set('municipioId', (string) $this->municipio('CELAYA', $this->guanajuato)->id)->call('guardar')->assertHasErrors(['municipioId']);
        $this->assertDatabaseCount('planteles', 0);
    }

    public function test_sin_catalogo_se_conserva_el_campo_de_texto(): void
    {
        $this->pagina()
            ->assertSeeHtml('wire:model.blur="municipio"')
            ->set('municipio', 'Querétaro')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertNull(Plantel::sole()->municipio_id);
    }
}
