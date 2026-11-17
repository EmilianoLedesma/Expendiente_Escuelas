<?php

namespace Tests\Feature\Application\ProyectoEducativo;

use App\Application\Excepciones\DatosInvalidos;
use App\Application\Excepciones\PrecondicionIncumplida;
use App\Application\ProyectoEducativo\BibliotecaProyectoCapturada;
use App\Application\ProyectoEducativo\DTO\DatosBibliotecaProyecto;
use App\Application\ProyectoEducativo\GuardarBibliotecaProyecto;
use App\Application\ProyectoEducativo\ReglasBibliotecaProyecto;
use App\Application\ProyectoEducativo\SeccionesProyectoEducativo;
use App\Application\Tramite\EstadoPaso3;
use App\Application\Tramite\TramiteEditable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\PreparaPaso3;
use Tests\TestCase;

class GuardarBibliotecaProyectoTest extends TestCase
{
    use PreparaPaso3;
    use RefreshDatabase;

    private const BLOQUE_1 = ['biblioteca_tipo', 'biblioteca_virtual_tipo', 'biblioteca_tiene_encargado', 'biblioteca_perfil_encargado', 'biblioteca_prestamo'];

    private const BLOQUE_2 = ['biblioteca_tiene_programa_lectura', 'biblioteca_programa_lectura', 'biblioteca_otros_programas', 'acervo_clasificacion', 'acervo_titulos_basicos', 'acervo_titulos_lectura'];

    private function palabras(int $n): string
    {
        return trim(str_repeat('palabra ', $n));
    }

    /** Respuestas válidas con "física", "sin encargado" y "sin programa de lectura" salvo lo que se cambie. @param  array<string, string|null>  $cambios */
    private function dto(array $cambios = []): DatosBibliotecaProyecto
    {
        return new DatosBibliotecaProyecto(...array_merge([
            'biblioteca_tipo' => 'fisica',
            'biblioteca_virtual_tipo' => null,
            'biblioteca_tiene_encargado' => 'no',
            'biblioteca_perfil_encargado' => null,
            'biblioteca_prestamo' => $this->palabras(80),
            'biblioteca_tiene_programa_lectura' => 'no',
            'biblioteca_programa_lectura' => null,
            'biblioteca_otros_programas' => $this->palabras(80),
            'acervo_clasificacion' => $this->palabras(80),
            'acervo_titulos_basicos' => $this->palabras(80),
            'acervo_titulos_lectura' => $this->palabras(80),
        ], $cambios));
    }

    /** @param  array<string, string|null>  $cambios */
    private function guardar(int $nivelId, int $bloque, array $cambios = []): void
    {
        app(GuardarBibliotecaProyecto::class)->ejecutar($nivelId, $bloque, $this->dto($cambios));
    }

    /**
     * @param  array<string, string|null>  $cambios
     * @return array<string, string>
     */
    private function errores(int $nivelId, int $bloque, array $cambios = []): array
    {
        try {
            $this->guardar($nivelId, $bloque, $cambios);
        } catch (DatosInvalidos $e) {
            return $e->errores;
        }

        return [];
    }

    private function fila(int $nivelId): object
    {
        return DB::table('proyecto_educativo_biblioteca')->where('escuela_nivel_id', $nivelId)->sole();
    }

    public function test_el_slug_sale_del_catalogo(): void
    {
        $this->assertSame('servicios-de-biblioteca-y-acervo-bibliografico', SeccionesProyectoEducativo::biblioteca());
    }

    public function test_las_opciones_los_bloques_y_el_rango_del_dueno(): void
    {
        $this->assertSame(['fisica', 'virtual', 'mixta'], array_keys(ReglasBibliotecaProyecto::TIPOS));
        $this->assertSame(['propia', 'dominio_publico', 'mixta'], array_keys(ReglasBibliotecaProyecto::TIPOS_VIRTUAL));
        $this->assertSame(['si', 'no'], array_keys(ReglasBibliotecaProyecto::SI_NO));
        $this->assertSame([80, 250], ReglasBibliotecaProyecto::PALABRAS);
        $this->assertSame(self::BLOQUE_1, ReglasBibliotecaProyecto::BLOQUES[1]['campos']);
        $this->assertSame(self::BLOQUE_2, ReglasBibliotecaProyecto::BLOQUES[2]['campos']);
    }

