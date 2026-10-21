<?php

namespace Tests\Feature\Application\Infraestructura;

use App\Application\Excepciones\DatosInvalidos;
use App\Application\Excepciones\PrecondicionIncumplida;
use App\Application\Infraestructura\DTO\DatosEdicionInfraestructura;
use App\Application\Infraestructura\EditarInfraestructuraCompartida;
use App\Application\Tramite\TramiteEditable;
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
 * WS-7b-2: el dueño corrige o quita filas ya capturadas de espacios y sanitarios del
 * plantel. Sin bloqueo reemplaza; con otro nivel con datos propios de Paso 3 solo
 * completa campos nulos y conjuntos hijos vacíos, y no quita.
 */
class EditarInfraestructuraCompartidaTest extends TestCase
{
    use RefreshDatabase;

    private Plantel $plantel;

    private Escuela $escuela;

    private EscuelaNivel $primaria;

    protected function setUp(): void
    {
        parent::setUp();

        (new CatalogoMinimoSeeder)->run();
        (new TiposEspaciosSeeder)->run();

        $this->plantel = Plantel::create(['calle' => 'Calle 1', 'colonia' => 'Centro', 'municipio' => 'Querétaro', 'codigo_postal' => '76000']);
        $this->escuela = Escuela::create(['plantel_id' => $this->plantel->id, 'solicitante_id' => Solicitante::factory()->create()->id]);
        $this->primaria = $this->nivel($this->escuela, 'primaria');
        $this->sembrar();
    }

    private function nivel(Escuela $escuela, string $clave, string $estado = 'en_captura'): EscuelaNivel
    {
        return EscuelaNivel::create([
            'escuela_id' => $escuela->id,
            'nivel_educativo_id' => NivelEducativo::where('clave', $clave)->value('id'),
            'estado_id' => DB::table('estados_expediente')->where('clave', $estado)->value('id'),
            'tipo_tramite' => 'alta_nueva',
        ]);
    }

    private function idTipo(string $clave): int
    {
        return (int) DB::table('tipos_espacios')->where('clave', $clave)->value('id');
    }

    private function idMaterial(string $clave): int
    {
        return (int) DB::table('tipos_material_biblioteca')->where('clave', $clave)->value('id');
    }

    /** Lo que otro nivel (o este) ya capturó en el plantel. */
    private function sembrar(): void
    {
        $espacio = fn (string $clave, array $campos) => DB::table('instalaciones_espacios')->insertGetId($campos + [
            'plantel_id' => $this->plantel->id,
            'tipo_espacio_id' => $this->idTipo($clave),
        ]);

        $espacio('direccion', ['cantidad' => 1, 'superficie_m2' => 18.5, 'capacidad_promedio' => null, 'ventilacion_natural' => false, 'iluminacion_natural' => null, 'destinado_a' => null]);
        $biblioteca = $espacio('biblioteca', ['cantidad' => 1, 'superficie_m2' => 45.0, 'capacidad_promedio' => 20]);
        DB::table('biblioteca_materiales')->insert(['instalacion_espacio_id' => $biblioteca, 'tipo_material_id' => $this->idMaterial('libros'), 'numero_titulos' => 300, 'numero_volumenes' => 450]);
        $futbol = $espacio('campo_futbol', ['cantidad' => 1, 'superficie_m2' => 800.0]);
        DB::table('campos_futbol')->insert(['instalacion_espacio_id' => $futbol, 'tipo_superficie' => 'pasto', 'formato' => '7']);

        DB::table('sanitarios')->insert(['plantel_id' => $this->plantel->id, 'categoria' => 'alumnado_masculino', 'cantidad_retretes' => 4, 'cantidad_mingitorios' => null, 'cantidad_lavabos' => 4, 'superficie_m2' => 12.0]);
        $maternal = DB::table('sanitarios')->insertGetId(['plantel_id' => $this->plantel->id, 'categoria' => 'alumnado_maternal', 'cantidad_retretes' => 2, 'superficie_m2' => 8.0]);
        DB::table('sanitarios_bacinicas')->insert(['sanitario_id' => $maternal, 'cantidad_bacinicas' => 5]);
    }

    /** @return array<string, mixed> */
    private function espacio(string $clave, array $campos = []): array
    {
        return $campos + [
            'tipoEspacioId' => $this->idTipo($clave),
            'cantidad' => null,
            'superficieM2' => null,
            'capacidadPromedio' => null,
            'ventilacionNatural' => null,
            'iluminacionNatural' => null,
            'destinadoA' => null,
            'campoFutbol' => null,
            'materialesBiblioteca' => [],
        ];
    }

