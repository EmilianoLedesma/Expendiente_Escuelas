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
        $this->assertSame('plantel/7/dictamen_uso_suelo.pdf', $ruta);
    }

    public function test_guardar_dos_veces_reemplaza_el_archivo_anterior(): void
    {
        Storage::fake('documentos');
        $almacen = new AlmacenDocumentosLocal;
        $primero = $almacen->guardar('escuela', 3, 'ine', UploadedFile::fake()->create('v1.pdf', 50, 'application/pdf'));

        $segundo = $almacen->guardar('escuela', 3, 'ine', UploadedFile::fake()->create('v2.pdf', 80, 'application/pdf'));

        $this->assertSame($primero, $segundo);
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
