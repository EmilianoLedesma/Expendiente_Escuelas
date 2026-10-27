<?php

namespace Tests\Feature\Application\Infraestructura;

use App\Application\Infraestructura\ConsultarAnexoInstalaciones;
use App\Models\Escuela;
use App\Models\EscuelaNivel;
use App\Models\NivelEducativo;
use App\Models\Plantel;
use App\Models\Solicitante;
use Database\Seeders\CatalogoMinimoSeeder;
use Database\Seeders\TiposEspaciosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Anexo 2 Instalaciones, T1: el modelo de lectura que alimenta el PDF. Una sola plantilla
 * genérica; Inicial solo agrega sus filas propias (filtro, maternal, personal, bacinicas).
 */
class ConsultarAnexoInstalacionesTest extends TestCase
{
    use RefreshDatabase;

    private Plantel $plantel;

    private Escuela $escuela;

    protected function setUp(): void
    {
        parent::setUp();

        (new CatalogoMinimoSeeder)->run();
        (new TiposEspaciosSeeder)->run();

        $this->plantel = Plantel::create(['calle' => 'Calle 1', 'numero_ext' => '10', 'colonia' => 'Centro', 'municipio' => 'Querétaro', 'codigo_postal' => '76000']);
        $this->escuela = Escuela::create(['plantel_id' => $this->plantel->id, 'solicitante_id' => Solicitante::factory()->create()->id]);
    }

    private function nivel(string $clave, ?Escuela $escuela = null): EscuelaNivel
    {
        return EscuelaNivel::create([
            'escuela_id' => ($escuela ?? $this->escuela)->id,
            'nivel_educativo_id' => NivelEducativo::where('clave', $clave)->value('id'),
            'estado_id' => DB::table('estados_expediente')->where('clave', 'en_captura')->value('id'),
            'tipo_tramite' => 'alta_nueva',
        ]);
    }

    private function consultar(EscuelaNivel $nivel)
    {
        return app(ConsultarAnexoInstalaciones::class)->ejecutar($nivel);
    }

    private function espacio(string $clave, array $campos = []): int
    {
        return DB::table('instalaciones_espacios')->insertGetId($campos + [
            'plantel_id' => $this->plantel->id,
            'tipo_espacio_id' => (int) DB::table('tipos_espacios')->where('clave', $clave)->value('id'),
        ]);
    }

    private function sanitario(string $categoria, array $campos = [], ?int $bacinicas = null): void
    {
        $id = DB::table('sanitarios')->insertGetId($campos + ['plantel_id' => $this->plantel->id, 'categoria' => $categoria]);
        if ($bacinicas !== null) {
            DB::table('sanitarios_bacinicas')->insert(['sanitario_id' => $id, 'cantidad_bacinicas' => $bacinicas]);
        }
    }

    /** @return array<string, mixed>|null */
    private function linea($anexo, string $seccion, string $clave): ?array
    {
        foreach ($anexo->secciones[$seccion]['lineas'] ?? [] as $linea) {
            if ($linea['clave'] === $clave) {
                return $linea;
            }
        }

        return null;
    }

    /** Simula la matriz corregida de Inicial (T4/D8): sin cubículo, cancha ni campo de fútbol. */
    private function matrizInicialCorregida(): void
    {
        DB::table('niveles_tipos_espacios')
            ->where('nivel_educativo_id', NivelEducativo::where('clave', 'inicial')->value('id'))
            ->whereIn('tipo_espacio_id', DB::table('tipos_espacios')->whereIn('clave', ['cubiculo', 'cancha_usos_multiples', 'campo_futbol'])->pluck('id'))
            ->delete();
    }

