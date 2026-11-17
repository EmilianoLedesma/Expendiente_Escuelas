<?php

namespace Tests\Feature\Application\ProyectoEducativo;

use App\Application\Excepciones\DatosInvalidos;
use App\Application\Excepciones\PrecondicionIncumplida;
use App\Application\ProyectoEducativo\DTO\DatosProgramasEstudiosProyecto;
use App\Application\ProyectoEducativo\GuardarProgramasEstudiosProyecto;
use App\Application\ProyectoEducativo\ProgramasEstudiosProyectoCapturada;
use App\Application\ProyectoEducativo\ReglasProgramasEstudiosProyecto;
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

class GuardarProgramasEstudiosProyectoTest extends TestCase
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

    /** Respuesta válida con "Sí retoma" salvo lo que se cambie. @param  array<string, mixed>  $cambios */
    private function dto(array $cambios = []): DatosProgramasEstudiosProyecto
    {
        return new DatosProgramasEstudiosProyecto(...array_merge([
            'periodicidad_ciclo' => 'semestral',
            'periodicidad_otra' => null,
            'modalidad_programas' => 'presencial',
            'retoma_dgb' => 'si',
            'justificacion_adaptacion' => null,
            'archivo_programas' => null,
        ], $cambios));
    }

    /** Respuesta válida con "No retoma": justificación y PDF. @param  array<string, mixed>  $cambios */
    private function adaptados(array $cambios = []): DatosProgramasEstudiosProyecto
    {
        return $this->dto(array_merge([
            'retoma_dgb' => 'no',
            'justificacion_adaptacion' => $this->palabras(200),
            'archivo_programas' => $this->archivoPdf(1, 'programas.pdf'),
        ], $cambios));
    }

    private function guardar(int $nivelId, DatosProgramasEstudiosProyecto $datos): void
    {
        app(GuardarProgramasEstudiosProyecto::class)->ejecutar($nivelId, $datos);
    }

    /** @return array<string, string> */
    private function errores(int $nivelId, DatosProgramasEstudiosProyecto $datos): array
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
        return DB::table('proyecto_educativo_programas_estudios')->where('escuela_nivel_id', $nivelId)->sole();
    }

    public function test_el_slug_sale_del_catalogo(): void
    {
        $this->assertSame('programas-de-estudios', SeccionesProyectoEducativo::programasEstudios());
    }

    public function test_las_opciones_y_limites_del_dueno(): void
    {
        $this->assertSame(['semestral', 'cuatrimestral', 'trimestral', 'bimestral', 'mensual', 'otra'], array_keys(ReglasProgramasEstudiosProyecto::PERIODICIDADES));
        $this->assertSame(['presencial', 'virtual', 'mixta'], array_keys(ReglasProgramasEstudiosProyecto::MODALIDADES));
        $this->assertSame(['si', 'no'], array_keys(ReglasProgramasEstudiosProyecto::RETOMA));
        $this->assertSame([200, 500], ReglasProgramasEstudiosProyecto::JUSTIFICACION);
        $this->assertSame(60, ReglasProgramasEstudiosProyecto::OTRA_MAX);
    }

    public function test_si_retoma_los_programas_guarda_sin_justificacion_ni_archivo_y_marca_completa(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');

        $this->guardar($nivel->id, $this->dto(['periodicidad_ciclo' => 'trimestral', 'modalidad_programas' => 'mixta']));

        $fila = $this->fila($nivel->id);
        $this->assertSame('trimestral', $fila->periodicidad);
        $this->assertSame('mixta', $fila->modalidad);
        $this->assertTrue((bool) $fila->retoma_dgb);
        $this->assertNull($fila->justificacion);
        $this->assertNull($fila->archivo_path);
        $this->assertSame([], $this->archivosEnDisco());
        $this->assertTrue(app(ProgramasEstudiosProyectoCapturada::class)->completa($nivel->id));
    }

    public function test_no_retoma_guarda_justificacion_y_pdf_y_marca_completa(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');

        $this->guardar($nivel->id, $this->adaptados());

        $fila = $this->fila($nivel->id);
        $this->assertFalse((bool) $fila->retoma_dgb);
        $this->assertSame($this->palabras(200), $fila->justificacion);
        $this->assertSame('programas.pdf', $fila->archivo_nombre);
        $this->assertSame(1, (int) $fila->archivo_paginas);
        Storage::disk('documentos')->assertExists($fila->archivo_path);
        $this->assertCount(1, $this->archivosEnDisco());
        $this->assertTrue(app(ProgramasEstudiosProyectoCapturada::class)->completa($nivel->id));
    }

    public function test_cada_periodicidad_y_modalidad_del_dueno_se_acepta(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');

        foreach (['semestral', 'cuatrimestral', 'trimestral', 'bimestral', 'mensual'] as $periodicidad) {
            $this->assertSame([], $this->errores($nivel->id, $this->dto(['periodicidad_ciclo' => $periodicidad])), $periodicidad);
        }
        foreach (['presencial', 'virtual', 'mixta'] as $modalidad) {
            $this->assertSame([], $this->errores($nivel->id, $this->dto(['modalidad_programas' => $modalidad])), $modalidad);
        }
        $this->assertSame(1, DB::table('proyecto_educativo_programas_estudios')->count());
    }

    /** @return array<string, array{array<string, mixed>, string}> */
    public static function respuestasInvalidas(): array
    {
        return [
            'periodicidad vacía' => [['periodicidad_ciclo' => null], 'periodicidad_ciclo'],
            'periodicidad inventada' => [['periodicidad_ciclo' => 'anual'], 'periodicidad_ciclo'],
            'modalidad vacía' => [['modalidad_programas' => ''], 'modalidad_programas'],
            'modalidad inventada' => [['modalidad_programas' => 'hibrida'], 'modalidad_programas'],
            'retoma vacío' => [['retoma_dgb' => null], 'retoma_dgb'],
            'retoma inventado' => [['retoma_dgb' => 'quizas'], 'retoma_dgb'],
        ];
    }

    /** @param  array<string, mixed>  $cambios */
    #[DataProvider('respuestasInvalidas')]
    public function test_las_tres_respuestas_son_obligatorias_y_de_la_lista(array $cambios, string $campo): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');

        $this->assertSame([$campo], array_keys($this->errores($nivel->id, $this->dto($cambios))));
        $this->assertSame(0, DB::table('proyecto_educativo_programas_estudios')->count());
    }

    public function test_otra_periodicidad_exige_su_texto_de_hasta_60_caracteres(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');

        foreach ([null, '', '   '] as $vacio) {
            $this->assertSame(['periodicidad_otra'], array_keys($this->errores($nivel->id, $this->dto(['periodicidad_ciclo' => 'otra', 'periodicidad_otra' => $vacio]))));
        }
        $this->assertSame(['periodicidad_otra'], array_keys($this->errores($nivel->id, $this->dto(['periodicidad_ciclo' => 'otra', 'periodicidad_otra' => str_repeat('a', 61)]))));
        $this->assertSame(0, DB::table('proyecto_educativo_programas_estudios')->count());

        $this->guardar($nivel->id, $this->dto(['periodicidad_ciclo' => 'otra', 'periodicidad_otra' => '  '.str_repeat('a', 60).'  ']));
        $this->assertSame('otra', $this->fila($nivel->id)->periodicidad);
        $this->assertSame(str_repeat('a', 60), $this->fila($nivel->id)->periodicidad_otra);
    }

    public function test_con_otra_periodicidad_distinta_el_texto_se_descarta(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');
        $this->guardar($nivel->id, $this->dto(['periodicidad_ciclo' => 'otra', 'periodicidad_otra' => 'Intensiva']));

        $this->guardar($nivel->id, $this->dto(['periodicidad_ciclo' => 'mensual', 'periodicidad_otra' => 'Intensiva']));

        $this->assertNull($this->fila($nivel->id)->periodicidad_otra);
    }

    /** @return array<string, array{int, bool}> */
    public static function limitesJustificacion(): array
    {
        return ['bajo' => [199, false], 'alto' => [501, false], 'minimo' => [200, true], 'maximo' => [500, true]];
    }

    #[DataProvider('limitesJustificacion')]
    public function test_la_justificacion_respeta_200_a_500_palabras_cuando_no_retoma(int $palabras, bool $valida): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');

        $errores = $this->errores($nivel->id, $this->adaptados(['justificacion_adaptacion' => $this->palabras($palabras)]));

        $this->assertSame($valida ? [] : ['justificacion_adaptacion'], array_keys($errores));
        $this->assertCount($valida ? 1 : 0, $this->archivosEnDisco());
    }

    public function test_no_retoma_exige_justificacion_y_archivo(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');

        $errores = $this->errores($nivel->id, $this->dto(['retoma_dgb' => 'no']));

        $this->assertEqualsCanonicalizing(['justificacion_adaptacion', 'archivo_programas'], array_keys($errores));
        $this->assertSame('Este campo es obligatorio.', $errores['archivo_programas']);
        $this->assertSame(0, DB::table('proyecto_educativo_programas_estudios')->count());
    }

    public function test_si_retoma_ignora_justificacion_y_archivo_enviados(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');

        $this->guardar($nivel->id, $this->dto([
            'justificacion_adaptacion' => 'texto que no aplica',
            'archivo_programas' => UploadedFile::fake()->createWithContent('no-es-pdf.pdf', 'tampoco se revisa'),
        ]));

        $fila = $this->fila($nivel->id);
        $this->assertNull($fila->justificacion);
        $this->assertNull($fila->archivo_path);
        $this->assertSame([], $this->archivosEnDisco());
    }

    public function test_cambiar_de_no_a_si_borra_la_justificacion_y_el_pdf_guardados(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');
        $this->guardar($nivel->id, $this->adaptados());
        $antes = $this->fila($nivel->id);

        $this->guardar($nivel->id, $this->dto());

        $despues = $this->fila($nivel->id);
        $this->assertTrue((bool) $despues->retoma_dgb);
        $this->assertNull($despues->justificacion);
        $this->assertNull($despues->archivo_path);
        $this->assertNull($despues->archivo_nombre);
        $this->assertNull($despues->archivo_paginas);
        Storage::disk('documentos')->assertMissing($antes->archivo_path);
        $this->assertSame([], $this->archivosEnDisco());
        $this->assertTrue(app(ProgramasEstudiosProyectoCapturada::class)->completa($nivel->id));
    }

    public function test_cambiar_de_si_a_no_sin_archivo_lo_exige(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');
        $this->guardar($nivel->id, $this->dto());

        $errores = $this->errores($nivel->id, $this->dto(['retoma_dgb' => 'no', 'justificacion_adaptacion' => $this->palabras(200)]));

        $this->assertSame(['archivo_programas'], array_keys($errores));
        $this->assertTrue((bool) $this->fila($nivel->id)->retoma_dgb, 'lo guardado no cambia');
    }

    public function test_editar_solo_la_justificacion_conserva_el_pdf_guardado(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');
        $this->guardar($nivel->id, $this->adaptados());
        $antes = $this->fila($nivel->id);

        $this->guardar($nivel->id, $this->dto(['retoma_dgb' => 'no', 'justificacion_adaptacion' => $this->palabras(250)]));

        $despues = $this->fila($nivel->id);
        $this->assertSame($this->palabras(250), $despues->justificacion);
        $this->assertSame($antes->archivo_path, $despues->archivo_path);
        $this->assertCount(1, $this->archivosEnDisco());
    }

    public function test_reemplazar_el_pdf_borra_el_anterior(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');
        $this->guardar($nivel->id, $this->adaptados());
        $antes = $this->fila($nivel->id);

        $this->guardar($nivel->id, $this->adaptados(['archivo_programas' => $this->archivoPdf(4, 'nuevos.pdf')]));

        $despues = $this->fila($nivel->id);
        $this->assertNotSame($antes->archivo_path, $despues->archivo_path);
        $this->assertSame('nuevos.pdf', $despues->archivo_nombre);
        $this->assertSame(4, (int) $despues->archivo_paginas);
        Storage::disk('documentos')->assertMissing($antes->archivo_path);
        Storage::disk('documentos')->assertExists($despues->archivo_path);
        $this->assertCount(1, $this->archivosEnDisco());
    }

    /** @return array<string, array{string, string}> */
    public static function archivosQueNoSonPdf(): array
    {
        return [
            'texto con nombre pdf' => ['notas.pdf', 'esto es texto plano, no un PDF'],
            'imagen PNG con nombre pdf' => ['programas.pdf', "\x89PNG\r\n\x1a\n\x00\x00\x00\rIHDR\x00\x00\x00\x01\x00\x00\x00\x01\x08\x06\x00\x00\x00"],
            'documento Word (zip)' => ['programas.docx', "PK\x03\x04\x14\x00\x06\x00\x08\x00\x00\x00!\x00"],
            'archivo vacío' => ['programas.pdf', ''],
        ];
    }

    #[DataProvider('archivosQueNoSonPdf')]
    public function test_cualquier_archivo_que_no_sea_pdf_se_rechaza(string $nombre, string $contenido): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');

        $errores = $this->errores($nivel->id, $this->adaptados(['archivo_programas' => UploadedFile::fake()->createWithContent($nombre, $contenido)]));

        $this->assertSame(['archivo_programas'], array_keys($errores));
        $this->assertStringContainsString('PDF', $errores['archivo_programas']);
        $this->assertSame([], $this->archivosEnDisco());
        $this->assertSame(0, DB::table('proyecto_educativo_programas_estudios')->count());
    }

    public function test_un_pdf_danado_y_uno_de_mas_de_diez_megas_se_rechazan(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');

        $danado = $this->errores($nivel->id, $this->adaptados(['archivo_programas' => UploadedFile::fake()->createWithContent('roto.pdf', "%PDF-1.4\n1 0 obj << /Broken >> endobj\ngarbage")]));
        $this->assertStringContainsString('No se pudo leer el PDF', $danado['archivo_programas']);

        $grande = $this->errores($nivel->id, $this->adaptados(['archivo_programas' => $this->archivoPdf(1, 'grande.pdf', 10 * 1024 * 1024 + 1)]));
        $this->assertStringContainsString('10 MB', $grande['archivo_programas']);

        $this->assertSame([], $this->archivosEnDisco());
    }

    public function test_un_pdf_de_muchas_paginas_se_acepta(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');

        $this->guardar($nivel->id, $this->adaptados(['archivo_programas' => $this->archivoPdf(15, 'programas.pdf')]));

        $this->assertSame(15, (int) $this->fila($nivel->id)->archivo_paginas);
    }

    public function test_todo_o_nada_un_error_en_el_texto_no_sube_el_archivo(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');

        $errores = $this->errores($nivel->id, $this->adaptados(['justificacion_adaptacion' => 'corta', 'modalidad_programas' => null]));

        $this->assertEqualsCanonicalizing(['justificacion_adaptacion', 'modalidad_programas'], array_keys($errores));
        $this->assertSame([], $this->archivosEnDisco());
        $this->assertSame(0, DB::table('proyecto_educativo_programas_estudios')->count());
    }

    public function test_un_reemplazo_con_error_conserva_lo_guardado(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');
        $this->guardar($nivel->id, $this->adaptados());
        $antes = $this->fila($nivel->id);

        $errores = $this->errores($nivel->id, $this->adaptados(['archivo_programas' => UploadedFile::fake()->createWithContent('nuevo.pdf', 'no es pdf')]));

        $this->assertSame(['archivo_programas'], array_keys($errores));
        $this->assertEquals($antes, $this->fila($nivel->id));
        $this->assertCount(1, $this->archivosEnDisco());
    }

    public function test_si_la_transaccion_exterior_se_revierte_no_quedan_archivos_nuevos(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');

        try {
            DB::transaction(function () use ($nivel) {
                $this->guardar($nivel->id, $this->adaptados());

                throw new RuntimeException('revertir');
            });
            $this->fail('Se esperaba RuntimeException.');
        } catch (RuntimeException $e) {
            $this->assertSame('revertir', $e->getMessage());
        }

        $this->assertSame(0, DB::table('proyecto_educativo_programas_estudios')->count());
        $this->assertSame([], $this->archivosEnDisco());
    }

    public function test_sin_fila_no_esta_completa_y_los_datos_salen_vacios_sin_exponer_la_ruta(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');
        $capturada = app(ProgramasEstudiosProyectoCapturada::class);

        $vacios = ['periodicidad_ciclo' => null, 'periodicidad_otra' => null, 'modalidad_programas' => null, 'retoma_dgb' => null, 'justificacion_adaptacion' => null, 'archivo_nombre' => null, 'archivo_paginas' => null];
        $this->assertFalse($capturada->completa($nivel->id));
        $this->assertSame($vacios, $capturada->datos($nivel->id));

        $this->guardar($nivel->id, $this->adaptados(['periodicidad_ciclo' => 'otra', 'periodicidad_otra' => 'Intensiva']));
        $this->assertSame([
            'periodicidad_ciclo' => 'otra', 'periodicidad_otra' => 'Intensiva', 'modalidad_programas' => 'presencial', 'retoma_dgb' => 'no',
            'justificacion_adaptacion' => $this->palabras(200), 'archivo_nombre' => 'programas.pdf', 'archivo_paginas' => 1,
        ], $capturada->datos($nivel->id));
    }

    public function test_no_marca_el_paso_completado(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');

        $this->guardar($nivel->id, $this->dto());

        $this->assertFalse(app(EstadoPaso3::class)->completado($nivel->id, SeccionesProyectoEducativo::CLAVE_PASO));
    }

    public function test_tramite_enviado_rechaza_el_guardado_y_no_sube_nada(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');
        $nivel->update(['estado_id' => DB::table('estados_expediente')->where('clave', 'en_revision')->value('id')]);

        try {
            $this->guardar($nivel->id, $this->adaptados());
            $this->fail('Debió rechazar el guardado.');
        } catch (PrecondicionIncumplida $e) {
            $this->assertSame(TramiteEditable::ENVIADO, $e->etapaFaltante);
        }
        $this->assertSame([], $this->archivosEnDisco());
        $this->assertSame(0, DB::table('proyecto_educativo_programas_estudios')->count());
    }

    public function test_antes_de_matricula_rechaza_el_guardado(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'matricula');

        $this->expectException(PrecondicionIncumplida::class);
        $this->guardar($nivel->id, $this->dto());
    }

    public function test_los_campos_del_dto_salen_del_constructor(): void
    {
        $this->assertSame(
            ['periodicidad_ciclo', 'periodicidad_otra', 'modalidad_programas', 'retoma_dgb', 'justificacion_adaptacion', 'archivo_programas'],
            DatosProgramasEstudiosProyecto::campos(),
        );
    }

    public function test_la_base_exige_una_fila_por_nivel(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');
        $fila = ['escuela_nivel_id' => $nivel->id, 'periodicidad' => 'semestral', 'modalidad' => 'virtual', 'retoma_dgb' => true];
        DB::table('proyecto_educativo_programas_estudios')->insert($fila);

        $this->expectException(QueryException::class);
        DB::table('proyecto_educativo_programas_estudios')->insert($fila);
    }

    public function test_la_base_rechaza_valores_fuera_de_lista_y_combinaciones_incoherentes(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');
        $base = ['escuela_nivel_id' => $nivel->id, 'periodicidad' => 'semestral', 'modalidad' => 'virtual', 'retoma_dgb' => true];

        foreach ([
            ['periodicidad' => 'anual'],
            ['modalidad' => 'hibrida'],
            ['periodicidad' => 'otra'],                                   // otra sin texto
            ['periodicidad_otra' => 'Intensiva'],                         // texto sin ser otra
            ['periodicidad' => 'otra', 'periodicidad_otra' => '  '],
            ['justificacion' => 'sobra'],                                 // sí retoma con justificación
            ['archivo_path' => 'a.pdf', 'archivo_nombre' => 'a.pdf', 'archivo_paginas' => 1], // sí retoma con archivo
            ['retoma_dgb' => false],                                      // no retoma sin nada
            ['retoma_dgb' => false, 'justificacion' => 'x', 'archivo_path' => 'a.pdf'],       // archivo a medias
            ['retoma_dgb' => false, 'justificacion' => 'x', 'archivo_path' => 'a.pdf', 'archivo_nombre' => 'a.pdf', 'archivo_paginas' => 0],
            ['retoma_dgb' => false, 'justificacion' => '  ', 'archivo_path' => 'a.pdf', 'archivo_nombre' => 'a.pdf', 'archivo_paginas' => 1],
        ] as $cambio) {
            try {
                DB::transaction(fn () => DB::table('proyecto_educativo_programas_estudios')->insert([...$base, ...$cambio])); // savepoint: un insert fallido no aborta la transacción de la prueba
                $this->fail('La base debió rechazar '.json_encode($cambio));
            } catch (QueryException) {
                $this->addToAssertionCount(1);
            }
        }
        $this->assertSame(0, DB::table('proyecto_educativo_programas_estudios')->count());
    }
}
