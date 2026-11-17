<?php

namespace Tests\Feature\Application\ProyectoEducativo;

use App\Application\Excepciones\DatosInvalidos;
use App\Application\Excepciones\PrecondicionIncumplida;
use App\Application\ProyectoEducativo\CriteriosProyectoCapturada;
use App\Application\ProyectoEducativo\DTO\DatosCriteriosProyecto;
use App\Application\ProyectoEducativo\GuardarCriteriosProyecto;
use App\Application\ProyectoEducativo\ReglasCriteriosProyecto;
use App\Application\ProyectoEducativo\SeccionesProyectoEducativo;
use App\Application\Tramite\EstadoPaso3;
use App\Application\Tramite\TramiteEditable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Tests\Concerns\PreparaPaso3;
use Tests\TestCase;

class GuardarCriteriosProyectoTest extends TestCase
{
    use PreparaPaso3;
    use RefreshDatabase;

    private const BLOQUE_1 = ['criterios_acreditacion', 'opciones_acreditacion', 'criterios_promocion'];

    private const BLOQUE_2 = ['criterios_titulacion', 'servicios_control_escolar'];

    private function palabras(int $n): string
    {
        return trim(str_repeat('palabra ', $n));
    }

    /** @param  array<string, string|null>  $campos  los que falten salen null */
    private function dto(array $campos): DatosCriteriosProyecto
    {
        return new DatosCriteriosProyecto(...$campos);
    }

    /** @return array<string, string> todos los campos con 100 palabras */
    private function validos(): array
    {
        return array_fill_keys([...self::BLOQUE_1, ...self::BLOQUE_2], $this->palabras(100));
    }

    /** @param  array<string, string|null>  $campos */
    private function guardar(int $nivelId, int $bloque, array $campos): void
    {
        app(GuardarCriteriosProyecto::class)->ejecutar($nivelId, $bloque, $this->dto($campos));
    }

    /**
     * @param  array<string, string|null>  $campos
     * @return array<string, string>
     */
    private function errores(int $nivelId, int $bloque, array $campos): array
    {
        try {
            $this->guardar($nivelId, $bloque, $campos);
        } catch (DatosInvalidos $e) {
            return $e->errores;
        }

        return [];
    }

    public function test_el_slug_sale_del_catalogo(): void
    {
        $this->assertSame('criterios-de-acreditacion-promocion-y-certificacion', SeccionesProyectoEducativo::criterios());
    }

    public function test_los_bloques_del_dueno(): void
    {
        $this->assertSame(self::BLOQUE_1, ReglasCriteriosProyecto::BLOQUES[1]['campos']);
        $this->assertSame(self::BLOQUE_2, ReglasCriteriosProyecto::BLOQUES[2]['campos']);
        foreach ([...self::BLOQUE_1, ...self::BLOQUE_2] as $campo) {
            $this->assertSame([100, 200], ReglasCriteriosProyecto::RANGOS[$campo], $campo);
        }
    }

    public function test_guardar_el_bloque_uno_escribe_solo_sus_campos_y_no_completa(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');

        $this->guardar($nivel->id, 1, $this->validos());

        $capturada = app(CriteriosProyectoCapturada::class);
        $datos = $capturada->datos($nivel->id);
        foreach (self::BLOQUE_1 as $campo) {
            $this->assertSame($this->palabras(100), $datos[$campo], $campo);
        }
        foreach (self::BLOQUE_2 as $campo) {
            $this->assertNull($datos[$campo], $campo);
        }
        $this->assertFalse($capturada->completa($nivel->id));
        $this->assertSame(1, DB::table('proyecto_educativo_criterios')->count());
    }

    public function test_los_dos_bloques_guardados_completan_la_seccion(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');

        $this->guardar($nivel->id, 1, $this->validos());
        $this->guardar($nivel->id, 2, $this->validos());

        $this->assertTrue(app(CriteriosProyectoCapturada::class)->completa($nivel->id));
        $this->assertSame(1, DB::table('proyecto_educativo_criterios')->count());
    }

    public function test_guardar_el_bloque_dos_solo_no_completa_y_deja_el_uno_vacio(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');

        $this->guardar($nivel->id, 2, $this->validos());

        $capturada = app(CriteriosProyectoCapturada::class);
        $this->assertFalse($capturada->completa($nivel->id));
        $this->assertNull($capturada->datos($nivel->id)['criterios_acreditacion']);
        $this->assertSame($this->palabras(100), $capturada->datos($nivel->id)['servicios_control_escolar']);
    }

    public function test_volver_a_guardar_un_bloque_no_toca_el_otro(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');
        $this->guardar($nivel->id, 1, $this->validos());
        $this->guardar($nivel->id, 2, $this->validos());

        $nuevo = $this->validos();
        $nuevo['criterios_acreditacion'] = $this->palabras(150);
        $nuevo['criterios_titulacion'] = null; // el bloque 2 llega vacío pero no se guarda
        $this->guardar($nivel->id, 1, $nuevo);

        $datos = app(CriteriosProyectoCapturada::class)->datos($nivel->id);
        $this->assertSame($this->palabras(150), $datos['criterios_acreditacion']);
        $this->assertSame($this->palabras(100), $datos['criterios_titulacion']);
    }