    public function test_plantel_vacio_imprime_todo_en_blanco(): void
    {
        $anexo = $this->consultar($this->nivel('primaria'));

        $this->assertSame('Calle 1', $anexo->datosGenerales['calle']);
        $this->assertNull($anexo->datosGenerales['telefono']);
        $this->assertSame([], $anexo->estudiosActuales);
        $this->assertSame([], $anexo->serviciosCercanos);
        $this->assertNull($anexo->areaCivicaM2);
        $this->assertNull($anexo->tieneAstaBandera);
        $this->assertNull($anexo->aulas);

        $direccion = $this->linea($anexo, 'administrativo', 'direccion');
        $this->assertNotNull($direccion);
        $this->assertFalse($direccion['capturado']);
        foreach (['cantidad', 'superficieM2', 'capacidadPromedio', 'ventilacionNatural', 'iluminacionNatural', 'destinadoA'] as $campo) {
            $this->assertNull($direccion[$campo], $campo);
        }

        $this->assertSame(['alumnado_masculino', 'alumnado_femenino', 'personal_masculino', 'personal_femenino'], array_column($anexo->sanitarios, 'categoria'));
        $this->assertSame([false], array_unique(array_column($anexo->sanitarios, 'capturado')));
        $this->assertNotNull($this->linea($anexo, 'cubiculos', 'cubiculo'));
        $this->assertArrayNotHasKey('otros', $anexo->secciones);
    }

    public function test_primaria_completa(): void
    {
        $nivel = $this->nivel('primaria');
        DB::table('planteles')->where('id', $this->plantel->id)->update(['telefono' => '4421234567', 'area_civica_m2' => 120.5, 'tiene_asta_bandera' => true]);
        $this->espacio('direccion', ['cantidad' => 1, 'superficie_m2' => 18.5, 'ventilacion_natural' => true, 'iluminacion_natural' => false, 'destinado_a' => null]);
        $this->espacio('bodega', ['cantidad' => 1, 'destinado_a' => 'limpieza']);
        $this->espacio('cubiculo', ['cantidad' => 2, 'destinado_a' => 'Orientación educativa']);
        $futbol = $this->espacio('campo_futbol', ['cantidad' => 1, 'superficie_m2' => 800]);
        DB::table('campos_futbol')->insert(['instalacion_espacio_id' => $futbol, 'tipo_superficie' => 'pasto', 'formato' => '11']);
        $biblioteca = $this->espacio('biblioteca', ['cantidad' => 1, 'superficie_m2' => 45, 'capacidad_promedio' => 20]);
        DB::table('biblioteca_materiales')->insert(['instalacion_espacio_id' => $biblioteca, 'tipo_material_id' => DB::table('tipos_material_biblioteca')->where('clave', 'libros')->value('id'), 'numero_titulos' => 300, 'numero_volumenes' => 450]);
        $this->sanitario('alumnado_masculino', ['cantidad_retretes' => 4, 'cantidad_mingitorios' => 3, 'cantidad_lavabos' => 5, 'superficie_m2' => 12, 'ventilacion_natural' => true, 'iluminacion_natural' => true]);
        DB::table('aulas_nivel')->insert(['escuela_nivel_id' => $nivel->id, 'numero_aulas' => 6, 'superficie_m2' => 300]);
        DB::table('servicios_cercanos')->insert(['plantel_id' => $this->plantel->id, 'nombre' => 'Hospital General', 'tipo' => 'salud', 'es_publico' => true, 'distancia_valor' => 2, 'distancia_unidad' => 'km']);
        DB::table('inmueble_estudios_actuales')->insert([
            ['plantel_id' => $this->plantel->id, 'nivel_educativo_id' => NivelEducativo::where('clave', 'primaria')->value('id'), 'otro_nivel_texto' => null, 'numero_alumnos' => 200],
            ['plantel_id' => $this->plantel->id, 'nivel_educativo_id' => null, 'otro_nivel_texto' => 'Danza', 'numero_alumnos' => 15],
        ]);

        $anexo = $this->consultar($nivel);

        $this->assertSame('4421234567', $anexo->datosGenerales['telefono']);
        $this->assertSame('120.50', $anexo->areaCivicaM2);
        $this->assertTrue($anexo->tieneAstaBandera);

        $direccion = $this->linea($anexo, 'administrativo', 'direccion');
        $this->assertTrue($direccion['capturado']);
        $this->assertSame(1, $direccion['cantidad']);
        $this->assertSame('18.50', $direccion['superficieM2']);
        $this->assertTrue($direccion['ventilacionNatural']);
        $this->assertFalse($direccion['iluminacionNatural']);
        $this->assertSame('limpieza', $this->linea($anexo, 'administrativo', 'bodega')['destinadoA']);
        $this->assertSame('Orientación educativa', $this->linea($anexo, 'cubiculos', 'cubiculo')['destinadoA']);
        $this->assertSame(['tipoSuperficie' => 'pasto', 'formato' => '11'], $this->linea($anexo, 'recreativo_deportivo', 'campo_futbol')['campoFutbol']);

        $libros = collect($this->linea($anexo, 'especial', 'biblioteca')['materiales'])->firstWhere('clave', 'libros');
        $this->assertSame([300, 450], [$libros['numeroTitulos'], $libros['numeroVolumenes']]);
        $periodicos = collect($this->linea($anexo, 'especial', 'biblioteca')['materiales'])->firstWhere('clave', 'periodicos');
        $this->assertNull($periodicos['numeroTitulos']);

        $masculino = $anexo->sanitarios[0];
        $this->assertSame('alumnado_masculino', $masculino['categoria']);
        $this->assertTrue($masculino['capturado']);
        $this->assertSame([4, 3, 5], [$masculino['cantidadRetretes'], $masculino['cantidadMingitorios'], $masculino['cantidadLavabos']]);
        $this->assertFalse($anexo->sanitarios[1]['capturado']);

        $this->assertSame(['numeroAulas' => 6, 'superficieM2' => '300.00'], $anexo->aulas);
        $this->assertSame('Hospital General', $anexo->serviciosCercanos[0]['nombre']);
        $this->assertSame(['Primaria', 'Danza'], array_column($anexo->estudiosActuales, 'nombre'));
        $this->assertSame([200, 15], array_column($anexo->estudiosActuales, 'numeroAlumnos'));
        $this->assertArrayNotHasKey('otros', $anexo->secciones);
    }

