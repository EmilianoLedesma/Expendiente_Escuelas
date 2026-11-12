<?php

namespace Tests\Feature\Application\ProyectoEducativo;

use App\Application\Excepciones\DatosInvalidos;
use App\Application\Excepciones\PrecondicionIncumplida;
use App\Application\ProyectoEducativo\DTO\DatosEnfoqueProyecto;
use App\Application\ProyectoEducativo\EnfoqueProyectoCapturada;
use App\Application\ProyectoEducativo\GuardarEnfoqueProyecto;
use App\Application\ProyectoEducativo\SeccionesProyectoEducativo;
use App\Application\Tramite\EstadoPaso3;
use App\Application\Tramite\TramiteEditable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\PreparaPaso3;
use Tests\TestCase;

class GuardarEnfoqueProyectoTest extends TestCase
{
    use PreparaPaso3;
    use RefreshDatabase;

    private function palabras(int $n): string
    {
        return trim(str_repeat('palabra ', $n));
    }

    /** @param array<string, mixed> $cambios */
    private function datos(array $cambios = []): DatosEnfoqueProyecto
    {
        return new DatosEnfoqueProyecto(...array_merge([
            'propuesta_global' => $this->palabras(100),
            'apropiacion_enfoque' => $this->palabras(450),
            'estrategias_centrales' => [$this->palabras(20), $this->palabras(20)],
            'actividades_sustantivas' => [$this->palabras(20), $this->palabras(20)],
        ], $cambios));
    }

    private function guardar(int $nivelId, DatosEnfoqueProyecto $datos): void
    {
        app(GuardarEnfoqueProyecto::class)->ejecutar($nivelId, $datos);
    }

    /** @return array<string, string> */
    private function errores(int $nivelId, DatosEnfoqueProyecto $datos): array
    {
        try {
            $this->guardar($nivelId, $datos);
        } catch (DatosInvalidos $e) {
            return $e->errores;
        }

        return [];
    }

    public function test_el_slug_sale_del_catalogo(): void
    {
        $this->assertSame('enfoque-pedagogico', SeccionesProyectoEducativo::enfoque());
    }

    public function test_guarda_completo_y_marca_completa(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');

        $this->guardar($nivel->id, $this->datos());

        $capturada = app(EnfoqueProyectoCapturada::class);
        $this->assertSame(1, DB::table('proyecto_educativo_enfoque')->where('escuela_nivel_id', $nivel->id)->count());
        $this->assertTrue($capturada->completa($nivel->id));
        $this->assertSame([$this->palabras(20), $this->palabras(20)], $capturada->datos($nivel->id)['estrategias_centrales']);
    }

    public function test_un_guardado_parcial_se_rechaza_con_todos_los_errores_y_no_escribe(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');

        $errores = $this->errores($nivel->id, new DatosEnfoqueProyecto(propuesta_global: $this->palabras(10)));

        $this->assertEqualsCanonicalizing(['propuesta_global', 'apropiacion_enfoque', 'estrategias_centrales', 'actividades_sustantivas'], array_keys($errores));
        $this->assertSame(0, DB::table('proyecto_educativo_enfoque')->count());
        $this->assertFalse(app(EnfoqueProyectoCapturada::class)->completa($nivel->id));
    }

    public function test_sin_fila_no_esta_completa_y_datos_vacios(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');
        $capturada = app(EnfoqueProyectoCapturada::class);

        $this->assertFalse($capturada->completa($nivel->id));
        $this->assertSame(['propuesta_global' => null, 'apropiacion_enfoque' => null, 'estrategias_centrales' => null, 'actividades_sustantivas' => null], $capturada->datos($nivel->id));
    }

    /** @return array<string, array{string, int, int}> */
    public static function rangos(): array
    {
        return ['propuesta_global' => ['propuesta_global', 100, 200], 'apropiacion_enfoque' => ['apropiacion_enfoque', 450, 600]];
    }

    #[DataProvider('rangos')]
    public function test_limites_de_palabras_por_campo(string $campo, int $min, int $max): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');
        $this->guardar($nivel->id, $this->datos());
        $valido = DB::table('proyecto_educativo_enfoque')->value($campo);

        foreach ([$min - 1, $max + 1] as $n) {
            $errores = $this->errores($nivel->id, $this->datos([$campo => $this->palabras($n)]));
            $this->assertSame([$campo], array_keys($errores), "{$campo} con {$n} palabras");
            $this->assertSame($valido, DB::table('proyecto_educativo_enfoque')->value($campo));
        }