    public function test_guardar_el_bloque_uno_escribe_solo_sus_campos_y_no_completa(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');

        $this->guardar($nivel->id, 1);

        $fila = $this->fila($nivel->id);
        $this->assertSame('fisica', $fila->biblioteca_tipo);
        $this->assertNull($fila->biblioteca_virtual_tipo);
        $this->assertFalse((bool) $fila->biblioteca_tiene_encargado);
        $this->assertNull($fila->biblioteca_perfil_encargado);
        $this->assertSame($this->palabras(80), $fila->biblioteca_prestamo);
        foreach (self::BLOQUE_2 as $campo) {
            $this->assertNull($fila->{$campo}, $campo);
        }
        $this->assertFalse(app(BibliotecaProyectoCapturada::class)->completa($nivel->id));
    }

    public function test_los_dos_bloques_guardados_completan_la_seccion(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');

        $this->guardar($nivel->id, 1);
        $this->guardar($nivel->id, 2);

        $this->assertTrue(app(BibliotecaProyectoCapturada::class)->completa($nivel->id));
        $this->assertSame(1, DB::table('proyecto_educativo_biblioteca')->count());
    }

    public function test_guardar_solo_el_bloque_dos_no_completa_y_deja_el_uno_vacio(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');

        $this->guardar($nivel->id, 2);

        $this->assertFalse(app(BibliotecaProyectoCapturada::class)->completa($nivel->id));
        $this->assertNull($this->fila($nivel->id)->biblioteca_tipo);
        $this->assertSame($this->palabras(80), $this->fila($nivel->id)->acervo_clasificacion);
    }

    public function test_volver_a_guardar_un_bloque_no_toca_el_otro(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');
        $this->guardar($nivel->id, 1);
        $this->guardar($nivel->id, 2);

        $this->guardar($nivel->id, 1, ['biblioteca_prestamo' => $this->palabras(120), 'acervo_clasificacion' => null]);

        $this->assertSame($this->palabras(120), $this->fila($nivel->id)->biblioteca_prestamo);
        $this->assertSame($this->palabras(80), $this->fila($nivel->id)->acervo_clasificacion);
    }

    public function test_una_biblioteca_virtual_o_mixta_exige_su_tipo_de_biblioteca_virtual(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');

        foreach (['virtual', 'mixta'] as $tipo) {
            $this->assertSame(['biblioteca_virtual_tipo'], array_keys($this->errores($nivel->id, 1, ['biblioteca_tipo' => $tipo])), $tipo);
            $this->assertSame(['biblioteca_virtual_tipo'], array_keys($this->errores($nivel->id, 1, ['biblioteca_tipo' => $tipo, 'biblioteca_virtual_tipo' => 'inventada'])), "{$tipo} inventada");
        }
        $this->assertSame(0, DB::table('proyecto_educativo_biblioteca')->count());

        foreach (['propia', 'dominio_publico', 'mixta'] as $virtual) {
            $this->guardar($nivel->id, 1, ['biblioteca_tipo' => 'virtual', 'biblioteca_virtual_tipo' => $virtual]);
            $this->assertSame($virtual, $this->fila($nivel->id)->biblioteca_virtual_tipo);
        }
    }

