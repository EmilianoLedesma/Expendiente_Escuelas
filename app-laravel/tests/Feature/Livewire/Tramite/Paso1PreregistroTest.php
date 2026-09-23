<?php

namespace Tests\Feature\Livewire\Tramite;

use App\Application\Preregistro\DTO\DatosPreregistro;
use App\Application\Preregistro\DTO\ResultadoPreregistro;
use App\Application\Preregistro\IniciarTramiteNuevo;
use App\Application\Preregistro\PlantelNoDisponible;
use App\Livewire\Tramite\Paso1Preregistro;
use App\Models\Solicitante;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

class Paso1PreregistroTest extends TestCase
{
    use RefreshDatabase;

    public function test_bifurcacion_nuevo_llama_al_caso_de_uso_con_el_dto_correcto_y_redirige(): void
    {
        $solicitante = Solicitante::factory()->create();
        $this->actingAs($solicitante->user);

        $this->mock(IniciarTramiteNuevo::class, function ($mock) use ($solicitante) {
            $mock->shouldReceive('ejecutar')
                ->once()
                ->with(
                    Mockery::on(function (DatosPreregistro $dto) {
                        return $dto->bifurcacion === 'nuevo'
                            && $dto->calle === 'Av. Reforma 100'
                            && $dto->colonia === 'Centro'
                            && $dto->municipio === 'Querétaro'
                            && $dto->codigoPostal === '76000';
                    }),
                    $solicitante->id,
                )
                ->andReturn(new ResultadoPreregistro(escuelaId: 42, plantelId: 7));
        });

        Livewire::test(Paso1Preregistro::class)
            ->set('bifurcacion', 'nuevo')
            ->set('calle', 'Av. Reforma 100')
            ->set('colonia', 'Centro')
            ->set('municipio', 'Querétaro')
            ->set('codigoPostal', '76000')
            ->call('guardar')
            ->assertRedirect(route('tramite.paso2', ['escuela' => 42]));
    }

    public function test_bifurcacion_nuevo_sin_campos_requeridos_no_llama_al_caso_de_uso(): void
    {
        $solicitante = Solicitante::factory()->create();
        $this->actingAs($solicitante->user);

        $this->mock(IniciarTramiteNuevo::class, function ($mock) {
            $mock->shouldNotReceive('ejecutar');
        });

        Livewire::test(Paso1Preregistro::class)
            ->set('bifurcacion', 'nuevo')
            ->set('calle', '')
            ->call('guardar')
            ->assertHasErrors(['calle', 'colonia', 'municipio', 'codigoPostal']);
    }

    public function test_bifurcacion_existente_sin_plantel_id_no_llama_al_caso_de_uso(): void
    {
        $solicitante = Solicitante::factory()->create();
        $this->actingAs($solicitante->user);

        $this->mock(IniciarTramiteNuevo::class, function ($mock) {
            $mock->shouldNotReceive('ejecutar');
        });

        Livewire::test(Paso1Preregistro::class)
            ->set('bifurcacion', 'existente')
            ->set('plantelId', null)
            ->call('guardar')
            ->assertHasErrors(['plantelId']);
    }

    /** Plantel ajeno e inexistente deben verse igual: no filtrar qué planteles existen. */
    public function test_plantel_ajeno_e_inexistente_dan_el_mismo_error_en_plantel_id(): void
    {
        $ajeno = app(IniciarTramiteNuevo::class)->ejecutar(new DatosPreregistro(
            bifurcacion: 'nuevo', calle: 'Calle', colonia: 'Col', municipio: 'Querétaro', codigoPostal: '76000',
        ), Solicitante::factory()->create()->id);
        $this->actingAs(Solicitante::factory()->create()->user);
        $mensaje = (new PlantelNoDisponible)->getMessage();

        foreach ([$ajeno->plantelId, 999999] as $plantelId) {
            Livewire::test(Paso1Preregistro::class)
                ->set('bifurcacion', 'existente')
                ->set('plantelId', $plantelId)
                ->call('guardar')
                ->assertHasErrors(['plantelId'])
                ->assertHasNoErrors(['bifurcacion'])
                ->assertSee($mensaje);
        }
    }
}
