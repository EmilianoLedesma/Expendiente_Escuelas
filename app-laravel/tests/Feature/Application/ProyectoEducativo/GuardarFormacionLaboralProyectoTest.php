<?php

namespace Tests\Feature\Application\ProyectoEducativo;

use App\Application\Excepciones\DatosInvalidos;
use App\Application\Excepciones\PrecondicionIncumplida;
use App\Application\ProyectoEducativo\DTO\DatosFormacionLaboralProyecto;
use App\Application\ProyectoEducativo\FormacionLaboralProyectoCapturada;
use App\Application\ProyectoEducativo\GuardarFormacionLaboralProyecto;
use App\Application\ProyectoEducativo\ReglasFormacionLaboralProyecto;
use App\Application\ProyectoEducativo\SeccionesProyectoEducativo;
use App\Application\Tramite\EstadoPaso3;
use App\Application\Tramite\TramiteEditable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\PreparaPaso3;
use Tests\TestCase;

class GuardarFormacionLaboralProyectoTest extends TestCase
{
    use PreparaPaso3;
    use RefreshDatabase;

    private function palabras(int $n): string
    {
        return trim(str_repeat('palabra ', $n));
    }

    private function guardar(int $nivelId, ?string $uno, ?string $dos, ?string $otras = null): void
    {
        app(GuardarFormacionLaboralProyecto::class)->ejecutar($nivelId, new DatosFormacionLaboralProyecto($uno, $dos, $otras));
    }

    /** @return array<string, string> */
    private function errores(int $nivelId, ?string $uno, ?string $dos, ?string $otras = null): array
    {
        try {
            $this->guardar($nivelId, $uno, $dos, $otras);
        } catch (DatosInvalidos $e) {
            return $e->errores;
        }

        return [];
    }

    /** @return array<string, array{string}> */
    public static function campos(): array
    {
        return ['formacion_laboral_1' => ['formacion_laboral_1'], 'formacion_laboral_2' => ['formacion_laboral_2'], 'otras_formaciones' => ['otras_formaciones']];
    }

    public function test_el_slug_sale_del_catalogo(): void
    {
        $this->assertSame('plan-de-estudios', SeccionesProyectoEducativo::planEstudios());
    }

    public function test_los_rangos_y_el_campo_opcional(): void
    {
        $this->assertSame([
            'formacion_laboral_1' => [100, 250],
            'formacion_laboral_2' => [100, 250],
            'otras_formaciones' => [100, 250],
        ], ReglasFormacionLaboralProyecto::RANGOS);
        $this->assertSame(['otras_formaciones'], ReglasFormacionLaboralProyecto::OPCIONALES);
    }

    public function test_guarda_con_otras_formaciones_y_marca_completa(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');

        $this->guardar($nivel->id, $this->palabras(100), $this->palabras(150), $this->palabras(200));

        $capturada = app(FormacionLaboralProyectoCapturada::class);
        $this->assertTrue($capturada->completa($nivel->id));
        $this->assertSame([
            'formacion_laboral_1' => $this->palabras(100),
            'formacion_laboral_2' => $this->palabras(150),
            'otras_formaciones' => $this->palabras(200),
        ], $capturada->datos($nivel->id));
    }

    public function test_otras_formaciones_vacia_es_valida_y_se_guarda_como_null(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');

        foreach ([null, '', "  \n "] as $vacio) {
            $this->guardar($nivel->id, $this->palabras(100), $this->palabras(100), $vacio);
            $this->assertNull(DB::table('proyecto_educativo_formacion_laboral')->value('otras_formaciones'));
        }
        $this->assertTrue(app(FormacionLaboralProyectoCapturada::class)->completa($nivel->id));
        $this->assertSame(1, DB::table('proyecto_educativo_formacion_laboral')->count());
    }

    public function test_otras_formaciones_con_texto_respeta_100_a_250(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');

        foreach ([1, 99, 251] as $n) {
            $this->assertSame(['otras_formaciones'], array_keys($this->errores($nivel->id, $this->palabras(100), $this->palabras(100), $this->palabras($n))), "con {$n}");
        }
        $this->assertSame(0, DB::table('proyecto_educativo_formacion_laboral')->count());

        foreach ([100, 250] as $n) {
            $this->guardar($nivel->id, $this->palabras(100), $this->palabras(100), $this->palabras($n));
            $this->assertSame($this->palabras($n), DB::table('proyecto_educativo_formacion_laboral')->value('otras_formaciones'));
        }
    }

    public function test_borrar_otras_formaciones_la_deja_en_null(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');
        $this->guardar($nivel->id, $this->palabras(100), $this->palabras(100), $this->palabras(120));

        $this->guardar($nivel->id, $this->palabras(100), $this->palabras(100), '');

        $this->assertNull(DB::table('proyecto_educativo_formacion_laboral')->value('otras_formaciones'));
        $this->assertSame(1, DB::table('proyecto_educativo_formacion_laboral')->count());
    }

