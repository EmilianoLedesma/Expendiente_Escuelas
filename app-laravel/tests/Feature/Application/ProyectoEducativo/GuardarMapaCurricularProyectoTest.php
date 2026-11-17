<?php

namespace Tests\Feature\Application\ProyectoEducativo;

use App\Application\Excepciones\DatosInvalidos;
use App\Application\Excepciones\PrecondicionIncumplida;
use App\Application\ProyectoEducativo\DTO\DatosMapaCurricularProyecto;
use App\Application\ProyectoEducativo\GuardarMapaCurricularProyecto;
use App\Application\ProyectoEducativo\MapaCurricularProyectoCapturada;
use App\Application\ProyectoEducativo\ReglasMapaCurricularProyecto;
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

class GuardarMapaCurricularProyectoTest extends TestCase
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

    private function guardar(int $nivelId, ?UploadedFile $archivo, ?string $descripcion = null): void
    {
        app(GuardarMapaCurricularProyecto::class)->ejecutar($nivelId, new DatosMapaCurricularProyecto($archivo, $descripcion));
    }

    /** @return array<string, string> */
    private function errores(int $nivelId, ?UploadedFile $archivo, ?string $descripcion = null): array
    {
        try {
            $this->guardar($nivelId, $archivo, $descripcion);
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
        return DB::table('proyecto_educativo_mapa_curricular')->where('escuela_nivel_id', $nivelId)->sole();
    }

    public function test_el_slug_sale_del_catalogo(): void
    {
        $this->assertSame('mapa-curricular', SeccionesProyectoEducativo::mapaCurricular());
    }

    public function test_los_limites_de_la_descripcion(): void
    {
        $this->assertSame([50, 250], ReglasMapaCurricularProyecto::DESCRIPCION);
    }

    public function test_guarda_el_pdf_con_nombre_y_paginas_y_marca_completa_sin_descripcion(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');

        $this->guardar($nivel->id, $this->archivoPdf(1, 'MapaCurricular_2024.pdf'));

        $fila = $this->fila($nivel->id);
        $this->assertSame('MapaCurricular_2024.pdf', $fila->archivo_nombre);
        $this->assertSame(1, (int) $fila->archivo_paginas);
        $this->assertNull($fila->descripcion);
        Storage::disk('documentos')->assertExists($fila->archivo_path);
        $this->assertCount(1, $this->archivosEnDisco());
        $this->assertTrue(app(MapaCurricularProyectoCapturada::class)->completa($nivel->id));
    }

    public function test_un_pdf_de_muchas_paginas_se_acepta(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');

        $this->guardar($nivel->id, $this->archivoPdf(12, 'mapa.pdf'));

        $this->assertSame(12, (int) $this->fila($nivel->id)->archivo_paginas);
    }

    public function test_guarda_con_descripcion(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');

        $this->guardar($nivel->id, $this->archivoPdf(1), $this->palabras(80));

        $this->assertSame($this->palabras(80), $this->fila($nivel->id)->descripcion);
    }

    /** @return array<string, array{int, bool}> */
    public static function limitesDescripcion(): array
    {
        return ['bajo' => [49, false], 'alto' => [251, false], 'minimo' => [50, true], 'maximo' => [250, true]];
    }

    #[DataProvider('limitesDescripcion')]
    public function test_la_descripcion_con_texto_respeta_50_a_250_palabras(int $palabras, bool $valida): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');

        $errores = $this->errores($nivel->id, $this->archivoPdf(1), $this->palabras($palabras));

        $this->assertSame($valida ? [] : ['descripcion_mapa'], array_keys($errores));
        $this->assertCount($valida ? 1 : 0, $this->archivosEnDisco());
    }

    public function test_la_descripcion_vacia_es_valida_y_se_guarda_como_null(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');

        foreach ([null, '', "  \n "] as $vacia) {
            $this->assertSame([], $this->errores($nivel->id, $this->archivoPdf(1), $vacia));
            $this->assertNull($this->fila($nivel->id)->descripcion);
        }
    }

    public function test_sin_archivo_y_sin_nada_guardado_el_archivo_es_obligatorio(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');

        $errores = $this->errores($nivel->id, null, $this->palabras(80));

        $this->assertSame(['archivo_mapa'], array_keys($errores));
        $this->assertSame('Este campo es obligatorio.', $errores['archivo_mapa']);
        $this->assertSame(0, DB::table('proyecto_educativo_mapa_curricular')->count());
    }

    /** @return array<string, array{string, string}> nombre => [nombre del archivo, contenido] */
    public static function archivosQueNoSonPdf(): array
    {
        return [
            'texto con nombre pdf' => ['notas.pdf', 'esto es texto plano, no un PDF'],
            'imagen PNG con nombre pdf' => ['mapa.pdf', "\x89PNG\r\n\x1a\n\x00\x00\x00\rIHDR\x00\x00\x00\x01\x00\x00\x00\x01\x08\x06\x00\x00\x00"],
            'imagen JPEG' => ['mapa.jpg', "\xFF\xD8\xFF\xE0\x00\x10JFIF\x00\x01\x01\x00\x00\x01\x00\x01\x00\x00"],
            'documento Word (zip)' => ['mapa.docx', "PK\x03\x04\x14\x00\x06\x00\x08\x00\x00\x00!\x00"],
            'archivo vacío' => ['mapa.pdf', ''],
        ];
    }

    #[DataProvider('archivosQueNoSonPdf')]
    public function test_cualquier_archivo_que_no_sea_pdf_se_rechaza(string $nombre, string $contenido): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');

        $errores = $this->errores($nivel->id, UploadedFile::fake()->createWithContent($nombre, $contenido));

        $this->assertSame(['archivo_mapa'], array_keys($errores));
        $this->assertStringContainsString('PDF', $errores['archivo_mapa']);
        $this->assertSame([], $this->archivosEnDisco());
        $this->assertSame(0, DB::table('proyecto_educativo_mapa_curricular')->count());
    }

    public function test_un_pdf_danado_se_rechaza_con_mensaje_claro(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');
        $danado = UploadedFile::fake()->createWithContent('roto.pdf', "%PDF-1.4\n1 0 obj << /Broken >> endobj\ngarbage");

        $errores = $this->errores($nivel->id, $danado);

        $this->assertSame(['archivo_mapa'], array_keys($errores));
        $this->assertStringContainsString('No se pudo leer el PDF', $errores['archivo_mapa']);
        $this->assertSame([], $this->archivosEnDisco());
    }

    public function test_un_pdf_de_mas_de_diez_megas_se_rechaza(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');

        $errores = $this->errores($nivel->id, $this->archivoPdf(1, 'grande.pdf', 10 * 1024 * 1024 + 1));

        $this->assertSame(['archivo_mapa'], array_keys($errores));
        $this->assertStringContainsString('10 MB', $errores['archivo_mapa']);
        $this->assertSame([], $this->archivosEnDisco());
    }

    public function test_todo_o_nada_un_error_en_la_descripcion_no_sube_el_archivo(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');

        $errores = $this->errores($nivel->id, $this->archivoPdf(1), 'corto');

        $this->assertSame(['descripcion_mapa'], array_keys($errores));
        $this->assertSame([], $this->archivosEnDisco());
        $this->assertSame(0, DB::table('proyecto_educativo_mapa_curricular')->count());
    }

    public function test_editar_solo_la_descripcion_conserva_el_archivo_guardado(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');
        $this->guardar($nivel->id, $this->archivoPdf(2, 'mapa.pdf'));
        $antes = $this->fila($nivel->id);

        $this->guardar($nivel->id, null, $this->palabras(60));

        $despues = $this->fila($nivel->id);
        $this->assertSame($this->palabras(60), $despues->descripcion);
        $this->assertSame($antes->archivo_path, $despues->archivo_path);
        $this->assertSame('mapa.pdf', $despues->archivo_nombre);
        $this->assertCount(1, $this->archivosEnDisco());
    }

    public function test_borrar_la_descripcion_la_deja_en_null(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');
        $this->guardar($nivel->id, $this->archivoPdf(1), $this->palabras(60));

        $this->guardar($nivel->id, null, '');

        $this->assertNull($this->fila($nivel->id)->descripcion);
    }

    public function test_reemplazar_el_archivo_borra_el_anterior(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');
        $this->guardar($nivel->id, $this->archivoPdf(1, 'vieja.pdf'));
        $antes = $this->fila($nivel->id);

        $this->guardar($nivel->id, $this->archivoPdf(3, 'nueva.pdf'));

        $despues = $this->fila($nivel->id);
        $this->assertNotSame($antes->archivo_path, $despues->archivo_path);
        $this->assertSame('nueva.pdf', $despues->archivo_nombre);
        $this->assertSame(3, (int) $despues->archivo_paginas);
        Storage::disk('documentos')->assertMissing($antes->archivo_path);
        Storage::disk('documentos')->assertExists($despues->archivo_path);
        $this->assertCount(1, $this->archivosEnDisco());
    }

    public function test_un_reemplazo_con_error_conserva_lo_guardado(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');
        $this->guardar($nivel->id, $this->archivoPdf(1, 'mapa.pdf'));
        $antes = $this->fila($nivel->id);

        $errores = $this->errores($nivel->id, UploadedFile::fake()->createWithContent('nuevo.pdf', 'no es pdf'));

        $this->assertSame(['archivo_mapa'], array_keys($errores));
        $this->assertEquals($antes, $this->fila($nivel->id));
        $this->assertCount(1, $this->archivosEnDisco());
    }

    public function test_si_la_transaccion_exterior_se_revierte_no_quedan_archivos_nuevos(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');

        try {
            DB::transaction(function () use ($nivel) {
                $this->guardar($nivel->id, $this->archivoPdf(1));

                throw new RuntimeException('revertir');
            });
            $this->fail('Se esperaba RuntimeException.');
        } catch (RuntimeException $e) {
            $this->assertSame('revertir', $e->getMessage());
        }

        $this->assertSame(0, DB::table('proyecto_educativo_mapa_curricular')->count());
        $this->assertSame([], $this->archivosEnDisco());
    }

    public function test_sin_fila_no_esta_completa_y_los_datos_salen_vacios_sin_exponer_la_ruta(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');
        $capturada = app(MapaCurricularProyectoCapturada::class);

        $this->assertFalse($capturada->completa($nivel->id));
        $this->assertSame(['descripcion_mapa' => null, 'archivo_nombre' => null, 'archivo_paginas' => null], $capturada->datos($nivel->id));

        $this->guardar($nivel->id, $this->archivoPdf(2, 'mapa.pdf'), $this->palabras(60));
        $this->assertSame(['descripcion_mapa' => $this->palabras(60), 'archivo_nombre' => 'mapa.pdf', 'archivo_paginas' => 2], $capturada->datos($nivel->id));
    }

    public function test_no_marca_el_paso_completado(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');

        $this->guardar($nivel->id, $this->archivoPdf(1));

        $this->assertFalse(app(EstadoPaso3::class)->completado($nivel->id, SeccionesProyectoEducativo::CLAVE_PASO));
    }

    public function test_tramite_enviado_rechaza_el_guardado_y_no_sube_nada(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');
        $nivel->update(['estado_id' => DB::table('estados_expediente')->where('clave', 'en_revision')->value('id')]);

        try {
            $this->guardar($nivel->id, $this->archivoPdf(1));
            $this->fail('Debió rechazar el guardado.');
        } catch (PrecondicionIncumplida $e) {
            $this->assertSame(TramiteEditable::ENVIADO, $e->etapaFaltante);
        }
        $this->assertSame([], $this->archivosEnDisco());
        $this->assertSame(0, DB::table('proyecto_educativo_mapa_curricular')->count());
    }

    public function test_antes_de_matricula_rechaza_el_guardado(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'matricula');

        $this->expectException(PrecondicionIncumplida::class);
        $this->guardar($nivel->id, $this->archivoPdf(1));
    }

    public function test_los_campos_del_dto_salen_del_constructor(): void
    {
        $this->assertSame(['archivo_mapa', 'descripcion_mapa'], DatosMapaCurricularProyecto::campos());
    }

    public function test_la_base_exige_una_fila_por_nivel(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');
        DB::table('proyecto_educativo_mapa_curricular')->insert(['escuela_nivel_id' => $nivel->id]);

        $this->expectException(QueryException::class);
        DB::table('proyecto_educativo_mapa_curricular')->insert(['escuela_nivel_id' => $nivel->id]);
    }

    public function test_la_base_rechaza_archivos_a_medias_paginas_cero_y_descripcion_en_blanco(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');

        foreach ([
            ['archivo_path' => 'a.pdf'],
            ['archivo_path' => 'a.pdf', 'archivo_nombre' => 'a.pdf', 'archivo_paginas' => 0],
            ['archivo_nombre' => 'a.pdf'],
            ['descripcion' => '  '],
        ] as $fila) {
            try {
                DB::transaction(fn () => DB::table('proyecto_educativo_mapa_curricular')->insert(['escuela_nivel_id' => $nivel->id, ...$fila])); // savepoint: un insert fallido no aborta la transacción de la prueba
                $this->fail('La base debió rechazar '.json_encode($fila));
            } catch (QueryException) {
                $this->addToAssertionCount(1);
            }
        }
        $this->assertSame(0, DB::table('proyecto_educativo_mapa_curricular')->count());
    }
}
