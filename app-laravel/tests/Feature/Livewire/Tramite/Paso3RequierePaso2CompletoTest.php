<?php

namespace Tests\Feature\Livewire\Tramite;

use App\Models\Escuela;
use App\Models\EscuelaNivel;
use App\Models\NivelEducativo;
use App\Models\Plantel;
use App\Models\Solicitante;
use Database\Seeders\DatabaseSeeder;
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

    private const RUTAS_PASO3 = [
        'tramite.paso3-inmueble',
        'tramite.paso3-infraestructura',
        'tramite.paso3-mobiliario',
        'tramite.paso3-proximos-pasos',
    ];

    /**
     * Lista estática: arrancar la app dentro de un data provider instala los
     * manejadores globales de errores de Laravel y vuelve "risky" a toda la suite.
     *
     * @return array<string, array{string}>
     */
    public static function rutasPaso3(): array
    {
        return array_combine(self::RUTAS_PASO3, array_map(fn ($r) => [$r], self::RUTAS_PASO3));
    }

    /** Una página nueva de Paso 3 que no se agregue a la lista (y a la compuerta) hace fallar este test. */
    public function test_la_lista_cubre_todas_las_rutas_paso3_del_router(): void
    {
        $delRouter = array_values(array_filter(
            array_keys(app('router')->getRoutes()->getRoutesByName()),
            fn ($nombre) => str_starts_with($nombre, 'tramite.paso3-'),
        ));

        $this->assertEqualsCanonicalizing($delRouter, self::RUTAS_PASO3);
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
