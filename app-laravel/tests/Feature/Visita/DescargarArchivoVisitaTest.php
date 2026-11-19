<?php

namespace Tests\Feature\Visita;

use App\Models\EscuelaNivel;
use App\Models\Solicitante;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\ConTramitesEnRevision;
use Tests\Concerns\ConVisitas;
use Tests\Concerns\GeneraPdfs;
use Tests\TestCase;

class DescargarArchivoVisitaTest extends TestCase
{
    use ConTramitesEnRevision;
    use ConVisitas;
    use GeneraPdfs;
    use RefreshDatabase;

    private Solicitante $dueno;

    private EscuelaNivel $nivel;

    private int $visita;

    private int $archivo;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('documentos');
        $this->dueno = Solicitante::factory()->create();
        $this->nivel = $this->nivelDe($this->dueno, 'primaria', 'en_subsanacion');
        $this->visita = $this->visitaDe($this->nivel, 1, 'realizada', 'con_observaciones', [
            'acta_path' => $this->archivoEnDisco('visitas/1/acta.pdf'), 'acta_nombre' => "../\"acta\"\r\nX/Y\\.exe",
        ]);
        [, $this->archivo] = $this->respuestaDe($this->visita, 'evidencia.pdf', $this->archivoEnDisco('visitas/1/resp.pdf'));
    }

    private function urlActa(?int $nivel = null, ?int $visita = null): string
    {
        return route('visita.acta', ['escuelaNivel' => $nivel ?? $this->nivel->id, 'visita' => $visita ?? $this->visita]);
    }

    private function urlArchivo(?int $nivel = null, ?int $visita = null, ?int $archivo = null): string
    {
        return route('visita.respuesta-archivo', ['escuelaNivel' => $nivel ?? $this->nivel->id, 'visita' => $visita ?? $this->visita, 'archivo' => $archivo ?? $this->archivo]);
    }

    public function test_supervisor_no_descarga_de_un_nivel_en_captura(): void
    {
        $borrador = $this->nivelEnRevision('en_captura');
        $visita = $this->visitaDe($borrador, 1, 'realizada', 'cumple', ['acta_path' => $this->archivoEnDisco('visitas/9/acta.pdf'), 'acta_nombre' => 'a.pdf']);

        $this->actingAs($this->supervisor())
            ->get($this->urlActa($borrador->id, $visita))->assertForbidden();
    }

    public function test_supervisor_y_dueno_descargan_el_acta_con_encabezados_seguros(): void
    {
        foreach ([$this->supervisor(), $this->dueno->user] as $quien) {
            $r = $this->actingAs($quien)->get($this->urlActa())->assertOk()
                ->assertHeader('Content-Type', 'application/pdf')->assertHeader('X-Content-Type-Options', 'nosniff');
            $disposicion = $r->headers->get('Content-Disposition');
            $this->assertStringStartsWith('attachment', $disposicion);
            $this->assertStringNotContainsString('..', $disposicion);
            $this->assertStringNotContainsString("\n", $disposicion);
            $this->assertStringNotContainsString('/', $disposicion);
            $this->assertStringContainsString('.pdf', $disposicion);
            $this->assertStringNotContainsString('.exe', $disposicion);
        }
    }

    public function test_supervisor_y_dueno_descargan_el_archivo_de_una_respuesta(): void
    {
        foreach ([$this->supervisor(), $this->dueno->user] as $quien) {
            $r = $this->actingAs($quien)->get($this->urlArchivo())->assertOk()
                ->assertHeader('Content-Type', 'application/pdf')->assertHeader('X-Content-Type-Options', 'nosniff');
            $this->assertStringContainsString('evidencia.pdf', $r->headers->get('Content-Disposition'));
        }
    }

    public function test_responsable_de_nivel_otro_dueno_y_revisor_reciben_403(): void
    {
        $quienes = [$this->responsableDe($this->nivel), Solicitante::factory()->create()->user, $this->revisor()];
        foreach ($quienes as $quien) {
            $this->actingAs($quien)->get($this->urlActa())->assertForbidden();
            $this->actingAs($quien)->get($this->urlArchivo())->assertForbidden();
        }
    }

    public function test_invitado_va_al_login(): void
    {
        $this->get($this->urlActa())->assertRedirect(route('login'));
        $this->get($this->urlArchivo())->assertRedirect(route('login'));
    }

    public function test_archivo_faltante_en_disco_da_404(): void
    {
        Storage::disk('documentos')->delete(['visitas/1/acta.pdf', 'visitas/1/resp.pdf']);

        $this->actingAs($this->supervisor())->get($this->urlActa())->assertNotFound();
        $this->actingAs($this->supervisor())->get($this->urlArchivo())->assertNotFound();
    }

    public function test_visita_sin_acta_da_404(): void
    {
        $otra = $this->visitaDe($this->nivel, 2, 'programada');

        $this->actingAs($this->supervisor())->get($this->urlActa(visita: $otra))->assertNotFound();
    }

    public function test_ids_de_otro_nivel_dan_404_aunque_el_actor_tenga_acceso(): void
    {
        $ajeno = $this->nivelDe(Solicitante::factory()->create(), 'primaria', 'en_subsanacion');
        $visitaAjena = $this->visitaDe($ajeno, 1, 'realizada', 'con_observaciones', ['acta_path' => $this->archivoEnDisco('visitas/2/acta.pdf')]);
        [, $archivoAjeno] = $this->respuestaDe($visitaAjena, 'x.pdf', $this->archivoEnDisco('visitas/2/r.pdf'));
        $sup = $this->supervisor();

        // La visita de otro nivel bajo la URL de mi nivel.
        $this->actingAs($sup)->get($this->urlActa(visita: $visitaAjena))->assertNotFound();
        $this->actingAs($this->dueno->user)->get($this->urlActa(visita: $visitaAjena))->assertNotFound();
        // El archivo de otra visita bajo mi visita.
        $this->actingAs($sup)->get($this->urlArchivo(archivo: $archivoAjeno))->assertNotFound();
        // Una visita propia con el nivel ajeno en la URL: el dueño no tiene acceso a ese nivel.
        $this->actingAs($this->dueno->user)->get($this->urlActa(nivel: $ajeno->id))->assertForbidden();
        $this->assertSame(1, DB::table('visitas_inspeccion')->where('id', $this->visita)->count());
    }
}
