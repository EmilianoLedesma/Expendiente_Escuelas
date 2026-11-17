<?php

namespace Tests\Feature\Livewire\Tramite;

use App\Application\ProyectoEducativo\DTO\DatosMapaCurricularProyecto;
use App\Application\ProyectoEducativo\GuardarMapaCurricularProyecto;
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

class Paso3MapaCurricularProyectoTest extends TestCase
{
    use GeneraPdfs;
    use PreparaPaso3;
    use RefreshDatabase;

    private const SLUG = 'mapa-curricular';

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

    public function test_la_pagina_pide_un_pdf_y_no_un_nombre_de_archivo_escrito(): void
    {
        $html = $this->get($this->ruta($this->nivel()))->assertOk()
            ->assertSee('Mapa Curricular')
            ->assertSee('Anexe el mapa curricular de su institución')
            ->assertSee('PDF')
            ->assertSee('Descripción del mapa curricular (50 a 250 palabras)')
            ->assertSee('Puede describir aquí la estructura general del mapa curricular...')
            ->assertSee('Guardar')
            ->assertSeeHtml('wire:submit="guardar"')
            ->assertDontSee('Nombre de la carpeta o archivo')
            ->assertDontSee('MapaCurricular_2024.pdf')
            ->assertDontSee('Contenido por definir')
            ->getContent();

        $this->assertSame(1, substr_count($html, 'type="file"'));
        $this->assertSame(1, substr_count($html, 'accept="application/pdf"'));
        $this->assertSame(1, substr_count($html, '<textarea'));
        $this->assertSame(0, substr_count($html, 'type="text"'), 'ningún campo de texto para el nombre del archivo');
    }

    public function test_guardar_con_un_pdf_guarda_y_avisa_que_la_seccion_esta_completa(): void
    {
        $nivel = $this->nivel();

        $this->componente($nivel)->set('archivo_mapa', $this->archivoPdf(3, 'MapaCurricular_2024.pdf'))->call('guardar')
            ->assertHasNoErrors()
            ->assertSet('archivo_mapa', null)
            ->assertDispatched('toast', tipo: 'success', titulo: 'Sección completa')
            ->assertSee('MapaCurricular_2024.pdf');

        $fila = DB::table('proyecto_educativo_mapa_curricular')->where('escuela_nivel_id', $nivel->id)->sole();
        $this->assertSame(3, (int) $fila->archivo_paginas);
        $this->assertNull($fila->descripcion);
        $this->assertCount(1, $this->archivosDeLaSeccion());
    }

    public function test_guardar_con_descripcion_opcional(): void
    {
        $nivel = $this->nivel();

        $this->componente($nivel)->set('archivo_mapa', $this->archivoPdf(1))->set('descripcion_mapa', $this->palabras(70))->call('guardar')
            ->assertHasNoErrors();

        $this->assertSame($this->palabras(70), DB::table('proyecto_educativo_mapa_curricular')->value('descripcion'));
    }

    public function test_sin_archivo_marca_el_campo_y_no_guarda(): void
    {
        $this->componente($this->nivel())->call('guardar')
            ->assertHasErrors(['archivo_mapa'])
            ->assertDispatched('toast', tipo: 'error', titulo: 'No se guardó');

        $this->assertSame(0, DB::table('proyecto_educativo_mapa_curricular')->count());
    }

    public function test_un_archivo_que_no_es_pdf_se_rechaza_aunque_se_llame_pdf(): void
    {
        $nivel = $this->nivel();
        $png = UploadedFile::fake()->createWithContent('mapa.pdf', "\x89PNG\r\n\x1a\n\x00\x00\x00\rIHDR\x00\x00\x00\x01\x00\x00\x00\x01\x08\x06\x00\x00\x00");

        $this->componente($nivel)->set('archivo_mapa', $png)->call('guardar')
            ->assertHasErrors(['archivo_mapa'])
            ->assertDispatched('toast', tipo: 'error', titulo: 'No se guardó');

        $this->assertSame(0, DB::table('proyecto_educativo_mapa_curricular')->count());
        $this->assertSame([], $this->archivosDeLaSeccion());
    }

    public function test_una_descripcion_corta_marca_el_campo_y_no_sube_el_archivo(): void
    {
        $this->componente($this->nivel())->set('archivo_mapa', $this->archivoPdf(1))->set('descripcion_mapa', 'corta')->call('guardar')
            ->assertHasErrors(['descripcion_mapa'])
            ->assertHasNoErrors(['archivo_mapa']);

        $this->assertSame([], $this->archivosDeLaSeccion());
    }

    public function test_abre_con_lo_guardado_y_se_puede_editar_la_descripcion_sin_subir_de_nuevo(): void
    {
        $nivel = $this->nivel();
        app(GuardarMapaCurricularProyecto::class)->ejecutar($nivel->id, new DatosMapaCurricularProyecto($this->archivoPdf(2, 'mapa.pdf'), $this->palabras(60)));
        $antes = DB::table('proyecto_educativo_mapa_curricular')->sole();

        $this->componente($nivel)
            ->assertSet('descripcion_mapa', $this->palabras(60))
            ->assertSee('mapa.pdf')
            ->set('descripcion_mapa', $this->palabras(90))->call('guardar')
            ->assertHasNoErrors()
            ->assertDispatched('toast', tipo: 'success', titulo: 'Sección completa');

        $despues = DB::table('proyecto_educativo_mapa_curricular')->sole();
        $this->assertSame($this->palabras(90), $despues->descripcion);
        $this->assertSame($antes->archivo_path, $despues->archivo_path);
    }

    public function test_un_valor_manipulado_en_el_campo_de_archivo_no_rompe_el_guardado(): void
    {
        $nivel = $this->nivel();
        app(GuardarMapaCurricularProyecto::class)->ejecutar($nivel->id, new DatosMapaCurricularProyecto($this->archivoPdf(1)));

        $this->componente($nivel)->set('archivo_mapa', 'no soy un archivo')->call('guardar')->assertHasNoErrors();
    }

    public function test_tramite_enviado_no_guarda(): void
    {
        $nivel = $this->nivel();
        $nivel->update(['estado_id' => DB::table('estados_expediente')->where('clave', 'en_revision')->value('id')]);

        $this->componente($nivel)->set('archivo_mapa', $this->archivoPdf(1))->call('guardar')
            ->assertDispatched('toast', tipo: 'error', titulo: 'No se guardó', descripcion: 'El trámite ya se envió a revisión y no puede modificarse.');

        $this->assertSame(0, DB::table('proyecto_educativo_mapa_curricular')->count());
        $this->assertSame([], $this->archivosDeLaSeccion());
    }

    public function test_el_indice_marca_completa_la_seccion_quince(): void
    {
        $nivel = $this->nivel();
        $indice = route('tramite.paso3-proyecto-educativo', ['escuelaNivel' => $nivel->id]);

        $this->assertSame(20, substr_count($this->get($indice)->assertOk()->getContent(), 'Pendiente'));

        $this->componente($nivel)->set('archivo_mapa', $this->archivoPdf(1))->call('guardar');
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
