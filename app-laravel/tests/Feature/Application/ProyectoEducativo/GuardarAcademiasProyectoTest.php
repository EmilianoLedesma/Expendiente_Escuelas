<?php

namespace Tests\Feature\Application\ProyectoEducativo;

use App\Application\Excepciones\DatosInvalidos;
use App\Application\Excepciones\PrecondicionIncumplida;
use App\Application\ProyectoEducativo\AcademiasProyectoCapturada;
use App\Application\ProyectoEducativo\DTO\DatosAcademiasProyecto;
use App\Application\ProyectoEducativo\GuardarAcademiasProyecto;
use App\Application\ProyectoEducativo\ReglasAcademiasProyecto;
use App\Application\ProyectoEducativo\SeccionesProyectoEducativo;
use App\Application\Tramite\EstadoPaso3;
use App\Application\Tramite\TramiteEditable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\PreparaPaso3;
use Tests\TestCase;

class GuardarAcademiasProyectoTest extends TestCase
{
    use PreparaPaso3;
    use RefreshDatabase;

    private function palabras(int $n): string
    {
        return trim(str_repeat('palabra ', $n));
    }

    private function guardar(int $nivelId, ?string $tipo, ?string $descripcion, ?string $justificacion): void
    {
        app(GuardarAcademiasProyecto::class)->ejecutar($nivelId, new DatosAcademiasProyecto($tipo, $descripcion, $justificacion));
    }

    /** @return array<string, string> */
    private function errores(int $nivelId, ?string $tipo, ?string $descripcion, ?string $justificacion): array
    {
        try {
            $this->guardar($nivelId, $tipo, $descripcion, $justificacion);
        } catch (DatosInvalidos $e) {
            return $e->errores;
        }

        return [];
    }

    public function test_el_slug_sale_del_catalogo(): void
    {
        $this->assertSame('programa-de-academias-docentes', SeccionesProyectoEducativo::academias());
    }

    public function test_las_seis_opciones_del_dueno(): void
    {
        $this->assertSame(
            ['transversal', 'por_recurso_area', 'por_grados', 'por_recursos_afines', 'mixta', 'otros'],
            array_keys(ReglasAcademiasProyecto::TIPOS),
        );
        $this->assertSame(['mixta', 'otros'], ReglasAcademiasProyecto::REQUIEREN_DESCRIPCION);
    }

    public function test_guarda_un_tipo_sin_descripcion_y_marca_completa(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');

        $this->guardar($nivel->id, 'transversal', null, $this->palabras(100));

        $capturada = app(AcademiasProyectoCapturada::class);
        $this->assertTrue($capturada->completa($nivel->id));
        $this->assertSame(
            ['tipo_academia' => 'transversal', 'descripcion_academia' => null, 'justificacion_academia' => $this->palabras(100)],
            $capturada->datos($nivel->id),
        );
    }

    public function test_mixta_y_otros_exigen_descripcion(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');

        foreach (['mixta', 'otros'] as $tipo) {
            $this->assertSame(['descripcion_academia'], array_keys($this->errores($nivel->id, $tipo, null, $this->palabras(100))), $tipo);
            $this->assertSame(['descripcion_academia'], array_keys($this->errores($nivel->id, $tipo, $this->palabras(19), $this->palabras(100))), "{$tipo} 19");
            $this->assertSame(['descripcion_academia'], array_keys($this->errores($nivel->id, $tipo, $this->palabras(101), $this->palabras(100))), "{$tipo} 101");
        }
        $this->assertSame(0, DB::table('proyecto_educativo_academias')->count());

        $this->guardar($nivel->id, 'mixta', $this->palabras(20), $this->palabras(100));
        $this->assertSame($this->palabras(20), DB::table('proyecto_educativo_academias')->value('descripcion_academia'));
    }

    public function test_con_otro_tipo_la_descripcion_se_descarta(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');

        $this->guardar($nivel->id, 'por_grados', $this->palabras(30), $this->palabras(100));

        $this->assertNull(DB::table('proyecto_educativo_academias')->value('descripcion_academia'));
    }

    public function test_cambiar_de_mixta_a_otro_tipo_borra_la_descripcion_guardada(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');

        $this->guardar($nivel->id, 'mixta', $this->palabras(30), $this->palabras(100));
        $this->guardar($nivel->id, 'transversal', $this->palabras(30), $this->palabras(100));

        $this->assertSame(1, DB::table('proyecto_educativo_academias')->count());
        $this->assertNull(DB::table('proyecto_educativo_academias')->value('descripcion_academia'));
    }

