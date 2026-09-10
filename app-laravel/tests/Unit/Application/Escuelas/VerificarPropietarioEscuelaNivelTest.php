<?php

namespace Tests\Unit\Application\Escuelas;

use App\Application\Escuelas\VerificarPropietarioEscuelaNivel;
use App\Models\Escuela;
use App\Models\EscuelaNivel;
use App\Models\NivelEducativo;
use App\Models\Plantel;
use App\Models\Solicitante;
use Database\Seeders\CatalogoMinimoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class VerificarPropietarioEscuelaNivelTest extends TestCase
{
    use RefreshDatabase;

    private function crearEscuelaNivelPara(Solicitante $solicitante): EscuelaNivel
    {
        (new CatalogoMinimoSeeder)->run();
        $plantel = Plantel::create(['calle' => 'Calle 1', 'colonia' => 'Centro', 'municipio' => 'Querétaro', 'codigo_postal' => '76000']);
        $escuela = Escuela::create(['plantel_id' => $plantel->id, 'solicitante_id' => $solicitante->id]);
        $nivel = NivelEducativo::where('clave', 'primaria')->first();
        $estadoId = DB::table('estados_expediente')->where('clave', 'en_captura')->value('id');

        return EscuelaNivel::create([
            'escuela_id' => $escuela->id,
            'nivel_educativo_id' => $nivel->id,
            'estado_id' => $estadoId,
            'tipo_tramite' => 'alta_nueva',
        ]);
    }

    public function test_devuelve_true_cuando_el_solicitante_es_dueno_de_la_escuela_padre(): void
    {
        $solicitante = Solicitante::factory()->create();
        $escuelaNivel = $this->crearEscuelaNivelPara($solicitante);

        $resultado = (new VerificarPropietarioEscuelaNivel)->ejecutar($escuelaNivel->id, $solicitante->id);

        $this->assertTrue($resultado);
    }

    public function test_devuelve_false_cuando_el_solicitante_no_es_dueno(): void
    {
        $dueno = Solicitante::factory()->create();
        $escuelaNivel = $this->crearEscuelaNivelPara($dueno);
        $otro = Solicitante::factory()->create();

        $resultado = (new VerificarPropietarioEscuelaNivel)->ejecutar($escuelaNivel->id, $otro->id);

        $this->assertFalse($resultado);
    }
}
