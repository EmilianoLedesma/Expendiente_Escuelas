<?php

namespace Tests\Feature\Application\ProyectoEducativo;

use App\Application\Excepciones\DatosInvalidos;
use App\Application\Excepciones\PrecondicionIncumplida;
use App\Application\ProyectoEducativo\DTO\DatosFormacionInicialProyecto;
use App\Application\ProyectoEducativo\FormacionInicialProyectoCapturada;
use App\Application\ProyectoEducativo\GuardarFormacionInicialProyecto;
use App\Application\ProyectoEducativo\ReglasFormacionProyecto;
use App\Application\ProyectoEducativo\SeccionesProyectoEducativo;
use App\Application\Tramite\EstadoPaso3;
use App\Application\Tramite\TramiteEditable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\PreparaPaso3;
use Tests\TestCase;

class GuardarFormacionInicialProyectoTest extends TestCase
{
    use PreparaPaso3;
    use RefreshDatabase;

    private function palabras(int $n): string
    {
        return trim(str_repeat('palabra ', $n));
    }

    /** Una acción válida con lo mínimo de cada texto. @param  array<string, mixed>  $cambios @return array<string, mixed> */
    private function accion(array $cambios = []): array
    {
        $base = [
            'nombre' => 'Taller de didáctica',
            'formato' => 'taller',
            'formato_otro' => null,
            'duracion_horas' => '40',
            'dias' => ['lunes', 'viernes'],
            'hora_inicio' => '09:00',
            'hora_fin' => '13:00',
            'fecha_inicio' => '2026-08-03',
            'fecha_fin' => '2026-12-18',
            'otros' => null,
        ];
        foreach (ReglasFormacionProyecto::TEXTOS as $campo => [$min]) {
            $base[$campo] = $this->palabras($min);
        }

        return array_merge($base, $cambios);
    }

    /** @param  list<array<string, mixed>>|null  $acciones */
    private function datos(?array $acciones = null, ?string $politica = null): DatosFormacionInicialProyecto
    {
        return new DatosFormacionInicialProyecto($politica ?? $this->palabras(100), $acciones ?? [$this->accion()]);
    }

    private function guardar(int $nivelId, DatosFormacionInicialProyecto $datos): void
    {
        app(GuardarFormacionInicialProyecto::class)->ejecutar($nivelId, $datos);
    }

    /** @return array<string, string> */
    private function errores(int $nivelId, DatosFormacionInicialProyecto $datos): array
    {
        try {
            $this->guardar($nivelId, $datos);
        } catch (DatosInvalidos $e) {
            return $e->errores;
        }

        return [];
    }

    private function acciones(): Collection
    {
        return DB::table('proyecto_educativo_formacion_acciones')->orderBy('orden')->get();
    }

    public function test_el_slug_sale_del_catalogo(): void
    {
        $this->assertSame('formacion-docente', SeccionesProyectoEducativo::formacion());
    }

    public function test_guarda_completo_y_lo_lee_igual_y_marca_completa(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');
        $segunda = $this->accion(['nombre' => 'Curso de evaluación', 'formato' => 'otro', 'formato_otro' => 'Conversatorio', 'dias' => ['sabado'], 'otros' => 'Material impreso']);

        $this->guardar($nivel->id, $this->datos([$this->accion(), $segunda]));

        $datos = app(FormacionInicialProyectoCapturada::class)->datos($nivel->id);
        $this->assertSame($this->palabras(100), $datos['politica_inicial']);
        $this->assertCount(2, $datos['acciones']);
        $this->assertSame('09:00', $datos['acciones'][0]['hora_inicio']);
        $this->assertSame('2026-12-18', $datos['acciones'][0]['fecha_fin']);
        $this->assertSame('40', $datos['acciones'][0]['duracion_horas']);
        $this->assertSame(['lunes', 'viernes'], $datos['acciones'][0]['dias']);
        $this->assertSame('Conversatorio', $datos['acciones'][1]['formato_otro']);
        $this->assertSame('Material impreso', $datos['acciones'][1]['otros']);
        $this->assertTrue(app(FormacionInicialProyectoCapturada::class)->completa($nivel->id));
    }