    public function test_limites_100_a_200_en_cada_campo_del_bloque(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');

        foreach ([1 => self::BLOQUE_1, 2 => self::BLOQUE_2] as $bloque => $campos) {
            foreach ($campos as $campo) {
                foreach ([99, 201] as $n) {
                    $this->assertSame([$campo], array_keys($this->errores($nivel->id, $bloque, [...$this->validos(), $campo => $this->palabras($n)])), "{$campo} con {$n}");
                }
                foreach ([null, "  \n "] as $vacio) {
                    $this->assertSame([$campo], array_keys($this->errores($nivel->id, $bloque, [...$this->validos(), $campo => $vacio])), "{$campo} vacío");
                }
            }
        }
        $this->assertSame(0, DB::table('proyecto_educativo_criterios')->count());

        foreach ([100, 200] as $n) {
            $this->guardar($nivel->id, 1, [...$this->validos(), 'criterios_promocion' => $this->palabras($n)]);
            $this->assertSame($this->palabras($n), DB::table('proyecto_educativo_criterios')->value('criterios_promocion'));
        }
    }

    public function test_un_bloque_solo_valida_sus_campos(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');
        $invalidoEnBloque2 = [...$this->validos(), 'criterios_titulacion' => 'corto', 'servicios_control_escolar' => null];

        $this->guardar($nivel->id, 1, $invalidoEnBloque2);

        $this->assertSame(1, DB::table('proyecto_educativo_criterios')->count());
    }

    public function test_todo_o_nada_dentro_del_bloque(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');

        $errores = $this->errores($nivel->id, 1, [...$this->validos(), 'criterios_acreditacion' => 'corto', 'criterios_promocion' => null]);

        $this->assertEqualsCanonicalizing(['criterios_acreditacion', 'criterios_promocion'], array_keys($errores));
        $this->assertSame(0, DB::table('proyecto_educativo_criterios')->count());
    }

    public function test_un_bloque_inexistente_se_rechaza(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');

        foreach ([0, 3] as $bloque) {
            try {
                $this->guardar($nivel->id, $bloque, $this->validos());
                $this->fail("Debió rechazar el bloque {$bloque}.");
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
        $this->assertSame(0, DB::table('proyecto_educativo_criterios')->count());
    }

    public function test_sin_fila_no_esta_completa_y_los_datos_salen_vacios(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');
        $capturada = app(CriteriosProyectoCapturada::class);

        $this->assertFalse($capturada->completa($nivel->id));
        $this->assertSame(array_fill_keys([...self::BLOQUE_1, ...self::BLOQUE_2], null), $capturada->datos($nivel->id));
    }

    public function test_no_marca_el_paso_completado(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');

        $this->guardar($nivel->id, 1, $this->validos());
        $this->guardar($nivel->id, 2, $this->validos());

        $this->assertFalse(app(EstadoPaso3::class)->completado($nivel->id, SeccionesProyectoEducativo::CLAVE_PASO));
    }

    public function test_tramite_enviado_rechaza_el_guardado(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');
        $nivel->update(['estado_id' => DB::table('estados_expediente')->where('clave', 'en_revision')->value('id')]);

        try {
            $this->guardar($nivel->id, 1, $this->validos());
            $this->fail('Debió rechazar el guardado.');
        } catch (PrecondicionIncumplida $e) {
            $this->assertSame(TramiteEditable::ENVIADO, $e->etapaFaltante);
        }
        $this->assertSame(0, DB::table('proyecto_educativo_criterios')->count());
    }

    public function test_antes_de_matricula_rechaza_el_guardado(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'matricula');

        $this->expectException(PrecondicionIncumplida::class);
        $this->guardar($nivel->id, 1, $this->validos());
    }

    public function test_los_campos_del_dto_salen_del_constructor(): void
    {
        $this->assertSame([...self::BLOQUE_1, ...self::BLOQUE_2], DatosCriteriosProyecto::campos());
    }

    public function test_la_base_rechaza_texto_en_blanco(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');

        foreach ([...self::BLOQUE_1, ...self::BLOQUE_2] as $campo) {
            try {
                DB::transaction(fn () => DB::table('proyecto_educativo_criterios')->insert(['escuela_nivel_id' => $nivel->id, $campo => '  '])); // savepoint: un insert fallido no aborta la transacción de la prueba
                $this->fail("La base debió rechazar {$campo} en blanco.");
            } catch (QueryException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_la_base_exige_una_fila_por_nivel(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');
        DB::table('proyecto_educativo_criterios')->insert(['escuela_nivel_id' => $nivel->id]);

        $this->expectException(QueryException::class);
        DB::table('proyecto_educativo_criterios')->insert(['escuela_nivel_id' => $nivel->id]);
    }
}