    /** @return array<string, mixed> */
    private function sanitario(string $categoria, array $campos = []): array
    {
        return $campos + [
            'categoria' => $categoria,
            'cantidadRetretes' => null,
            'cantidadMingitorios' => null,
            'cantidadLavabos' => null,
            'superficieM2' => null,
            'ventilacionNatural' => null,
            'iluminacionNatural' => null,
            'cantidadBacinicas' => null,
        ];
    }

    private function editar(array $espacios = [], array $sanitarios = [], array $quitarEspacios = [], array $quitarSanitarios = []): void
    {
        app(EditarInfraestructuraCompartida::class)->ejecutar(
            $this->plantel->id,
            $this->primaria->id,
            new DatosEdicionInfraestructura(espacios: $espacios, sanitarios: $sanitarios, quitarEspacios: $quitarEspacios, quitarSanitarios: $quitarSanitarios),
        );
    }

    /** Otro nivel del plantel declara datos propios de Paso 3. */
    private function bloquear(): EscuelaNivel
    {
        $secundaria = $this->nivel($this->escuela, 'secundaria');
        $secundaria->update(['modalidad' => 'escolarizada']);

        return $secundaria;
    }

    private function fila(string $clave): object
    {
        return DB::table('instalaciones_espacios')->where('plantel_id', $this->plantel->id)->where('tipo_espacio_id', $this->idTipo($clave))->first();
    }

    private function filaSanitario(string $categoria): ?object
    {
        return DB::table('sanitarios')->where('plantel_id', $this->plantel->id)->where('categoria', $categoria)->first();
    }

    public function test_edita_valores_y_reemplaza_los_hijos(): void
    {
        $this->editar(
            espacios: [
                $this->espacio('direccion', ['cantidad' => 2, 'superficieM2' => 20.0, 'ventilacionNatural' => true]),
                $this->espacio('biblioteca', ['cantidad' => 1, 'superficieM2' => 50.0, 'materialesBiblioteca' => [
                    ['tipoMaterialId' => $this->idMaterial('periodicos'), 'numeroTitulos' => 10, 'numeroVolumenes' => null],
                ]]),
                $this->espacio('campo_futbol', ['cantidad' => 1, 'superficieM2' => 900.0, 'campoFutbol' => ['tipoSuperficie' => 'tierra', 'formato' => '11']]),
            ],
            sanitarios: [
                $this->sanitario('alumnado_masculino', ['cantidadRetretes' => 6, 'superficieM2' => 15.0]),
                $this->sanitario('alumnado_maternal', ['cantidadRetretes' => 3, 'superficieM2' => 9.0, 'cantidadBacinicas' => 7]),
            ],
        );

        $direccion = $this->fila('direccion');
        $this->assertSame(2, $direccion->cantidad);
        $this->assertEquals(20.0, (float) $direccion->superficie_m2);
        $this->assertTrue($direccion->ventilacion_natural);

        $biblioteca = $this->fila('biblioteca');
        $this->assertNull($biblioteca->capacidad_promedio, 'sin bloqueo los campos se reemplazan, también a null');
        $this->assertSame([$this->idMaterial('periodicos')], DB::table('biblioteca_materiales')->where('instalacion_espacio_id', $biblioteca->id)->pluck('tipo_material_id')->all());

        $futbol = DB::table('campos_futbol')->where('instalacion_espacio_id', $this->fila('campo_futbol')->id)->get();
        $this->assertCount(1, $futbol);
        $this->assertSame('11', $futbol[0]->formato);

        $masculino = $this->filaSanitario('alumnado_masculino');
        $this->assertSame(6, $masculino->cantidad_retretes);
        $this->assertNull($masculino->cantidad_lavabos);
        $this->assertSame([7], DB::table('sanitarios_bacinicas')->where('sanitario_id', $this->filaSanitario('alumnado_maternal')->id)->pluck('cantidad_bacinicas')->all());

        $this->assertSame(3, DB::table('instalaciones_espacios')->where('plantel_id', $this->plantel->id)->count(), 'editar no crea filas');
    }

    public function test_quitar_borra_la_fila_y_sus_hijos_sin_huerfanos(): void
    {
        $this->editar(quitarEspacios: [$this->idTipo('biblioteca'), $this->idTipo('campo_futbol')], quitarSanitarios: ['alumnado_maternal']);

        $this->assertSame([$this->idTipo('direccion')], DB::table('instalaciones_espacios')->where('plantel_id', $this->plantel->id)->pluck('tipo_espacio_id')->all());
        $this->assertSame(0, DB::table('biblioteca_materiales')->count());
        $this->assertSame(0, DB::table('campos_futbol')->count());
        $this->assertSame(['alumnado_masculino'], DB::table('sanitarios')->where('plantel_id', $this->plantel->id)->pluck('categoria')->all());
        $this->assertSame(0, DB::table('sanitarios_bacinicas')->count());
    }

