<?php

namespace Tests\Feature\Application\Inmueble;

use App\Application\Excepciones\DatosInvalidos;
use App\Application\Inmueble\DatosInmuebleYaCapturados;
use App\Application\Inmueble\DTO\DatosInmueble;
use App\Application\Inmueble\RegistrarDatosInmueble;
use App\Models\Escuela;
use App\Models\EscuelaNivel;
use App\Models\NivelEducativo;
use App\Models\Plantel;
use App\Models\Solicitante;
use Database\Seeders\CatalogoMinimoSeeder;
use Database\Seeders\PasosCapturaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RegistrarDatosInmuebleTest extends TestCase
{
    use RefreshDatabase;

    private Plantel $plantel;

    private Escuela $escuela;

    protected function setUp(): void
    {
        parent::setUp();

        (new CatalogoMinimoSeeder)->run();
        (new PasosCapturaSeeder)->run();

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

    private function datos(float $metrosTotales = 1200.5): DatosInmueble
    {
        return new DatosInmueble(
            metrosTotales: $metrosTotales,
            metrosConstruidos: 640.25,
            colindanciaNorte: 'Calle Hidalgo',
            colindanciaSur: 'Predio particular',
            colindanciaEste: 'Av. Constituyentes',
            colindanciaOeste: 'Terreno baldío',
            latitud: 20.5887900,
            longitud: -100.3898800,
            areaCivicaM2: 180.0,
            tieneAstaBandera: true,
            serviciosCercanos: [
                ['nombre' => 'Cruz Roja Querétaro', 'tipo' => 'emergencia', 'esPublico' => false, 'distanciaValor' => 1.2, 'distanciaUnidad' => 'km'],
                ['nombre' => 'Centro de Salud Centro', 'tipo' => 'salud', 'esPublico' => true, 'distanciaValor' => 800.0, 'distanciaUnidad' => 'm'],
            ],
            estudiosActuales: [
                ['nivelEducativoId' => (int) DB::table('niveles_educativos')->where('clave', 'preescolar')->value('id'), 'otroNivelTexto' => null, 'numeroAlumnos' => 45],
                ['nivelEducativoId' => null, 'otroNivelTexto' => 'Academia de inglés', 'numeroAlumnos' => 30],
            ],
        );
    }

    public function test_escribe_las_columnas_del_plantel(): void
    {
        $escuelaNivel = $this->escuelaNivel('primaria');

        app(RegistrarDatosInmueble::class)->ejecutar($this->plantel->id, $escuelaNivel->id, $this->datos());

        $plantel = $this->plantel->fresh();
        $this->assertEquals(1200.5, (float) $plantel->metros_totales);
        $this->assertEquals(640.25, (float) $plantel->metros_construidos);
        $this->assertSame('Calle Hidalgo', $plantel->colindancia_norte);
        $this->assertSame('Terreno baldío', $plantel->colindancia_oeste);
        $this->assertEquals(180.0, (float) $plantel->area_civica_m2);
        $this->assertTrue((bool) $plantel->tiene_asta_bandera);
    }

    public function test_escribe_los_servicios_cercanos(): void
    {
        $escuelaNivel = $this->escuelaNivel('primaria');

        app(RegistrarDatosInmueble::class)->ejecutar($this->plantel->id, $escuelaNivel->id, $this->datos());

        $this->assertSame(2, DB::table('servicios_cercanos')->where('plantel_id', $this->plantel->id)->count());
        $this->assertDatabaseHas('servicios_cercanos', [
            'plantel_id' => $this->plantel->id,
            'nombre' => 'Cruz Roja Querétaro',
            'tipo' => 'emergencia',
            'distancia_unidad' => 'km',
        ]);
    }

    public function test_escribe_los_estudios_actuales_incluyendo_la_variante_otro(): void
    {
        $escuelaNivel = $this->escuelaNivel('primaria');

        app(RegistrarDatosInmueble::class)->ejecutar($this->plantel->id, $escuelaNivel->id, $this->datos());

        $this->assertSame(2, DB::table('inmueble_estudios_actuales')->where('plantel_id', $this->plantel->id)->count());
        $this->assertDatabaseHas('inmueble_estudios_actuales', [
            'plantel_id' => $this->plantel->id,
            'nivel_educativo_id' => null,
            'otro_nivel_texto' => 'Academia de inglés',
            'numero_alumnos' => 30,
        ]);
    }

    public function test_marca_el_paso_inmueble_como_completado(): void
    {
        $escuelaNivel = $this->escuelaNivel('primaria');

        app(RegistrarDatosInmueble::class)->ejecutar($this->plantel->id, $escuelaNivel->id, $this->datos());

        $pasoId = DB::table('pasos_captura')->where('clave', 'inmueble')->value('id');
        $this->assertDatabaseHas('escuela_nivel_pasos', [
            'escuela_nivel_id' => $escuelaNivel->id,
            'paso_captura_id' => $pasoId,
            'estado' => 'completado',
        ]);
    }

    public function test_un_segundo_nivel_no_reescribe_el_plantel_pero_si_marca_su_paso(): void
    {
        $primaria = $this->escuelaNivel('primaria');
        app(RegistrarDatosInmueble::class)->ejecutar($this->plantel->id, $primaria->id, $this->datos(1200.5));

        $preescolar = $this->escuelaNivel('preescolar');
        app(RegistrarDatosInmueble::class)->ejecutar($this->plantel->id, $preescolar->id, $this->datos(9999.0));

        $this->assertEquals(1200.5, (float) $this->plantel->fresh()->metros_totales);
        $this->assertSame(2, DB::table('servicios_cercanos')->where('plantel_id', $this->plantel->id)->count());
        $this->assertSame(2, DB::table('inmueble_estudios_actuales')->where('plantel_id', $this->plantel->id)->count());

        $pasoId = DB::table('pasos_captura')->where('clave', 'inmueble')->value('id');
        $this->assertDatabaseHas('escuela_nivel_pasos', [
            'escuela_nivel_id' => $preescolar->id,
            'paso_captura_id' => $pasoId,
            'estado' => 'completado',
        ]);
    }

    public function test_ya_capturados_es_falso_antes_y_verdadero_despues(): void
    {
        $escuelaNivel = $this->escuelaNivel('primaria');
        $yaCapturados = app(DatosInmuebleYaCapturados::class);

        $this->assertFalse($yaCapturados->ejecutar($this->plantel->id));

        app(RegistrarDatosInmueble::class)->ejecutar($this->plantel->id, $escuelaNivel->id, $this->datos());

        $this->assertTrue($yaCapturados->ejecutar($this->plantel->id));
    }

    // WS-2.4a — invariantes de entrada, para que un caller que se salte el
    // formulario no pueda escribir datos que violan un CHECK del DDL o un
    // invariante de negocio.

    public function test_rechaza_metros_totales_cero_o_negativos(): void
    {
        $escuelaNivel = $this->escuelaNivel('primaria');

        try {
            app(RegistrarDatosInmueble::class)->ejecutar($this->plantel->id, $escuelaNivel->id, $this->datos(0.0));
            $this->fail('Se esperaba DatosInvalidos.');
        } catch (DatosInvalidos $e) {
            $this->assertArrayHasKey('metrosTotales', $e->errores);
        }

        $this->assertNull($this->plantel->fresh()->metros_totales);
    }

    public function test_rechaza_latitud_fuera_de_rango(): void
    {
        $escuelaNivel = $this->escuelaNivel('primaria');
        $datos = new DatosInmueble(metrosTotales: 100.0, latitud: 95.0);

        try {
            app(RegistrarDatosInmueble::class)->ejecutar($this->plantel->id, $escuelaNivel->id, $datos);
            $this->fail('Se esperaba DatosInvalidos.');
        } catch (DatosInvalidos $e) {
            $this->assertArrayHasKey('latitud', $e->errores);
        }
    }

    public function test_rechaza_longitud_fuera_de_rango(): void
    {
        $escuelaNivel = $this->escuelaNivel('primaria');
        $datos = new DatosInmueble(metrosTotales: 100.0, longitud: -185.0);

        try {
            app(RegistrarDatosInmueble::class)->ejecutar($this->plantel->id, $escuelaNivel->id, $datos);
            $this->fail('Se esperaba DatosInvalidos.');
        } catch (DatosInvalidos $e) {
            $this->assertArrayHasKey('longitud', $e->errores);
        }
    }

    public function test_rechaza_tipo_de_servicio_cercano_fuera_del_enum(): void
    {
        $escuelaNivel = $this->escuelaNivel('primaria');
        $datos = new DatosInmueble(metrosTotales: 100.0, serviciosCercanos: [
            ['nombre' => 'X', 'tipo' => 'bomberos', 'esPublico' => null, 'distanciaValor' => null, 'distanciaUnidad' => null],
        ]);

        try {
            app(RegistrarDatosInmueble::class)->ejecutar($this->plantel->id, $escuelaNivel->id, $datos);
            $this->fail('Se esperaba DatosInvalidos.');
        } catch (DatosInvalidos $e) {
            $this->assertArrayHasKey('serviciosCercanos.0.tipo', $e->errores);
        }

        $this->assertSame(0, DB::table('servicios_cercanos')->count());
    }

    public function test_rechaza_unidad_de_distancia_fuera_del_enum(): void
    {
        $escuelaNivel = $this->escuelaNivel('primaria');
        $datos = new DatosInmueble(metrosTotales: 100.0, serviciosCercanos: [
            ['nombre' => 'X', 'tipo' => 'salud', 'esPublico' => null, 'distanciaValor' => 3.0, 'distanciaUnidad' => 'millas'],
        ]);

        try {
            app(RegistrarDatosInmueble::class)->ejecutar($this->plantel->id, $escuelaNivel->id, $datos);
            $this->fail('Se esperaba DatosInvalidos.');
        } catch (DatosInvalidos $e) {
            $this->assertArrayHasKey('serviciosCercanos.0.distanciaUnidad', $e->errores);
        }
    }

    public function test_rechaza_estudio_actual_sin_nivel_ni_texto_otro(): void
    {
        $escuelaNivel = $this->escuelaNivel('primaria');
        $datos = new DatosInmueble(metrosTotales: 100.0, estudiosActuales: [
            ['nivelEducativoId' => null, 'otroNivelTexto' => null, 'numeroAlumnos' => 10],
        ]);

        try {
            app(RegistrarDatosInmueble::class)->ejecutar($this->plantel->id, $escuelaNivel->id, $datos);
            $this->fail('Se esperaba DatosInvalidos.');
        } catch (DatosInvalidos $e) {
            $this->assertArrayHasKey('estudiosActuales.0.nivelEducativoId', $e->errores);
        }
    }

    public function test_rechaza_estudio_actual_con_nivel_y_texto_otro_a_la_vez(): void
    {
        $escuelaNivel = $this->escuelaNivel('primaria');
        $nivelId = (int) DB::table('niveles_educativos')->where('clave', 'preescolar')->value('id');
        $datos = new DatosInmueble(metrosTotales: 100.0, estudiosActuales: [
            ['nivelEducativoId' => $nivelId, 'otroNivelTexto' => 'Academia', 'numeroAlumnos' => 10],
        ]);

        try {
            app(RegistrarDatosInmueble::class)->ejecutar($this->plantel->id, $escuelaNivel->id, $datos);
            $this->fail('Se esperaba DatosInvalidos.');
        } catch (DatosInvalidos $e) {
            $this->assertArrayHasKey('estudiosActuales.0.nivelEducativoId', $e->errores);
        }
    }
}