        foreach ([$min, $max] as $n) {
            $this->guardar($nivel->id, $this->datos([$campo => $this->palabras($n)]));
            $this->assertSame($this->palabras($n), DB::table('proyecto_educativo_enfoque')->value($campo));
        }
    }

    /** @return array<string, array{string}> */
    public static function listas(): array
    {
        return ['estrategias' => ['estrategias_centrales'], 'actividades' => ['actividades_sustantivas']];
    }

    #[DataProvider('listas')]
    public function test_una_lista_lleva_de_dos_a_cinco_ejemplos(string $lista): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');
        $uno = [$this->palabras(20)];
        $seis = array_fill(0, 6, $this->palabras(20));

        foreach ([$uno, $seis, []] as $malo) {
            $this->assertSame([$lista], array_keys($this->errores($nivel->id, $this->datos([$lista => $malo]))));
        }
        $this->assertSame(0, DB::table('proyecto_educativo_enfoque')->count());

        $this->guardar($nivel->id, $this->datos([$lista => array_fill(0, 5, $this->palabras(20))]));
        $this->assertCount(5, app(EnfoqueProyectoCapturada::class)->datos($nivel->id)[$lista]);
    }

    #[DataProvider('listas')]
    public function test_cada_ejemplo_lleva_de_veinte_a_ochenta_palabras(string $lista): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');

        foreach ([19, 81] as $n) {
            $errores = $this->errores($nivel->id, $this->datos([$lista => [$this->palabras(20), $this->palabras($n)]]));
            $this->assertSame(["{$lista}.1"], array_keys($errores), "{$lista} con {$n} palabras");
        }
        $this->assertSame(0, DB::table('proyecto_educativo_enfoque')->count());

        $this->guardar($nivel->id, $this->datos([$lista => [$this->palabras(20), $this->palabras(80)]]));
        $this->assertTrue(app(EnfoqueProyectoCapturada::class)->completa($nivel->id));
    }

    #[DataProvider('listas')]
    public function test_un_ejemplo_vacio_o_de_espacios_se_rechaza(string $lista): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');

        $errores = $this->errores($nivel->id, $this->datos([$lista => [$this->palabras(20), "  \n\t "]]));

        $this->assertSame(["{$lista}.1"], array_keys($errores));
        $this->assertSame(0, DB::table('proyecto_educativo_enfoque')->count());
    }

    public function test_segundo_guardado_actualiza_la_misma_fila_y_puede_quitar_ejemplos(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');

        $this->guardar($nivel->id, $this->datos(['estrategias_centrales' => array_fill(0, 4, $this->palabras(30))]));
        $this->guardar($nivel->id, $this->datos(['estrategias_centrales' => array_fill(0, 2, $this->palabras(30))]));

        $this->assertSame(1, DB::table('proyecto_educativo_enfoque')->count());
        $this->assertCount(2, app(EnfoqueProyectoCapturada::class)->datos($nivel->id)['estrategias_centrales']);
    }

    public function test_no_marca_el_paso_completado(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');

        $this->guardar($nivel->id, $this->datos());

        $this->assertFalse(app(EstadoPaso3::class)->completado($nivel->id, SeccionesProyectoEducativo::CLAVE_PASO));
    }

    public function test_tramite_enviado_rechaza_el_guardado(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');
        $nivel->update(['estado_id' => DB::table('estados_expediente')->where('clave', 'en_revision')->value('id')]);

        try {
            $this->guardar($nivel->id, $this->datos());
            $this->fail('Debió rechazar el guardado.');
        } catch (PrecondicionIncumplida $e) {
            $this->assertSame(TramiteEditable::ENVIADO, $e->etapaFaltante);
        }
        $this->assertSame(0, DB::table('proyecto_educativo_enfoque')->count());
    }

    public function test_antes_de_matricula_rechaza_el_guardado(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'matricula');

        $this->expectException(PrecondicionIncumplida::class);
        $this->guardar($nivel->id, $this->datos());
    }

    public function test_los_campos_del_dto_salen_del_constructor(): void
    {
        $this->assertSame(array_keys((new DatosEnfoqueProyecto)->aArray()), DatosEnfoqueProyecto::campos());
        $this->assertSame(['propuesta_global', 'apropiacion_enfoque', 'estrategias_centrales', 'actividades_sustantivas'], DatosEnfoqueProyecto::campos());
    }

    public function test_la_base_exige_una_fila_por_nivel(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');
        DB::table('proyecto_educativo_enfoque')->insert(['escuela_nivel_id' => $nivel->id]);

        $this->expectException(QueryException::class);
        DB::table('proyecto_educativo_enfoque')->insert(['escuela_nivel_id' => $nivel->id]);
    }

    public function test_la_base_rechaza_listas_fuera_de_dos_a_cinco(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');

        $this->expectException(QueryException::class);
        DB::table('proyecto_educativo_enfoque')->insert(['escuela_nivel_id' => $nivel->id, 'estrategias_centrales' => json_encode(['solo una'])]);
    }
}