    public function test_una_fila_editada_totalmente_vacia_se_rechaza(): void
    {
        $direccion = $this->idTipo('direccion');

        try {
            // false no cuenta como dato (FilaSignificativa): borrar es una acción explícita.
            $this->editar(
                espacios: [$this->espacio('direccion', ['ventilacionNatural' => false])],
                sanitarios: [$this->sanitario('alumnado_masculino')],
            );
            $this->fail('Se esperaba DatosInvalidos.');
        } catch (DatosInvalidos $e) {
            $this->assertArrayHasKey("edicionEspacios.{$direccion}.tipoEspacioId", $e->errores);
            $this->assertArrayHasKey('edicionSanitarios.alumnado_masculino.categoria', $e->errores);
        }

        $this->assertSame(1, $this->fila('direccion')->cantidad);
        $this->assertSame(4, $this->filaSanitario('alumnado_masculino')->cantidad_retretes);
    }

    public function test_un_tipo_no_capturado_se_rechaza(): void
    {
        $sala = $this->idTipo('sala_maestros');

        try {
            $this->editar(
                espacios: [$this->espacio('sala_maestros', ['cantidad' => 1])],
                sanitarios: [$this->sanitario('personal_femenino', ['cantidadRetretes' => 1])],
                quitarEspacios: [$this->idTipo('bodega')],
                quitarSanitarios: ['personal'],
            );
            $this->fail('Se esperaba DatosInvalidos.');
        } catch (DatosInvalidos $e) {
            $this->assertArrayHasKey("edicionEspacios.{$sala}.tipoEspacioId", $e->errores);
            $this->assertArrayHasKey('edicionEspacios.'.$this->idTipo('bodega').'.tipoEspacioId', $e->errores);
            $this->assertArrayHasKey('edicionSanitarios.personal_femenino.categoria', $e->errores);
            $this->assertArrayHasKey('edicionSanitarios.personal.categoria', $e->errores);
        }

        $this->assertSame(3, DB::table('instalaciones_espacios')->where('plantel_id', $this->plantel->id)->count());
        $this->assertSame(2, DB::table('sanitarios')->where('plantel_id', $this->plantel->id)->count());
    }

    public function test_sin_bloqueo_conserva_las_reglas_de_campo_requerido(): void
    {
        $biblioteca = $this->idTipo('biblioteca');

        try {
            $this->editar(
                espacios: [$this->espacio('biblioteca', ['cantidad' => 1, 'superficieM2' => 45.0, 'materialesBiblioteca' => [
                    ['tipoMaterialId' => $this->idMaterial('libros'), 'numeroTitulos' => null, 'numeroVolumenes' => -1],
                ]]), $this->espacio('campo_futbol', ['cantidad' => -1])],
                sanitarios: [$this->sanitario('alumnado_masculino', ['cantidadRetretes' => 4])],
            );
            $this->fail('Se esperaba DatosInvalidos.');
        } catch (DatosInvalidos $e) {
            $libros = $this->idMaterial('libros');
            $this->assertArrayHasKey("edicionMateriales.{$libros}.numeroTitulos", $e->errores);
            $this->assertArrayHasKey("edicionMateriales.{$libros}.numeroVolumenes", $e->errores);
            $this->assertArrayHasKey('edicionEspacios.'.$this->idTipo('campo_futbol').'.cantidad', $e->errores);
            $this->assertArrayHasKey('edicionSanitarios.alumnado_masculino.superficieM2', $e->errores, 'FALTA_SUPERFICIE del alumnado');
            $this->assertArrayNotHasKey("edicionEspacios.{$biblioteca}.tipoEspacioId", $e->errores);
        }

        try {
            $this->editar(espacios: [$this->espacio('biblioteca', ['cantidad' => 1, 'superficieM2' => 45.0])]);
            $this->fail('Se esperaba DatosInvalidos.');
        } catch (DatosInvalidos $e) {
            $this->assertArrayHasKey('edicionMateriales', $e->errores, 'al menos un material si el tipo permite biblioteca');
        }
    }

    public function test_bloqueado_completa_solo_nulos_y_no_cambia_valores_existentes(): void
    {
        $this->bloquear();

        $this->editar(
            espacios: [$this->espacio('direccion', [
                'cantidad' => 9, 'superficieM2' => 99.0, 'capacidadPromedio' => 4,
                'ventilacionNatural' => true, 'iluminacionNatural' => false, 'destinadoA' => 'Dirección general',
            ])],
            sanitarios: [$this->sanitario('alumnado_masculino', ['cantidadRetretes' => 1, 'cantidadMingitorios' => 3])],
        );

        $direccion = $this->fila('direccion');
        $this->assertSame(1, $direccion->cantidad);
        $this->assertEquals(18.5, (float) $direccion->superficie_m2);
        $this->assertSame(4, $direccion->capacidad_promedio);
        $this->assertFalse($direccion->ventilacion_natural, 'false es un valor: no se sobrescribe');
        $this->assertFalse($direccion->iluminacion_natural, 'null es completable');
        $this->assertSame('Dirección general', $direccion->destinado_a);

        $masculino = $this->filaSanitario('alumnado_masculino');
        $this->assertSame(4, $masculino->cantidad_retretes);
        $this->assertSame(3, $masculino->cantidad_mingitorios);
        $this->assertSame(4, $masculino->cantidad_lavabos, 'un null enviado no borra lo existente');
    }

