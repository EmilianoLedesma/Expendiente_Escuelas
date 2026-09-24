<?php

namespace Tests\Unit\Infrastructure\Documentos;

use App\Infrastructure\Documentos\AlmacenDocumentosLocal;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AlmacenDocumentosLocalTest extends TestCase
{
    public function test_guarda_el_archivo_en_el_disco_documentos_y_devuelve_la_ruta(): void
    {
        Storage::fake('documentos');
        $archivo = UploadedFile::fake()->create('dictamen.pdf', 100, 'application/pdf');

        $ruta = (new AlmacenDocumentosLocal)->guardar('plantel', 7, 'dictamen_uso_suelo', $archivo);

        Storage::disk('documentos')->assertExists($ruta);
        $this->assertMatchesRegularExpression('#^plantel/7/dictamen_uso_suelo-[0-9A-Z]{26}\.pdf$#', $ruta);
    }

    public function test_guardar_dos_veces_produce_rutas_distintas_y_conserva_ambos_archivos(): void
    {
        Storage::fake('documentos');
        $almacen = new AlmacenDocumentosLocal;
        $primero = $almacen->guardar('escuela', 3, 'ine', UploadedFile::fake()->create('v1.pdf', 50, 'application/pdf'));

        $segundo = $almacen->guardar('escuela', 3, 'ine', UploadedFile::fake()->create('v2.pdf', 80, 'application/pdf'));

        $this->assertNotSame($primero, $segundo);
        Storage::disk('documentos')->assertExists($primero);
        Storage::disk('documentos')->assertExists($segundo);
    }

    public function test_eliminar_borra_el_archivo(): void
    {
        Storage::fake('documentos');
        $almacen = new AlmacenDocumentosLocal;
        $ruta = $almacen->guardar('escuela', 1, 'ine', UploadedFile::fake()->create('a.pdf', 10, 'application/pdf'));

        $almacen->eliminar($ruta);

        Storage::disk('documentos')->assertMissing($ruta);
    }
}
