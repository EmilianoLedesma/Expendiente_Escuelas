<?php

namespace Tests\Feature\Livewire\Tramite;

use App\Application\ProyectoEducativo\DTO\DatosPaecProyecto;
use App\Application\ProyectoEducativo\GuardarPaecProyecto;
use App\Livewire\Tramite\Paso3\ProyectoEducativoSeccion;
use App\Models\EscuelaNivel;
use App\Models\Solicitante;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Concerns\GeneraPdfs;
use Tests\Concerns\PreparaPaso3;
use Tests\TestCase;

class Paso3PaecProyectoTest extends TestCase
{
    use GeneraPdfs;
    use PreparaPaso3;
    use RefreshDatabase;

    private const SLUG = 'programa-aula-escuela-y-comunidad-paec';

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

    private function ruta(EscuelaNivel $nivel, string $slug = self::SLUG): string
    {
        return route('tramite.paso3-proyecto-educativo-seccion', ['escuelaNivel' => $nivel->id, 'seccion' => $slug]);
    }

    /** Diagnóstico mínimo válido y dos PDF válidos (1 y 2 páginas). */
    private function llenar($componente, int $paginasHoja = 1, int $paginasPlaneacion = 2)
    {
        return $componente->set('diagnostico', $this->palabras(150))
            ->set('hoja_ruta', $this->archivoPdf($paginasHoja, 'hoja.pdf'))
            ->set('planeacion', $this->archivoPdf($paginasPlaneacion, 'planeacion.pdf'));
    }

    /** @return list<string> */
    private function archivosDeLaSeccion(): array
    {
        return Storage::disk('documentos')->allFiles('proyecto_educativo');
    }

    public function test_la_pagina_es_un_solo_bloque_con_los_tres_campos_las_etiquetas_y_los_placeholders_del_dueno(): void
    {
        $html = $this->get($this->ruta($this->nivel()))->assertOk()
            ->assertSee('Diagnóstico para el PAEC (150 a 500 palabras)')
            ->assertSee('Presente el diagnóstico que sustenta el Programa Aula, Escuela y Comunidad...')
            ->assertSee('Hoja de Ruta del PEC (1 a 2 páginas)')
            ->assertSee('Describa la hoja de ruta que guiará la implementación del Programa Escolar de Mejora Continua...')
            ->assertSee('Planeación del Abordaje (1 a 2 páginas)')
            ->assertSee('Deberá contemplar las Progresiones de las UAC del Currículum Fundamental y Ampliado que se alinean al tema que aborda el PEC...')
            ->assertSee('Guardar')
            ->assertSeeHtml('wire:submit="guardar"')
            ->assertDontSeeHtml('wire:submit="siguiente')
            ->assertDontSee('Contenido por definir')
            ->getContent();

        $this->assertSame(1, substr_count($html, '<textarea'));
        $this->assertSame(2, substr_count($html, 'type="file"'));
        $this->assertSame(2, substr_count($html, 'accept="application/pdf"'));
    }

    public function test_guardar_completo_guarda_los_pdf_y_avisa_que_la_seccion_esta_completa(): void
    {
        $nivel = $this->nivel();

        $this->llenar($this->componente($nivel))->call('guardar')
            ->assertHasNoErrors()
            ->assertSet('hoja_ruta', null)
            ->assertSet('planeacion', null)
            ->assertDispatched('toast', tipo: 'success', titulo: 'Sección completa')
            ->assertSee('hoja.pdf')
            ->assertSee('planeacion.pdf');

        $fila = DB::table('proyecto_educativo_paec')->where('escuela_nivel_id', $nivel->id)->sole();
        $this->assertSame(1, (int) $fila->hoja_ruta_paginas);
        $this->assertSame(2, (int) $fila->planeacion_paginas);
        $this->assertCount(2, $this->archivosDeLaSeccion());
    }

