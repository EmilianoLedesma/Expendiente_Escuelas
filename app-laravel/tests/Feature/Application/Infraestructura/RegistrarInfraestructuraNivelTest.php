<?php

namespace Tests\Feature\Application\Infraestructura;

use App\Application\Infraestructura\DTO\DatosInfraestructuraNivel;
use App\Application\Infraestructura\InfraestructuraYaCapturada;
use App\Application\Infraestructura\RegistrarInfraestructuraNivel;
use App\Models\Escuela;
use App\Models\EscuelaNivel;
use App\Models\NivelEducativo;
use App\Models\Plantel;
use App\Models\Solicitante;
use Database\Seeders\CatalogoMinimoSeeder;
use Database\Seeders\PasosCapturaSeeder;
use Database\Seeders\TiposEspaciosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RegistrarInfraestructuraNivelTest extends TestCase
{
    use RefreshDatabase;

    private Plantel $plantel;

    private Escuela $escuela;

    protected function setUp(): void
    {
        parent::setUp();

        (new CatalogoMinimoSeeder)->run();
        (new PasosCapturaSeeder)->run();
        (new TiposEspaciosSeeder)->run();

        $solicitante = Solicitante::factory()->create();
        $this->plantel = Plantel::create(['calle' => 'Calle 1', 'colonia' => 'Centro', 'municipio' => 'Querétaro', 'codigo_postal' => '76000']);
        $this->escuela = Escuela::create(['plantel_id' => $this->plantel->id, 'solicitante_id' => $solicitante->id]);
    }

    private function escuelaNivel(string $claveNivel): EscuelaNivel
    {
        $nivel = NivelEducativo::where('clave', $claveNivel)->first();
        $estadoId = DB::table('estados_expediente')->where('clave', 'en_captura')->value('id');

        return EscuelaNivel::create([
            'escuela_id' => $this->escuela->id,
            'nivel_educativo_id' => $nivel->id,
            'estado_id' => $estadoId,
            'tipo_tramite' => 'alta_nueva',
        ]);
    }

    private function idTipo(string $clave): int
    {
        return (int) DB::table('tipos_espacios')->where('clave', $clave)->value('id');
    }

    private function datos(int $numeroAulas = 6): DatosInfraestructuraNivel
    {
        return new DatosInfraestructuraNivel(
            espacios: [
                [
                    'tipoEspacioId' => $this->idTipo('direccion'),
                    'cantidad' => 1,
                    'superficieM2' => 18.5,
                    'capacidadPromedio' => 3,
                    'ventilacionNatural' => true,
                    'iluminacionNatural' => true,
                    'destinadoA' => null,
                    'campoFutbol' => null,
                    'materialesBiblioteca' => [],
                ],
                [
                    'tipoEspacioId' => $this->idTipo('bodega'),
                    'cantidad' => 2,
                    'superficieM2' => 9.0,
                    'capacidadPromedio' => null,
                    'ventilacionNatural' => false,
                    'iluminacionNatural' => false,
                    'destinadoA' => 'limpieza',
                    'campoFutbol' => null,
                    'materialesBiblioteca' => [],
                ],
                [
                    'tipoEspacioId' => $this->idTipo('campo_futbol'),
                    'cantidad' => 1,
                    'superficieM2' => 800.0,
                    'capacidadPromedio' => null,
                    'ventilacionNatural' => null,
                    'iluminacionNatural' => null,
                    'destinadoA' => null,
                    'campoFutbol' => ['tipoSuperficie' => 'pasto sintético', 'formato' => '7'],
                    'materialesBiblioteca' => [],
                ],
                [
                    'tipoEspacioId' => $this->idTipo('biblioteca'),
                    'cantidad' => 1,
                    'superficieM2' => 45.0,
                    'capacidadPromedio' => 20,
                    'ventilacionNatural' => true,
                    'iluminacionNatural' => true,
                    'destinadoA' => null,
                    'campoFutbol' => null,
                    'materialesBiblioteca' => [
                        ['tipoMaterialId' => (int) DB::table('tipos_material_biblioteca')->where('clave', 'libros')->value('id'), 'numeroTitulos' => 300, 'numeroVolumenes' => 450],
                    ],
                ],
            ],
            sanitarios: [
                [
                    'categoria' => 'alumnado_masculino',
                    'cantidadRetretes' => 4,
                    'cantidadMingitorios' => 3,
                    'cantidadLavabos' => 4,
                    'superficieM2' => 12.0,
                    'ventilacionNatural' => true,
                    'iluminacionNatural' => true,
                    'cantidadBacinicas' => null,
                ],
                [
                    'categoria' => 'alumnado_maternal',
                    'cantidadRetretes' => 2,
                    'cantidadMingitorios' => 0,
                    'cantidadLavabos' => 2,
                    'superficieM2' => 8.0,
                    'ventilacionNatural' => true,
                    'iluminacionNatural' => true,
                    'cantidadBacinicas' => 5,
                ],
            ],
            numeroAulas: $numeroAulas,
            superficieAulasM2: 240.0,
        );
    }

    public function test_escribe_espacios_sanitarios_y_aulas(): void
    {
        $escuelaNivel = $this->escuelaNivel('primaria');

        app(RegistrarInfraestructuraNivel::class)->ejecutar($this->plantel->id, $escuelaNivel->id, $this->datos());

        $this->assertSame(4, DB::table('instalaciones_espacios')->where('plantel_id', $this->plantel->id)->count());
        $this->assertSame(2, DB::table('sanitarios')->where('plantel_id', $this->plantel->id)->count());
        $this->assertDatabaseHas('aulas_nivel', [
            'escuela_nivel_id' => $escuelaNivel->id,
            'numero_aulas' => 6,
        ]);
    }

    public function test_guarda_el_tipo_de_bodega_en_destinado_a(): void
    {
        $escuelaNivel = $this->escuelaNivel('primaria');

        app(RegistrarInfraestructuraNivel::class)->ejecutar($this->plantel->id, $escuelaNivel->id, $this->datos());

        $this->assertDatabaseHas('instalaciones_espacios', [
            'plantel_id' => $this->plantel->id,
            'tipo_espacio_id' => $this->idTipo('bodega'),
            'destinado_a' => 'limpieza',
        ]);
    }

    public function test_escribe_las_extensiones_de_campo_futbol_y_biblioteca(): void
    {
        $escuelaNivel = $this->escuelaNivel('primaria');

        app(RegistrarInfraestructuraNivel::class)->ejecutar($this->plantel->id, $escuelaNivel->id, $this->datos());

        $campoId = DB::table('instalaciones_espacios')
            ->where('plantel_id', $this->plantel->id)
            ->where('tipo_espacio_id', $this->idTipo('campo_futbol'))
            ->value('id');
        $this->assertDatabaseHas('campos_futbol', [
            'instalacion_espacio_id' => $campoId,
            'tipo_superficie' => 'pasto sintético',
            'formato' => '7',
        ]);

        $bibliotecaId = DB::table('instalaciones_espacios')
            ->where('plantel_id', $this->plantel->id)
            ->where('tipo_espacio_id', $this->idTipo('biblioteca'))
            ->value('id');
        $this->assertDatabaseHas('biblioteca_materiales', [
            'instalacion_espacio_id' => $bibliotecaId,
            'numero_titulos' => 300,
            'numero_volumenes' => 450,
        ]);
    }

    public function test_escribe_bacinicas_solo_donde_se_declararon(): void
    {
        $escuelaNivel = $this->escuelaNivel('inicial');

        app(RegistrarInfraestructuraNivel::class)->ejecutar($this->plantel->id, $escuelaNivel->id, $this->datos());

        $this->assertSame(1, DB::table('sanitarios_bacinicas')->count());
        $maternalId = DB::table('sanitarios')
            ->where('plantel_id', $this->plantel->id)
            ->where('categoria', 'alumnado_maternal')
            ->value('id');
        $this->assertDatabaseHas('sanitarios_bacinicas', ['sanitario_id' => $maternalId, 'cantidad_bacinicas' => 5]);
    }

    public function test_marca_el_paso_infraestructura_como_completado(): void
    {
        $escuelaNivel = $this->escuelaNivel('primaria');

        app(RegistrarInfraestructuraNivel::class)->ejecutar($this->plantel->id, $escuelaNivel->id, $this->datos());

        $pasoId = DB::table('pasos_captura')->where('clave', 'infraestructura')->value('id');
        $this->assertDatabaseHas('escuela_nivel_pasos', [
            'escuela_nivel_id' => $escuelaNivel->id,
            'paso_captura_id' => $pasoId,
            'estado' => 'completado',
        ]);
    }

    public function test_un_segundo_nivel_del_mismo_plantel_no_duplica_datos_plantel_pero_si_escribe_sus_aulas(): void
    {
        $primaria = $this->escuelaNivel('primaria');
        app(RegistrarInfraestructuraNivel::class)->ejecutar($this->plantel->id, $primaria->id, $this->datos(6));

        $preescolar = $this->escuelaNivel('preescolar');
        app(RegistrarInfraestructuraNivel::class)->ejecutar($this->plantel->id, $preescolar->id, $this->datos(3));

        $this->assertSame(4, DB::table('instalaciones_espacios')->where('plantel_id', $this->plantel->id)->count());
        $this->assertSame(2, DB::table('sanitarios')->where('plantel_id', $this->plantel->id)->count());
        $this->assertDatabaseHas('aulas_nivel', ['escuela_nivel_id' => $primaria->id, 'numero_aulas' => 6]);
        $this->assertDatabaseHas('aulas_nivel', ['escuela_nivel_id' => $preescolar->id, 'numero_aulas' => 3]);
    }

    public function test_reenviar_el_mismo_nivel_actualiza_sus_aulas_en_vez_de_duplicarlas(): void
    {
        $escuelaNivel = $this->escuelaNivel('primaria');

        app(RegistrarInfraestructuraNivel::class)->ejecutar($this->plantel->id, $escuelaNivel->id, $this->datos(6));
        app(RegistrarInfraestructuraNivel::class)->ejecutar($this->plantel->id, $escuelaNivel->id, $this->datos(8));

        $this->assertSame(1, DB::table('aulas_nivel')->where('escuela_nivel_id', $escuelaNivel->id)->count());
        $this->assertDatabaseHas('aulas_nivel', ['escuela_nivel_id' => $escuelaNivel->id, 'numero_aulas' => 8]);
    }

    /** @return list<array<string, mixed>> */
    private function soloSanitarios(): array
    {
        return [
            [
                'categoria' => 'alumnado_masculino',
                'cantidadRetretes' => 4,
                'cantidadMingitorios' => 3,
                'cantidadLavabos' => 4,
                'superficieM2' => 12.0,
                'ventilacionNatural' => true,
                'iluminacionNatural' => true,
                'cantidadBacinicas' => null,
            ],
        ];
    }

    public function test_sanitarios_sin_espacios_marcan_el_plantel_como_capturado_y_no_se_duplican(): void
    {
        $primaria = $this->escuelaNivel('primaria');
        $yaCapturada = app(InfraestructuraYaCapturada::class);

        $datosSoloSanitarios = new DatosInfraestructuraNivel(
            espacios: [],
            sanitarios: $this->soloSanitarios(),
            numeroAulas: 6,
        );
        app(RegistrarInfraestructuraNivel::class)->ejecutar($this->plantel->id, $primaria->id, $datosSoloSanitarios);

        $this->assertTrue($yaCapturada->ejecutar($this->plantel->id));
        $this->assertSame(1, DB::table('sanitarios')->where('plantel_id', $this->plantel->id)->count());

        $preescolar = $this->escuelaNivel('preescolar');
        app(RegistrarInfraestructuraNivel::class)->ejecutar($this->plantel->id, $preescolar->id, $datosSoloSanitarios);

        $this->assertSame(1, DB::table('sanitarios')->where('plantel_id', $this->plantel->id)->count());
    }

    public function test_ya_capturada_es_falso_antes_y_verdadero_despues(): void
    {
        $escuelaNivel = $this->escuelaNivel('primaria');
        $yaCapturada = app(InfraestructuraYaCapturada::class);

        $this->assertFalse($yaCapturada->ejecutar($this->plantel->id));

        app(RegistrarInfraestructuraNivel::class)->ejecutar($this->plantel->id, $escuelaNivel->id, $this->datos());

        $this->assertTrue($yaCapturada->ejecutar($this->plantel->id));
    }

    // WS-2.1 — the use case itself must not drop an espacio whose cantidad is null
    // but that carries other data (e.g. superficie for a recreational space per Anexo 2).
    public function test_escribe_un_espacio_con_cantidad_null_y_superficie_declarada(): void
    {
        $escuelaNivel = $this->escuelaNivel('primaria');

        $datos = new DatosInfraestructuraNivel(
            espacios: [
                [
                    'tipoEspacioId' => $this->idTipo('areas_verdes'),
                    'cantidad' => null,
                    'superficieM2' => 120.0,
                    'capacidadPromedio' => null,
                    'ventilacionNatural' => null,
                    'iluminacionNatural' => null,
                    'destinadoA' => null,
                    'campoFutbol' => null,
                    'materialesBiblioteca' => [],
                ],
            ],
            sanitarios: [],
            numeroAulas: 6,
        );

        app(RegistrarInfraestructuraNivel::class)->ejecutar($this->plantel->id, $escuelaNivel->id, $datos);

        $this->assertDatabaseHas('instalaciones_espacios', [
            'plantel_id' => $this->plantel->id,
            'tipo_espacio_id' => $this->idTipo('areas_verdes'),
            'cantidad' => null,
            'superficie_m2' => 120.0,
        ]);
    }
}
