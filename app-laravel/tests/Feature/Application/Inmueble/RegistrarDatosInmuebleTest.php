<?php

namespace Tests\Feature\Application\Inmueble;

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
}
