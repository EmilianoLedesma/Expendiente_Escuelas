<?php

namespace Tests\Feature\Livewire\Tramite;

use App\Livewire\Tramite\Paso3\Matricula;
use App\Livewire\Tramite\Paso3\PlanEstudios;
use App\Livewire\Tramite\Paso3\PlantillaDocente;
use App\Models\Escuela;
use App\Models\EscuelaNivel;
use App\Models\Solicitante;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\Concerns\PreparaPaso3;
use Tests\TestCase;

/** Same guard as WS-5b's Paso24DocumentosNivel: the client cannot point the page at another owner's level. */
class Paso3NivelBloqueadoTest extends TestCase
{
    use PreparaPaso3;
    use RefreshDatabase;

    public function test_el_nivel_de_las_paginas_nuevas_del_paso3_esta_bloqueado(): void
    {
        $propio = $this->nivelListoPara('primaria', 'matricula');
        $ajeno = EscuelaNivel::create([
            'escuela_id' => Escuela::create(['plantel_id' => $propio->escuela->plantel_id, 'solicitante_id' => Solicitante::factory()->create()->id])->id,
            'nivel_educativo_id' => $propio->nivel_educativo_id,
            'estado_id' => $propio->estado_id,
            'tipo_tramite' => 'alta_nueva',
        ]);
        $this->actingAs($propio->escuela->solicitante->user);

        foreach ([PlanEstudios::class, PlantillaDocente::class, Matricula::class] as $pagina) {
            foreach (['escuelaNivel' => $ajeno->id, 'escuelaNivel.id' => $ajeno->id, 'escuelaNivel.escuela_id' => $ajeno->escuela_id] as $ruta => $valor) {
                $componente = Livewire::test($pagina, ['escuelaNivel' => $propio->fresh()]);

                try {
                    $componente->set($ruta, $valor);
                    $this->fail("{$pagina} aceptó reescribir {$ruta}.");
                } catch (CannotUpdateLockedPropertyException) {
                    $this->addToAssertionCount(1);
                }
            }
        }
    }
}
