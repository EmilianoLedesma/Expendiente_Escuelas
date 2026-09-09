<?php

namespace Tests\Feature\Livewire\Tramite;

use App\Application\ResponsableLegal\DTO\DatosResponsableLegal;
use App\Application\ResponsableLegal\RegistrarResponsableLegal;
use App\Livewire\Tramite\Paso2Responsable;
use App\Models\Escuela;
use App\Models\EscuelaNivel;
use App\Models\NivelEducativo;
use App\Models\Plantel;
use App\Models\Solicitante;
use Database\Seeders\CatalogoMinimoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class Paso2ResponsableTest extends TestCase
{
    use RefreshDatabase;

    private function crearEscuelaPara(Solicitante $solicitante): Escuela
    {
        $plantel = Plantel::create(['calle' => 'Calle 1', 'colonia' => 'Centro', 'municipio' => 'Querétaro', 'codigo_postal' => '76000']);

        return Escuela::create(['plantel_id' => $plantel->id, 'solicitante_id' => $solicitante->id]);
    }

    public function test_arranca_en_fase_responsable_cuando_no_hay_responsable_legal(): void
    {
        $solicitante = Solicitante::factory()->create();
        $escuela = $this->crearEscuelaPara($solicitante);
        $this->actingAs($solicitante->user);

        Livewire::test(Paso2Responsable::class, ['escuela' => $escuela])
            ->assertSet('fase', 'responsable');
    }

    public function test_resume_en_fase_niveles_cuando_ya_hay_responsable_legal(): void
    {
        $solicitante = Solicitante::factory()->create();
        $escuela = $this->crearEscuelaPara($solicitante);
        (new RegistrarResponsableLegal)->ejecutar($escuela->id, new DatosResponsableLegal(tipoPersona: 'fisica', nombre: 'Juana Pérez'));
        $this->actingAs($solicitante->user);

        Livewire::test(Paso2Responsable::class, ['escuela' => $escuela])
            ->assertSet('fase', 'niveles');
    }

    public function test_tipo_fisica_guarda_y_avanza_a_fase_niveles(): void
    {
        $solicitante = Solicitante::factory()->create();
        $escuela = $this->crearEscuelaPara($solicitante);
        $this->actingAs($solicitante->user);

        Livewire::test(Paso2Responsable::class, ['escuela' => $escuela])
            ->set('tipoPersona', 'fisica')
            ->set('personaFisicaForm.nombre', 'Juana Pérez')
            ->call('guardarResponsable')
            ->assertSet('fase', 'niveles')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('responsables_legales', ['escuela_id' => $escuela->id, 'tipo_persona' => 'fisica']);
    }

    public function test_rechaza_envio_sin_ningun_nivel_seleccionado(): void
    {
        $solicitante = Solicitante::factory()->create();
        $escuela = $this->crearEscuelaPara($solicitante);
        (new RegistrarResponsableLegal)->ejecutar($escuela->id, new DatosResponsableLegal(tipoPersona: 'fisica', nombre: 'Juana Pérez'));
        $this->actingAs($solicitante->user);

        Livewire::test(Paso2Responsable::class, ['escuela' => $escuela])
            ->set('nivelesSeleccionados', [])
            ->call('guardarNiveles')
            ->assertHasErrors('nivelesSeleccionados');

        $this->assertDatabaseCount('escuela_niveles', 0);
    }

    public function test_envio_con_niveles_crea_escuela_niveles_y_redirige(): void
    {
        (new CatalogoMinimoSeeder)->run();
        $solicitante = Solicitante::factory()->create();
        $escuela = $this->crearEscuelaPara($solicitante);
        (new RegistrarResponsableLegal)->ejecutar($escuela->id, new DatosResponsableLegal(tipoPersona: 'fisica', nombre: 'Juana Pérez'));
        $this->actingAs($solicitante->user);
        $preescolar = NivelEducativo::where('clave', 'preescolar')->first();

        $component = Livewire::test(Paso2Responsable::class, ['escuela' => $escuela])
            ->set('nivelesSeleccionados', [$preescolar->id])
            ->call('guardarNiveles');

        $this->assertDatabaseHas('escuela_niveles', ['escuela_id' => $escuela->id, 'nivel_educativo_id' => $preescolar->id]);
        $escuelaNivel = EscuelaNivel::where('escuela_id', $escuela->id)->firstOrFail();
        $component->assertRedirect(route('tramite.paso3-placeholder', ['escuelaNivel' => $escuelaNivel->id]));
    }
}
