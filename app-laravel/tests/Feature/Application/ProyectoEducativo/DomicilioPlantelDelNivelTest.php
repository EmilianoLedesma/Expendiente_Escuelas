<?php

namespace Tests\Feature\Application\ProyectoEducativo;

use App\Application\ProyectoEducativo\DomicilioPlantelDelNivel;
use App\Models\Escuela;
use App\Models\Plantel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CapturaExpedienteConsistente;
use Tests\TestCase;

/** El domicilio de la sección 1 no se captura otra vez: se lee del plantel del nivel (3FN, una sola fuente). */
class DomicilioPlantelDelNivelTest extends TestCase
{
    use CapturaExpedienteConsistente;
    use RefreshDatabase;

    private function plantelDe(Escuela $escuela): Plantel
    {
        return Plantel::findOrFail($escuela->plantel_id);
    }

    public function test_devuelve_cada_parte_del_domicilio_del_plantel_en_orden(): void
    {
        $escuela = $this->crearEscuelaConPlantel();
        $this->plantelDe($escuela)->update(['calle' => 'Av. Universidad', 'numero_ext' => '10', 'numero_int' => 'B', 'colonia' => 'Centro', 'localidad' => 'Querétaro', 'municipio' => 'Querétaro', 'codigo_postal' => '76000']);
        $nivel = $this->elegirNivel($escuela->id, 'media_superior');

        $this->assertSame([
            ['Calle', 'Av. Universidad'],
            ['Número exterior', '10'],
            ['Número interior', 'B'],
            ['Colonia', 'Centro'],
            ['Localidad', 'Querétaro'],
            ['Municipio', 'Querétaro'],
            ['Código postal', '76000'],
        ], app(DomicilioPlantelDelNivel::class)->ejecutar($nivel->id));
    }

    public function test_omite_las_partes_vacias(): void
    {
        $escuela = $this->crearEscuelaConPlantel();
        $this->plantelDe($escuela)->update(['numero_int' => null, 'localidad' => null]);
        $nivel = $this->elegirNivel($escuela->id, 'media_superior');

        $etiquetas = array_column(app(DomicilioPlantelDelNivel::class)->ejecutar($nivel->id), 0);

        $this->assertNotContains('Número interior', $etiquetas);
        $this->assertNotContains('Localidad', $etiquetas);
        $this->assertContains('Calle', $etiquetas);
    }
}
