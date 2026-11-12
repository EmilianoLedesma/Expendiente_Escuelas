<?php

namespace Tests\Feature\Application\ProyectoEducativo;

use App\Application\Excepciones\DatosInvalidos;
use App\Application\Excepciones\PrecondicionIncumplida;
use App\Application\ProyectoEducativo\DTO\DatosPaecProyecto;
use App\Application\ProyectoEducativo\GuardarPaecProyecto;
use App\Application\ProyectoEducativo\PaecProyectoCapturada;
use App\Application\ProyectoEducativo\SeccionesProyectoEducativo;
use App\Application\Tramite\EstadoPaso3;
use App\Application\Tramite\TramiteEditable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Concerns\GeneraPdfs;
use Tests\Concerns\PreparaPaso3;
use Tests\TestCase;

class GuardarPaecProyectoTest extends TestCase
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

    private function datos(array $cambios = []): DatosPaecProyecto
    {
        return new DatosPaecProyecto(...array_merge([
            'diagnostico' => $this->palabras(150),
            'hoja_ruta' => $this->archivoPdf(1, 'hoja.pdf'),
            'planeacion' => $this->archivoPdf(2, 'planeacion.pdf'),
        ], $cambios));
    }

    private function guardar(int $nivelId, DatosPaecProyecto $datos): void
    {
        app(GuardarPaecProyecto::class)->ejecutar($nivelId, $datos);
    }

    /** @return array<string, string> */
    private function errores(int $nivelId, DatosPaecProyecto $datos): array
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
        // Solo lo de esta sección: el fixture del trámite ya sube otros documentos al disco.
        return Storage::disk('documentos')->allFiles('proyecto_educativo');
    }

    private function fila(int $nivelId): object
    {
        return DB::table('proyecto_educativo_paec')->where('escuela_nivel_id', $nivelId)->sole();
    }

    public function test_el_slug_sale_del_catalogo(): void
    {
        $this->assertSame('programa-aula-escuela-y-comunidad-paec', SeccionesProyectoEducativo::paec());
    }

    public function test_guarda_completo_con_nombres_y_paginas_y_marca_completa(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');

        $this->guardar($nivel->id, $this->datos());

        $fila = $this->fila($nivel->id);
        $this->assertSame($this->palabras(150), $fila->diagnostico);
        $this->assertSame('hoja.pdf', $fila->hoja_ruta_nombre);
        $this->assertSame(1, (int) $fila->hoja_ruta_paginas);
        $this->assertSame('planeacion.pdf', $fila->planeacion_nombre);
        $this->assertSame(2, (int) $fila->planeacion_paginas);
        Storage::disk('documentos')->assertExists($fila->hoja_ruta_path);
        Storage::disk('documentos')->assertExists($fila->planeacion_path);
        $this->assertCount(2, $this->archivosEnDisco());
        $this->assertTrue(app(PaecProyectoCapturada::class)->completa($nivel->id));
    }

    public function test_sin_fila_no_esta_completa_y_datos_vacios(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');
        $capturada = app(PaecProyectoCapturada::class);

        $this->assertFalse($capturada->completa($nivel->id));
        $this->assertSame(['diagnostico' => null, 'hoja_ruta_nombre' => null, 'hoja_ruta_paginas' => null, 'planeacion_nombre' => null, 'planeacion_paginas' => null], $capturada->datos($nivel->id));
    }

    public function test_sin_archivos_y_sin_nada_guardado_se_rechazan_los_tres_campos(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');

        $errores = $this->errores($nivel->id, new DatosPaecProyecto);

        $this->assertEqualsCanonicalizing(['diagnostico', 'hoja_ruta', 'planeacion'], array_keys($errores));
        $this->assertSame([], $this->archivosEnDisco());
        $this->assertSame(0, DB::table('proyecto_educativo_paec')->count());
    }

    /** @return array<string, array{string, int, int}> */
    public static function limitesDiagnostico(): array
    {
        return ['bajo' => ['bajo', 149, 1], 'alto' => ['alto', 501, 1], 'minimo' => ['minimo', 150, 0], 'maximo' => ['maximo', 500, 0]];
    }

    #[DataProvider('limitesDiagnostico')]
    public function test_limites_de_palabras_del_diagnostico(string $caso, int $palabras, int $erroresEsperados): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');

        $errores = $this->errores($nivel->id, $this->datos(['diagnostico' => $this->palabras($palabras)]));

        $this->assertSame($erroresEsperados === 0 ? [] : ['diagnostico'], array_keys($errores), $caso);
        $this->assertCount($erroresEsperados === 0 ? 2 : 0, $this->archivosEnDisco());
    }

    /** @return array<string, array{string}> */
    public static function archivos(): array
    {
        return ['hoja de ruta' => ['hoja_ruta'], 'planeacion' => ['planeacion']];
    }

    #[DataProvider('archivos')]
    public function test_un_pdf_de_una_o_dos_paginas_se_acepta_y_de_tres_se_rechaza(string $campo): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');

        $errores = $this->errores($nivel->id, $this->datos([$campo => $this->archivoPdf(3)]));

        $this->assertSame([$campo], array_keys($errores));
        $this->assertStringContainsString('entre 1 y 2 páginas (tiene 3)', $errores[$campo]);
        $this->assertSame([], $this->archivosEnDisco());
        $this->assertSame(0, DB::table('proyecto_educativo_paec')->count());

        foreach ([1, 2] as $paginas) {
            $this->assertSame([], $this->errores($nivel->id, $this->datos([$campo => $this->archivoPdf($paginas)])), "{$paginas} página(s)");
        }
    }

    #[DataProvider('archivos')]
    public function test_un_archivo_que_no_es_pdf_se_rechaza(string $campo): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');
        $texto = UploadedFile::fake()->createWithContent('notas.pdf', 'esto es texto plano, no un PDF');

        $errores = $this->errores($nivel->id, $this->datos([$campo => $texto]));

        $this->assertSame([$campo], array_keys($errores));
        $this->assertStringContainsString('PDF', $errores[$campo]);
        $this->assertSame([], $this->archivosEnDisco());
    }

    #[DataProvider('archivos')]
    public function test_un_pdf_danado_se_rechaza_con_mensaje_claro(string $campo): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');
        $danado = UploadedFile::fake()->createWithContent('roto.pdf', "%PDF-1.4\n1 0 obj << /Broken >> endobj\ngarbage");

        $errores = $this->errores($nivel->id, $this->datos([$campo => $danado]));

        $this->assertSame([$campo], array_keys($errores));
        $this->assertStringContainsString('No se pudo leer el PDF', $errores[$campo]);
        $this->assertSame([], $this->archivosEnDisco());
    }

    #[DataProvider('archivos')]
    public function test_un_pdf_de_mas_de_diez_megas_se_rechaza(string $campo): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');

        $errores = $this->errores($nivel->id, $this->datos([$campo => $this->archivoPdf(1, 'grande.pdf', 10 * 1024 * 1024 + 1)]));

        $this->assertSame([$campo], array_keys($errores));
        $this->assertStringContainsString('10 MB', $errores[$campo]);
        $this->assertSame([], $this->archivosEnDisco());
    }

    public function test_todo_o_nada_un_error_en_cualquier_campo_no_escribe_ni_sube_nada(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');

        $errores = $this->errores($nivel->id, $this->datos(['diagnostico' => $this->palabras(10), 'planeacion' => $this->archivoPdf(3)]));

        $this->assertEqualsCanonicalizing(['diagnostico', 'planeacion'], array_keys($errores));
        $this->assertSame([], $this->archivosEnDisco());
        $this->assertSame(0, DB::table('proyecto_educativo_paec')->count());
    }

    public function test_editar_solo_el_diagnostico_conserva_los_archivos_guardados(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');
        $this->guardar($nivel->id, $this->datos());
        $antes = $this->fila($nivel->id);

        $this->guardar($nivel->id, new DatosPaecProyecto(diagnostico: $this->palabras(200)));

        $despues = $this->fila($nivel->id);
        $this->assertSame($this->palabras(200), $despues->diagnostico);
        $this->assertSame($antes->hoja_ruta_path, $despues->hoja_ruta_path);
        $this->assertSame($antes->planeacion_path, $despues->planeacion_path);
        $this->assertCount(2, $this->archivosEnDisco());
    }

    public function test_reemplazar_un_archivo_borra_el_anterior_y_deja_el_otro(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');
        $this->guardar($nivel->id, $this->datos());
        $antes = $this->fila($nivel->id);

        $this->guardar($nivel->id, new DatosPaecProyecto(diagnostico: $this->palabras(150), hoja_ruta: $this->archivoPdf(2, 'nueva.pdf')));

        $despues = $this->fila($nivel->id);
        $this->assertNotSame($antes->hoja_ruta_path, $despues->hoja_ruta_path);
        $this->assertSame('nueva.pdf', $despues->hoja_ruta_nombre);
        $this->assertSame(2, (int) $despues->hoja_ruta_paginas);
        Storage::disk('documentos')->assertMissing($antes->hoja_ruta_path);
        Storage::disk('documentos')->assertExists($despues->hoja_ruta_path);
        $this->assertSame($antes->planeacion_path, $despues->planeacion_path);
        $this->assertCount(2, $this->archivosEnDisco());
    }

    public function test_un_reemplazo_con_error_conserva_lo_guardado(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');
        $this->guardar($nivel->id, $this->datos());
        $antes = $this->fila($nivel->id);

        $errores = $this->errores($nivel->id, new DatosPaecProyecto(diagnostico: $this->palabras(150), hoja_ruta: $this->archivoPdf(3)));

        $this->assertSame(['hoja_ruta'], array_keys($errores));
        $this->assertEquals($antes, $this->fila($nivel->id));
        $this->assertCount(2, $this->archivosEnDisco());
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

        $this->assertSame(0, DB::table('proyecto_educativo_paec')->count());
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
        $this->assertSame(0, DB::table('proyecto_educativo_paec')->count());
    }

    public function test_antes_de_matricula_rechaza_el_guardado(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'matricula');

        $this->expectException(PrecondicionIncumplida::class);
        $this->guardar($nivel->id, $this->datos());
    }

    public function test_los_campos_del_dto_salen_del_constructor(): void
    {
        $this->assertSame(['diagnostico', 'hoja_ruta', 'planeacion'], DatosPaecProyecto::campos());
    }

    public function test_la_base_exige_una_fila_por_nivel(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');
        DB::table('proyecto_educativo_paec')->insert(['escuela_nivel_id' => $nivel->id]);

        $this->expectException(QueryException::class);
        DB::table('proyecto_educativo_paec')->insert(['escuela_nivel_id' => $nivel->id]);
    }

    public function test_la_base_rechaza_mas_de_dos_paginas_y_archivos_a_medias(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');

        foreach ([
            ['hoja_ruta_path' => 'a.pdf', 'hoja_ruta_nombre' => 'a.pdf', 'hoja_ruta_paginas' => 3],
            ['hoja_ruta_path' => 'a.pdf'],
        ] as $fila) {
            try {
                DB::table('proyecto_educativo_paec')->insert(['escuela_nivel_id' => $nivel->id, ...$fila]);
                $this->fail('La base debió rechazar la fila.');
            } catch (QueryException) {
                $this->addToAssertionCount(1);
            }
        }
    }
}
