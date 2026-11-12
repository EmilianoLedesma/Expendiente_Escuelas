<?php

namespace Tests\Feature\Application\ProyectoEducativo;

use App\Application\Excepciones\DatosInvalidos;
use App\Application\Excepciones\PrecondicionIncumplida;
use App\Application\ProyectoEducativo\CurriculumProyectoCapturada;
use App\Application\ProyectoEducativo\DTO\DatosCurriculumProyecto;
use App\Application\ProyectoEducativo\GuardarCurriculumProyecto;
use App\Application\ProyectoEducativo\ReglasCurriculumProyecto;
use App\Application\ProyectoEducativo\SeccionesProyectoEducativo;
use App\Application\Tramite\EstadoPaso3;
use App\Application\Tramite\TramiteEditable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\PreparaPaso3;
use Tests\TestCase;

class GuardarCurriculumProyectoTest extends TestCase
{
    use PreparaPaso3;
    use RefreshDatabase;

    private function palabras(int $n): string
    {
        return trim(str_repeat('palabra ', $n));
    }

    /** @param array<string, mixed> $cambios */
    private function datos(array $cambios = []): DatosCurriculumProyecto
    {
        $base = [];
        foreach (array_keys(ReglasCurriculumProyecto::AMBITOS) as $ambito) {
            $base["{$ambito}_trabaja"] = 'si';
            $base["{$ambito}_justificacion"] = $this->palabras(100);
        }

        return new DatosCurriculumProyecto(...array_merge($base, $cambios));
    }

    private function guardar(int $nivelId, DatosCurriculumProyecto $datos): void
    {
        app(GuardarCurriculumProyecto::class)->ejecutar($nivelId, $datos);
    }

    /** @return array<string, string> */
    private function errores(int $nivelId, DatosCurriculumProyecto $datos): array
    {
        try {
            $this->guardar($nivelId, $datos);
        } catch (DatosInvalidos $e) {
            return $e->errores;
        }

        return [];
    }

    /** @return array<string, array{string}> */
    public static function ambitos(): array
    {
        $claves = array_keys(ReglasCurriculumProyecto::AMBITOS);

        return array_combine($claves, array_map(fn (string $a) => [$a], $claves));
    }

    public function test_el_slug_sale_del_catalogo(): void
    {
        $this->assertSame('curriculum-ampliado', SeccionesProyectoEducativo::curriculum());
    }

    public function test_guarda_completo_y_marca_completa(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');

        $this->guardar($nivel->id, $this->datos(['salud_trabaja' => 'no']));

        $capturada = app(CurriculumProyectoCapturada::class);
        $this->assertSame(1, DB::table('proyecto_educativo_curriculum')->where('escuela_nivel_id', $nivel->id)->count());
        $this->assertTrue($capturada->completa($nivel->id));
        $this->assertSame('no', $capturada->datos($nivel->id)['salud_trabaja']);
        $this->assertSame('si', $capturada->datos($nivel->id)['actividad_fisica_trabaja']);
        $this->assertFalse((bool) DB::table('proyecto_educativo_curriculum')->value('salud_trabaja'));
    }

    public function test_un_guardado_parcial_se_rechaza_con_todos_los_errores_y_no_escribe(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');

        $errores = $this->errores($nivel->id, new DatosCurriculumProyecto(salud_justificacion: $this->palabras(10)));

        $this->assertEqualsCanonicalizing(DatosCurriculumProyecto::campos(), array_keys($errores));
        $this->assertSame(0, DB::table('proyecto_educativo_curriculum')->count());
        $this->assertFalse(app(CurriculumProyectoCapturada::class)->completa($nivel->id));
    }

    public function test_sin_fila_no_esta_completa_y_datos_vacios(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');
        $capturada = app(CurriculumProyectoCapturada::class);

        $this->assertFalse($capturada->completa($nivel->id));
        $this->assertSame(array_fill_keys(DatosCurriculumProyecto::campos(), null), $capturada->datos($nivel->id));
    }