    public function test_un_pdf_de_mas_de_dos_paginas_marca_el_campo_y_no_guarda_nada(): void
    {
        $nivel = $this->nivel();

        $this->llenar($this->componente($nivel), paginasHoja: 3)->call('guardar')
            ->assertHasErrors(['hoja_ruta'])
            ->assertHasNoErrors(['planeacion', 'diagnostico'])
            ->assertDispatched('toast', tipo: 'error', titulo: 'No se guardó');

        $this->assertSame(0, DB::table('proyecto_educativo_paec')->count());
        $this->assertSame([], $this->archivosDeLaSeccion());
    }

    public function test_sin_nada_capturado_marca_los_tres_campos(): void
    {
        $this->componente($this->nivel())->call('guardar')->assertHasErrors(['diagnostico', 'hoja_ruta', 'planeacion']);

        $this->assertSame(0, DB::table('proyecto_educativo_paec')->count());
    }

    public function test_abre_con_lo_guardado_y_se_puede_editar_el_diagnostico_sin_subir_de_nuevo(): void
    {
        $nivel = $this->nivel();
        app(GuardarPaecProyecto::class)->ejecutar($nivel->id, new DatosPaecProyecto($this->palabras(150), $this->archivoPdf(1, 'hoja.pdf'), $this->archivoPdf(2, 'planeacion.pdf')));
        $antes = DB::table('proyecto_educativo_paec')->sole();

        $this->componente($nivel)
            ->assertSet('diagnostico', $this->palabras(150))
            ->assertSee('hoja.pdf')
            ->assertSee('planeacion.pdf')
            ->set('diagnostico', $this->palabras(200))->call('guardar')
            ->assertHasNoErrors()
            ->assertDispatched('toast', tipo: 'success', titulo: 'Sección completa');

        $despues = DB::table('proyecto_educativo_paec')->sole();
        $this->assertSame($this->palabras(200), $despues->diagnostico);
        $this->assertSame($antes->hoja_ruta_path, $despues->hoja_ruta_path);
        $this->assertSame($antes->planeacion_path, $despues->planeacion_path);
    }

    public function test_un_valor_manipulado_en_el_campo_de_archivo_no_rompe_el_guardado(): void
    {
        $nivel = $this->nivel();
        app(GuardarPaecProyecto::class)->ejecutar($nivel->id, new DatosPaecProyecto($this->palabras(150), $this->archivoPdf(1), $this->archivoPdf(1)));

        $this->componente($nivel)->set('hoja_ruta', 'no soy un archivo')->call('guardar')->assertHasNoErrors();
    }

    public function test_tramite_enviado_no_guarda(): void
    {
        $nivel = $this->nivel();
        $nivel->update(['estado_id' => DB::table('estados_expediente')->where('clave', 'en_revision')->value('id')]);

        $this->llenar($this->componente($nivel))->call('guardar')
            ->assertDispatched('toast', tipo: 'error', titulo: 'No se guardó', descripcion: 'El trámite ya se envió a revisión y no puede modificarse.');

        $this->assertSame(0, DB::table('proyecto_educativo_paec')->count());
        $this->assertSame([], $this->archivosDeLaSeccion());
    }

    public function test_otro_solicitante_recibe_403_y_primaria_404(): void
    {
        $nivel = $this->nivel();
        $this->actingAs(Solicitante::factory()->create()->user)->get($this->ruta($nivel))->assertForbidden();

        $primaria = $this->nivelListoPara('primaria', 'matricula');
        $this->actingAs($primaria->escuela->solicitante->user)->get($this->ruta($primaria))->assertNotFound();
    }

    public function test_indice_marca_completa_la_seccion_seis_cuando_lo_esta(): void
    {
        $nivel = $this->nivel();
        $indice = route('tramite.paso3-proyecto-educativo', ['escuelaNivel' => $nivel->id]);

        $this->assertSame(20, substr_count($this->get($indice)->assertOk()->getContent(), 'Pendiente'));

        $this->llenar($this->componente($nivel))->call('guardar');
        $html = $this->get($indice)->getContent();
        $this->assertSame(19, substr_count($html, 'Pendiente'));
        $this->assertSame(1, substr_count($html, 'Completa'));
    }
}
