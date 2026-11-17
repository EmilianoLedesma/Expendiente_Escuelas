<?php

namespace Tests\Feature\Application\ProyectoEducativo;

use App\Application\Excepciones\DatosInvalidos;
use App\Application\Excepciones\PrecondicionIncumplida;
use App\Application\ProyectoEducativo\BibliografiaProyectoCapturada;
use App\Application\ProyectoEducativo\DTO\DatosBibliografiaProyecto;
use App\Application\ProyectoEducativo\FormatoReferenciaApa;
use App\Application\ProyectoEducativo\GuardarBibliografiaProyecto;
use App\Application\ProyectoEducativo\ReglasBibliografiaProyecto;
use App\Application\ProyectoEducativo\SeccionesProyectoEducativo;
use App\Application\Tramite\EstadoPaso3;
use App\Application\Tramite\TramiteEditable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\PreparaPaso3;
use Tests\TestCase;

class GuardarBibliografiaProyectoTest extends TestCase
{
    use PreparaPaso3;
    use RefreshDatabase;

    /** @param  array<string, string>  $cambios */
    private function ref(array $cambios = []): array
    {
        return array_merge(['autores' => 'Pérez, J.', 'anio' => '2020', 'titulo' => 'Aprender a aprender', 'fuente' => 'Editorial Trillas', 'url' => ''], $cambios);
    }

    /** @param  list<mixed>  $referencias */
    private function guardar(int $nivelId, array $referencias): void
    {
        app(GuardarBibliografiaProyecto::class)->ejecutar($nivelId, new DatosBibliografiaProyecto($referencias));
    }

    /**
     * @param  list<mixed>  $referencias
     * @return array<string, string>
     */
    private function errores(int $nivelId, array $referencias): array
    {
        try {
            $this->guardar($nivelId, $referencias);
        } catch (DatosInvalidos $e) {
            return $e->errores;
        }

        return [];
    }

    /** @return list<object> */
    private function filas(int $nivelId): array
    {
        return DB::table('proyecto_educativo_bibliografia')->where('escuela_nivel_id', $nivelId)->orderBy('orden')->get()->all();
    }

    public function test_el_slug_sale_del_catalogo(): void
    {
        $this->assertSame('bibliografia-del-proyecto', SeccionesProyectoEducativo::bibliografia());
    }

    public function test_los_limites_del_catalogo(): void
    {
        $this->assertSame(1, ReglasBibliografiaProyecto::MIN);
        $this->assertSame(50, ReglasBibliografiaProyecto::MAX);
    }

    public function test_guarda_una_referencia_y_marca_completa(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');

        $this->guardar($nivel->id, [$this->ref(['url' => 'https://doi.org/10.1000/xyz'])]);

        $filas = $this->filas($nivel->id);
        $this->assertCount(1, $filas);
        $this->assertSame(1, (int) $filas[0]->orden);
        $this->assertSame('Pérez, J.', $filas[0]->autores);
        $this->assertSame(2020, (int) $filas[0]->anio);
        $this->assertSame('https://doi.org/10.1000/xyz', $filas[0]->url);
        $this->assertTrue(app(BibliografiaProyectoCapturada::class)->completa($nivel->id));
    }

    public function test_la_url_vacia_se_guarda_como_null(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');

        $this->guardar($nivel->id, [$this->ref(['url' => '   '])]);

        $this->assertNull($this->filas($nivel->id)[0]->url);
    }

    public function test_guarda_ordenado_alfabeticamente_por_autor_sin_importar_mayusculas_ni_acentos(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');

        $this->guardar($nivel->id, [
            $this->ref(['autores' => 'zapata, M.', 'titulo' => 'Z']),
            $this->ref(['autores' => 'Benítez, A.', 'titulo' => 'B']),
            $this->ref(['autores' => 'Álvarez, C.', 'titulo' => 'A']),
            $this->ref(['autores' => 'Benítez, A.', 'anio' => '2018', 'titulo' => 'B anterior']),
        ]);

        $this->assertSame(['A', 'B anterior', 'B', 'Z'], array_map(fn ($f) => $f->titulo, $this->filas($nivel->id)));
        $this->assertSame([1, 2, 3, 4], array_map(fn ($f) => (int) $f->orden, $this->filas($nivel->id)));
    }

