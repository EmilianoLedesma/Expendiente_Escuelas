<?php

namespace Tests\Feature\Application\Inmueble;

use App\Application\Excepciones\DatosInvalidos;
use App\Application\Excepciones\PrecondicionIncumplida;
use App\Application\Inmueble\DatosInmuebleYaCapturados;
use App\Application\Inmueble\DTO\DatosInmueble;
use App\Application\Inmueble\RegistrarDatosInmueble;
use App\Application\Tramite\TramiteEditable;
use App\Models\Escuela;
use App\Models\EscuelaNivel;
use App\Models\NivelEducativo;
use App\Models\Plantel;
use App\Models\Solicitante;
use Database\Seeders\CatalogoMinimoSeeder;
use Database\Seeders\PasosCapturaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CompletaPaso2;
use Tests\Concerns\CompletaPaso24;
use Tests\TestCase;

class RegistrarDatosInmuebleTest extends TestCase
{
    use CompletaPaso2;
    use CompletaPaso24;
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
        // WS-2.4b: RegistrarDatosInmueble ahora exige Paso 2 completo antes de escribir.
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
        $this->completarPaso24($escuelaNivel->id);

        return $escuelaNivel;
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

    /** WS-7b-2: un segundo nivel reemplaza el plantel mientras el otro no tenga datos propios de Paso 3 y lo conserva cuando sí. */
    public function test_un_segundo_nivel_reemplaza_el_plantel_sin_bloqueo_y_lo_conserva_con_bloqueo(): void
    {
        $primaria = $this->escuelaNivel('primaria');
        app(RegistrarDatosInmueble::class)->ejecutar($this->plantel->id, $primaria->id, $this->datos(1200.5));

        $preescolar = $this->escuelaNivel('preescolar');
        app(RegistrarDatosInmueble::class)->ejecutar($this->plantel->id, $preescolar->id, $this->datos(9999.0));

        $this->assertEquals(9999.0, (float) $this->plantel->fresh()->metros_totales);
        $this->assertSame(2, DB::table('servicios_cercanos')->where('plantel_id', $this->plantel->id)->count());
        $this->assertSame(2, DB::table('inmueble_estudios_actuales')->where('plantel_id', $this->plantel->id)->count());

        // Primaria declara datos propios de Paso 3: el plantel queda bloqueado para preescolar.
        $primaria->update(['modalidad' => 'escolarizada']);
        app(RegistrarDatosInmueble::class)->ejecutar($this->plantel->id, $preescolar->id, $this->datos(5555.0));

        $this->assertEquals(9999.0, (float) $this->plantel->fresh()->metros_totales);
        $this->assertSame(2, DB::table('servicios_cercanos')->where('plantel_id', $this->plantel->id)->count());

        $pasoId = DB::table('pasos_captura')->where('clave', 'inmueble')->value('id');
        $this->assertDatabaseHas('escuela_nivel_pasos', [
            'escuela_nivel_id' => $preescolar->id,
            'paso_captura_id' => $pasoId,
            'estado' => 'completado',
        ]);
    }

    public function test_reeditar_reemplaza_campos_y_conjuntos_cuando_no_esta_bloqueado(): void
    {
        $escuelaNivel = $this->escuelaNivel('primaria');
        app(RegistrarDatosInmueble::class)->ejecutar($this->plantel->id, $escuelaNivel->id, $this->datos(1200.5));

        $nuevos = new DatosInmueble(
            metrosTotales: 800.0,
            metrosConstruidos: 400.0,
            tieneAstaBandera: false,
            serviciosCercanos: [
                ['nombre' => 'Hospital General', 'tipo' => 'salud', 'esPublico' => true, 'distanciaValor' => 2.0, 'distanciaUnidad' => 'km'],
            ],
        );
        app(RegistrarDatosInmueble::class)->ejecutar($this->plantel->id, $escuelaNivel->id, $nuevos);

        $plantel = $this->plantel->fresh();
        $this->assertEquals(800.0, (float) $plantel->metros_totales);
        $this->assertNull($plantel->colindancia_norte);
        $this->assertNull($plantel->latitud);
        $this->assertFalse((bool) $plantel->tiene_asta_bandera);
        $this->assertSame(['Hospital General'], DB::table('servicios_cercanos')->where('plantel_id', $this->plantel->id)->pluck('nombre')->all());
        $this->assertSame(0, DB::table('inmueble_estudios_actuales')->where('plantel_id', $this->plantel->id)->count());
    }

