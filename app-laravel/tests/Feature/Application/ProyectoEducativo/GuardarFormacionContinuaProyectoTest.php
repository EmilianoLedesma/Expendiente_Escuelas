<?php

namespace Tests\Feature\Application\ProyectoEducativo;

use App\Application\Excepciones\DatosInvalidos;
use App\Application\Excepciones\PrecondicionIncumplida;
use App\Application\ProyectoEducativo\DTO\DatosFormacionContinuaProyecto;
use App\Application\ProyectoEducativo\DTO\DatosFormacionInicialProyecto;
use App\Application\ProyectoEducativo\FormacionContinuaProyectoCapturada;
use App\Application\ProyectoEducativo\GuardarFormacionContinuaProyecto;
use App\Application\ProyectoEducativo\GuardarFormacionInicialProyecto;
use App\Application\ProyectoEducativo\ReglasFormacionProyecto;
use App\Application\ProyectoEducativo\SeccionesProyectoEducativo;
use App\Application\Tramite\EstadoPaso3;
use App\Application\Tramite\TramiteEditable;
use App\Models\ProyectoEducativoFormacionPolitica;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\Concerns\GeneraPdfs;
use Tests\Concerns\PreparaPaso3;
use Tests\TestCase;

class GuardarFormacionContinuaProyectoTest extends TestCase
{
    use GeneraPdfs;
    use PreparaPaso3;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('documentos');
    }

    private function palabras(int $n): string
    {
        return trim(str_repeat('palabra ', $n));
    }

    /** @param  array<string, mixed>  $cambios @return array<string, mixed> */
    private function accion(array $cambios = []): array
    {
        $base = [
            'nombre' => 'Curso de actualización', 'formato' => 'curso', 'formato_otro' => null, 'duracion_horas' => '20', 'dias' => ['sabado'],
            'hora_inicio' => '10:00', 'hora_fin' => '14:00', 'fecha_inicio' => '2027-01-09', 'fecha_fin' => '2027-03-27', 'otros' => null,
        ];
        foreach (ReglasFormacionProyecto::TEXTOS as $campo => [$min]) {
            $base[$campo] = $this->palabras($min);
        }

        return array_merge($base, $cambios);
    }

    /** @param  array<string, mixed>  $cambios */
    private function datos(array $cambios = []): DatosFormacionContinuaProyecto
    {
        return new DatosFormacionContinuaProyecto(...array_merge([
            'politica_continua' => $this->palabras(100),
            'planeacion_continua' => $this->archivoPdf(1, 'planeacion.pdf'),
            'acciones_continua' => [$this->accion()],
        ], $cambios));
    }

    private function guardar(int $nivelId, DatosFormacionContinuaProyecto $datos): void
    {
        app(GuardarFormacionContinuaProyecto::class)->ejecutar($nivelId, $datos);
    }

    /** @return array<string, string> */
    private function errores(int $nivelId, DatosFormacionContinuaProyecto $datos): array
    {
        try {
            $this->guardar($nivelId, $datos);
        } catch (DatosInvalidos $e) {
            return $e->errores;
        }

        return [];
    }

    /** @return list<string> */
    private function archivosEnDisco(): array
    {
        return Storage::disk('documentos')->allFiles('proyecto_educativo');
    }

    private function politica(int $nivelId): object
    {
        return DB::table('proyecto_educativo_formacion_politica')->where('escuela_nivel_id', $nivelId)->where('tipo', 'continua')->sole();
    }

    private function acciones(string $tipo = 'continua'): Collection
    {
        return DB::table('proyecto_educativo_formacion_acciones')->where('tipo', $tipo)->orderBy('orden')->get();
    }

    public function test_guarda_completo_y_lo_lee_igual_y_marca_completa(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');

        $this->guardar($nivel->id, $this->datos(['acciones_continua' => [$this->accion(), $this->accion(['nombre' => 'Segunda', 'formato' => 'otro', 'formato_otro' => 'Círculo de estudio'])]]));

        $capturada = app(FormacionContinuaProyectoCapturada::class);
        $datos = $capturada->datos($nivel->id);
        $this->assertSame($this->palabras(100), $datos['politica_continua']);
        $this->assertSame('planeacion.pdf', $datos['planeacion_nombre']);
        $this->assertSame(1, $datos['planeacion_paginas']);
        $this->assertCount(2, $datos['acciones_continua']);
        $this->assertSame('10:00', $datos['acciones_continua'][0]['hora_inicio']);
        $this->assertSame('Círculo de estudio', $datos['acciones_continua'][1]['formato_otro']);
        $this->assertArrayNotHasKey('planeacion_path', $datos);
        $this->assertTrue($capturada->completa($nivel->id));
        $this->assertCount(1, $this->archivosEnDisco());
    }

    public function test_no_toca_el_bloque_inicial(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');
        app(GuardarFormacionInicialProyecto::class)->ejecutar($nivel->id, new DatosFormacionInicialProyecto($this->palabras(100), [$this->accion(['nombre' => 'Inicial'])]));

        $this->guardar($nivel->id, $this->datos());
        $this->guardar($nivel->id, $this->datos(['acciones_continua' => [$this->accion(), $this->accion()]]));

        $this->assertSame(['Inicial'], $this->acciones('inicial')->pluck('nombre')->all());
        $this->assertCount(2, $this->acciones('continua'));
        $this->assertSame(2, DB::table('proyecto_educativo_formacion_politica')->count());
    }

    public function test_sin_filas_no_esta_completa_y_datos_vacios(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');
        $capturada = app(FormacionContinuaProyectoCapturada::class);

        $this->assertFalse($capturada->completa($nivel->id));
        $this->assertSame(['politica_continua' => null, 'planeacion_nombre' => null, 'planeacion_paginas' => null, 'acciones_continua' => []], $capturada->datos($nivel->id));
    }

    public function test_sin_nada_se_rechazan_politica_planeacion_y_acciones(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');

        $errores = $this->errores($nivel->id, new DatosFormacionContinuaProyecto);

        $this->assertEqualsCanonicalizing(['politica_continua', 'planeacion_continua', 'acciones_continua'], array_keys($errores));
        $this->assertSame(0, DB::table('proyecto_educativo_formacion_politica')->count());
    }

    public function test_limites_de_palabras_de_la_politica(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');

        foreach ([99, 251] as $n) {
            $this->assertSame(['politica_continua'], array_keys($this->errores($nivel->id, $this->datos(['politica_continua' => $this->palabras($n)]))), "con {$n}");
        }
        foreach ([100, 250] as $n) {
            $this->guardar($nivel->id, $this->datos(['politica_continua' => $this->palabras($n)]));
            $this->assertSame($this->palabras($n), $this->politica($nivel->id)->politica);
        }
    }

    public function test_las_acciones_siguen_las_mismas_reglas_con_claves_de_la_continua(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');

        $errores = $this->errores($nivel->id, $this->datos(['acciones_continua' => [$this->accion(), $this->accion(['duracion_horas' => '0', 'hora_fin' => '09:00', 'proposito' => $this->palabras(3)])]]));

        $this->assertEqualsCanonicalizing(['acciones_continua.1.duracion_horas', 'acciones_continua.1.hora_fin', 'acciones_continua.1.proposito'], array_keys($errores));
        $this->assertSame([], $this->archivosEnDisco());
        $this->assertSame(['acciones_continua'], array_keys($this->errores($nivel->id, $this->datos(['acciones_continua' => []]))));
        $this->assertSame(['acciones_continua'], array_keys($this->errores($nivel->id, $this->datos(['acciones_continua' => array_fill(0, 21, $this->accion())]))));
    }

    public function test_un_pdf_de_una_o_dos_paginas_se_acepta_y_de_tres_se_rechaza(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');

        $errores = $this->errores($nivel->id, $this->datos(['planeacion_continua' => $this->archivoPdf(3)]));

        $this->assertSame(['planeacion_continua'], array_keys($errores));
        $this->assertStringContainsString('entre 1 y 2 páginas (tiene 3)', $errores['planeacion_continua']);
        $this->assertSame([], $this->archivosEnDisco());
        $this->assertSame(0, DB::table('proyecto_educativo_formacion_politica')->count());

        foreach ([1, 2] as $paginas) {
            $this->assertSame([], $this->errores($nivel->id, $this->datos(['planeacion_continua' => $this->archivoPdf($paginas)])), "{$paginas} página(s)");
        }
    }

    public function test_un_archivo_que_no_es_pdf_danado_o_enorme_se_rechaza(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');

        foreach ([
            ['PDF', UploadedFile::fake()->createWithContent('notas.pdf', 'texto plano')],
            ['No se pudo leer el PDF', UploadedFile::fake()->createWithContent('roto.pdf', "%PDF-1.4\n1 0 obj << /Broken >> endobj\ngarbage")],
            ['10 MB', $this->archivoPdf(1, 'grande.pdf', 10 * 1024 * 1024 + 1)],
        ] as [$mensaje, $archivo]) {
            $errores = $this->errores($nivel->id, $this->datos(['planeacion_continua' => $archivo]));
            $this->assertSame(['planeacion_continua'], array_keys($errores), $mensaje);
            $this->assertStringContainsString($mensaje, $errores['planeacion_continua']);
            $this->assertSame([], $this->archivosEnDisco());
        }
    }

    public function test_todo_o_nada_un_error_en_una_accion_no_sube_el_archivo_ni_escribe(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');

        $this->errores($nivel->id, $this->datos(['acciones_continua' => [$this->accion(['nombre' => ''])]]));

        $this->assertSame([], $this->archivosEnDisco());
        $this->assertSame(0, DB::table('proyecto_educativo_formacion_politica')->count());
        $this->assertSame(0, DB::table('proyecto_educativo_formacion_acciones')->count());
    }

    public function test_editar_sin_volver_a_subir_conserva_el_archivo_guardado(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');
        $this->guardar($nivel->id, $this->datos());
        $antes = $this->politica($nivel->id);

        $this->guardar($nivel->id, $this->datos(['planeacion_continua' => null, 'politica_continua' => $this->palabras(150), 'acciones_continua' => [$this->accion(['nombre' => 'Otra'])]]));

        $despues = $this->politica($nivel->id);
        $this->assertSame($this->palabras(150), $despues->politica);
        $this->assertSame($antes->planeacion_path, $despues->planeacion_path);
        $this->assertSame(['Otra'], $this->acciones()->pluck('nombre')->all());
        $this->assertCount(1, $this->archivosEnDisco());
    }

    public function test_sin_archivo_y_sin_nada_guardado_se_rechaza_el_campo(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');

        $this->assertSame(['planeacion_continua'], array_keys($this->errores($nivel->id, $this->datos(['planeacion_continua' => null]))));
    }

    public function test_reemplazar_el_archivo_borra_el_anterior(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');
        $this->guardar($nivel->id, $this->datos());
        $antes = $this->politica($nivel->id);

        $this->guardar($nivel->id, $this->datos(['planeacion_continua' => $this->archivoPdf(2, 'nueva.pdf')]));

        $despues = $this->politica($nivel->id);
        $this->assertNotSame($antes->planeacion_path, $despues->planeacion_path);
        $this->assertSame('nueva.pdf', $despues->planeacion_nombre);
        $this->assertSame(2, (int) $despues->planeacion_paginas);
        Storage::disk('documentos')->assertMissing($antes->planeacion_path);
        Storage::disk('documentos')->assertExists($despues->planeacion_path);
        $this->assertCount(1, $this->archivosEnDisco());
    }

    public function test_un_reemplazo_con_error_conserva_lo_guardado(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');
        $this->guardar($nivel->id, $this->datos());
        $antes = $this->politica($nivel->id);

        $this->errores($nivel->id, $this->datos(['planeacion_continua' => $this->archivoPdf(3)]));

        $this->assertEquals($antes, $this->politica($nivel->id));
        $this->assertCount(1, $this->archivosEnDisco());
    }

    public function test_si_la_transaccion_exterior_se_revierte_no_quedan_archivos_nuevos(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');

        try {
            DB::transaction(function () use ($nivel) {
                $this->guardar($nivel->id, $this->datos());

                throw new RuntimeException('revertir');
            });
            $this->fail('Se esperaba RuntimeException.');
        } catch (RuntimeException $e) {
            $this->assertSame('revertir', $e->getMessage());
        }

        $this->assertSame(0, DB::table('proyecto_educativo_formacion_politica')->count());
        $this->assertSame([], $this->archivosEnDisco());
    }

    public function test_no_marca_el_paso_completado(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');

        $this->guardar($nivel->id, $this->datos());

        $this->assertFalse(app(EstadoPaso3::class)->completado($nivel->id, SeccionesProyectoEducativo::CLAVE_PASO));
    }

    public function test_tramite_enviado_rechaza_el_guardado_y_no_sube_nada(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');
        $nivel->update(['estado_id' => DB::table('estados_expediente')->where('clave', 'en_revision')->value('id')]);

        try {
            $this->guardar($nivel->id, $this->datos());
            $this->fail('Debió rechazar el guardado.');
        } catch (PrecondicionIncumplida $e) {
            $this->assertSame(TramiteEditable::ENVIADO, $e->etapaFaltante);
        }
        $this->assertSame([], $this->archivosEnDisco());
        $this->assertSame(0, DB::table('proyecto_educativo_formacion_acciones')->count());
    }

    public function test_antes_de_matricula_rechaza_el_guardado(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'matricula');

        $this->expectException(PrecondicionIncumplida::class);
        $this->guardar($nivel->id, $this->datos());
    }

    public function test_rutas_de_reune_solo_las_rutas_con_archivo(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');
        $this->assertSame([], ProyectoEducativoFormacionPolitica::rutasDe([$nivel->id]));

        $this->guardar($nivel->id, $this->datos());

        $this->assertSame([$this->politica($nivel->id)->planeacion_path], ProyectoEducativoFormacionPolitica::rutasDe([$nivel->id]));
    }

    public function test_la_base_exige_el_archivo_completo_y_de_1_a_2_paginas(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');

        foreach ([
            ['planeacion_path' => 'a.pdf'],
            ['planeacion_path' => 'a.pdf', 'planeacion_nombre' => 'a.pdf', 'planeacion_paginas' => 3],
            ['planeacion_paginas' => 1],
        ] as $fila) {
            try {
                DB::table('proyecto_educativo_formacion_politica')->insert(['escuela_nivel_id' => $nivel->id, 'tipo' => 'continua', ...$fila]);
                $this->fail('La base debió rechazar la fila.');
            } catch (QueryException) {
                $this->addToAssertionCount(1);
            }
        }
    }
}