    public function test_guardar_de_nuevo_reemplaza_toda_la_lista(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');
        $this->guardar($nivel->id, [$this->ref(['autores' => 'A']), $this->ref(['autores' => 'B']), $this->ref(['autores' => 'C'])]);

        $this->guardar($nivel->id, [$this->ref(['autores' => 'Z'])]);

        $filas = $this->filas($nivel->id);
        $this->assertCount(1, $filas);
        $this->assertSame('Z', $filas[0]->autores);
    }

    public function test_de_una_a_cincuenta_referencias(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');

        $this->assertSame(['referencias'], array_keys($this->errores($nivel->id, [])));
        $this->assertSame(['referencias'], array_keys($this->errores($nivel->id, array_fill(0, 51, $this->ref()))));
        $this->assertSame(0, DB::table('proyecto_educativo_bibliografia')->count());

        $this->guardar($nivel->id, array_fill(0, 50, $this->ref()));
        $this->assertCount(50, $this->filas($nivel->id));
    }

    /** @return array<string, array{string, string}> */
    public static function anios(): array
    {
        $actual = (int) date('Y');

        return [
            'cuatro dígitos' => ['2020', 'ok'],
            'el año que viene' => [(string) ($actual + 1), 'ok'],
            'pasado mañana' => [(string) ($actual + 2), 'error'],
            'tres dígitos' => ['999', 'error'],
            'texto' => ['abcd', 'error'],
            'decimal' => ['2020.5', 'error'],
            'vacío' => ['', 'error'],
            'negativo' => ['-2020', 'error'],
        ];
    }

    #[DataProvider('anios')]
    public function test_el_anio_es_de_cuatro_digitos_hasta_el_proximo_ano(string $anio, string $esperado): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');

        $errores = $this->errores($nivel->id, [$this->ref(['anio' => $anio])]);

