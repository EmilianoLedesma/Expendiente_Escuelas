<?php

namespace Tests\Feature\Application\ProyectoEducativo;

use App\Application\Excepciones\DatosInvalidos;
use App\Application\Excepciones\PrecondicionIncumplida;
use App\Application\ProyectoEducativo\DTO\DatosIngresoProyecto;
use App\Application\ProyectoEducativo\GuardarIngresoProyecto;
use App\Application\ProyectoEducativo\IngresoProyectoCapturada;
use App\Application\ProyectoEducativo\ReglasIngresoProyecto;
use App\Application\ProyectoEducativo\SeccionesProyectoEducativo;
use App\Application\Tramite\EstadoPaso3;
use App\Application\Tramite\TramiteEditable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\PreparaPaso3;
use Tests\TestCase;

class GuardarIngresoProyectoTest extends TestCase
{
    use PreparaPaso3;
    use RefreshDatabase;

    private function palabras(int $n): string
    {
        return trim(str_repeat('palabra ', $n));
    }

    /** @param array<string, mixed> $cambios */
    private function datos(array $cambios = []): DatosIngresoProyecto
    {
        return new DatosIngresoProyecto(...array_merge([
            'perfil_ingreso' => $this->palabras(50),
            'mecanismo_ingreso' => $this->palabras(100),
            'costo_usuario' => $this->palabras(30),
            'instrumentos_evaluacion' => $this->palabras(100),
        ], $cambios));
    }

    private function guardar(int $nivelId, DatosIngresoProyecto $datos): void
    {
        app(GuardarIngresoProyecto::class)->ejecutar($nivelId, $datos);
    }

    /** @return array<string, string> */
    private function errores(int $nivelId, DatosIngresoProyecto $datos): array
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
        $this->assertSame('perfil-de-ingreso', SeccionesProyectoEducativo::ingreso());
    }

    public function test_guarda_completo_y_marca_completa(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');

        $this->guardar($nivel->id, $this->datos());

        $capturada = app(IngresoProyectoCapturada::class);
        $this->assertSame(1, DB::table('proyecto_educativo_ingreso')->where('escuela_nivel_id', $nivel->id)->count());
        $this->assertTrue($capturada->completa($nivel->id));
        $this->assertSame($this->palabras(30), $capturada->datos($nivel->id)['costo_usuario']);
    }

    public function test_un_guardado_parcial_se_rechaza_con_todos_los_errores_y_no_escribe(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');

        $errores = $this->errores($nivel->id, new DatosIngresoProyecto(perfil_ingreso: $this->palabras(10)));

        $this->assertEqualsCanonicalizing(DatosIngresoProyecto::campos(), array_keys($errores));
        $this->assertSame(0, DB::table('proyecto_educativo_ingreso')->count());
        $this->assertFalse(app(IngresoProyectoCapturada::class)->completa($nivel->id));
    }

    public function test_sin_fila_no_esta_completa_y_datos_vacios(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');
        $capturada = app(IngresoProyectoCapturada::class);

        $this->assertFalse($capturada->completa($nivel->id));
        $this->assertSame(array_fill_keys(DatosIngresoProyecto::campos(), null), $capturada->datos($nivel->id));
    }

    /** @return array<string, array{string, int, int}> */
    public static function rangos(): array
    {
        $casos = [];
        foreach (ReglasIngresoProyecto::RANGOS as $campo => [$min, $max]) {
            $casos[$campo] = [$campo, $min, $max];
        }

        return $casos;
    }

    public function test_los_rangos_son_los_del_dueno(): void
    {
        $this->assertSame([
            'perfil_ingreso' => [50, 150],
            'mecanismo_ingreso' => [100, 250],
            'costo_usuario' => [30, 100],
            'instrumentos_evaluacion' => [100, 250],
        ], ReglasIngresoProyecto::RANGOS);
    }

    #[DataProvider('rangos')]
    public function test_limites_de_palabras_por_campo(string $campo, int $min, int $max): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');

        foreach ([$min - 1, $max + 1] as $n) {
            $this->assertSame([$campo], array_keys($this->errores($nivel->id, $this->datos([$campo => $this->palabras($n)]))), "{$campo} con {$n}");
        }
        $this->assertSame(0, DB::table('proyecto_educativo_ingreso')->count());

        foreach ([$min, $max] as $n) {
            $this->guardar($nivel->id, $this->datos([$campo => $this->palabras($n)]));
            $this->assertSame($this->palabras($n), DB::table('proyecto_educativo_ingreso')->value($campo));
        }
    }

    #[DataProvider('rangos')]
    public function test_cada_requerido_vacio_o_de_espacios_se_rechaza(string $campo): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');

        foreach ([null, "  \n\t "] as $vacio) {
            $this->assertSame([$campo], array_keys($this->errores($nivel->id, $this->datos([$campo => $vacio]))));
        }
        $this->assertSame(0, DB::table('proyecto_educativo_ingreso')->count());
    }

    public function test_segundo_guardado_actualiza_la_misma_fila(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');

        $this->guardar($nivel->id, $this->datos());
        $this->guardar($nivel->id, $this->datos(['costo_usuario' => $this->palabras(60)]));

        $this->assertSame(1, DB::table('proyecto_educativo_ingreso')->count());
        $this->assertSame($this->palabras(60), DB::table('proyecto_educativo_ingreso')->value('costo_usuario'));
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
        $this->assertSame(0, DB::table('proyecto_educativo_ingreso')->count());
    }

    public function test_antes_de_matricula_rechaza_el_guardado(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'matricula');

        $this->expectException(PrecondicionIncumplida::class);
        $this->guardar($nivel->id, $this->datos());
    }

    public function test_los_campos_del_dto_salen_del_constructor_y_coinciden_con_las_reglas(): void
    {
        $this->assertSame(array_keys((new DatosIngresoProyecto)->aArray()), DatosIngresoProyecto::campos());
        $this->assertSame(array_keys(ReglasIngresoProyecto::RANGOS), DatosIngresoProyecto::campos());
    }

    public function test_los_dos_bloques_cubren_los_campos_en_el_orden_del_dueno(): void
    {
        $this->assertCount(2, ReglasIngresoProyecto::BLOQUES);
        $this->assertSame(['perfil_ingreso', 'mecanismo_ingreso'], ReglasIngresoProyecto::BLOQUES[1]['campos']);
        $this->assertSame(['costo_usuario', 'instrumentos_evaluacion'], ReglasIngresoProyecto::BLOQUES[2]['campos']);
        $this->assertSame(DatosIngresoProyecto::campos(), array_merge(...array_column(ReglasIngresoProyecto::BLOQUES, 'campos')));
    }

    public function test_la_base_exige_una_fila_por_nivel(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');
        DB::table('proyecto_educativo_ingreso')->insert(['escuela_nivel_id' => $nivel->id]);

        $this->expectException(QueryException::class);
        DB::table('proyecto_educativo_ingreso')->insert(['escuela_nivel_id' => $nivel->id]);
    }
}
