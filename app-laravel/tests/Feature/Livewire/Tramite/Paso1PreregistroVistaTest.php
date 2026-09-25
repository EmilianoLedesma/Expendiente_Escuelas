<?php

namespace Tests\Feature\Livewire\Tramite;

use App\Livewire\Tramite\Paso1Preregistro;
use App\Models\Solicitante;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class Paso1PreregistroVistaTest extends TestCase
{
    use RefreshDatabase;

    public function test_muestra_el_encabezado_del_paso_y_la_barra_de_acciones(): void
    {
        $this->actingAs(Solicitante::factory()->create()->user)
            ->get(route('tramite.preregistro'))
            ->assertOk()
            ->assertSee('Datos generales · Paso 1 de 4')
            ->assertSee('Guardar y continuar')
            ->assertSee('Volver a Mis trámites')
            ->assertDontSee('Secciones del trámite');
    }

    public function test_cada_campo_tiene_etiqueta_asociada(): void
    {
        $html = $this->actingAs(Solicitante::factory()->create()->user)
            ->get(route('tramite.preregistro'))
            ->getContent();

        foreach (['calle', 'numeroExt', 'numeroInt', 'colonia', 'localidad', 'municipio', 'codigoPostal', 'telefono', 'correoElectronico'] as $id) {
            $this->assertStringContainsString('for="'.$id.'"', $html, $id);
            $this->assertStringContainsString('id="'.$id.'"', $html, $id);
        }
        $this->assertStringContainsString('id="bifurcacion-nuevo"', $html);
        $this->assertStringContainsString('id="bifurcacion-existente"', $html);
    }

    public function test_el_plantel_existente_tiene_etiqueta(): void
    {
        $this->actingAs(Solicitante::factory()->create()->user);

        Livewire::test(Paso1Preregistro::class)
            ->set('bifurcacion', 'existente')
            ->assertSeeHtml('for="plantelId"')
            ->assertSeeHtml('id="plantelId"');
    }

    public function test_el_resumen_de_errores_enlaza_a_campos_existentes(): void
    {
        $this->actingAs(Solicitante::factory()->create()->user);

        Livewire::test(Paso1Preregistro::class)
            ->call('guardar')
            ->assertSee('Revisa los siguientes datos')
            ->assertSeeHtml('href="#calle"')
            ->assertSeeHtml('id="calle"')
            ->assertSeeHtml('id="calle-error"')
            ->assertSeeHtml('aria-invalid="true"');
    }
}