    public function test_una_biblioteca_fisica_descarta_el_tipo_virtual_enviado_y_el_guardado(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');
        $this->guardar($nivel->id, 1, ['biblioteca_tipo' => 'mixta', 'biblioteca_virtual_tipo' => 'propia']);

        $this->guardar($nivel->id, 1, ['biblioteca_tipo' => 'fisica', 'biblioteca_virtual_tipo' => 'propia']);

        $fila = $this->fila($nivel->id);
        $this->assertSame('fisica', $fila->biblioteca_tipo);
        $this->assertNull($fila->biblioteca_virtual_tipo);
    }

    public function test_con_encargado_el_perfil_es_obligatorio_y_sin_encargado_se_descarta(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');

        $this->assertSame(['biblioteca_perfil_encargado'], array_keys($this->errores($nivel->id, 1, ['biblioteca_tiene_encargado' => 'si'])));
        $this->assertSame(0, DB::table('proyecto_educativo_biblioteca')->count());

        $this->guardar($nivel->id, 1, ['biblioteca_tiene_encargado' => 'si', 'biblioteca_perfil_encargado' => $this->palabras(100)]);
        $this->assertTrue((bool) $this->fila($nivel->id)->biblioteca_tiene_encargado);
        $this->assertSame($this->palabras(100), $this->fila($nivel->id)->biblioteca_perfil_encargado);

        $this->guardar($nivel->id, 1, ['biblioteca_tiene_encargado' => 'no', 'biblioteca_perfil_encargado' => $this->palabras(100)]);
        $this->assertFalse((bool) $this->fila($nivel->id)->biblioteca_tiene_encargado);
        $this->assertNull($this->fila($nivel->id)->biblioteca_perfil_encargado);
    }

    public function test_con_programa_de_lectura_su_texto_es_obligatorio_y_sin_programa_se_descarta(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');

        $this->assertSame(['biblioteca_programa_lectura'], array_keys($this->errores($nivel->id, 2, ['biblioteca_tiene_programa_lectura' => 'si'])));
        $this->assertSame(0, DB::table('proyecto_educativo_biblioteca')->count());

        $this->guardar($nivel->id, 2, ['biblioteca_tiene_programa_lectura' => 'si', 'biblioteca_programa_lectura' => $this->palabras(100)]);
        $this->assertSame($this->palabras(100), $this->fila($nivel->id)->biblioteca_programa_lectura);

        $this->guardar($nivel->id, 2, ['biblioteca_tiene_programa_lectura' => 'no', 'biblioteca_programa_lectura' => $this->palabras(100)]);
        $this->assertFalse((bool) $this->fila($nivel->id)->biblioteca_tiene_programa_lectura);
        $this->assertNull($this->fila($nivel->id)->biblioteca_programa_lectura);
    }

    /** @return array<string, array{int, string, array<string, string>}> bloque, campo, cambios que lo vuelven obligatorio */
    public static function camposDeTexto(): array
    {
        return [
            'perfil del encargado' => [1, 'biblioteca_perfil_encargado', ['biblioteca_tiene_encargado' => 'si']],
            'préstamo' => [1, 'biblioteca_prestamo', []],
            'programa de lectura' => [2, 'biblioteca_programa_lectura', ['biblioteca_tiene_programa_lectura' => 'si']],
            'otros programas' => [2, 'biblioteca_otros_programas', []],
            'clasificación' => [2, 'acervo_clasificacion', []],
            'títulos básicos' => [2, 'acervo_titulos_basicos', []],
            'títulos de lectura' => [2, 'acervo_titulos_lectura', []],
        ];
    }

    /** @param  array<string, string>  $base */
    #[DataProvider('camposDeTexto')]
    public function test_cada_texto_respeta_80_a_250_palabras_y_es_obligatorio(int $bloque, string $campo, array $base): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');

        foreach ([79, 251] as $n) {
            $this->assertSame([$campo], array_keys($this->errores($nivel->id, $bloque, [...$base, $campo => $this->palabras($n)])), "{$campo} con {$n}");
        }
        foreach ([null, '', "  \n "] as $vacio) {
            $this->assertSame([$campo], array_keys($this->errores($nivel->id, $bloque, [...$base, $campo => $vacio])), "{$campo} vacío");
        }
        $this->assertSame(0, DB::table('proyecto_educativo_biblioteca')->count());