    public function test_bloqueado_rechaza_quitar(): void
    {
        $this->bloquear();

        try {
            $this->editar(quitarEspacios: [$this->idTipo('biblioteca')]);
            $this->fail('Se esperaba PrecondicionIncumplida.');
        } catch (PrecondicionIncumplida) {
        }

        try {
            $this->editar(quitarSanitarios: ['alumnado_maternal']);
            $this->fail('Se esperaba PrecondicionIncumplida.');
        } catch (PrecondicionIncumplida) {
        }

        $this->assertNotNull($this->fila('biblioteca'));
        $this->assertSame(1, DB::table('biblioteca_materiales')->count());
        $this->assertNotNull($this->filaSanitario('alumnado_maternal'));
    }

    public function test_bloqueado_inserta_biblioteca_solo_si_no_habia_materiales(): void
    {
        DB::table('biblioteca_materiales')->delete();
        $this->bloquear();
        $conMaterial = fn (string $clave) => $this->espacio('biblioteca', ['cantidad' => 1, 'materialesBiblioteca' => [
            ['tipoMaterialId' => $this->idMaterial($clave), 'numeroTitulos' => 5, 'numeroVolumenes' => null],
        ]]);

        $this->editar(espacios: [$conMaterial('periodicos')]);
        $this->assertSame([$this->idMaterial('periodicos')], DB::table('biblioteca_materiales')->pluck('tipo_material_id')->all());

        $this->editar(espacios: [$conMaterial('videos')]);
        $this->assertSame([$this->idMaterial('periodicos')], DB::table('biblioteca_materiales')->pluck('tipo_material_id')->all(), 'ya hay materiales: no se tocan');
    }

    public function test_se_desbloquea_al_quitar_los_datos_del_otro_nivel(): void
    {
        $secundaria = $this->nivel($this->escuela, 'secundaria');
        DB::table('aulas_nivel')->insert(['escuela_nivel_id' => $secundaria->id, 'numero_aulas' => 3]);

        $this->editar(espacios: [$this->espacio('direccion', ['cantidad' => 5])]);
        $this->assertSame(1, $this->fila('direccion')->cantidad);

        DB::table('aulas_nivel')->where('escuela_nivel_id', $secundaria->id)->delete();

        $this->editar(espacios: [$this->espacio('direccion', ['cantidad' => 5])]);
        $this->assertSame(5, $this->fila('direccion')->cantidad);
    }

    public function test_un_tramite_hermano_enviado_rechaza(): void
    {
        $otraEscuela = Escuela::create(['plantel_id' => $this->plantel->id, 'solicitante_id' => Solicitante::factory()->create()->id]);
        $this->nivel($otraEscuela, 'secundaria', 'en_revision');

        try {
            $this->editar(espacios: [$this->espacio('direccion', ['cantidad' => 5])], quitarSanitarios: ['alumnado_maternal']);
            $this->fail('Se esperaba PrecondicionIncumplida.');
        } catch (PrecondicionIncumplida $e) {
            $this->assertSame(TramiteEditable::ENVIADO, $e->etapaFaltante);
        }

        $this->assertSame(1, $this->fila('direccion')->cantidad);
        $this->assertNotNull($this->filaSanitario('alumnado_maternal'));
    }

    public function test_la_transaccion_toma_el_bloqueo_primero(): void
    {
        $this->nivel($this->escuela, 'secundaria');
        $sql = [];
        DB::listen(function ($query) use (&$sql) {
            $sql[] = $query->sql;
        });

        $this->editar(espacios: [$this->espacio('direccion', ['cantidad' => 5])]);

        $bloqueos = array_values(array_filter($sql, fn (string $s) => str_ends_with($s, 'for update') || str_ends_with($s, 'for share')));
        $this->assertNotEmpty($bloqueos);
        $this->assertMatchesRegularExpression('/^select .* from "escuela_niveles" .* for update$/', $bloqueos[0], 'El primer bloqueo debe ser FOR UPDATE sobre todos los niveles del plantel.');
        $primerEscuelas = array_search(true, array_map(fn (string $s) => preg_match('/from "escuelas".* for share$/', $s) === 1, $bloqueos), true);
        $this->assertNotFalse($primerEscuelas);
        $this->assertGreaterThan(0, $primerEscuelas, 'escuelas se bloquea después de escuela_niveles.');
    }
}