    /** Bloqueado: solo se completan campos nulos; false es un valor, null es completable. */
    public function test_bloqueado_completa_solo_campos_nulos_y_no_cambia_los_que_tienen_valor(): void
    {
        $primaria = $this->escuelaNivel('primaria');
        $preescolar = $this->escuelaNivel('preescolar');
        app(RegistrarDatosInmueble::class)->ejecutar($this->plantel->id, $primaria->id, new DatosInmueble(
            metrosTotales: 1000.0,
            metrosConstruidos: 500.0,
            colindanciaNorte: 'Calle Norte',
            tieneAstaBandera: false,
        ));
        $primaria->update(['modalidad' => 'escolarizada']);

        app(RegistrarDatosInmueble::class)->ejecutar($this->plantel->id, $preescolar->id, new DatosInmueble(
            metrosTotales: 7777.0,
            metrosConstruidos: 7777.0,
            colindanciaNorte: 'Otra calle',
            colindanciaSur: 'Calle Sur',
            latitud: 20.5,
            tieneAstaBandera: true,
        ));

        $plantel = $this->plantel->fresh();
        $this->assertEquals(1000.0, (float) $plantel->metros_totales);
        $this->assertEquals(500.0, (float) $plantel->metros_construidos);
        $this->assertSame('Calle Norte', $plantel->colindancia_norte);
        $this->assertSame('Calle Sur', $plantel->colindancia_sur);
        $this->assertEquals(20.5, (float) $plantel->latitud);
        $this->assertFalse((bool) $plantel->tiene_asta_bandera, 'false es un valor: no se sobrescribe');
        $this->assertNull($plantel->longitud);
    }

    public function test_bloqueado_completa_la_bandera_nula(): void
    {
        $primaria = $this->escuelaNivel('primaria');
        $preescolar = $this->escuelaNivel('preescolar');
        app(RegistrarDatosInmueble::class)->ejecutar($this->plantel->id, $primaria->id, new DatosInmueble(metrosTotales: 1000.0, metrosConstruidos: 500.0));
        $primaria->update(['modalidad' => 'escolarizada']);

        app(RegistrarDatosInmueble::class)->ejecutar($this->plantel->id, $preescolar->id, new DatosInmueble(metrosTotales: 1.0, metrosConstruidos: 1.0, tieneAstaBandera: false));

        $this->assertFalse($this->plantel->fresh()->tiene_asta_bandera);
        $this->assertNotNull($this->plantel->fresh()->tiene_asta_bandera);
    }

    public function test_bloqueado_inserta_servicios_solo_si_no_habia_ninguno(): void
    {
        $primaria = $this->escuelaNivel('primaria');
        $preescolar = $this->escuelaNivel('preescolar');
        app(RegistrarDatosInmueble::class)->ejecutar($this->plantel->id, $primaria->id, new DatosInmueble(metrosTotales: 1000.0, metrosConstruidos: 500.0));
        $primaria->update(['modalidad' => 'escolarizada']);

        // Plantel sin servicios ni estudios: el nivel bloqueado los aporta.
        app(RegistrarDatosInmueble::class)->ejecutar($this->plantel->id, $preescolar->id, $this->datos());
        $this->assertSame(2, DB::table('servicios_cercanos')->where('plantel_id', $this->plantel->id)->count());
        $this->assertSame(2, DB::table('inmueble_estudios_actuales')->where('plantel_id', $this->plantel->id)->count());

        // Ya los hay: un segundo envío bloqueado no los toca.
        $otros = new DatosInmueble(
            metrosTotales: 1.0,
            metrosConstruidos: 1.0,
            serviciosCercanos: [['nombre' => 'Otro', 'tipo' => 'salud', 'esPublico' => null, 'distanciaValor' => null, 'distanciaUnidad' => null]],
            estudiosActuales: [['nivelEducativoId' => null, 'otroNivelTexto' => 'Otro', 'numeroAlumnos' => 1]],
        );
        app(RegistrarDatosInmueble::class)->ejecutar($this->plantel->id, $preescolar->id, $otros);

        $this->assertSame(2, DB::table('servicios_cercanos')->where('plantel_id', $this->plantel->id)->count());
        $this->assertDatabaseMissing('servicios_cercanos', ['nombre' => 'Otro']);
        $this->assertDatabaseMissing('inmueble_estudios_actuales', ['otro_nivel_texto' => 'Otro']);
    }

