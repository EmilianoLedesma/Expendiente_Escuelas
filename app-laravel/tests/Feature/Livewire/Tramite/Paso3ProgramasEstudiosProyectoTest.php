<?php

namespace Tests\Feature\Livewire\Tramite;

use App\Application\ProyectoEducativo\DTO\DatosProgramasEstudiosProyecto;
use App\Application\ProyectoEducativo\GuardarProgramasEstudiosProyecto;
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

class Paso3ProgramasEstudiosProyectoTest extends TestCase
{
    use GeneraPdfs;
    use PreparaPaso3;
    use RefreshDatabase;

    private const SLUG = 'programas-de-estudios';

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

    /** Las tres respuestas obligatorias. */
    private function responder($componente, string $retoma = 'si')
    {
        return $componente->set(['periodicidad_ciclo' => 'semestral', 'modalidad_programas' => 'presencial', 'retoma_dgb' => $retoma]);
    }

    public function test_la_pagina_muestra_las_preguntas_del_dueno_y_oculta_lo_que_depende_del_no(): void
    {
        $html = $this->get($this->ruta($this->nivel()))->assertOk()
            ->assertSee('Periodicidad del ciclo')
            ->assertSee('Semestral')->assertSee('Cuatrimestral')->assertSee('Trimestral')->assertSee('Bimestral')->assertSee('Mensual')->assertSee('Otra')
            ->assertSee('Modalidad')
            ->assertSee('Presencial')->assertSee('Virtual')->assertSee('Mixta')
            ->assertSee('¿Retoma los programas de la DGB completos?')
            ->assertSee('Guardar')
            ->assertSeeHtml('wire:submit="guardar"')
            ->assertDontSee('Justificación y criterios de adaptación')
            ->assertDontSee('Programas adaptados')
            ->assertDontSee('Contenido por definir')
            ->getContent();

        $this->assertSame(0, substr_count($html, 'type="file"'));
        $this->assertSame(0, substr_count($html, '<textarea'));
    }

    public function test_otra_periodicidad_muestra_su_campo_de_texto(): void
    {
        $componente = $this->componente($this->nivel());

        $componente->assertDontSee('Especifique la periodicidad');
        $componente->set('periodicidad_ciclo', 'mensual')->assertDontSee('Especifique la periodicidad');
        $componente->set('periodicidad_ciclo', 'otra')->assertSee('Especifique la periodicidad');
    }

    public function test_responder_no_muestra_la_justificacion_y_el_pdf(): void
    {
        $componente = $this->componente($this->nivel());

        $componente->set('retoma_dgb', 'si')->assertDontSee('Justificación y criterios de adaptación');
        $html = $componente->set('retoma_dgb', 'no')
            ->assertSee('Justificación y criterios de adaptación (200 a 500 palabras)')
            ->assertSee('Justifique los criterios utilizados para adaptar los programas de estudio...')
            ->assertSee('Programas adaptados')
            ->assertSee('PDF')
            ->assertDontSee('Nombre de la carpeta o archivo')
            ->html();

        $this->assertSame(1, substr_count($html, 'type="file"'));
        $this->assertSame(1, substr_count($html, 'accept="application/pdf"'));
        $this->assertSame(1, substr_count($html, '<textarea'));
    }

    public function test_guardar_si_retoma_avisa_que_la_seccion_esta_completa_y_no_guarda_archivos(): void
    {
        $nivel = $this->nivel();

        $this->responder($this->componente($nivel))->call('guardar')
            ->assertHasNoErrors()
            ->assertDispatched('toast', tipo: 'success', titulo: 'Sección completa');

        $fila = DB::table('proyecto_educativo_programas_estudios')->where('escuela_nivel_id', $nivel->id)->sole();
        $this->assertTrue((bool) $fila->retoma_dgb);
        $this->assertSame([], $this->archivosDeLaSeccion());
    }

    public function test_guardar_no_retoma_con_justificacion_y_pdf(): void
    {
        $nivel = $this->nivel();

        $this->responder($this->componente($nivel), 'no')
            ->set('justificacion_adaptacion', $this->palabras(220))
            ->set('archivo_programas', $this->archivoPdf(2, 'Programas_Adaptados_2024.pdf'))
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertSet('archivo_programas', null)
            ->assertDispatched('toast', tipo: 'success', titulo: 'Sección completa')
            ->assertSee('Programas_Adaptados_2024.pdf');

        $fila = DB::table('proyecto_educativo_programas_estudios')->where('escuela_nivel_id', $nivel->id)->sole();
        $this->assertFalse((bool) $fila->retoma_dgb);
        $this->assertSame($this->palabras(220), $fila->justificacion);
        $this->assertSame(2, (int) $fila->archivo_paginas);
        $this->assertCount(1, $this->archivosDeLaSeccion());
    }

    public function test_sin_responder_marca_las_tres_preguntas(): void
    {
        $this->componente($this->nivel())->call('guardar')
            ->assertHasErrors(['periodicidad_ciclo', 'modalidad_programas', 'retoma_dgb'])
            ->assertDispatched('toast', tipo: 'error', titulo: 'No se guardó');

        $this->assertSame(0, DB::table('proyecto_educativo_programas_estudios')->count());
    }