        foreach ([80, 250] as $n) {
            $this->guardar($nivel->id, $bloque, [...$base, $campo => $this->palabras($n)]);
            $this->assertSame($this->palabras($n), $this->fila($nivel->id)->{$campo});
        }
    }

    /** @return array<string, array{int, array<string, string|null>, string}> */
    public static function respuestasInvalidas(): array
    {
        return [
            'tipo vacío' => [1, ['biblioteca_tipo' => null], 'biblioteca_tipo'],
            'tipo inventado' => [1, ['biblioteca_tipo' => 'digital'], 'biblioteca_tipo'],
            'encargado vacío' => [1, ['biblioteca_tiene_encargado' => null], 'biblioteca_tiene_encargado'],
            'encargado inventado' => [1, ['biblioteca_tiene_encargado' => 'quizas'], 'biblioteca_tiene_encargado'],
            'programa vacío' => [2, ['biblioteca_tiene_programa_lectura' => ''], 'biblioteca_tiene_programa_lectura'],
            'programa inventado' => [2, ['biblioteca_tiene_programa_lectura' => 'tal vez'], 'biblioteca_tiene_programa_lectura'],
        ];
    }

    /** @param  array<string, string|null>  $cambios */
    #[DataProvider('respuestasInvalidas')]
    public function test_las_respuestas_son_obligatorias_y_de_la_lista(int $bloque, array $cambios, string $campo): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');

        $this->assertSame([$campo], array_keys($this->errores($nivel->id, $bloque, $cambios)));
        $this->assertSame(0, DB::table('proyecto_educativo_biblioteca')->count());
    }

    public function test_un_bloque_solo_valida_sus_campos(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');

        $this->guardar($nivel->id, 1, ['acervo_clasificacion' => 'corto', 'biblioteca_tiene_programa_lectura' => null]);

        $this->assertSame(1, DB::table('proyecto_educativo_biblioteca')->count());
    }

    public function test_todo_o_nada_dentro_del_bloque(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');

        $errores = $this->errores($nivel->id, 1, ['biblioteca_tipo' => null, 'biblioteca_prestamo' => 'corto']);

        $this->assertEqualsCanonicalizing(['biblioteca_tipo', 'biblioteca_prestamo'], array_keys($errores));
        $this->assertSame(0, DB::table('proyecto_educativo_biblioteca')->count());
    }

    public function test_un_bloque_inexistente_se_rechaza(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');

        foreach ([0, 3] as $bloque) {
            try {
                $this->guardar($nivel->id, $bloque);
                $this->fail("Debió rechazar el bloque {$bloque}.");
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
        $this->assertSame(0, DB::table('proyecto_educativo_biblioteca')->count());
    }

    public function test_sin_fila_no_esta_completa_y_los_datos_salen_vacios_y_con_si_no(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');
        $capturada = app(BibliotecaProyectoCapturada::class);

        $this->assertFalse($capturada->completa($nivel->id));
        $this->assertSame(array_fill_keys([...self::BLOQUE_1, ...self::BLOQUE_2], null), $capturada->datos($nivel->id));

        $this->guardar($nivel->id, 1, ['biblioteca_tiene_encargado' => 'si', 'biblioteca_perfil_encargado' => $this->palabras(90)]);
        $this->guardar($nivel->id, 2);
        $datos = $capturada->datos($nivel->id);
        $this->assertSame('si', $datos['biblioteca_tiene_encargado']);
        $this->assertSame('no', $datos['biblioteca_tiene_programa_lectura']);
        $this->assertSame('fisica', $datos['biblioteca_tipo']);
    }

    public function test_no_marca_el_paso_completado(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');

        $this->guardar($nivel->id, 1);
        $this->guardar($nivel->id, 2);

        $this->assertFalse(app(EstadoPaso3::class)->completado($nivel->id, SeccionesProyectoEducativo::CLAVE_PASO));
    }

    public function test_tramite_enviado_rechaza_el_guardado(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');
        $nivel->update(['estado_id' => DB::table('estados_expediente')->where('clave', 'en_revision')->value('id')]);

        try {
            $this->guardar($nivel->id, 1);
            $this->fail('Debió rechazar el guardado.');
        } catch (PrecondicionIncumplida $e) {
            $this->assertSame(TramiteEditable::ENVIADO, $e->etapaFaltante);
        }
        $this->assertSame(0, DB::table('proyecto_educativo_biblioteca')->count());
    }

    public function test_antes_de_matricula_rechaza_el_guardado(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'matricula');

        $this->expectException(PrecondicionIncumplida::class);
        $this->guardar($nivel->id, 1);
    }

    public function test_los_campos_del_dto_salen_del_constructor(): void
    {
        $this->assertSame([...self::BLOQUE_1, ...self::BLOQUE_2], DatosBibliotecaProyecto::campos());
    }

    public function test_la_base_exige_una_fila_por_nivel(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');
        DB::table('proyecto_educativo_biblioteca')->insert(['escuela_nivel_id' => $nivel->id]);

        $this->expectException(QueryException::class);
        DB::table('proyecto_educativo_biblioteca')->insert(['escuela_nivel_id' => $nivel->id]);
    }

    public function test_la_base_rechaza_valores_fuera_de_lista_y_combinaciones_incoherentes(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');
        $bloque1 = ['biblioteca_tipo' => 'fisica', 'biblioteca_tiene_encargado' => false, 'biblioteca_prestamo' => 'x'];
        $bloque2 = ['biblioteca_tiene_programa_lectura' => false, 'biblioteca_otros_programas' => 'x', 'acervo_clasificacion' => 'x', 'acervo_titulos_basicos' => 'x', 'acervo_titulos_lectura' => 'x'];

        foreach ([
            [...$bloque1, 'biblioteca_tipo' => 'digital'],
            [...$bloque1, 'biblioteca_virtual_tipo' => 'propia'],                          // física con tipo virtual
            [...$bloque1, 'biblioteca_tipo' => 'virtual'],                                 // virtual sin tipo virtual
            [...$bloque1, 'biblioteca_tipo' => 'virtual', 'biblioteca_virtual_tipo' => 'otra'],
            [...$bloque1, 'biblioteca_perfil_encargado' => 'sobra'],                       // sin encargado con perfil
            [...$bloque1, 'biblioteca_tiene_encargado' => true],                           // con encargado sin perfil
            [...$bloque1, 'biblioteca_prestamo' => null],                                  // bloque a medias
            [...$bloque1, 'biblioteca_prestamo' => '  '],
            [...$bloque2, 'biblioteca_programa_lectura' => 'sobra'],                       // sin programa con texto
            [...$bloque2, 'biblioteca_tiene_programa_lectura' => true],                    // con programa sin texto
            [...$bloque2, 'acervo_titulos_lectura' => null],                               // bloque a medias
            [...$bloque2, 'acervo_clasificacion' => '  '],
        ] as $fila) {
            try {
                DB::transaction(fn () => DB::table('proyecto_educativo_biblioteca')->insert(['escuela_nivel_id' => $nivel->id, ...$fila])); // savepoint: un insert fallido no aborta la transacción de la prueba
                $this->fail('La base debió rechazar '.json_encode($fila));
            } catch (QueryException) {
                $this->addToAssertionCount(1);
            }
        }
        $this->assertSame(0, DB::table('proyecto_educativo_biblioteca')->count());

        DB::table('proyecto_educativo_biblioteca')->insert(['escuela_nivel_id' => $nivel->id, ...$bloque1, ...$bloque2]);
        $this->assertSame(1, DB::table('proyecto_educativo_biblioteca')->count());
    }
}