    public function test_preescolar_completo_sin_biblioteca_pero_con_sus_propios_espacios(): void
    {
        $nivel = $this->nivel('preescolar');
        $this->espacio('subdireccion', ['cantidad' => 1]);
        $this->espacio('taller', ['cantidad' => 1, 'superficie_m2' => 30]);
        $this->sanitario('personal_femenino', ['cantidad_retretes' => 2]);
        DB::table('aulas_nivel')->insert(['escuela_nivel_id' => $nivel->id, 'numero_aulas' => 3, 'superficie_m2' => 90]);

        $anexo = $this->consultar($nivel);

        $this->assertTrue($this->linea($anexo, 'administrativo', 'subdireccion')['capturado']);
        $this->assertTrue($this->linea($anexo, 'especial', 'taller')['capturado']);
        $this->assertNull($this->linea($anexo, 'especial', 'biblioteca'), 'biblioteca no aplica a Preescolar');
        $this->assertNull($this->linea($anexo, 'administrativo', 'filtro_recepcion'), 'filtro es solo de Inicial');
        $this->assertSame(3, $anexo->aulas['numeroAulas']);
        $this->assertSame(2, collect($anexo->sanitarios)->firstWhere('categoria', 'personal_femenino')['cantidadRetretes']);
        $this->assertArrayNotHasKey('otros', $anexo->secciones);
    }

