<?php

namespace Tests\Feature\Livewire\Tramite;

use App\Livewire\Tramite\Paso2Responsable;
use App\Models\Escuela;
use App\Models\NivelEducativo;
use App\Models\Plantel;
use App\Models\Solicitante;
use Database\Seeders\CatalogoMinimoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\CompletaPaso2;
use Tests\TestCase;

class Paso2ResponsableVistaTest extends TestCase
{
    use CompletaPaso2;
    use RefreshDatabase;

    private function escuelaDe(Solicitante $solicitante): Escuela
    {
        (new CatalogoMinimoSeeder)->run();
        $plantel = Plantel::create(['calle' => 'Calle 1', 'colonia' => 'Centro', 'municipio' => 'Querétaro', 'codigo_postal' => '76000']);

        return Escuela::create(['plantel_id' => $plantel->id, 'solicitante_id' => $solicitante->id]);
    }

    public function test_fase_responsable_con_encabezado_y_secciones(): void
    {
        $solicitante = Solicitante::factory()->create();
        $escuela = $this->escuelaDe($solicitante);

        $this->actingAs($solicitante->user)
            ->get(route('tramite.paso2', ['escuela' => $escuela->id]))
            ->assertOk()
            ->assertSee('Datos generales · Paso 2 de 4')
            ->assertSee('href="'.route('tramite.resumen', ['escuela' => $escuela->id]).'"', false)
            ->assertSee('Datos personales')
            ->assertSee('Domicilio para notificaciones')
            ->assertSee('Terna de nombres')
            ->assertSee('id="tipoPersona-fisica_con_gestor"', false)
            ->assertSee('aria-current="step"', false);
    }

    public function test_persona_moral_muestra_sus_secciones(): void
    {
        $solicitante = Solicitante::factory()->create();
        $escuela = $this->escuelaDe($solicitante);
        $this->actingAs($solicitante->user);

        Livewire::test(Paso2Responsable::class, ['escuela' => $escuela])
            ->set('tipoPersona', 'moral')
            ->assertSee('Datos de la persona moral')
            ->assertSee('Datos notariales')
            ->assertSeeHtml('for="personaMoralForm.razonSocial"')
            ->assertDontSee('Datos personales');
    }

    public function test_el_resumen_de_errores_enlaza_a_campos_existentes(): void
    {
        $solicitante = Solicitante::factory()->create();
        $escuela = $this->escuelaDe($solicitante);
        $this->actingAs($solicitante->user);

        Livewire::test(Paso2Responsable::class, ['escuela' => $escuela])
            ->call('guardarResponsable')
            ->assertSee('Revisa los siguientes datos')
            ->assertSeeHtml('href="#domicilioNotificaciones"')
            ->assertSeeHtml('id="domicilioNotificaciones"')
            ->assertSeeHtml('href="#nombrePropuesto1"')
            ->assertSeeHtml('id="nombrePropuesto1"');
    }

    public function test_fase_niveles_con_encabezado_checkboxes_y_error_enlazado(): void
    {
        $solicitante = Solicitante::factory()->create();
        $escuela = $this->escuelaDe($solicitante);
        $this->completarPaso2($escuela->id);
        $this->actingAs($solicitante->user);
        $primaria = NivelEducativo::where('clave', 'primaria')->value('id');

        Livewire::test(Paso2Responsable::class, ['escuela' => $escuela])
            ->assertSee('Datos generales · Paso 4 de 4')
            ->assertSee('Responsable legal (ya registrado)')
            ->assertSeeHtml('for="nivel-'.$primaria.'"')
            ->assertSeeHtml('border-nivel-primaria')
            ->call('guardarNiveles')
            ->assertSeeHtml('href="#nivelesSeleccionados"')
            ->assertSeeHtml('id="nivelesSeleccionados"');
    }
}