    public function test_otra_sin_texto_marca_el_campo(): void
    {
        $this->responder($this->componente($this->nivel()))->set('periodicidad_ciclo', 'otra')->call('guardar')
            ->assertHasErrors(['periodicidad_otra']);
    }

    public function test_no_retoma_sin_justificacion_ni_pdf_marca_ambos_y_no_guarda(): void
    {
        $this->responder($this->componente($this->nivel()), 'no')->call('guardar')
            ->assertHasErrors(['justificacion_adaptacion', 'archivo_programas']);

        $this->assertSame(0, DB::table('proyecto_educativo_programas_estudios')->count());
    }

    public function test_un_archivo_que_no_es_pdf_se_rechaza_aunque_se_llame_pdf(): void
    {
        $png = UploadedFile::fake()->createWithContent('programas.pdf', "\x89PNG\r\n\x1a\n\x00\x00\x00\rIHDR\x00\x00\x00\x01\x00\x00\x00\x01\x08\x06\x00\x00\x00");

        $this->responder($this->componente($this->nivel()), 'no')
            ->set('justificacion_adaptacion', $this->palabras(200))
            ->set('archivo_programas', $png)
            ->call('guardar')
            ->assertHasErrors(['archivo_programas'])
            ->assertHasNoErrors(['justificacion_adaptacion']);

        $this->assertSame(0, DB::table('proyecto_educativo_programas_estudios')->count());
        $this->assertSame([], $this->archivosDeLaSeccion());
    }

    public function test_cambiar_de_no_a_si_borra_el_pdf_guardado(): void
    {
        $nivel = $this->nivel();
        app(GuardarProgramasEstudiosProyecto::class)->ejecutar($nivel->id, new DatosProgramasEstudiosProyecto('semestral', null, 'virtual', 'no', $this->palabras(200), $this->archivoPdf(1, 'programas.pdf')));
        $this->assertCount(1, $this->archivosDeLaSeccion());

        $this->componente($nivel)->assertSet('retoma_dgb', 'no')->set('retoma_dgb', 'si')->call('guardar')
            ->assertHasNoErrors()
            ->assertDispatched('toast', tipo: 'success', titulo: 'Sección completa');

        $this->assertSame([], $this->archivosDeLaSeccion());
        $this->assertNull(DB::table('proyecto_educativo_programas_estudios')->value('justificacion'));
    }

    public function test_abre_con_lo_guardado_y_se_puede_editar_sin_subir_de_nuevo(): void
    {
        $nivel = $this->nivel();
        app(GuardarProgramasEstudiosProyecto::class)->ejecutar($nivel->id, new DatosProgramasEstudiosProyecto('otra', 'Intensiva', 'mixta', 'no', $this->palabras(210), $this->archivoPdf(3, 'programas.pdf')));
        $antes = DB::table('proyecto_educativo_programas_estudios')->sole();

        $this->componente($nivel)
            ->assertSet('periodicidad_ciclo', 'otra')
            ->assertSet('periodicidad_otra', 'Intensiva')
            ->assertSet('modalidad_programas', 'mixta')
            ->assertSet('retoma_dgb', 'no')
            ->assertSet('justificacion_adaptacion', $this->palabras(210))
            ->assertSee('programas.pdf')
            ->set('justificacion_adaptacion', $this->palabras(300))->call('guardar')
            ->assertHasNoErrors()
            ->assertDispatched('toast', tipo: 'success', titulo: 'Sección completa');

        $despues = DB::table('proyecto_educativo_programas_estudios')->sole();
        $this->assertSame($this->palabras(300), $despues->justificacion);
        $this->assertSame($antes->archivo_path, $despues->archivo_path);
    }

    public function test_un_valor_manipulado_en_el_campo_de_archivo_no_rompe_el_guardado(): void
    {
        $nivel = $this->nivel();
        app(GuardarProgramasEstudiosProyecto::class)->ejecutar($nivel->id, new DatosProgramasEstudiosProyecto('semestral', null, 'virtual', 'no', $this->palabras(200), $this->archivoPdf(1)));

        $this->componente($nivel)->set('archivo_programas', 'no soy un archivo')->call('guardar')->assertHasNoErrors();
    }

    public function test_tramite_enviado_no_guarda(): void
    {
        $nivel = $this->nivel();
        $nivel->update(['estado_id' => DB::table('estados_expediente')->where('clave', 'en_revision')->value('id')]);

        $this->responder($this->componente($nivel))->call('guardar')
            ->assertDispatched('toast', tipo: 'error', titulo: 'No se guardó', descripcion: 'El trámite ya se envió a revisión y no puede modificarse.');

        $this->assertSame(0, DB::table('proyecto_educativo_programas_estudios')->count());
    }

    public function test_el_indice_marca_completa_la_seccion_dieciseis(): void
    {
        $nivel = $this->nivel();
        $indice = route('tramite.paso3-proyecto-educativo', ['escuelaNivel' => $nivel->id]);

        $this->assertSame(20, substr_count($this->get($indice)->assertOk()->getContent(), 'Pendiente'));

        $this->responder($this->componente($nivel))->call('guardar');
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
