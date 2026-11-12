<?php

namespace Tests\Feature\Application\ProyectoEducativo;

use App\Application\Excepciones\DatosInvalidos;
use App\Application\Excepciones\PrecondicionIncumplida;
use App\Application\ProyectoEducativo\DTO\DatosPerfilDocenteProyecto;
use App\Application\ProyectoEducativo\GuardarPerfilDocenteProyecto;
use App\Application\ProyectoEducativo\PerfilDocenteProyectoCapturada;
use App\Application\ProyectoEducativo\ReglasPerfilDocenteProyecto;
use App\Application\ProyectoEducativo\SeccionesProyectoEducativo;
use App\Application\Tramite\EstadoPaso3;
use App\Application\Tramite\TramiteEditable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\PreparaPaso3;
use Tests\TestCase;

class GuardarPerfilDocenteProyectoTest extends TestCase
{
    use PreparaPaso3;
    use RefreshDatabase;

    private function palabras(int $n): string
    {
        return trim(str_repeat('palabra ', $n));
    }

    private function guardar(int $nivelId, ?string $texto): void
    {
        app(GuardarPerfilDocenteProyecto::class)->ejecutar($nivelId, new DatosPerfilDocenteProyecto($texto));
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
        $this->assertSame('perfil-docente', SeccionesProyectoEducativo::perfilDocente());
    }

    public function test_guarda_y_marca_completa(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');

        $this->guardar($nivel->id, $this->palabras(200));

        $capturada = app(PerfilDocenteProyectoCapturada::class);
        $this->assertSame(1, DB::table('proyecto_educativo_perfil_docente')->where('escuela_nivel_id', $nivel->id)->count());
        $this->assertTrue($capturada->completa($nivel->id));
        $this->assertSame($this->palabras(200), $capturada->datos($nivel->id)['habilidades_virtudes']);
    }

    public function test_sin_fila_no_esta_completa_y_datos_vacios(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');
        $capturada = app(PerfilDocenteProyectoCapturada::class);

        $this->assertFalse($capturada->completa($nivel->id));
        $this->assertSame(['habilidades_virtudes' => null], $capturada->datos($nivel->id));
    }

    public function test_el_rango_es_200_a_400(): void
    {
        $this->assertSame(['habilidades_virtudes' => [200, 400]], ReglasPerfilDocenteProyecto::RANGOS);
    }

    public function test_limites_de_palabras(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');

        foreach ([199, 401] as $n) {
            $this->assertSame(['habilidades_virtudes'], array_keys($this->errores($nivel->id, $this->palabras($n))), "con {$n}");
        }
        $this->assertSame(0, DB::table('proyecto_educativo_perfil_docente')->count());

        foreach ([200, 400] as $n) {
            $this->guardar($nivel->id, $this->palabras($n));
            $this->assertSame($this->palabras($n), DB::table('proyecto_educativo_perfil_docente')->value('habilidades_virtudes'));
        }
    }

    public function test_vacio_o_de_espacios_se_rechaza(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');

        foreach ([null, "  \n\t "] as $vacio) {
            $this->assertSame(['habilidades_virtudes'], array_keys($this->errores($nivel->id, $vacio)));
        }
        $this->assertSame(0, DB::table('proyecto_educativo_perfil_docente')->count());
    }

    public function test_segundo_guardado_actualiza_la_misma_fila(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');

        $this->guardar($nivel->id, $this->palabras(200));
        $this->guardar($nivel->id, $this->palabras(250));

        $this->assertSame(1, DB::table('proyecto_educativo_perfil_docente')->count());
        $this->assertSame($this->palabras(250), DB::table('proyecto_educativo_perfil_docente')->value('habilidades_virtudes'));
    }

    public function test_no_marca_el_paso_completado(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');

        $this->guardar($nivel->id, $this->palabras(200));

        $this->assertFalse(app(EstadoPaso3::class)->completado($nivel->id, SeccionesProyectoEducativo::CLAVE_PASO));
    }

    public function test_tramite_enviado_rechaza_el_guardado(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');
        $nivel->update(['estado_id' => DB::table('estados_expediente')->where('clave', 'en_revision')->value('id')]);

        try {
            $this->guardar($nivel->id, $this->palabras(200));
            $this->fail('Debió rechazar el guardado.');
        } catch (PrecondicionIncumplida $e) {
            $this->assertSame(TramiteEditable::ENVIADO, $e->etapaFaltante);
        }
        $this->assertSame(0, DB::table('proyecto_educativo_perfil_docente')->count());
    }

    public function test_antes_de_matricula_rechaza_el_guardado(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'matricula');

        $this->expectException(PrecondicionIncumplida::class);
        $this->guardar($nivel->id, $this->palabras(200));
    }

    public function test_los_campos_del_dto_salen_del_constructor_y_coinciden_con_las_reglas(): void
    {
        $this->assertSame(['habilidades_virtudes'], DatosPerfilDocenteProyecto::campos());
        $this->assertSame(array_keys(ReglasPerfilDocenteProyecto::RANGOS), DatosPerfilDocenteProyecto::campos());
    }

    public function test_la_base_exige_una_fila_por_nivel(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');
        DB::table('proyecto_educativo_perfil_docente')->insert(['escuela_nivel_id' => $nivel->id]);

        $this->expectException(QueryException::class);
        DB::table('proyecto_educativo_perfil_docente')->insert(['escuela_nivel_id' => $nivel->id]);
    }
}