    public function test_un_guardado_parcial_se_rechaza_con_errores_y_no_escribe(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');

        $errores = $this->errores($nivel->id, new DatosFormacionInicialProyecto($this->palabras(10), [['nombre' => 'x']]));

        $this->assertArrayHasKey('politica_inicial', $errores);
        foreach (['proposito', 'participantes', 'formato', 'duracion_horas', 'dias', 'hora_inicio', 'hora_fin', 'fecha_inicio', 'fecha_fin', 'temario', 'evaluacion', 'recursos', 'perfil_ponente', 'reconocimiento'] as $campo) {
            $this->assertArrayHasKey("acciones.0.{$campo}", $errores, $campo);
        }
        $this->assertArrayNotHasKey('acciones.0.nombre', $errores);
        $this->assertArrayNotHasKey('acciones.0.otros', $errores);
        $this->assertSame(0, DB::table('proyecto_educativo_formacion_politica')->count());
        $this->assertSame(0, DB::table('proyecto_educativo_formacion_acciones')->count());
        $this->assertFalse(app(FormacionInicialProyectoCapturada::class)->completa($nivel->id));
    }

    public function test_sin_filas_no_esta_completa_y_datos_vacios(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');
        $capturada = app(FormacionInicialProyectoCapturada::class);

        $this->assertFalse($capturada->completa($nivel->id));
        $this->assertSame(['politica_inicial' => null, 'acciones' => []], $capturada->datos($nivel->id));
    }

    public function test_los_limites_del_dueno_y_los_propuestos(): void
    {
        $this->assertSame([100, 250], ReglasFormacionProyecto::POLITICA);
        $this->assertSame(20, ReglasFormacionProyecto::MAX_ACCIONES);
        $this->assertSame(
            ['proposito' => [10, 100], 'participantes' => [5, 50], 'temario' => [20, 150], 'evaluacion' => [10, 100], 'recursos' => [5, 100], 'perfil_ponente' => [10, 100], 'reconocimiento' => [3, 50]],
            ReglasFormacionProyecto::TEXTOS,
        );
        $this->assertSame([1, 100], ReglasFormacionProyecto::OTROS);
        $this->assertSame(['taller', 'curso', 'seminario', 'diplomado', 'otro'], ReglasFormacionProyecto::FORMATOS);
        $this->assertSame(['lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado', 'domingo'], ReglasFormacionProyecto::DIAS);
    }

    public function test_limites_de_palabras_de_la_politica(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');

        foreach ([99, 251] as $n) {
            $this->assertSame(['politica_inicial'], array_keys($this->errores($nivel->id, $this->datos(null, $this->palabras($n)))), "con {$n}");
        }
        foreach ([100, 250] as $n) {
            $this->guardar($nivel->id, $this->datos(null, $this->palabras($n)));
            $this->assertSame($this->palabras($n), DB::table('proyecto_educativo_formacion_politica')->value('politica'));
        }
    }

    /** @return array<string, array{string, int, int}> */
    public static function textos(): array
    {
        $casos = [];
        foreach (ReglasFormacionProyecto::TEXTOS as $campo => [$min, $max]) {
            $casos[$campo] = [$campo, $min, $max];
        }

        return $casos;
    }

    #[DataProvider('textos')]
    public function test_limites_de_palabras_de_cada_texto_de_la_accion(string $campo, int $min, int $max): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');

