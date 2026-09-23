<?php

namespace Tests\Feature\Livewire\Tramite;

use App\Models\Escuela;
use App\Models\EscuelaNivel;
use App\Models\NivelEducativo;
use App\Models\Plantel;
use App\Models\Solicitante;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * WS-1.2: una escuela_niveles "atorada" (creada antes de que existiera la
 * precondición de Paso 2) no puede usarse para entrar a Paso 3 — cada página
 * de Paso 3 manda de vuelta a /tramite/paso2/{escuela}. Round-trip HTTP real
 * (ADR-003).
 */
class Paso3RequierePaso2CompletoTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Derivado del router, no de una lista a mano: una página nueva de Paso 3
     * que olvide la compuerta hace fallar este test. Los data providers corren
     * antes de setUp(), así que arrancan su propia instancia de la app.
     *
     * @return array<string, array{string}>
     */
    public static function rutasPaso3(): array
    {
        $app = require dirname(__DIR__, 4).'/bootstrap/app.php';
        $app->make(Kernel::class)->bootstrap();

        $rutas = [];
        foreach (array_keys($app['router']->getRoutes()->getRoutesByName()) as $nombre) {
            if (str_starts_with($nombre, 'tramite.paso3-')) {
                $rutas[$nombre] = [$nombre];
            }
        }

        return $rutas;
    }

    #[DataProvider('rutasPaso3')]
    public function test_paso3_redirige_a_paso2_si_paso2_esta_incompleto(string $ruta): void
    {
        $this->seed(DatabaseSeeder::class);
        $solicitante = Solicitante::factory()->create();
        $plantel = Plantel::create(['calle' => 'Calle 1', 'colonia' => 'Centro', 'municipio' => 'Querétaro', 'codigo_postal' => '76000']);
        $escuela = Escuela::create(['plantel_id' => $plantel->id, 'solicitante_id' => $solicitante->id]);
        $escuelaNivel = EscuelaNivel::create([
            'escuela_id' => $escuela->id,
            'nivel_educativo_id' => NivelEducativo::where('clave', 'preescolar')->value('id'),
            'estado_id' => DB::table('estados_expediente')->where('clave', 'en_captura')->value('id'),
            'tipo_tramite' => 'alta_nueva',
        ]);

        $response = $this->actingAs($solicitante->user)->get(route($ruta, ['escuelaNivel' => $escuelaNivel->id]));

        $response->assertRedirect(route('tramite.paso2', ['escuela' => $escuela->id]));
        $this->assertDatabaseCount('escuela_nivel_pasos', 0);
    }
}
