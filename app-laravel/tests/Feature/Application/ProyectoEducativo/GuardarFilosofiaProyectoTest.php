<?php

namespace Tests\Feature\Application\ProyectoEducativo;

use App\Application\Excepciones\DatosInvalidos;
use App\Application\Excepciones\PrecondicionIncumplida;
use App\Application\ProyectoEducativo\ConteoPalabras;
use App\Application\ProyectoEducativo\DTO\DatosFilosofiaProyecto;
use App\Application\ProyectoEducativo\FilosofiaProyectoCapturada;
use App\Application\ProyectoEducativo\GuardarFilosofiaProyecto;
use App\Application\ProyectoEducativo\ReglasFilosofiaProyecto;
use App\Application\ProyectoEducativo\SeccionesProyectoEducativo;
use App\Application\Tramite\EstadoPaso3;
use App\Application\Tramite\TramiteEditable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\PreparaPaso3;
use Tests\TestCase;

class GuardarFilosofiaProyectoTest extends TestCase
{
    use PreparaPaso3;
    use RefreshDatabase;

    private function palabras(int $n): string
    {
        return trim(str_repeat('palabra ', $n));
    }

    /** @param array<string, mixed> $cambios */
    private function datos(array $cambios = []): DatosFilosofiaProyecto
    {
        return new DatosFilosofiaProyecto(...array_merge([
            'mision' => $this->palabras(50),
            'vision' => $this->palabras(50),
            'valores' => $this->palabras(50),
        ], $cambios));
    }

    private function guardar(int $nivelId, DatosFilosofiaProyecto $datos): void
    {
        app(GuardarFilosofiaProyecto::class)->ejecutar($nivelId, $datos);
    }

    /** @return array<string, string> */
    private function errores(int $nivelId, DatosFilosofiaProyecto $datos): array
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
        $this->assertSame('filosofia-institucional', SeccionesProyectoEducativo::filosofia());
    }

    public function test_el_conteo_de_palabras_es_el_compartido(): void
    {
        $this->assertSame(3, ConteoPalabras::contar("  uno\n dos\u{00A0}tres "));
        $this->assertSame(0, ConteoPalabras::contar('   '));
        $this->assertSame(0, ConteoPalabras::contar(null));
    }

    public function test_guarda_completo_y_marca_completa(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');

        $this->guardar($nivel->id, $this->datos());

        $capturada = app(FilosofiaProyectoCapturada::class);
        $this->assertSame(1, DB::table('proyecto_educativo_filosofia')->where('escuela_nivel_id', $nivel->id)->count());
        $this->assertTrue($capturada->completa($nivel->id));
        $this->assertSame($this->palabras(50), $capturada->datos($nivel->id)['mision']);
    }

    public function test_un_guardado_parcial_se_rechaza_con_todos_los_errores_y_no_escribe(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');

        $errores = $this->errores($nivel->id, new DatosFilosofiaProyecto(mision: $this->palabras(10)));

        $this->assertEqualsCanonicalizing(['mision', 'vision', 'valores'], array_keys($errores));
        $this->assertSame(0, DB::table('proyecto_educativo_filosofia')->count());
        $this->assertFalse(app(FilosofiaProyectoCapturada::class)->completa($nivel->id));
    }

    public function test_sin_fila_no_esta_completa_y_datos_vacios(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');
        $capturada = app(FilosofiaProyectoCapturada::class);

        $this->assertFalse($capturada->completa($nivel->id));
        $this->assertSame(['mision' => null, 'vision' => null, 'valores' => null], $capturada->datos($nivel->id));
    }

    public function test_solo_espacios_cuenta_como_vacio_y_no_escribe(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');

        $this->assertSame(['mision'], array_keys($this->errores($nivel->id, $this->datos(['mision' => "  \n\t "]))));
        $this->assertSame(0, DB::table('proyecto_educativo_filosofia')->count());
    }

    public function test_segundo_guardado_actualiza_la_misma_fila(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');

        $this->guardar($nivel->id, $this->datos());
        $this->guardar($nivel->id, $this->datos(['vision' => $this->palabras(60)]));

        $this->assertSame(1, DB::table('proyecto_educativo_filosofia')->count());
        $this->assertSame($this->palabras(60), DB::table('proyecto_educativo_filosofia')->value('vision'));
    }

    /** @return array<string, array{string, int, int}> */
    public static function rangos(): array
    {
        return ['mision' => ['mision', 50, 150], 'vision' => ['vision', 50, 150], 'valores' => ['valores', 50, 250]];
    }

    #[DataProvider('rangos')]
    public function test_limites_de_palabras_por_campo(string $campo, int $min, int $max): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');
        $capturada = app(FilosofiaProyectoCapturada::class);
        $this->guardar($nivel->id, $this->datos());
        $valido = $this->palabras(50);

        // Por debajo del mínimo y por encima del máximo: se rechaza y no se escribe nada.
        foreach ([$min - 1, $max + 1] as $n) {
            $errores = $this->errores($nivel->id, $this->datos([$campo => $this->palabras($n)]));
            $this->assertSame([$campo], array_keys($errores), "{$campo} con {$n} palabras");
            $this->assertSame($valido, DB::table('proyecto_educativo_filosofia')->value($campo));
        }

        foreach ([$min, $max] as $n) {
            $this->guardar($nivel->id, $this->datos([$campo => $this->palabras($n)]));
            $this->assertSame($this->palabras($n), DB::table('proyecto_educativo_filosofia')->value($campo));
            $this->assertTrue($capturada->completa($nivel->id));
        }
    }

    public function test_cada_requerido_vacio_se_rechaza(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');

        foreach (['mision', 'vision', 'valores'] as $campo) {
            $this->assertSame([$campo], array_keys($this->errores($nivel->id, $this->datos([$campo => null]))));
        }
        $this->assertSame(0, DB::table('proyecto_educativo_filosofia')->count());
    }

    public function test_ya_no_existe_la_via_de_borrador(): void
    {
        $this->assertSame(1, (new \ReflectionMethod(ReglasFilosofiaProyecto::class, 'errores'))->getNumberOfParameters());
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
        $this->assertSame(0, DB::table('proyecto_educativo_filosofia')->count());
    }

    public function test_antes_de_matricula_rechaza_el_guardado(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'matricula');

        $this->expectException(PrecondicionIncumplida::class);
        $this->guardar($nivel->id, $this->datos());
    }

    public function test_los_campos_del_dto_salen_del_constructor(): void
    {
        $this->assertSame(array_keys((new DatosFilosofiaProyecto)->aArray()), DatosFilosofiaProyecto::campos());
        $this->assertSame(['mision', 'vision', 'valores'], DatosFilosofiaProyecto::campos());
    }

    public function test_la_base_exige_una_fila_por_nivel(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');
        DB::table('proyecto_educativo_filosofia')->insert(['escuela_nivel_id' => $nivel->id]);

        $this->expectException(QueryException::class);
        DB::table('proyecto_educativo_filosofia')->insert(['escuela_nivel_id' => $nivel->id]);
    }
}
