<?php

namespace Tests\Feature\Application\EscuelaNiveles;

use App\Application\EscuelaNiveles\MarcarPasoCompletado;
use App\Application\Excepciones\PrecondicionIncumplida;
use App\Models\Escuela;
use App\Models\EscuelaNivel;
use App\Models\NivelEducativo;
use App\Models\Plantel;
use App\Models\Solicitante;
use Database\Seeders\CatalogoMinimoSeeder;
use Database\Seeders\PasosCapturaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Tests\Concerns\CompletaPaso2;
use Tests\TestCase;

class MarcarPasoCompletadoTest extends TestCase
{
    use CompletaPaso2;
    use RefreshDatabase;

    // Minor 7 — MarcarPasoCompletado ahora exige Paso 2 completo (mismo
    // gate que los casos de uso Registrar* de Paso 3), así que el fixture
    // por defecto lo deja completo; test_rechaza_marcar_un_paso_sin_paso_2_completo
    // es la única prueba que deliberadamente lo deja incompleto.
    private function crearEscuelaNivel(string $claveNivel = 'primaria', bool $conPaso2Completo = true): EscuelaNivel
    {
        (new CatalogoMinimoSeeder)->run();
        (new PasosCapturaSeeder)->run();

        $solicitante = Solicitante::factory()->create();
        $plantel = Plantel::create(['calle' => 'Calle 1', 'colonia' => 'Centro', 'municipio' => 'Querétaro', 'codigo_postal' => '76000']);
        $escuela = Escuela::create(['plantel_id' => $plantel->id, 'solicitante_id' => $solicitante->id]);
        $nivel = NivelEducativo::where('clave', $claveNivel)->first();
        $estadoId = DB::table('estados_expediente')->where('clave', 'en_captura')->value('id');

        if ($conPaso2Completo) {
            $this->completarPaso2($escuela->id);
        }

        return EscuelaNivel::create([
            'escuela_id' => $escuela->id,
            'nivel_educativo_id' => $nivel->id,
            'estado_id' => $estadoId,
            'tipo_tramite' => 'alta_nueva',
        ]);
    }

    private function fila(int $escuelaNivelId, string $pasoClave): ?object
    {
        $pasoId = DB::table('pasos_captura')->where('clave', $pasoClave)->value('id');

        return DB::table('escuela_nivel_pasos')
            ->where('escuela_nivel_id', $escuelaNivelId)
            ->where('paso_captura_id', $pasoId)
            ->first();
    }

    public function test_inserta_la_fila_cuando_no_existia(): void
    {
        $escuelaNivel = $this->crearEscuelaNivel();

        (new MarcarPasoCompletado)->ejecutar($escuelaNivel->id, 'inmueble');

        $fila = $this->fila($escuelaNivel->id, 'inmueble');
        $this->assertNotNull($fila);
        $this->assertSame('completado', $fila->estado);
        $this->assertNotNull($fila->completado_at);
    }

    public function test_actualiza_la_fila_cuando_ya_estaba_pendiente(): void
    {
        $escuelaNivel = $this->crearEscuelaNivel();
        // WS-2.4b: infraestructura solo es alcanzable con inmueble completado.
        (new MarcarPasoCompletado)->ejecutar($escuelaNivel->id, 'inmueble');
        $pasoId = DB::table('pasos_captura')->where('clave', 'infraestructura')->value('id');
        DB::table('escuela_nivel_pasos')->insert([
            'escuela_nivel_id' => $escuelaNivel->id,
            'paso_captura_id' => $pasoId,
            'estado' => 'pendiente',
        ]);

        (new MarcarPasoCompletado)->ejecutar($escuelaNivel->id, 'infraestructura');

        $this->assertSame('completado', $this->fila($escuelaNivel->id, 'infraestructura')->estado);
        $this->assertSame(2, DB::table('escuela_nivel_pasos')->where('escuela_nivel_id', $escuelaNivel->id)->count());
    }

    public function test_llamarlo_dos_veces_no_duplica_la_fila(): void
    {
        $escuelaNivel = $this->crearEscuelaNivel();
        // WS-2.4b: mobiliario solo es alcanzable con inmueble e infraestructura completados.
        (new MarcarPasoCompletado)->ejecutar($escuelaNivel->id, 'inmueble');
        (new MarcarPasoCompletado)->ejecutar($escuelaNivel->id, 'infraestructura');

        (new MarcarPasoCompletado)->ejecutar($escuelaNivel->id, 'mobiliario');
        (new MarcarPasoCompletado)->ejecutar($escuelaNivel->id, 'mobiliario');

        $this->assertSame(3, DB::table('escuela_nivel_pasos')->where('escuela_nivel_id', $escuelaNivel->id)->count());
    }

    public function test_rechaza_una_clave_de_paso_desconocida(): void
    {
        $escuelaNivel = $this->crearEscuelaNivel();

        $this->expectException(InvalidArgumentException::class);

        (new MarcarPasoCompletado)->ejecutar($escuelaNivel->id, 'paso_inexistente');
    }

    // WS-2.4b — un caller que no pase por CompuertaPaso3 no debe poder marcar
    // un sub-paso completado si su predecesor aún no lo está.
    public function test_rechaza_marcar_un_paso_cuyo_predecesor_no_esta_completado(): void
    {
        $escuelaNivel = $this->crearEscuelaNivel();

        $this->expectException(PrecondicionIncumplida::class);

        (new MarcarPasoCompletado)->ejecutar($escuelaNivel->id, 'infraestructura');
    }

    // Minor 7 — al igual que los casos de uso Registrar* de Paso 3, un caller
    // que se salte CompuertaPaso3 no debe poder marcar ningún sub-paso de
    // Paso 3 completado si Paso 2 (responsable + documentos) no lo está.
    public function test_rechaza_marcar_un_paso_sin_paso_2_completo(): void
    {
        $escuelaNivel = $this->crearEscuelaNivel(conPaso2Completo: false);
        // Deliberadamente sin ResponsableLegal ni documentos: Paso 2 incompleto.

        $this->expectException(PrecondicionIncumplida::class);

        (new MarcarPasoCompletado)->ejecutar($escuelaNivel->id, 'inmueble');
    }
}