        foreach ([$min - 1, $max + 1] as $n) {
            $this->assertSame(["acciones.0.{$campo}"], array_keys($this->errores($nivel->id, $this->datos([$this->accion([$campo => $this->palabras($n)])]))), "{$campo} con {$n}");
        }
        foreach ([$min, $max] as $n) {
            $this->guardar($nivel->id, $this->datos([$this->accion([$campo => $this->palabras($n)])]));
            $this->assertSame($this->palabras($n), DB::table('proyecto_educativo_formacion_acciones')->value($campo));
        }
        foreach ([null, "  \n"] as $vacio) {
            $this->assertSame(["acciones.0.{$campo}"], array_keys($this->errores($nivel->id, $this->datos([$this->accion([$campo => $vacio])]))));
        }
    }

    public function test_otros_es_opcional_pero_con_maximo(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');

        $this->guardar($nivel->id, $this->datos([$this->accion(['otros' => ''])]));
        $this->assertNull(DB::table('proyecto_educativo_formacion_acciones')->value('otros'));
        $this->assertSame(['acciones.0.otros'], array_keys($this->errores($nivel->id, $this->datos([$this->accion(['otros' => $this->palabras(101)])]))));
        $this->guardar($nivel->id, $this->datos([$this->accion(['otros' => $this->palabras(100)])]));
    }

    public function test_el_nombre_es_requerido_y_de_150_caracteres_como_maximo(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');

        foreach ([null, '  ', str_repeat('a', 151)] as $malo) {
            $this->assertSame(['acciones.0.nombre'], array_keys($this->errores($nivel->id, $this->datos([$this->accion(['nombre' => $malo])]))));
        }
        $this->guardar($nivel->id, $this->datos([$this->accion(['nombre' => str_repeat('a', 150)])]));
    }

    public function test_el_formato_es_de_la_lista_y_otro_exige_especificar(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');

        foreach ([null, '', 'webinar', 'Taller'] as $malo) {
            $this->assertSame(['acciones.0.formato'], array_keys($this->errores($nivel->id, $this->datos([$this->accion(['formato' => $malo])]))), var_export($malo, true));
        }
        foreach ([null, '  ', str_repeat('a', 61)] as $malo) {
            $this->assertSame(['acciones.0.formato_otro'], array_keys($this->errores($nivel->id, $this->datos([$this->accion(['formato' => 'otro', 'formato_otro' => $malo])]))));
        }
        $this->guardar($nivel->id, $this->datos([$this->accion(['formato' => 'otro', 'formato_otro' => 'Conversatorio'])]));
        $this->assertSame('Conversatorio', DB::table('proyecto_educativo_formacion_acciones')->value('formato_otro'));

        // Con un formato de la lista, el texto de "Otro" que haya quedado escrito se descarta.
        $this->guardar($nivel->id, $this->datos([$this->accion(['formato' => 'curso', 'formato_otro' => 'sobrante'])]));
        $this->assertNull(DB::table('proyecto_educativo_formacion_acciones')->value('formato_otro'));
    }

    public function test_la_duracion_es_un_numero_entero_de_horas_de_1_a_999(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');

        foreach ([null, '', '0', '1000', '-5', 'abc', '12.5', '40 horas'] as $malo) {
            $this->assertSame(['acciones.0.duracion_horas'], array_keys($this->errores($nivel->id, $this->datos([$this->accion(['duracion_horas' => $malo])]))), var_export($malo, true));
        }
        foreach (['1', '999', 40] as $bueno) {
            $this->guardar($nivel->id, $this->datos([$this->accion(['duracion_horas' => $bueno])]));
            $this->assertSame((int) $bueno, (int) DB::table('proyecto_educativo_formacion_acciones')->value('duracion_horas'));
        }
    }

    public function test_los_dias_son_al_menos_uno_validos_y_sin_repetir(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');

        foreach ([[], null, ['lunes', 'lunes'], ['funday'], 'lunes'] as $malo) {
            $errores = $this->errores($nivel->id, $this->datos([$this->accion(['dias' => $malo])]));
            $this->assertNotSame([], $errores, var_export($malo, true));
            foreach (array_keys($errores) as $clave) {
                $this->assertStringStartsWith('acciones.0.dias', $clave);
            }
        }
        $this->guardar($nivel->id, $this->datos([$this->accion(['dias' => ['domingo', 'lunes']])]));
        $this->assertSame(['lunes', 'domingo'], json_decode((string) DB::table('proyecto_educativo_formacion_acciones')->value('dias'), true));
    }

    public function test_las_horas_usan_formato_hh_mm_y_el_fin_es_despues_del_inicio(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');

        foreach (['9:00 pm', '25:00', 'nueve', '', null] as $malo) {
            $this->assertSame(['acciones.0.hora_inicio'], array_keys($this->errores($nivel->id, $this->datos([$this->accion(['hora_inicio' => $malo, 'hora_fin' => '23:00'])]))), var_export($malo, true));
        }
        foreach (['09:00', '08:00', '13:60', '', null] as $malo) {
            $this->assertSame(['acciones.0.hora_fin'], array_keys($this->errores($nivel->id, $this->datos([$this->accion(['hora_inicio' => '09:00', 'hora_fin' => $malo])]))), var_export($malo, true));
        }
        $this->guardar($nivel->id, $this->datos([$this->accion(['hora_inicio' => '00:00', 'hora_fin' => '23:59'])]));
    }

    public function test_las_fechas_usan_aaaa_mm_dd_y_el_fin_no_es_antes_del_inicio(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');

        foreach (['2026-13-45', '03/08/2026', 'agosto', '', null] as $malo) {
            $this->assertSame(['acciones.0.fecha_inicio'], array_keys($this->errores($nivel->id, $this->datos([$this->accion(['fecha_inicio' => $malo, 'fecha_fin' => '2027-01-01'])]))), var_export($malo, true));
        }
        foreach (['2026-08-02', '2026-02-30', '', null] as $malo) {
            $this->assertSame(['acciones.0.fecha_fin'], array_keys($this->errores($nivel->id, $this->datos([$this->accion(['fecha_inicio' => '2026-08-03', 'fecha_fin' => $malo])]))), var_export($malo, true));
        }
        $this->guardar($nivel->id, $this->datos([$this->accion(['fecha_inicio' => '2026-08-03', 'fecha_fin' => '2026-08-03'])]));
    }

    public function test_hay_de_una_a_veinte_acciones(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');

        $this->assertSame(['acciones'], array_keys($this->errores($nivel->id, $this->datos([]))));
        $this->assertSame(['acciones'], array_keys($this->errores($nivel->id, $this->datos(array_fill(0, 21, $this->accion())))));
        $this->guardar($nivel->id, $this->datos(array_fill(0, 20, $this->accion())));
        $this->assertCount(20, $this->acciones());
    }

    public function test_un_error_en_una_accion_nombra_su_indice(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');

        $errores = $this->errores($nivel->id, $this->datos([$this->accion(), $this->accion(['duracion_horas' => '0'])]));

        $this->assertSame(['acciones.1.duracion_horas'], array_keys($errores));
        $this->assertSame(0, $this->acciones()->count());
    }

    public function test_guardar_de_nuevo_reemplaza_las_acciones_en_orden(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');

        $this->guardar($nivel->id, $this->datos([$this->accion(['nombre' => 'A']), $this->accion(['nombre' => 'B']), $this->accion(['nombre' => 'C'])]));
        $this->assertSame(['A', 'B', 'C'], $this->acciones()->pluck('nombre')->all());

        $this->guardar($nivel->id, $this->datos([$this->accion(['nombre' => 'Z'])], $this->palabras(120)));

        $this->assertSame(['Z'], $this->acciones()->pluck('nombre')->all());
        $this->assertSame([1], $this->acciones()->pluck('orden')->map(fn ($o) => (int) $o)->all());
        $this->assertSame(1, DB::table('proyecto_educativo_formacion_politica')->count());
        $this->assertSame($this->palabras(120), DB::table('proyecto_educativo_formacion_politica')->value('politica'));
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
        $this->assertSame(0, DB::table('proyecto_educativo_formacion_acciones')->count());
    }

    public function test_antes_de_matricula_rechaza_el_guardado(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'matricula');

        $this->expectException(PrecondicionIncumplida::class);
        $this->guardar($nivel->id, $this->datos());
    }

    public function test_la_base_rechaza_una_politica_repetida_por_nivel_y_tipo(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');
        DB::table('proyecto_educativo_formacion_politica')->insert(['escuela_nivel_id' => $nivel->id, 'tipo' => 'inicial', 'politica' => 'x']);

        $this->expectException(QueryException::class);
        DB::table('proyecto_educativo_formacion_politica')->insert(['escuela_nivel_id' => $nivel->id, 'tipo' => 'inicial', 'politica' => 'y']);
    }

    /** @return array<string, array{array<string, mixed>}> */
    public static function filasInvalidas(): array
    {
        return [
            'hora_fin no posterior' => [['hora_inicio' => '10:00', 'hora_fin' => '10:00']],
            'fecha_fin anterior' => [['fecha_inicio' => '2026-08-03', 'fecha_fin' => '2026-08-02']],
            'otro sin texto' => [['formato' => 'otro', 'formato_otro' => null]],
            'texto sin otro' => [['formato' => 'taller', 'formato_otro' => 'x']],
            'formato fuera de la lista' => [['formato' => 'webinar']],
            'duración 0' => [['duracion_horas' => 0]],
            'duración 1000' => [['duracion_horas' => 1000]],
            'días vacíos' => [['dias' => '[]']],
            'días no es lista' => [['dias' => '{"a":1}']],
            'orden 0' => [['orden' => 0]],
        ];
    }

    /** @param  array<string, mixed>  $cambios */
    #[DataProvider('filasInvalidas')]
    public function test_la_base_rechaza_filas_de_accion_invalidas(array $cambios): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');
        $fila = [
            'escuela_nivel_id' => $nivel->id, 'tipo' => 'inicial', 'orden' => 1, 'nombre' => 'n', 'proposito' => 'p', 'participantes' => 'q',
            'formato' => 'taller', 'formato_otro' => null, 'duracion_horas' => 40, 'dias' => '["lunes"]', 'hora_inicio' => '09:00', 'hora_fin' => '13:00',
            'fecha_inicio' => '2026-08-03', 'fecha_fin' => '2026-12-18', 'temario' => 't', 'evaluacion' => 'e', 'recursos' => 'r', 'perfil_ponente' => 'pp', 'reconocimiento' => 'rc',
        ];

        DB::table('proyecto_educativo_formacion_acciones')->insert($fila);
        DB::table('proyecto_educativo_formacion_acciones')->delete();
        $this->expectException(QueryException::class);
        DB::table('proyecto_educativo_formacion_acciones')->insert(array_merge($fila, $cambios));
    }

    public function test_la_base_rechaza_un_orden_repetido(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');
        $fila = [
            'escuela_nivel_id' => $nivel->id, 'tipo' => 'inicial', 'orden' => 1, 'nombre' => 'n', 'proposito' => 'p', 'participantes' => 'q',
            'formato' => 'taller', 'duracion_horas' => 40, 'dias' => '["lunes"]', 'hora_inicio' => '09:00', 'hora_fin' => '13:00',
            'fecha_inicio' => '2026-08-03', 'fecha_fin' => '2026-12-18', 'temario' => 't', 'evaluacion' => 'e', 'recursos' => 'r', 'perfil_ponente' => 'pp', 'reconocimiento' => 'rc',
        ];
        DB::table('proyecto_educativo_formacion_acciones')->insert($fila);

        $this->expectException(QueryException::class);
        DB::table('proyecto_educativo_formacion_acciones')->insert($fila);
    }
}