    public function test_un_tramite_hermano_enviado_rechaza_la_edicion(): void
    {
        $escuelaNivel = $this->escuelaNivel('primaria');
        $otraEscuela = Escuela::create(['plantel_id' => $this->plantel->id, 'solicitante_id' => Solicitante::factory()->create()->id]);
        EscuelaNivel::create([
            'escuela_id' => $otraEscuela->id,
            'nivel_educativo_id' => NivelEducativo::where('clave', 'secundaria')->value('id'),
            'estado_id' => DB::table('estados_expediente')->where('clave', '!=', 'en_captura')->value('id'),
            'tipo_tramite' => 'alta_nueva',
        ]);

        try {
            app(RegistrarDatosInmueble::class)->ejecutar($this->plantel->id, $escuelaNivel->id, $this->datos());
            $this->fail('Se esperaba PrecondicionIncumplida.');
        } catch (PrecondicionIncumplida $e) {
            $this->assertSame(TramiteEditable::ENVIADO, $e->etapaFaltante);
        }
        $this->assertNull($this->plantel->fresh()->metros_totales);
    }

    public function test_la_edicion_toma_el_bloqueo_de_todos_los_niveles_del_plantel_primero(): void
    {
        $escuelaNivel = $this->escuelaNivel('primaria');
        $this->escuelaNivel('preescolar');
        $sql = [];
        DB::listen(function ($query) use (&$sql) {
            $sql[] = $query->sql;
        });

        app(RegistrarDatosInmueble::class)->ejecutar($this->plantel->id, $escuelaNivel->id, $this->datos());

        $bloqueos = array_values(array_filter($sql, fn (string $s) => str_ends_with($s, 'for update') || str_ends_with($s, 'for share')));
        $this->assertNotEmpty($bloqueos);
        $this->assertMatchesRegularExpression('/^select .* from "escuela_niveles" .* for update$/', $bloqueos[0], 'El primer bloqueo debe ser FOR UPDATE sobre todos los niveles del plantel.');
        $primerEscuelas = array_search(true, array_map(fn (string $s) => preg_match('/from "escuelas".* for share$/', $s) === 1, $bloqueos), true);
        $this->assertNotFalse($primerEscuelas);
        $this->assertGreaterThan(0, $primerEscuelas, 'escuelas se bloquea después de escuela_niveles.');
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

    // WS-7a §3.1: feeds preescolar.superficie.construida_total and Inmueble is written once.
    public function test_exige_los_metros_construidos(): void
    {
        $escuelaNivel = $this->escuelaNivel('primaria');

        try {
            app(RegistrarDatosInmueble::class)->ejecutar($this->plantel->id, $escuelaNivel->id, new DatosInmueble(metrosTotales: 900.0));
            $this->fail('Se esperaba DatosInvalidos.');
        } catch (DatosInvalidos $e) {
            $this->assertSame('Captura la superficie construida.', $e->errores['metrosConstruidos'] ?? null);
        }

        $this->assertNull($this->plantel->fresh()->metros_totales);
    }

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

    // WS-2.4b — un caller que se salte CompuertaPaso3 no debe poder escribir
    // datos del inmueble si Paso 2 (responsable + documentos) no está completo.
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

        $this->expectException(PrecondicionIncumplida::class);

        app(RegistrarDatosInmueble::class)->ejecutar($plantel->id, $escuelaNivel->id, $this->datos());
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

    /** WS-5b, con control: sin 2.4 del nivel no se escribe nada del plantel; con 2.4 el mismo llamado pasa. */
    public function test_rechaza_registrar_sin_paso24_completo(): void
    {
        // Nivel armado aquí (sin el helper): el barrido del Paso 6 completa 2.4 en escuelaNivel().
        $escuelaNivel = EscuelaNivel::create([
            'escuela_id' => $this->escuela->id,
            'nivel_educativo_id' => NivelEducativo::where('clave', 'primaria')->value('id'),
            'estado_id' => DB::table('estados_expediente')->where('clave', 'en_captura')->value('id'),
            'tipo_tramite' => 'alta_nueva',
        ]);

        try {
            app(RegistrarDatosInmueble::class)->ejecutar($this->plantel->id, $escuelaNivel->id, $this->datos());
            $this->fail('Se esperaba PrecondicionIncumplida.');
        } catch (PrecondicionIncumplida) {
            // esperado
        }
        $this->assertNull($this->plantel->fresh()->metros_totales);

        $this->completarPaso24($escuelaNivel->id);
        app(RegistrarDatosInmueble::class)->ejecutar($this->plantel->id, $escuelaNivel->id, $this->datos());

        $this->assertEquals(1200.5, (float) $this->plantel->fresh()->metros_totales);
    }
}