    #[DataProvider('campos')]
    public function test_limites_100_a_250_por_campo(string $campo): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');
        $base = ['uno' => $this->palabras(100), 'dos' => $this->palabras(100), 'otras' => null];
        $clave = ['formacion_laboral_1' => 'uno', 'formacion_laboral_2' => 'dos', 'otras_formaciones' => 'otras'][$campo];

        foreach ([99, 251] as $n) {
            $this->assertSame([$campo], array_keys($this->errores($nivel->id, ...[...$base, $clave => $this->palabras($n)])), "{$campo} con {$n}");
        }
        $this->assertSame(0, DB::table('proyecto_educativo_formacion_laboral')->count());

        foreach ([100, 250] as $n) {
            $this->guardar($nivel->id, ...[...$base, $clave => $this->palabras($n)]);
            $this->assertSame($this->palabras($n), DB::table('proyecto_educativo_formacion_laboral')->value($campo));
        }
    }

    public function test_formacion_uno_y_dos_son_obligatorias(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');

        foreach ([null, '', "  \n\t "] as $vacio) {
            $this->assertSame(['formacion_laboral_1'], array_keys($this->errores($nivel->id, $vacio, $this->palabras(100))));
            $this->assertSame(['formacion_laboral_2'], array_keys($this->errores($nivel->id, $this->palabras(100), $vacio)));
        }
        $this->assertSame(0, DB::table('proyecto_educativo_formacion_laboral')->count());
    }

    public function test_todo_o_nada_con_un_error_no_escribe_nada(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');

        $errores = $this->errores($nivel->id, 'corto', $this->palabras(100), $this->palabras(5));

        $this->assertEqualsCanonicalizing(['formacion_laboral_1', 'otras_formaciones'], array_keys($errores));
        $this->assertSame(0, DB::table('proyecto_educativo_formacion_laboral')->count());
    }

    public function test_segundo_guardado_actualiza_la_misma_fila(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');

        $this->guardar($nivel->id, $this->palabras(100), $this->palabras(100));
        $this->guardar($nivel->id, $this->palabras(120), $this->palabras(130));

        $this->assertSame(1, DB::table('proyecto_educativo_formacion_laboral')->count());
        $this->assertSame($this->palabras(120), DB::table('proyecto_educativo_formacion_laboral')->value('formacion_laboral_1'));
    }

    public function test_sin_fila_no_esta_completa_y_los_datos_salen_vacios(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');
        $capturada = app(FormacionLaboralProyectoCapturada::class);

        $this->assertFalse($capturada->completa($nivel->id));
        $this->assertSame(['formacion_laboral_1' => null, 'formacion_laboral_2' => null, 'otras_formaciones' => null], $capturada->datos($nivel->id));
    }

    public function test_no_marca_el_paso_completado(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');

        $this->guardar($nivel->id, $this->palabras(100), $this->palabras(100));

        $this->assertFalse(app(EstadoPaso3::class)->completado($nivel->id, SeccionesProyectoEducativo::CLAVE_PASO));
    }

    public function test_tramite_enviado_rechaza_el_guardado(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');
        $nivel->update(['estado_id' => DB::table('estados_expediente')->where('clave', 'en_revision')->value('id')]);

        try {
            $this->guardar($nivel->id, $this->palabras(100), $this->palabras(100));
            $this->fail('Debió rechazar el guardado.');
        } catch (PrecondicionIncumplida $e) {
            $this->assertSame(TramiteEditable::ENVIADO, $e->etapaFaltante);
        }
        $this->assertSame(0, DB::table('proyecto_educativo_formacion_laboral')->count());
    }

    public function test_antes_de_matricula_rechaza_el_guardado(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'matricula');

        $this->expectException(PrecondicionIncumplida::class);
        $this->guardar($nivel->id, $this->palabras(100), $this->palabras(100));
    }

    public function test_los_campos_del_dto_salen_del_constructor(): void
    {
        $this->assertSame(['formacion_laboral_1', 'formacion_laboral_2', 'otras_formaciones'], DatosFormacionLaboralProyecto::campos());
    }

    public function test_la_base_rechaza_texto_en_blanco_y_exige_una_fila_por_nivel(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');

        foreach (['formacion_laboral_1', 'formacion_laboral_2', 'otras_formaciones'] as $campo) {
            $fila = ['escuela_nivel_id' => $nivel->id, 'formacion_laboral_1' => 'x', 'formacion_laboral_2' => 'x', $campo => '  '];
            try {
                DB::transaction(fn () => DB::table('proyecto_educativo_formacion_laboral')->insert($fila)); // savepoint: un insert fallido no aborta la transacción de la prueba
                $this->fail("La base debió rechazar {$campo} en blanco.");
            } catch (QueryException) {
                $this->addToAssertionCount(1);
            }
        }

        $fila = ['escuela_nivel_id' => $nivel->id, 'formacion_laboral_1' => 'x', 'formacion_laboral_2' => 'x'];
        DB::table('proyecto_educativo_formacion_laboral')->insert($fila);
        $this->expectException(QueryException::class);
        DB::table('proyecto_educativo_formacion_laboral')->insert($fila);
    }
}
