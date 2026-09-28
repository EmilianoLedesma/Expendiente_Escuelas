<?php

namespace Tests\Unit\Application\ResponsableLegal;

use App\Application\ResponsableLegal\TipoPersonaDeEscuela;
use App\Models\Escuela;
use App\Models\Plantel;
use App\Models\ResponsableLegal;
use App\Models\Solicitante;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TipoPersonaDeEscuelaTest extends TestCase
{
    use RefreshDatabase;

    private function crearEscuela(): Escuela
    {
        $solicitante = Solicitante::factory()->create();
        $plantel = Plantel::create(['calle' => 'Calle 1', 'colonia' => 'Centro', 'municipio' => 'Querétaro', 'codigo_postal' => '76000']);

        return Escuela::create(['plantel_id' => $plantel->id, 'solicitante_id' => $solicitante->id]);
    }

    public function test_devuelve_tipo_persona_cuando_hay_responsable(): void
    {
        $escuela = $this->crearEscuela();
        ResponsableLegal::create(['escuela_id' => $escuela->id, 'tipo_persona' => 'moral']);

        $resultado = app(TipoPersonaDeEscuela::class)->ejecutar($escuela->id);

        $this->assertSame('moral', $resultado);
    }

    public function test_devuelve_null_sin_responsable(): void
    {
        $escuela = $this->crearEscuela();

        $resultado = app(TipoPersonaDeEscuela::class)->ejecutar($escuela->id);

        $this->assertNull($resultado);
    }
}