    public function test_el_rango_es_100_a_250_en_los_cinco_ambitos(): void
    {
        $this->assertSame(['practica_ciudadana', 'salud', 'actividad_fisica', 'sexualidad_genero', 'artistico_cultural'], array_keys(ReglasCurriculumProyecto::AMBITOS));
        $this->assertSame([100, 250], ReglasCurriculumProyecto::PALABRAS);
    }

    #[DataProvider('ambitos')]
    public function test_limites_de_palabras_de_la_justificacion(string $ambito): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');
        $campo = "{$ambito}_justificacion";

        foreach ([99, 251] as $n) {
            $this->assertSame([$campo], array_keys($this->errores($nivel->id, $this->datos([$campo => $this->palabras($n)]))), "{$campo} con {$n}");
        }
        $this->assertSame(0, DB::table('proyecto_educativo_curriculum')->count());

        foreach ([100, 250] as $n) {
            $this->guardar($nivel->id, $this->datos([$campo => $this->palabras($n)]));
            $this->assertSame($this->palabras($n), DB::table('proyecto_educativo_curriculum')->value($campo));
        }
    }

    #[DataProvider('ambitos')]
    public function test_la_justificacion_vacia_se_rechaza_tambien_con_no(string $ambito): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');

        foreach ([null, "  \n\t "] as $vacio) {
            $this->assertSame(["{$ambito}_justificacion"], array_keys($this->errores($nivel->id, $this->datos(["{$ambito}_trabaja" => 'no', "{$ambito}_justificacion" => $vacio]))));
        }
    }

    #[DataProvider('ambitos')]
    public function test_la_respuesta_solo_acepta_si_o_no(string $ambito): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');

        foreach ([null, '', 'tal vez', 'SI', '1'] as $malo) {
            $this->assertSame(["{$ambito}_trabaja"], array_keys($this->errores($nivel->id, $this->datos(["{$ambito}_trabaja" => $malo]))), var_export($malo, true));
        }
        $this->assertSame(0, DB::table('proyecto_educativo_curriculum')->count());
    }

    public function test_segundo_guardado_actualiza_la_misma_fila(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');

        $this->guardar($nivel->id, $this->datos());
        $this->guardar($nivel->id, $this->datos(['salud_trabaja' => 'no', 'salud_justificacion' => $this->palabras(150)]));

        $this->assertSame(1, DB::table('proyecto_educativo_curriculum')->count());
        $this->assertSame($this->palabras(150), DB::table('proyecto_educativo_curriculum')->value('salud_justificacion'));
        $this->assertSame('no', app(CurriculumProyectoCapturada::class)->datos($nivel->id)['salud_trabaja']);
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
        $this->assertSame(0, DB::table('proyecto_educativo_curriculum')->count());
    }

    public function test_antes_de_matricula_rechaza_el_guardado(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'matricula');

        $this->expectException(PrecondicionIncumplida::class);
        $this->guardar($nivel->id, $this->datos());
    }

    public function test_los_campos_del_dto_salen_del_constructor_y_los_bloques_los_cubren(): void
    {
        $this->assertSame(array_keys((new DatosCurriculumProyecto)->aArray()), DatosCurriculumProyecto::campos());
        $this->assertCount(10, DatosCurriculumProyecto::campos());
        $this->assertSame(DatosCurriculumProyecto::campos(), array_merge(...array_column(ReglasCurriculumProyecto::BLOQUES, 'campos')));
        $this->assertCount(5, ReglasCurriculumProyecto::BLOQUES);
    }

    public function test_la_base_exige_una_fila_por_nivel(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');
        DB::table('proyecto_educativo_curriculum')->insert(['escuela_nivel_id' => $nivel->id]);

        $this->expectException(QueryException::class);
        DB::table('proyecto_educativo_curriculum')->insert(['escuela_nivel_id' => $nivel->id]);
    }

    public function test_la_base_exige_respuesta_y_justificacion_juntas(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');

        $this->expectException(QueryException::class);
        DB::table('proyecto_educativo_curriculum')->insert(['escuela_nivel_id' => $nivel->id, 'salud_trabaja' => true]);
    }
}
