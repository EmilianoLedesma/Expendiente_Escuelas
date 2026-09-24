<?php

namespace Tests\Feature\Application\Infraestructura;

use App\Application\EscuelaNiveles\MarcarPasoCompletado;
use App\Application\Excepciones\DatosInvalidos;
use App\Application\Excepciones\PrecondicionIncumplida;
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
use Tests\Concerns\CompletaPaso2;
use Tests\TestCase;

class RegistrarInfraestructuraNivelTest extends TestCase
{
    use CompletaPaso2;
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
        // WS-2.4b: RegistrarInfraestructuraNivel ahora exige Paso 2 completo antes de escribir.
        $this->completarPaso2($this->escuela->id);
    }

    private function escuelaNivel(string $claveNivel): EscuelaNivel
    {
        $nivel = NivelEducativo::where('clave', $claveNivel)->first();
        $estadoId = DB::table('estados_expediente')->where('clave', 'en_captura')->value('id');

        $escuelaNivel = EscuelaNivel::create([
            'escuela_id' => $this->escuela->id,
            'nivel_educativo_id' => $nivel->id,
            'estado_id' => $estadoId,
            'tipo_tramite' => 'alta_nueva',
        ]);
        // WS-2.4b: Infraestructura solo es alcanzable con Inmueble completado.
        (new MarcarPasoCompletado)->ejecutar($escuelaNivel->id, 'inmueble');

        return $escuelaNivel;
    }

    private function idTipo(string $clave): int
    {
        return (int) DB::table('tipos_espacios')->where('clave', $clave)->value('id');
    }

    // WS-2.4a: el caso de uso ahora valida que cada tipo_espacio/categoría
    // aplique al nivel en niveles_tipos_espacios / CategoriasSanitariosPorNivel,
    // así que el fixture ya no puede mezclar espacios/categorías exclusivos de
    // Inicial (filtro_recepcion, alumnado_maternal) con los de Básica (bodega,
    // biblioteca, alumnado_masculino/femenino) — $nivelClave elige el set.
    private function datos(int $numeroAulas = 6, string $nivelClave = 'primaria'): DatosInfraestructuraNivel
    {
        $esInicial = $nivelClave === 'inicial';

        $espacios = [
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
                'tipoEspacioId' => $this->idTipo($esInicial ? 'filtro_recepcion' : 'bodega'),
                'cantidad' => 2,
                'superficieM2' => 9.0,
                'capacidadPromedio' => null,
                'ventilacionNatural' => false,
                'iluminacionNatural' => false,
                'destinadoA' => $esInicial ? null : 'limpieza',
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
        ];

        if (! $esInicial) {
            $espacios[] = [
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
            ];
        }

        $sanitarios = $esInicial
            ? [
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
            ]
            : [
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
                    'categoria' => 'alumnado_femenino',
                    'cantidadRetretes' => 4,
                    'cantidadMingitorios' => 0,
                    'cantidadLavabos' => 4,
                    'superficieM2' => 12.0,
                    'ventilacionNatural' => true,
                    'iluminacionNatural' => true,
                    'cantidadBacinicas' => null,
                ],
            ];

        return new DatosInfraestructuraNivel(
            espacios: $espacios,
            sanitarios: $sanitarios,
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

        app(RegistrarInfraestructuraNivel::class)->ejecutar($this->plantel->id, $escuelaNivel->id, $this->datos(nivelClave: 'inicial'));

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

    // WS-2.1 fix round 1 — the use case must independently refuse to write an
    // espacio with no meaningful data, so an API caller can't create empty rows.
    public function test_espacio_sin_datos_significativos_no_escribe_fila(): void
    {
        $escuelaNivel = $this->escuelaNivel('primaria');

        $datos = new DatosInfraestructuraNivel(
            espacios: [
                [
                    'tipoEspacioId' => $this->idTipo('areas_verdes'),
                    'cantidad' => null,
                    'superficieM2' => null,
                    'capacidadPromedio' => null,
                    'ventilacionNatural' => false,
                    'iluminacionNatural' => false,
                    'destinadoA' => null,
                    'campoFutbol' => null,
                    'materialesBiblioteca' => [],
                ],
            ],
            sanitarios: [],
            numeroAulas: 6,
        );

        app(RegistrarInfraestructuraNivel::class)->ejecutar($this->plantel->id, $escuelaNivel->id, $datos);

        $this->assertDatabaseMissing('instalaciones_espacios', [
            'plantel_id' => $this->plantel->id,
            'tipo_espacio_id' => $this->idTipo('areas_verdes'),
        ]);
    }

    // WS-2 item 1 — un espacio cuyo único dato es campo_futbol (tipoSuperficie
    // o formato) debe escribirse: tieneDatosSignificativos() ignoraba
    // campoFutbol y el espacio se perdía en silencio aunque el formulario ya
    // lo hubiera construido.
    public function test_espacio_con_solo_campo_futbol_declarado_se_escribe(): void
    {
        $escuelaNivel = $this->escuelaNivel('primaria');

        $datos = new DatosInfraestructuraNivel(
            espacios: [
                [
                    'tipoEspacioId' => $this->idTipo('campo_futbol'),
                    'cantidad' => null,
                    'superficieM2' => null,
                    'capacidadPromedio' => null,
                    'ventilacionNatural' => null,
                    'iluminacionNatural' => null,
                    'destinadoA' => null,
                    'campoFutbol' => ['tipoSuperficie' => null, 'formato' => '7'],
                    'materialesBiblioteca' => [],
                ],
            ],
            sanitarios: [],
            numeroAulas: 6,
        );

        app(RegistrarInfraestructuraNivel::class)->ejecutar($this->plantel->id, $escuelaNivel->id, $datos);

        $this->assertDatabaseHas('instalaciones_espacios', [
            'plantel_id' => $this->plantel->id,
            'tipo_espacio_id' => $this->idTipo('campo_futbol'),
        ]);
        $campoId = DB::table('instalaciones_espacios')
            ->where('plantel_id', $this->plantel->id)
            ->where('tipo_espacio_id', $this->idTipo('campo_futbol'))
            ->value('id');
        $this->assertDatabaseHas('campos_futbol', [
            'instalacion_espacio_id' => $campoId,
            'formato' => '7',
        ]);
    }

    // Minor 8 — un sanitario totalmente vacío (todo null) no debe crear fila,
    // aunque venga de un caller de API que no filtre como lo hace el componente.
    public function test_sanitario_sin_datos_significativos_no_escribe_fila(): void
    {
        $escuelaNivel = $this->escuelaNivel('primaria');

        $datos = new DatosInfraestructuraNivel(
            espacios: [],
            sanitarios: [
                [
                    'categoria' => 'alumnado_masculino',
                    'cantidadRetretes' => null,
                    'cantidadMingitorios' => null,
                    'cantidadLavabos' => null,
                    'superficieM2' => null,
                    'ventilacionNatural' => false,
                    'iluminacionNatural' => false,
                    'cantidadBacinicas' => null,
                ],
            ],
            numeroAulas: 6,
        );

        app(RegistrarInfraestructuraNivel::class)->ejecutar($this->plantel->id, $escuelaNivel->id, $datos);

        $this->assertDatabaseMissing('sanitarios', [
            'plantel_id' => $this->plantel->id,
            'categoria' => 'alumnado_masculino',
        ]);
    }

    // WS-2.4a — el caso de uso no debe confiar en que el formulario ya filtró
    // por niveles_tipos_espacios: filtro_recepcion solo aplica a 'inicial'.
    public function test_rechaza_un_tipo_de_espacio_no_aplicable_al_nivel(): void
    {
        $escuelaNivel = $this->escuelaNivel('primaria');

        $datos = new DatosInfraestructuraNivel(
            espacios: [[
                'tipoEspacioId' => $this->idTipo('filtro_recepcion'),
                'cantidad' => 1,
                'superficieM2' => null,
                'capacidadPromedio' => null,
                'ventilacionNatural' => null,
                'iluminacionNatural' => null,
                'destinadoA' => null,
                'campoFutbol' => null,
                'materialesBiblioteca' => [],
            ]],
            sanitarios: [],
            numeroAulas: 6,
        );

        try {
            app(RegistrarInfraestructuraNivel::class)->ejecutar($this->plantel->id, $escuelaNivel->id, $datos);
            $this->fail('Se esperaba DatosInvalidos.');
        } catch (DatosInvalidos $e) {
            $this->assertArrayHasKey('espacios.'.$this->idTipo('filtro_recepcion').'.tipoEspacioId', $e->errores);
        }

        $this->assertDatabaseCount('instalaciones_espacios', 0);
    }

    public function test_rechaza_una_categoria_de_sanitario_no_aplicable_al_nivel(): void
    {
        // Primaria (Básica) no admite alumnado_maternal — es exclusivo de Inicial.
        $escuelaNivel = $this->escuelaNivel('primaria');

        $datos = new DatosInfraestructuraNivel(
            espacios: [],
            sanitarios: [[
                'categoria' => 'alumnado_maternal',
                'cantidadRetretes' => 2,
                'cantidadMingitorios' => 0,
                'cantidadLavabos' => 2,
                'superficieM2' => null,
                'ventilacionNatural' => null,
                'iluminacionNatural' => null,
                'cantidadBacinicas' => 5,
            ]],
            numeroAulas: 6,
        );

        try {
            app(RegistrarInfraestructuraNivel::class)->ejecutar($this->plantel->id, $escuelaNivel->id, $datos);
            $this->fail('Se esperaba DatosInvalidos.');
        } catch (DatosInvalidos $e) {
            $this->assertArrayHasKey('sanitarios.alumnado_maternal.categoria', $e->errores);
        }

        $this->assertDatabaseCount('sanitarios', 0);
    }

    // WS-2.4b — un caller que se salte CompuertaPaso3 no debe poder escribir
    // infraestructura si Paso 2 no está completo.
    public function test_rechaza_registrar_sin_paso_2_completo(): void
    {
        $solicitante = Solicitante::factory()->create();
        $plantel = Plantel::create(['calle' => 'Calle 2', 'colonia' => 'Centro', 'municipio' => 'Querétaro', 'codigo_postal' => '76000']);
        $escuela = Escuela::create(['plantel_id' => $plantel->id, 'solicitante_id' => $solicitante->id]);
        $nivel = NivelEducativo::where('clave', 'primaria')->first();
        $estadoId = DB::table('estados_expediente')->where('clave', 'en_captura')->value('id');
        $escuelaNivel = EscuelaNivel::create([
            'escuela_id' => $escuela->id,
            'nivel_educativo_id' => $nivel->id,
            'estado_id' => $estadoId,
            'tipo_tramite' => 'alta_nueva',
        ]);
        // Sin Paso 2 completo, ni el orden de Paso 3 ('inmueble' pendiente) se cumple.

        $this->expectException(PrecondicionIncumplida::class);

        app(RegistrarInfraestructuraNivel::class)->ejecutar($plantel->id, $escuelaNivel->id, $this->datos());
    }

    // WS-2.4b — con Paso 2 completo pero sin 'inmueble' marcado, el orden de
    // Paso 3 sigue rechazando la escritura.
    public function test_rechaza_registrar_cuando_inmueble_no_esta_completado(): void
    {
        $nivel = NivelEducativo::where('clave', 'primaria')->first();
        $estadoId = DB::table('estados_expediente')->where('clave', 'en_captura')->value('id');
        $escuelaNivel = EscuelaNivel::create([
            'escuela_id' => $this->escuela->id,
            'nivel_educativo_id' => $nivel->id,
            'estado_id' => $estadoId,
            'tipo_tramite' => 'alta_nueva',
        ]);
        // Deliberadamente NO se llama a MarcarPasoCompletado('inmueble').

        $this->expectException(PrecondicionIncumplida::class);

        app(RegistrarInfraestructuraNivel::class)->ejecutar($this->plantel->id, $escuelaNivel->id, $this->datos());
    }

    public function test_rechaza_numeros_negativos(): void
    {
        $escuelaNivel = $this->escuelaNivel('primaria');

        $datos = new DatosInfraestructuraNivel(
            espacios: [[
                'tipoEspacioId' => $this->idTipo('direccion'),
                'cantidad' => -1,
                'superficieM2' => null,
                'capacidadPromedio' => null,
                'ventilacionNatural' => null,
                'iluminacionNatural' => null,
                'destinadoA' => null,
                'campoFutbol' => null,
                'materialesBiblioteca' => [],
            ]],
            sanitarios: [],
            numeroAulas: -3,
        );

        try {
            app(RegistrarInfraestructuraNivel::class)->ejecutar($this->plantel->id, $escuelaNivel->id, $datos);
            $this->fail('Se esperaba DatosInvalidos.');
        } catch (DatosInvalidos $e) {
            $this->assertArrayHasKey('numeroAulas', $e->errores);
            $this->assertArrayHasKey('espacios.'.$this->idTipo('direccion').'.cantidad', $e->errores);
        }

        $this->assertDatabaseCount('instalaciones_espacios', 0);
        $this->assertDatabaseCount('aulas_nivel', 0);
    }
}
