<?php

namespace Tests\Feature\Application\EscuelaNiveles;

use App\Application\EscuelaNiveles\RegistrarNivelesSeleccionados;
use App\Models\Escuela;
use App\Models\NivelEducativo;
use App\Models\Plantel;
use App\Models\Solicitante;
use Database\Seeders\CatalogoMinimoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class RegistrarNivelesSeleccionadosTest extends TestCase
{
    use RefreshDatabase;

    private function crearEscuela(): Escuela
    {
        $solicitante = Solicitante::factory()->create();
        $plantel = Plantel::create(['calle' => 'Calle 1', 'colonia' => 'Centro', 'municipio' => 'Querétaro', 'codigo_postal' => '76000']);

        return Escuela::create(['plantel_id' => $plantel->id, 'solicitante_id' => $solicitante->id]);
    }

    public function test_crea_un_escuela_nivel_por_cada_nivel_seleccionado(): void
    {
        (new CatalogoMinimoSeeder)->run();
        $escuela = $this->crearEscuela();
        $preescolar = NivelEducativo::where('clave', 'preescolar')->first();
        $primaria = NivelEducativo::where('clave', 'primaria')->first();

        (new RegistrarNivelesSeleccionados)->ejecutar($escuela->id, [$preescolar->id, $primaria->id]);

        $this->assertDatabaseCount('escuela_niveles', 2);
        $this->assertDatabaseHas('escuela_niveles', [
            'escuela_id' => $escuela->id,
            'nivel_educativo_id' => $preescolar->id,
            'tipo_tramite' => 'alta_nueva',
        ]);
        $this->assertDatabaseHas('escuela_niveles', [
            'escuela_id' => $escuela->id,
            'nivel_educativo_id' => $primaria->id,
            'tipo_tramite' => 'alta_nueva',
        ]);
    }

    public function test_rechaza_una_lista_vacia_de_niveles(): void
    {
        (new CatalogoMinimoSeeder)->run();
        $escuela = $this->crearEscuela();

        $this->expectException(InvalidArgumentException::class);

        (new RegistrarNivelesSeleccionados)->ejecutar($escuela->id, []);
    }
}