    public function test_inicial_completo_con_sus_filas_propias_y_sin_cubiculos(): void
    {
        $this->matrizInicialCorregida();
        $nivel = $this->nivel('inicial');
        $this->espacio('filtro_recepcion', ['cantidad' => 1, 'superficie_m2' => 6]);
        $this->sanitario('alumnado_maternal', ['cantidad_retretes' => 3, 'cantidad_lavabos' => 3], bacinicas: 4);
        $this->sanitario('personal', ['cantidad_retretes' => 1]);

        $anexo = $this->consultar($nivel);

        $this->assertTrue($this->linea($anexo, 'administrativo', 'filtro_recepcion')['capturado']);
        $this->assertArrayNotHasKey('cubiculos', $anexo->secciones);
        $this->assertNull($this->linea($anexo, 'recreativo_deportivo', 'cancha_usos_multiples'));
        $this->assertNotNull($this->linea($anexo, 'recreativo_deportivo', 'chapoteadero'));
        $this->assertNull($this->linea($anexo, 'administrativo', 'subdireccion'));

        $this->assertSame(['alumnado_maternal', 'personal'], array_column($anexo->sanitarios, 'categoria'));
        $this->assertSame(4, $anexo->sanitarios[0]['cantidadBacinicas']);
        $this->assertNull($anexo->sanitarios[1]['cantidadBacinicas']);
        $this->assertArrayNotHasKey('otros', $anexo->secciones);
    }

    public function test_un_segundo_nivel_ve_lo_compartido_y_sus_propias_aulas(): void
    {
        $primaria = $this->nivel('primaria');
        $secundaria = $this->nivel('secundaria');
        $this->espacio('direccion', ['cantidad' => 1]);
        $this->sanitario('alumnado_femenino', ['cantidad_retretes' => 5]);
        DB::table('aulas_nivel')->insert([
            ['escuela_nivel_id' => $primaria->id, 'numero_aulas' => 6, 'superficie_m2' => 300],
            ['escuela_nivel_id' => $secundaria->id, 'numero_aulas' => 9, 'superficie_m2' => 450],
        ]);

        $anexo = $this->consultar($secundaria);

        $this->assertTrue($this->linea($anexo, 'administrativo', 'direccion')['capturado']);
        $this->assertSame(5, collect($anexo->sanitarios)->firstWhere('categoria', 'alumnado_femenino')['cantidadRetretes']);
        $this->assertSame(['numeroAulas' => 9, 'superficieM2' => '450.00'], $anexo->aulas);
        $this->assertSame(6, $this->consultar($primaria)->aulas['numeroAulas']);
    }

    public function test_fila_capturada_en_tipo_que_ya_no_aplica_se_imprime_en_otros(): void
    {
        $this->matrizInicialCorregida();
        $nivel = $this->nivel('inicial');
        $this->espacio('cubiculo', ['cantidad' => 1, 'destinado_a' => 'Orientación educativa']);
        $campo = $this->espacio('campo_futbol', ['cantidad' => 1, 'superficie_m2' => 500]);
        DB::table('campos_futbol')->insert(['instalacion_espacio_id' => $campo, 'tipo_superficie' => 'pasto', 'formato' => '7']);
        $this->sanitario('alumnado_masculino', ['cantidad_retretes' => 2]);

        $anexo = $this->consultar($nivel);

        $this->assertArrayNotHasKey('cubiculos', $anexo->secciones);
        $this->assertSame('Orientación educativa', $this->linea($anexo, 'otros', 'cubiculo')['destinadoA']);
        $this->assertTrue($this->linea($anexo, 'otros', 'cubiculo')['capturado']);
        $this->assertSame('7', $this->linea($anexo, 'otros', 'campo_futbol')['campoFutbol']['formato']);
        $this->assertNull($this->linea($anexo, 'recreativo_deportivo', 'campo_futbol'));
        $this->assertContains('alumnado_masculino', array_column($anexo->sanitarios, 'categoria'), 'sanitario capturado fuera de la categoría del nivel no se pierde');
    }

    public function test_asta_bandera_nula_sigue_nula_y_falso_sigue_falso(): void
    {
        $nivel = $this->nivel('primaria');
        $this->assertNull($this->consultar($nivel)->tieneAstaBandera);

        DB::table('planteles')->where('id', $this->plantel->id)->update(['tiene_asta_bandera' => false]);
        $this->assertFalse($this->consultar($nivel)->tieneAstaBandera);
    }
}
