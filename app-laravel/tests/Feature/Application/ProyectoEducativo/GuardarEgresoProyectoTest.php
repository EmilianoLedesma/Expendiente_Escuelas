<?php

namespace Tests\Feature\Application\ProyectoEducativo;

use App\Application\Excepciones\DatosInvalidos;
use App\Application\Excepciones\PrecondicionIncumplida;
use App\Application\ProyectoEducativo\DTO\DatosEgresoProyecto;
use App\Application\ProyectoEducativo\EgresoProyectoCapturada;
use App\Application\ProyectoEducativo\GuardarEgresoProyecto;
use App\Application\ProyectoEducativo\ReglasEgresoProyecto;
use App\Application\ProyectoEducativo\SeccionesProyectoEducativo;
use App\Application\Tramite\EstadoPaso3;
use App\Application\Tramite\TramiteEditable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\PreparaPaso3;
use Tests\TestCase;

class GuardarEgresoProyectoTest extends TestCase
{
    use PreparaPaso3;
    use RefreshDatabase;

    private function palabras(int $n): string
    {
        return trim(str_repeat('palabra ', $n));
    }

    private function guardar(int $nivelId, ?string $texto): void
    {
        app(GuardarEgresoProyecto::class)->ejecutar($nivelId, new DatosEgresoProyecto($texto));
    }

    /** @return array<string, string> */
    private function errores(int $nivelId, ?string $texto): array
    {
        try {
            $this->guardar($nivelId, $texto);
        } catch (DatosInvalidos $e) {
            return $e->errores;
        }

        return [];
    }

    public function test_el_slug_sale_del_catalogo(): void
    {
        $this->assertSame('perfil-de-egreso', SeccionesProyectoEducativo::egreso());
    }

    public function test_guarda_y_marca_completa(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');

        $this->guardar($nivel->id, $this->palabras(200));

        $capturada = app(EgresoProyectoCapturada::class);
        $this->assertSame(1, DB::table('proyecto_educativo_egreso')->where('escuela_nivel_id', $nivel->id)->count());
        $this->assertTrue($capturada->completa($nivel->id));
        $this->assertSame($this->palabras(200), $capturada->datos($nivel->id)['perfil_egreso']);
    }

    public function test_sin_fila_no_esta_completa_y_datos_vacios(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');
        $capturada = app(EgresoProyectoCapturada::class);

        $this->assertFalse($capturada->completa($nivel->id));
        $this->assertSame(['perfil_egreso' => null], $capturada->datos($nivel->id));
    }

    public function test_el_rango_es_100_a_400(): void
    {
        $this->assertSame(['perfil_egreso' => [100, 400]], ReglasEgresoProyecto::RANGOS);
    }

    public function test_limites_de_palabras(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');

        foreach ([99, 401] as $n) {
            $this->assertSame(['perfil_egreso'], array_keys($this->errores($nivel->id, $this->palabras($n))), "con {$n}");
        }
        $this->assertSame(0, DB::table('proyecto_educativo_egreso')->count());

        foreach ([100, 400] as $n) {
            $this->guardar($nivel->id, $this->palabras($n));
            $this->assertSame($this->palabras($n), DB::table('proyecto_educativo_egreso')->value('perfil_egreso'));
        }
    }

    public function test_vacio_o_de_espacios_se_rechaza(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');

        foreach ([null, "  \n\t "] as $vacio) {
            $this->assertSame(['perfil_egreso'], array_keys($this->errores($nivel->id, $vacio)));
        }
        $this->assertSame(0, DB::table('proyecto_educativo_egreso')->count());
    }

    public function test_segundo_guardado_actualiza_la_misma_fila(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');

        $this->guardar($nivel->id, $this->palabras(100));
        $this->guardar($nivel->id, $this->palabras(150));

        $this->assertSame(1, DB::table('proyecto_educativo_egreso')->count());
        $this->assertSame($this->palabras(150), DB::table('proyecto_educativo_egreso')->value('perfil_egreso'));
    }

    public function test_no_marca_el_paso_completado(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');

        $this->guardar($nivel->id, $this->palabras(100));

        $this->assertFalse(app(EstadoPaso3::class)->completado($nivel->id, SeccionesProyectoEducativo::CLAVE_PASO));
    }

    public function test_tramite_enviado_rechaza_el_guardado(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');
        $nivel->update(['estado_id' => DB::table('estados_expediente')->where('clave', 'en_revision')->value('id')]);

        try {
            $this->guardar($nivel->id, $this->palabras(100));
            $this->fail('Debió rechazar el guardado.');
        } catch (PrecondicionIncumplida $e) {
            $this->assertSame(TramiteEditable::ENVIADO, $e->etapaFaltante);
        }
        $this->assertSame(0, DB::table('proyecto_educativo_egreso')->count());
    }

    public function test_antes_de_matricula_rechaza_el_guardado(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'matricula');

        $this->expectException(PrecondicionIncumplida::class);
        $this->guardar($nivel->id, $this->palabras(100));
    }

    public function test_los_campos_del_dto_salen_del_constructor_y_coinciden_con_las_reglas(): void
    {
        $this->assertSame(['perfil_egreso'], DatosEgresoProyecto::campos());
        $this->assertSame(array_keys(ReglasEgresoProyecto::RANGOS), DatosEgresoProyecto::campos());
    }

    public function test_la_base_exige_una_fila_por_nivel(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');
        DB::table('proyecto_educativo_egreso')->insert(['escuela_nivel_id' => $nivel->id]);

        $this->expectException(QueryException::class);
        DB::table('proyecto_educativo_egreso')->insert(['escuela_nivel_id' => $nivel->id]);
    }
}
