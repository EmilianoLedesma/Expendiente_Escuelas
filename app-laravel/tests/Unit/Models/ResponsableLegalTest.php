<?php

namespace Tests\Unit\Models;

use App\Models\Escuela;
use App\Models\Gestor;
use App\Models\PersonaFisica;
use App\Models\Plantel;
use App\Models\ResponsableLegal;
use App\Models\Solicitante;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResponsableLegalTest extends TestCase
{
    use RefreshDatabase;

    private function crearEscuela(): Escuela
    {
        $solicitante = Solicitante::factory()->create();
        $plantel = Plantel::create(['calle' => 'Calle 1', 'colonia' => 'Centro', 'municipio' => 'Querétaro', 'codigo_postal' => '76000']);

        return Escuela::create(['plantel_id' => $plantel->id, 'solicitante_id' => $solicitante->id]);
    }

    public function test_responsable_legal_fisica_con_gestor_expone_ambos_subtipos(): void
    {
        $escuela = $this->crearEscuela();

        $responsable = ResponsableLegal::create([
            'escuela_id' => $escuela->id,
            'tipo_persona' => 'fisica_con_gestor',
        ]);
        PersonaFisica::create([
            'responsable_legal_id' => $responsable->id,
            'nombre' => 'Juana Pérez',
        ]);
        Gestor::create([
            'responsable_legal_id' => $responsable->id,
            'nombre' => 'Carlos Gómez',
        ]);

        $this->assertTrue($responsable->personaFisica->is(PersonaFisica::find($responsable->id)));
        $this->assertSame('Carlos Gómez', $responsable->gestor->nombre);
        $this->assertTrue($escuela->responsableLegal->is($responsable));
    }
}