        $this->assertSame($esperado === 'ok' ? [] : ['referencias.0.anio'], array_keys($errores));
    }

    /** @return array<string, array{string, string}> */
    public static function camposObligatorios(): array
    {
        return ['autores' => ['autores', ''], 'titulo' => ['titulo', ''], 'fuente' => ['fuente', ''], 'solo espacios' => ['titulo', "  \n "]];
    }

    #[DataProvider('camposObligatorios')]
    public function test_autores_titulo_y_fuente_son_obligatorios(string $campo, string $valor): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');

        $this->assertSame(["referencias.0.{$campo}"], array_keys($this->errores($nivel->id, [$this->ref([$campo => $valor])])));
        $this->assertSame(0, DB::table('proyecto_educativo_bibliografia')->count());
    }

    public function test_longitudes_maximas(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');

        foreach (['autores' => 300, 'titulo' => 300, 'fuente' => 200] as $campo => $max) {
            $this->assertSame(["referencias.0.{$campo}"], array_keys($this->errores($nivel->id, [$this->ref([$campo => str_repeat('a', $max + 1)])])), "{$campo} {$max}+1");
            $this->assertSame([], $this->errores($nivel->id, [$this->ref([$campo => str_repeat('a', $max)])]), "{$campo} {$max}");
        }
        $this->assertSame(['referencias.0.url'], array_keys($this->errores($nivel->id, [$this->ref(['url' => 'https://a.com/'.str_repeat('a', 500)])])));
    }

    /** @return array<string, array{string, bool}> */
    public static function urls(): array
    {
        return [
            'https' => ['https://www.ejemplo.com/a?b=1', true],
            'http' => ['http://ejemplo.com', true],
            'doi como enlace' => ['https://doi.org/10.1000/182', true],
            'vacía' => ['', true],
            'ftp' => ['ftp://ejemplo.com', false],
            'sin esquema' => ['www.ejemplo.com', false],
            'con espacios' => ['https://ejemplo.com/a b', false],
            'javascript' => ['javascript:alert(1)', false],
        ];
    }

    #[DataProvider('urls')]
    public function test_la_url_es_opcional_pero_si_se_escribe_es_http_o_https(string $url, bool $valida): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');

        $errores = $this->errores($nivel->id, [$this->ref(['url' => $url])]);

        $this->assertSame($valida ? [] : ['referencias.0.url'], array_keys($errores));
    }

    public function test_los_errores_llevan_el_indice_de_la_referencia(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');

        $errores = $this->errores($nivel->id, [$this->ref(), $this->ref(['titulo' => '']), $this->ref(['anio' => 'x', 'fuente' => ''])]);

        $this->assertEqualsCanonicalizing(['referencias.1.titulo', 'referencias.2.anio', 'referencias.2.fuente'], array_keys($errores));
    }

    public function test_todo_o_nada_un_error_conserva_la_lista_guardada(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');
        $this->guardar($nivel->id, [$this->ref(['autores' => 'Guardada'])]);

        $errores = $this->errores($nivel->id, [$this->ref(['autores' => 'Nueva']), $this->ref(['titulo' => ''])]);

        $this->assertSame(['referencias.1.titulo'], array_keys($errores));
        $this->assertSame(['Guardada'], array_map(fn ($f) => $f->autores, $this->filas($nivel->id)));
    }

    public function test_datos_manipulados_del_navegador_no_rompen_el_guardado(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');

        foreach ([[['autores' => ['x'], 'anio' => ['2020'], 'titulo' => null, 'fuente' => 1, 'url' => []]], ['no soy una referencia'], [null]] as $malo) {
            $errores = $this->errores($nivel->id, $malo);
            $this->assertNotSame([], $errores);
            $this->assertStringStartsWith('referencias.0.', (string) array_key_first($errores));
        }
        $this->assertSame(0, DB::table('proyecto_educativo_bibliografia')->count());
    }

    public function test_sin_filas_no_esta_completa_y_las_referencias_salen_vacias(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');
        $capturada = app(BibliografiaProyectoCapturada::class);

        $this->assertFalse($capturada->completa($nivel->id));
        $this->assertSame([], $capturada->referencias($nivel->id));

        $this->guardar($nivel->id, [$this->ref(['autores' => 'B', 'url' => 'https://a.com']), $this->ref(['autores' => 'A'])]);
        $this->assertSame([
            ['autores' => 'A', 'anio' => '2020', 'titulo' => 'Aprender a aprender', 'fuente' => 'Editorial Trillas', 'url' => ''],
            ['autores' => 'B', 'anio' => '2020', 'titulo' => 'Aprender a aprender', 'fuente' => 'Editorial Trillas', 'url' => 'https://a.com'],
        ], $capturada->referencias($nivel->id));
    }

    public function test_no_marca_el_paso_completado(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');

        $this->guardar($nivel->id, [$this->ref()]);

        $this->assertFalse(app(EstadoPaso3::class)->completado($nivel->id, SeccionesProyectoEducativo::CLAVE_PASO));
    }

    public function test_tramite_enviado_rechaza_el_guardado(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');
        $nivel->update(['estado_id' => DB::table('estados_expediente')->where('clave', 'en_revision')->value('id')]);

        try {
            $this->guardar($nivel->id, [$this->ref()]);
            $this->fail('Debió rechazar el guardado.');
        } catch (PrecondicionIncumplida $e) {
            $this->assertSame(TramiteEditable::ENVIADO, $e->etapaFaltante);
        }
        $this->assertSame(0, DB::table('proyecto_educativo_bibliografia')->count());
    }

    public function test_antes_de_matricula_rechaza_el_guardado(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'matricula');

        $this->expectException(PrecondicionIncumplida::class);
        $this->guardar($nivel->id, [$this->ref()]);
    }

    public function test_los_campos_del_dto_salen_del_constructor(): void
    {
        $this->assertSame(['referencias'], DatosBibliografiaProyecto::campos());
    }

    /** @return array<string, array{array<string, string>, string}> */
    public static function formatosApa(): array
    {
        return [
            'básico' => [['autores' => 'Pérez, J., & Gómez, L.', 'anio' => '2020', 'titulo' => 'Aprender a aprender', 'fuente' => 'Editorial Trillas', 'url' => ''], 'Pérez, J., & Gómez, L. (2020). Aprender a aprender. Editorial Trillas.'],
            'con url' => [['autores' => 'OMS', 'anio' => '2021', 'titulo' => 'Informe mundial', 'fuente' => 'Organización Mundial de la Salud', 'url' => 'https://www.who.int/informe'], 'OMS (2021). Informe mundial. Organización Mundial de la Salud. https://www.who.int/informe'],
            'título con pregunta' => [['autores' => 'Díaz, R.', 'anio' => '2019', 'titulo' => '¿Por qué enseñar?', 'fuente' => 'Revista Educar', 'url' => ''], 'Díaz, R. (2019). ¿Por qué enseñar? Revista Educar.'],
            'fuente con punto' => [['autores' => 'Ruiz, P.', 'anio' => '2018', 'titulo' => 'Didáctica', 'fuente' => 'Ed. Paidós.', 'url' => ''], 'Ruiz, P. (2018). Didáctica. Ed. Paidós.'],
            'título con punto' => [['autores' => 'Soto, M.', 'anio' => '2017', 'titulo' => 'Pedagogía crítica.', 'fuente' => 'Siglo XXI', 'url' => ''], 'Soto, M. (2017). Pedagogía crítica. Siglo XXI.'],
        ];
    }

    /** @param  array<string, string>  $ref */
    #[DataProvider('formatosApa')]
    public function test_el_formato_apa_de_una_referencia(array $ref, string $esperado): void
    {
        $this->assertSame($esperado, FormatoReferenciaApa::texto($ref));

        [$antes, $titulo, $despues] = FormatoReferenciaApa::partes($ref);
        $this->assertSame($esperado, $antes.$titulo.$despues);
    }

    public function test_el_titulo_va_aparte_para_poder_ponerlo_en_cursiva(): void
    {
        [$antes, $titulo, $despues] = FormatoReferenciaApa::partes($this->ref());

        $this->assertSame('Pérez, J. (2020). ', $antes);
        $this->assertSame('Aprender a aprender.', $titulo);
        $this->assertSame(' Editorial Trillas.', $despues);
    }

    public function test_la_base_exige_orden_unico_por_nivel_y_valores_validos(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');
        $base = ['escuela_nivel_id' => $nivel->id, 'orden' => 1, 'autores' => 'A', 'anio' => 2020, 'titulo' => 'T', 'fuente' => 'F'];

        foreach ([
            [...$base, 'orden' => 0],
            [...$base, 'orden' => 51],
            [...$base, 'autores' => '  '],
            [...$base, 'titulo' => '  '],
            [...$base, 'fuente' => '  '],
            [...$base, 'anio' => 999],
            [...$base, 'url' => 'ftp://x.com'],
            [...$base, 'url' => 'www.x.com'],
        ] as $fila) {
            try {
                DB::transaction(fn () => DB::table('proyecto_educativo_bibliografia')->insert($fila)); // savepoint: un insert fallido no aborta la transacción de la prueba
                $this->fail('La base debió rechazar '.json_encode($fila));
            } catch (QueryException) {
                $this->addToAssertionCount(1);
            }
        }
        $this->assertSame(0, DB::table('proyecto_educativo_bibliografia')->count());

        DB::table('proyecto_educativo_bibliografia')->insert($base);
        $this->expectException(QueryException::class);
        DB::table('proyecto_educativo_bibliografia')->insert($base);
    }
}
