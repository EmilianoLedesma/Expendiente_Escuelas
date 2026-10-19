<?php

namespace Tests\Feature\Application\Geografia;

use App\Application\Geografia\FuenteCatalogoGeografico;
use App\Application\Geografia\ImportarCatalogoGeografico;
use App\Infrastructure\Geografia\ApiInfoMexico;
use App\Models\EntidadFederativa;
use App\Models\Municipio;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class ImportarCatalogoGeograficoTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  list<array{clave: int, nombre: string, abreviatura: ?string}>  $entidades
     * @param  array<int, list<array{clave: int, nombre: string}>>  $municipios  por clave de entidad
     */
    private function fuente(array $entidades, array $municipios): FuenteCatalogoGeografico
    {
        return new class($entidades, $municipios) implements FuenteCatalogoGeografico
        {
            public function __construct(private array $entidades, private array $municipios) {}

            public function entidades(): array
            {
                return $this->entidades;
            }

            public function municipios(int $claveEntidad): array
            {
                return $this->municipios[$claveEntidad] ?? [];
            }
        };
    }

    /** @return array{0: list<array{clave: int, nombre: string, abreviatura: ?string}>, 1: array<int, list<array{clave: int, nombre: string}>>} */
    private function datos(): array
    {
        return [
            [
                ['clave' => 0, 'nombre' => 'NO ESPECIFICADO', 'abreviatura' => 'NE'],
                ['clave' => 1, 'nombre' => 'AGUASCALIENTES', 'abreviatura' => 'AS'],
                ['clave' => 22, 'nombre' => 'QUERÉTARO', 'abreviatura' => 'QT'],
                ['clave' => 88, 'nombre' => 'NO APLICA', 'abreviatura' => null],
                ['clave' => 99, 'nombre' => 'SE IGNORA', 'abreviatura' => 'SI'],
            ],
            [
                1 => [['clave' => 1, 'nombre' => 'AGUASCALIENTES'], ['clave' => 2, 'nombre' => 'ASIENTOS']],
                22 => [['clave' => 14, 'nombre' => 'QUERÉTARO'], ['clave' => 6, 'nombre' => 'CORREGIDORA']],
            ],
        ];
    }

    public function test_imports_states_and_municipios_and_skips_non_geographic_keys(): void
    {
        [$entidades, $municipios] = $this->datos();

        $conteo = (new ImportarCatalogoGeografico($this->fuente($entidades, $municipios), minimoEntidades: 2))->ejecutar();

        $this->assertSame(['entidades' => 2, 'municipios' => 4], $conteo);
        $this->assertDatabaseCount('entidades_federativas', 2);
        $this->assertDatabaseMissing('entidades_federativas', ['clave' => 0]);
        $this->assertDatabaseMissing('entidades_federativas', ['clave' => 88]);
        $this->assertDatabaseMissing('entidades_federativas', ['clave' => 99]);
        $queretaro = EntidadFederativa::where('clave', 22)->firstOrFail();
        $this->assertSame('QT', $queretaro->abreviatura);
        $this->assertSame(['CORREGIDORA', 'QUERÉTARO'], $queretaro->municipios()->orderBy('nombre')->pluck('nombre')->all());
    }

    public function test_running_twice_does_not_duplicate_and_updates_changed_names(): void
    {
        [$entidades, $municipios] = $this->datos();
        $importar = new ImportarCatalogoGeografico($this->fuente($entidades, $municipios), minimoEntidades: 2);
        $importar->ejecutar();

        $municipios[22][1]['nombre'] = 'CORREGIDORA (ACTUALIZADO)';
        (new ImportarCatalogoGeografico($this->fuente($entidades, $municipios), minimoEntidades: 2))->ejecutar();

        $this->assertDatabaseCount('entidades_federativas', 2);
        $this->assertDatabaseCount('municipios', 4);
        $this->assertDatabaseHas('municipios', ['clave' => 6, 'nombre' => 'CORREGIDORA (ACTUALIZADO)']);
    }

    public function test_aborts_without_writing_when_the_catalog_has_too_few_states(): void
    {
        [$entidades, $municipios] = $this->datos();

        try {
            (new ImportarCatalogoGeografico($this->fuente($entidades, $municipios), minimoEntidades: 32))->ejecutar();
            $this->fail('Se esperaba RuntimeException.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('no se importó nada', $e->getMessage());
        }

        $this->assertDatabaseCount('entidades_federativas', 0);
        $this->assertDatabaseCount('municipios', 0);
    }

    public function test_aborts_without_writing_when_a_state_has_no_municipios(): void
    {
        [$entidades, $municipios] = $this->datos();
        unset($municipios[22]);

        $this->expectException(RuntimeException::class);

        try {
            (new ImportarCatalogoGeografico($this->fuente($entidades, $municipios), minimoEntidades: 2))->ejecutar();
        } finally {
            $this->assertDatabaseCount('entidades_federativas', 0);
            $this->assertDatabaseCount('municipios', 0);
        }
    }

    public function test_a_failing_source_writes_nothing(): void
    {
        $fuente = new class implements FuenteCatalogoGeografico
        {
            public function entidades(): array
            {
                return [['clave' => 1, 'nombre' => 'AGUASCALIENTES', 'abreviatura' => 'AS']];
            }

            public function municipios(int $claveEntidad): array
            {
                throw new RuntimeException('API caída');
            }
        };

        try {
            (new ImportarCatalogoGeografico($fuente, minimoEntidades: 1))->ejecutar();
            $this->fail('Se esperaba RuntimeException.');
        } catch (RuntimeException $e) {
            $this->assertSame('API caída', $e->getMessage());
        }

        $this->assertDatabaseCount('entidades_federativas', 0);
    }

    // --- Cliente HTTP de api_info_mexico ---

    private function fingirApi(): void
    {
        Http::fake([
            'https://api.test/api/estados' => Http::response([
                ['EFE_KEY' => 0, 'ENTIDAD_FEDERATIVA' => 'NO ESPECIFICADO', 'ABREVIATURA' => 'NE'],
                ['EFE_KEY' => 22, 'ENTIDAD_FEDERATIVA' => 'QUERÉTARO', 'ABREVIATURA' => 'QT'],
            ]),
            'https://api.test/api/estados/22/municipios' => Http::response([
                ['MUN_KEY' => 14, 'MUNICIPIO' => 'QUERÉTARO'],
                ['MUN_KEY' => 6, 'MUNICIPIO' => 'CORREGIDORA'],
            ]),
        ]);
    }

    public function test_the_http_client_maps_the_api_fields(): void
    {
        $this->fingirApi();
        $api = new ApiInfoMexico('https://api.test');

        $this->assertSame(
            [['clave' => 0, 'nombre' => 'NO ESPECIFICADO', 'abreviatura' => 'NE'], ['clave' => 22, 'nombre' => 'QUERÉTARO', 'abreviatura' => 'QT']],
            $api->entidades(),
        );
        $this->assertSame([['clave' => 14, 'nombre' => 'QUERÉTARO'], ['clave' => 6, 'nombre' => 'CORREGIDORA']], $api->municipios(22));
    }

    public function test_the_http_client_fails_on_http_errors_and_unexpected_payloads(): void
    {
        Http::fake([
            'https://api.test/api/estados' => Http::response('boom', 500),
            'https://api.test/api/estados/1/municipios' => Http::response(['no' => 'es una lista']),
            'https://api.test/api/estados/2/municipios' => Http::response([['MUN_KEY' => 1]]),
        ]);
        $api = new ApiInfoMexico('https://api.test');

        foreach ([fn () => $api->entidades(), fn () => $api->municipios(1), fn () => $api->municipios(2)] as $llamada) {
            try {
                $llamada();
                $this->fail('Se esperaba RuntimeException.');
            } catch (RuntimeException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    // --- Comando ---

    public function test_the_command_imports_from_the_configured_api_and_reports_counts(): void
    {
        $this->fingirApi();
        config(['services.api_info_mexico.url' => 'https://api.test', 'services.api_info_mexico.minimo_entidades' => 1]);

        $this->artisan('geografia:importar')
            ->expectsOutputToContain('1 entidades y 2 municipios')
            ->assertSuccessful();

        $this->assertSame(1, EntidadFederativa::count());
        $this->assertSame(2, Municipio::count());
    }

    public function test_the_command_fails_with_a_message_and_writes_nothing_when_the_api_is_down(): void
    {
        Http::fake(['https://api.test/*' => Http::response('boom', 500)]);
        config(['services.api_info_mexico.url' => 'https://api.test']);

        $this->artisan('geografia:importar')->assertFailed();

        $this->assertSame(0, EntidadFederativa::count());
    }
}
