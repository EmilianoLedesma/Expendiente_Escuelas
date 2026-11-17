<?php

namespace Tests\Feature\Livewire\Tramite;

use App\Application\ProyectoEducativo\DTO\DatosPlaneacionAcademicaProyecto;
use App\Application\ProyectoEducativo\GuardarPlaneacionAcademicaProyecto;
use App\Livewire\Tramite\Paso3\ProyectoEducativoSeccion;
use App\Models\EscuelaNivel;
use App\Models\Solicitante;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Concerns\GeneraPdfs;
use Tests\Concerns\PreparaPaso3;
use Tests\TestCase;

class Paso3PlaneacionAcademicaProyectoTest extends TestCase
{
    use GeneraPdfs;
    use PreparaPaso3;
    use RefreshDatabase;

    private const SLUG = 'planeacion-academica';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('documentos');
        Storage::fake(); // disco por defecto: ahí deja Livewire los archivos temporales de subida
    }

    private function palabras(int $n): string
    {
        return trim(str_repeat('palabra ', $n));
    }

    private function nivel(): EscuelaNivel
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');
        $this->actingAs($nivel->escuela->solicitante->user);

        return $nivel;
    }

    private function componente(EscuelaNivel $nivel)
    {
        return Livewire::test(ProyectoEducativoSeccion::class, ['escuelaNivel' => $nivel, 'seccion' => self::SLUG]);
    }

    private function ruta(EscuelaNivel $nivel): string
    {
        return route('tramite.paso3-proyecto-educativo-seccion', ['escuelaNivel' => $nivel->id, 'seccion' => self::SLUG]);
    }

    /** @return list<string> */
    private function archivosDeLaSeccion(): array
    {
        return Storage::disk('documentos')->allFiles('proyecto_educativo');
    }

    public function test_la_pagina_pide_un_pdf_y_una_descripcion_y_no_un_nombre_de_archivo_escrito(): void
    {
        $html = $this->get($this->ruta($this->nivel()))->assertOk()
            ->assertSee('Planeación Académica')
            ->assertSee('Presente tres ejemplos de planeación académica de diferente campo disciplinar y semestre')
            ->assertSee('PDF')
            ->assertSee('Descripción de los ejemplos de planeación (50 a 250 palabras)')
            ->assertSee('Indique los campos disciplinares y semestres de los tres ejemplos presentados...')
            ->assertSee('Guardar')
            ->assertSeeHtml('wire:submit="guardar"')
            ->assertDontSee('Nombre de la carpeta o archivo')
            ->assertDontSee('Planeaciones_Academicas_2024')
            ->assertDontSee('Contenido por definir')
            ->getContent();

        $this->assertSame(1, substr_count($html, 'type="file"'));
        $this->assertSame(1, substr_count($html, 'accept="application/pdf"'));
        $this->assertSame(1, substr_count($html, '<textarea'));
        $this->assertSame(0, substr_count($html, 'type="text"'), 'ningún campo de texto para el nombre del archivo');
    }

    public function test_guardar_con_pdf_y_descripcion_guarda_y_avisa_que_la_seccion_esta_completa(): void
    {
        $nivel = $this->nivel();

        $this->componente($nivel)->set('archivo_planeacion', $this->archivoPdf(3, 'Planeaciones_Academicas_2024.pdf'))->set('descripcion_planeacion', $this->palabras(80))->call('guardar')
            ->assertHasNoErrors()
            ->assertSet('archivo_planeacion', null)
            ->assertDispatched('toast', tipo: 'success', titulo: 'Sección completa')
            ->assertSee('Planeaciones_Academicas_2024.pdf');

        $fila = DB::table('proyecto_educativo_planeacion_academica')->where('escuela_nivel_id', $nivel->id)->sole();
        $this->assertSame(3, (int) $fila->archivo_paginas);
        $this->assertSame($this->palabras(80), $fila->descripcion);
        $this->assertCount(1, $this->archivosDeLaSeccion());
    }

    public function test_sin_nada_marca_el_archivo_y_la_descripcion(): void
    {
        $this->componente($this->nivel())->call('guardar')
            ->assertHasErrors(['archivo_planeacion', 'descripcion_planeacion'])
            ->assertDispatched('toast', tipo: 'error', titulo: 'No se guardó');

        $this->assertSame(0, DB::table('proyecto_educativo_planeacion_academica')->count());
    }

    public function test_un_archivo_que_no_es_pdf_se_rechaza_aunque_se_llame_pdf(): void
    {
        $png = UploadedFile::fake()->createWithContent('planeaciones.pdf', "\x89PNG\r\n\x1a\n\x00\x00\x00\rIHDR\x00\x00\x00\x01\x00\x00\x00\x01\x08\x06\x00\x00\x00");

        $this->componente($this->nivel())->set('archivo_planeacion', $png)->set('descripcion_planeacion', $this->palabras(80))->call('guardar')
            ->assertHasErrors(['archivo_planeacion'])
            ->assertHasNoErrors(['descripcion_planeacion']);

        $this->assertSame(0, DB::table('proyecto_educativo_planeacion_academica')->count());
        $this->assertSame([], $this->archivosDeLaSeccion());
    }

    public function test_una_descripcion_corta_marca_el_campo_y_no_sube_el_archivo(): void
    {
        $this->componente($this->nivel())->set('archivo_planeacion', $this->archivoPdf(1))->set('descripcion_planeacion', 'corta')->call('guardar')
            ->assertHasErrors(['descripcion_planeacion'])
            ->assertHasNoErrors(['archivo_planeacion']);

        $this->assertSame([], $this->archivosDeLaSeccion());
    }

    public function test_abre_con_lo_guardado_y_se_puede_editar_la_descripcion_sin_subir_de_nuevo(): void
    {
        $nivel = $this->nivel();
        app(GuardarPlaneacionAcademicaProyecto::class)->ejecutar($nivel->id, new DatosPlaneacionAcademicaProyecto($this->archivoPdf(2, 'planeaciones.pdf'), $this->palabras(60)));
        $antes = DB::table('proyecto_educativo_planeacion_academica')->sole();

        $this->componente($nivel)
            ->assertSet('descripcion_planeacion', $this->palabras(60))
            ->assertSee('planeaciones.pdf')
            ->set('descripcion_planeacion', $this->palabras(90))->call('guardar')
            ->assertHasNoErrors()
            ->assertDispatched('toast', tipo: 'success', titulo: 'Sección completa');

        $despues = DB::table('proyecto_educativo_planeacion_academica')->sole();
        $this->assertSame($this->palabras(90), $despues->descripcion);
        $this->assertSame($antes->archivo_path, $despues->archivo_path);
    }

    public function test_un_valor_manipulado_en_el_campo_de_archivo_no_rompe_el_guardado(): void
    {
        $nivel = $this->nivel();
        app(GuardarPlaneacionAcademicaProyecto::class)->ejecutar($nivel->id, new DatosPlaneacionAcademicaProyecto($this->archivoPdf(1), $this->palabras(60)));

        $this->componente($nivel)->set('archivo_planeacion', 'no soy un archivo')->call('guardar')->assertHasNoErrors();
    }

    public function test_tramite_enviado_no_guarda(): void
    {
        $nivel = $this->nivel();
        $nivel->update(['estado_id' => DB::table('estados_expediente')->where('clave', 'en_revision')->value('id')]);

        $this->componente($nivel)->set('archivo_planeacion', $this->archivoPdf(1))->set('descripcion_planeacion', $this->palabras(60))->call('guardar')
            ->assertDispatched('toast', tipo: 'error', titulo: 'No se guardó', descripcion: 'El trámite ya se envió a revisión y no puede modificarse.');

        $this->assertSame(0, DB::table('proyecto_educativo_planeacion_academica')->count());
        $this->assertSame([], $this->archivosDeLaSeccion());
    }

    public function test_el_indice_marca_completa_la_seccion_diecisiete(): void
    {
        $nivel = $this->nivel();
        $indice = route('tramite.paso3-proyecto-educativo', ['escuelaNivel' => $nivel->id]);

        $this->assertSame(20, substr_count($this->get($indice)->assertOk()->getContent(), 'Pendiente'));

        $this->componente($nivel)->set('archivo_planeacion', $this->archivoPdf(1))->set('descripcion_planeacion', $this->palabras(60))->call('guardar');
        $html = $this->get($indice)->getContent();
        $this->assertSame(19, substr_count($html, 'Pendiente'));
        $this->assertSame(1, substr_count($html, 'Completa'));
    }

    public function test_otro_solicitante_recibe_403_y_primaria_404(): void
    {
        $nivel = $this->nivel();
        $this->actingAs(Solicitante::factory()->create()->user)->get($this->ruta($nivel))->assertForbidden();

        $primaria = $this->nivelListoPara('primaria', 'matricula');
        $this->actingAs($primaria->escuela->solicitante->user)->get(route('tramite.paso3-proyecto-educativo-seccion', ['escuelaNivel' => $primaria->id, 'seccion' => self::SLUG]))->assertNotFound();
    }
}