    public function test_el_tipo_es_obligatorio_y_debe_ser_uno_de_la_lista(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');

        foreach ([null, '', 'inventado'] as $tipo) {
            $this->assertSame(['tipo_academia'], array_keys($this->errores($nivel->id, $tipo, null, $this->palabras(100))), var_export($tipo, true));
        }
        $this->assertSame(0, DB::table('proyecto_educativo_academias')->count());
    }

    public function test_limites_de_la_justificacion_100_a_250(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');

        foreach ([99, 251] as $n) {
            $this->assertSame(['justificacion_academia'], array_keys($this->errores($nivel->id, 'transversal', null, $this->palabras($n))), "con {$n}");
        }
        foreach ([null, "  \n "] as $vacio) {
            $this->assertSame(['justificacion_academia'], array_keys($this->errores($nivel->id, 'transversal', null, $vacio)));
        }
        $this->assertSame(0, DB::table('proyecto_educativo_academias')->count());

        foreach ([100, 250] as $n) {
            $this->guardar($nivel->id, 'transversal', null, $this->palabras($n));
            $this->assertSame($this->palabras($n), DB::table('proyecto_educativo_academias')->value('justificacion_academia'));
        }
    }

    public function test_todo_o_nada_con_un_error_no_escribe_nada(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');

        $errores = $this->errores($nivel->id, 'mixta', null, $this->palabras(5));

        $this->assertEqualsCanonicalizing(['descripcion_academia', 'justificacion_academia'], array_keys($errores));
        $this->assertSame(0, DB::table('proyecto_educativo_academias')->count());
    }

    public function test_sin_fila_no_esta_completa_y_datos_vacios(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');
        $capturada = app(AcademiasProyectoCapturada::class);

        $this->assertFalse($capturada->completa($nivel->id));
        $this->assertSame(['tipo_academia' => null, 'descripcion_academia' => null, 'justificacion_academia' => null], $capturada->datos($nivel->id));
    }

    public function test_no_marca_el_paso_completado(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');

        $this->guardar($nivel->id, 'transversal', null, $this->palabras(100));

        $this->assertFalse(app(EstadoPaso3::class)->completado($nivel->id, SeccionesProyectoEducativo::CLAVE_PASO));
    }

    public function test_tramite_enviado_rechaza_el_guardado(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');
        $nivel->update(['estado_id' => DB::table('estados_expediente')->where('clave', 'en_revision')->value('id')]);

        try {
            $this->guardar($nivel->id, 'transversal', null, $this->palabras(100));
            $this->fail('Debió rechazar el guardado.');
        } catch (PrecondicionIncumplida $e) {
            $this->assertSame(TramiteEditable::ENVIADO, $e->etapaFaltante);
        }
        $this->assertSame(0, DB::table('proyecto_educativo_academias')->count());
    }

    public function test_antes_de_matricula_rechaza_el_guardado(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'matricula');

        $this->expectException(PrecondicionIncumplida::class);
        $this->guardar($nivel->id, 'transversal', null, $this->palabras(100));
    }

    public function test_los_campos_del_dto_salen_del_constructor(): void
    {
        $this->assertSame(['tipo_academia', 'descripcion_academia', 'justificacion_academia'], DatosAcademiasProyecto::campos());
    }

    public function test_la_base_rechaza_tipo_invalido_y_descripcion_incoherente(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');
        $fila = ['escuela_nivel_id' => $nivel->id, 'justificacion_academia' => 'x'];

        foreach ([
            ['tipo_academia' => 'inventado', 'descripcion_academia' => null],
            ['tipo_academia' => 'mixta', 'descripcion_academia' => null],
            ['tipo_academia' => 'otros', 'descripcion_academia' => '  '],
            ['tipo_academia' => 'transversal', 'descripcion_academia' => 'sobra'],
        ] as $caso) {
            try {
                DB::table('proyecto_educativo_academias')->insert($fila + $caso);
                $this->fail('La base debió rechazar '.json_encode($caso));
            } catch (QueryException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_la_base_exige_una_fila_por_nivel(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');
        $fila = ['escuela_nivel_id' => $nivel->id, 'tipo_academia' => 'transversal', 'justificacion_academia' => 'x'];
        DB::table('proyecto_educativo_academias')->insert($fila);

        $this->expectException(QueryException::class);
        DB::table('proyecto_educativo_academias')->insert($fila);
    }
}
