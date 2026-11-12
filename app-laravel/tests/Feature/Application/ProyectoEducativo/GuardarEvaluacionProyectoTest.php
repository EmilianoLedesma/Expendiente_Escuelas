<?php

namespace Tests\Feature\Application\ProyectoEducativo;

use App\Application\Excepciones\DatosInvalidos;
use App\Application\Excepciones\PrecondicionIncumplida;
use App\Application\ProyectoEducativo\DTO\DatosEvaluacionProyecto;
use App\Application\ProyectoEducativo\EvaluacionProyectoCapturada;
use App\Application\ProyectoEducativo\GuardarEvaluacionProyecto;
use App\Application\ProyectoEducativo\ReglasEvaluacionProyecto;
use App\Application\ProyectoEducativo\SeccionesProyectoEducativo;
use App\Application\Tramite\EstadoPaso3;
use App\Application\Tramite\TramiteEditable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\PreparaPaso3;
use Tests\TestCase;

class GuardarEvaluacionProyectoTest extends TestCase
{
    use PreparaPaso3;
    use RefreshDatabase;

    private function palabras(int $n): string
    {
        return trim(str_repeat('palabra ', $n));
    }

    /** @param array<string, mixed> $cambios */
    private function datos(array $cambios = []): DatosEvaluacionProyecto
    {
        return new DatosEvaluacionProyecto(...array_merge([
            'evaluacion_diagnostica' => $this->palabras(100),
            'evaluacion_formativa' => $this->palabras(100),
            'evaluacion_sumativa' => $this->palabras(100),
            'heteroevaluacion' => $this->palabras(100),
            'coevaluacion' => $this->palabras(100),
            'autoevaluacion' => $this->palabras(100),
            'elementos_porcentajes' => $this->palabras(200),
        ], $cambios));
    }

    private function guardar(int $nivelId, DatosEvaluacionProyecto $datos): void
    {
        app(GuardarEvaluacionProyecto::class)->ejecutar($nivelId, $datos);
    }

    /** @return array<string, string> */
    private function errores(int $nivelId, DatosEvaluacionProyecto $datos): array
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
        $this->assertSame('modelo-de-evaluacion', SeccionesProyectoEducativo::evaluacion());
    }

    public function test_guarda_completo_y_marca_completa(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');

        $this->guardar($nivel->id, $this->datos());

        $capturada = app(EvaluacionProyectoCapturada::class);
        $this->assertSame(1, DB::table('proyecto_educativo_evaluacion')->where('escuela_nivel_id', $nivel->id)->count());
        $this->assertTrue($capturada->completa($nivel->id));
        $this->assertSame($this->palabras(200), $capturada->datos($nivel->id)['elementos_porcentajes']);
    }

    public function test_un_guardado_parcial_se_rechaza_con_todos_los_errores_y_no_escribe(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');

        $errores = $this->errores($nivel->id, new DatosEvaluacionProyecto(evaluacion_diagnostica: $this->palabras(10)));

        $this->assertEqualsCanonicalizing(DatosEvaluacionProyecto::campos(), array_keys($errores));
        $this->assertSame(0, DB::table('proyecto_educativo_evaluacion')->count());
        $this->assertFalse(app(EvaluacionProyectoCapturada::class)->completa($nivel->id));
    }

    public function test_sin_fila_no_esta_completa_y_datos_vacios(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');
        $capturada = app(EvaluacionProyectoCapturada::class);

        $this->assertFalse($capturada->completa($nivel->id));
        $this->assertSame(array_fill_keys(DatosEvaluacionProyecto::campos(), null), $capturada->datos($nivel->id));
    }

    /** @return array<string, array{string, int, int}> */
    public static function rangos(): array
    {
        $casos = [];
        foreach (ReglasEvaluacionProyecto::RANGOS as $campo => [$min, $max]) {
            $casos[$campo] = [$campo, $min, $max];
        }

        return $casos;
    }

    public function test_los_rangos_son_los_del_dueno(): void
    {
        $this->assertSame([
            'evaluacion_diagnostica' => [100, 300], 'evaluacion_formativa' => [100, 300], 'evaluacion_sumativa' => [100, 300],
            'heteroevaluacion' => [100, 300], 'coevaluacion' => [100, 300], 'autoevaluacion' => [100, 300],
            'elementos_porcentajes' => [200, 500],
        ], ReglasEvaluacionProyecto::RANGOS);
    }

    #[DataProvider('rangos')]
    public function test_limites_de_palabras_por_campo(string $campo, int $min, int $max): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');
        $this->guardar($nivel->id, $this->datos());
        $valido = DB::table('proyecto_educativo_evaluacion')->value($campo);

        foreach ([$min - 1, $max + 1] as $n) {
            $errores = $this->errores($nivel->id, $this->datos([$campo => $this->palabras($n)]));
            $this->assertSame([$campo], array_keys($errores), "{$campo} con {$n} palabras");
            $this->assertSame($valido, DB::table('proyecto_educativo_evaluacion')->value($campo));
        }

        foreach ([$min, $max] as $n) {
            $this->guardar($nivel->id, $this->datos([$campo => $this->palabras($n)]));
            $this->assertSame($this->palabras($n), DB::table('proyecto_educativo_evaluacion')->value($campo));
        }
    }

    #[DataProvider('rangos')]
    public function test_cada_requerido_vacio_o_de_espacios_se_rechaza(string $campo): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');

        foreach ([null, "  \n\t "] as $vacio) {
            $this->assertSame([$campo], array_keys($this->errores($nivel->id, $this->datos([$campo => $vacio]))));
        }
        $this->assertSame(0, DB::table('proyecto_educativo_evaluacion')->count());
    }

    public function test_segundo_guardado_actualiza_la_misma_fila(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');

        $this->guardar($nivel->id, $this->datos());
        $this->guardar($nivel->id, $this->datos(['coevaluacion' => $this->palabras(150)]));

        $this->assertSame(1, DB::table('proyecto_educativo_evaluacion')->count());
        $this->assertSame($this->palabras(150), DB::table('proyecto_educativo_evaluacion')->value('coevaluacion'));
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
        $this->assertSame(0, DB::table('proyecto_educativo_evaluacion')->count());
    }

    public function test_antes_de_matricula_rechaza_el_guardado(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'matricula');

        $this->expectException(PrecondicionIncumplida::class);
        $this->guardar($nivel->id, $this->datos());
    }

    public function test_los_campos_del_dto_salen_del_constructor_y_los_bloques_los_cubren(): void
    {
        $this->assertSame(array_keys((new DatosEvaluacionProyecto)->aArray()), DatosEvaluacionProyecto::campos());
        $this->assertSame(array_keys(ReglasEvaluacionProyecto::RANGOS), DatosEvaluacionProyecto::campos());
        $this->assertSame(DatosEvaluacionProyecto::campos(), array_merge(...array_column(ReglasEvaluacionProyecto::BLOQUES, 'campos')));
        $this->assertCount(3, ReglasEvaluacionProyecto::BLOQUES);
    }

    public function test_la_base_exige_una_fila_por_nivel(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');
        DB::table('proyecto_educativo_evaluacion')->insert(['escuela_nivel_id' => $nivel->id]);

        $this->expectException(QueryException::class);
        DB::table('proyecto_educativo_evaluacion')->insert(['escuela_nivel_id' => $nivel->id]);
    }
}
