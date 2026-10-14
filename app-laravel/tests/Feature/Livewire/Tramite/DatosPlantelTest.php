<?php

namespace Tests\Feature\Livewire\Tramite;

use App\Application\Excepciones\PrecondicionIncumplida;
use App\Application\Preregistro\ActualizarContactoPlantel;
use App\Application\Tramite\TramiteEditable;
use App\Livewire\Tramite\DatosPlantel;
use App\Models\Escuela;
use App\Models\Plantel;
use App\Models\Solicitante;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Exceptions\PublicPropertyNotFoundException;
use Livewire\Livewire;
use Tests\Concerns\ConNivelParaResponsables;
use Tests\TestCase;

class DatosPlantelTest extends TestCase
{
    use ConNivelParaResponsables;
    use RefreshDatabase;

    private function escuela(Solicitante $solicitante): Escuela
    {
        $plantel = Plantel::create([
            'calle' => 'Av. Juárez', 'numero_ext' => '12', 'colonia' => 'Centro', 'municipio' => 'Querétaro',
            'codigo_postal' => '76000', 'telefono' => '4420000000', 'correo_electronico' => 'viejo@ejemplo.mx',
        ]);

        return Escuela::create(['plantel_id' => $plantel->id, 'solicitante_id' => $solicitante->id]);
    }

    public function test_el_domicilio_es_texto_y_el_contacto_es_editable(): void
    {
        $solicitante = Solicitante::factory()->create();
        $escuela = $this->escuela($solicitante);

        $this->actingAs($solicitante->user)->get(route('tramite.plantel', ['escuela' => $escuela->id]))
            ->assertOk()
            ->assertSeeLivewire('tramite.datos-plantel')
            ->assertSee('Av. Juárez')
            ->assertSee('requiere autorización previa de la Dirección de Educación')
            ->assertSee('wire:model.blur="telefono"', false)
            ->assertDontSee('wire:model.blur="calle"', false);
    }

    public function test_guarda_telefono_y_correo(): void
    {
        $solicitante = Solicitante::factory()->create();
        $escuela = $this->escuela($solicitante);

        Livewire::actingAs($solicitante->user)
            ->test(DatosPlantel::class, ['escuela' => $escuela])
            ->assertSet('telefono', '4420000000')
            ->set('telefono', '4421234567')
            ->set('correoElectronico', 'nuevo@ejemplo.mx')
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertDispatched('toast', tipo: 'success', titulo: 'Datos del plantel guardados');

        $this->assertDatabaseHas('planteles', ['id' => $escuela->plantel_id, 'telefono' => '4421234567', 'correo_electronico' => 'nuevo@ejemplo.mx']);
    }

    public function test_un_telefono_mal_capturado_se_marca_y_no_se_guarda(): void
    {
        $solicitante = Solicitante::factory()->create();
        $escuela = $this->escuela($solicitante);

        Livewire::actingAs($solicitante->user)
            ->test(DatosPlantel::class, ['escuela' => $escuela])
            ->set('telefono', '123')
            ->call('guardar')
            ->assertHasErrors('telefono');

        $this->assertDatabaseHas('planteles', ['id' => $escuela->plantel_id, 'telefono' => '4420000000']);
    }

    public function test_el_rechazo_del_plantel_se_muestra_en_la_pagina(): void
    {
        $solicitante = Solicitante::factory()->create();
        $escuela = $this->escuela($solicitante);
        $this->mock(ActualizarContactoPlantel::class)->shouldReceive('ejecutar')
            ->andThrow(new PrecondicionIncumplida(TramiteEditable::ENVIADO, 'Un trámite de este plantel ya se envió a SEDEQ.'));

        Livewire::actingAs($solicitante->user)
            ->test(DatosPlantel::class, ['escuela' => $escuela])
            ->call('guardar')
            ->assertHasErrors('plantel')
            ->assertSee('Un trámite de este plantel ya se envió a SEDEQ.')
            ->assertDispatched('toast', tipo: 'error', titulo: 'No se guardó');
    }

    public function test_el_boton_dice_guardar_cambios(): void
    {
        $solicitante = Solicitante::factory()->create();
        $escuela = $this->escuela($solicitante);

        Livewire::actingAs($solicitante->user)
            ->test(DatosPlantel::class, ['escuela' => $escuela])
            ->assertSee('Guardar cambios')
            ->assertDontSee('Guardar y continuar');
    }

    public function test_el_domicilio_no_se_puede_enviar_desde_el_componente(): void
    {
        $solicitante = Solicitante::factory()->create();
        $escuela = $this->escuela($solicitante);
        $componente = Livewire::actingAs($solicitante->user)->test(DatosPlantel::class, ['escuela' => $escuela]);

        foreach (['calle', 'codigoPostal', 'municipio'] as $campo) {
            try {
                $componente->set($campo, 'X');
                $this->fail("El campo de domicilio {$campo} fue aceptado.");
            } catch (PublicPropertyNotFoundException) {
                $this->addToAssertionCount(1);
            }
        }

        $componente->set('telefono', '4421234567')->call('guardar')->assertHasNoErrors();

        $this->assertDatabaseHas('planteles', [
            'id' => $escuela->plantel_id, 'calle' => 'Av. Juárez', 'numero_ext' => '12', 'colonia' => 'Centro',
            'municipio' => 'Querétaro', 'codigo_postal' => '76000', 'telefono' => '4421234567',
        ]);
    }

    public function test_el_rechazo_del_plantel_no_se_duplica_en_el_resumen_de_errores(): void
    {
        $solicitante = Solicitante::factory()->create();
        $escuela = $this->escuela($solicitante);
        $this->mock(ActualizarContactoPlantel::class)->shouldReceive('ejecutar')
            ->andThrow(new PrecondicionIncumplida(TramiteEditable::ENVIADO, 'Un trámite de este plantel ya se envió a SEDEQ.'));

        $html = Livewire::actingAs($solicitante->user)
            ->test(DatosPlantel::class, ['escuela' => $escuela])
            ->call('guardar')
            ->html();

        $this->assertSame(1, substr_count($html, 'Un trámite de este plantel ya se envió a SEDEQ.'));
        $this->assertStringNotContainsString('Revisa los siguientes datos', $html);
    }

    public function test_otro_solicitante_y_un_responsable_de_nivel_reciben_403(): void
    {
        $dueno = Solicitante::factory()->create();
        $nivel = $this->nivelDe($dueno);
        $url = route('tramite.plantel', ['escuela' => $nivel->escuela_id]);

        $this->actingAs(Solicitante::factory()->create()->user)->get($url)->assertForbidden();
        $this->actingAs($this->responsableDe($nivel))->get($url)->assertForbidden();
    }
}
