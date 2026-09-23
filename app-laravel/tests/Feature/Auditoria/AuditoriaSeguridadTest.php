<?php

namespace Tests\Feature\Auditoria;

use App\Application\Documentos\DTO\DatosDocumento;
use App\Application\Documentos\RegistrarDocumento;
use App\Application\EscuelaNiveles\RegistrarNivelesSeleccionados;
use App\Application\Preregistro\DTO\DatosPreregistro;
use App\Application\Preregistro\DTO\ResultadoPreregistro;
use App\Application\Preregistro\IniciarTramiteNuevo;
use App\Livewire\Tramite\Paso1Preregistro;
use App\Livewire\Tramite\Paso2Responsable;
use App\Models\Escuela;
use App\Models\EscuelaNivel;
use App\Models\NivelEducativo;
use App\Models\Solicitante;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\TestCase;
use Throwable;

/**
 * Regression tests from the 2026-09-23 audit. Each one FAILS on master 776ffee
 * (red for the right reason) and must pass once its workstream lands.
 */
class AuditoriaSeguridadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    private function nuevoTramite(Solicitante $s): ResultadoPreregistro
    {
        return app(IniciarTramiteNuevo::class)->ejecutar(new DatosPreregistro(
            bifurcacion: 'nuevo',
            calle: 'Calle',
            colonia: 'Col',
            municipio: 'Querétaro',
            codigoPostal: '76000'
        ), $s->id);
    }

    // WS-1.1 — a solicitante must never attach an escuela to another solicitante's plantel.
    public function test_paso1_rechaza_plantel_ajeno_en_livewire(): void
    {
        $a = Solicitante::factory()->create();
        $resA = $this->nuevoTramite($a);
        $b = Solicitante::factory()->create();
        $this->actingAs($b->user);

        Livewire::test(Paso1Preregistro::class)
            ->set('bifurcacion', 'existente')
            ->set('plantelId', $resA->plantelId)
            ->call('guardar')
            ->assertHasErrors('plantelId');

        $this->assertSame(0, Escuela::where('solicitante_id', $b->id)->count());
    }

    // WS-1.1 — enforced in Application, so a future API adapter inherits it.
    public function test_iniciar_tramite_nuevo_rechaza_plantel_ajeno(): void
    {
        $a = Solicitante::factory()->create();
        $resA = $this->nuevoTramite($a);
        $b = Solicitante::factory()->create();

        $this->expectException(\InvalidArgumentException::class);
        app(IniciarTramiteNuevo::class)->ejecutar(new DatosPreregistro(bifurcacion: 'existente', plantelId: $resA->plantelId), $b->id);
    }

    // WS-1.1 — the original exploit end to end: B must not be able to read A's escritura.
    public function test_solicitante_b_no_puede_descargar_documentos_de_plantel_de_a(): void
    {
        Storage::fake('documentos');
        $a = Solicitante::factory()->create();
        $resA = $this->nuevoTramite($a);
        app(RegistrarDocumento::class)->ejecutar(
            $resA->escuelaId,
            'escritura_inmueble',
            UploadedFile::fake()->createWithContent('e.pdf', 'SECRETO-DE-A'),
            new DatosDocumento(tipoAcreditacion: 'otro', otroEspecifique: 'x')
        );
        $b = Solicitante::factory()->create();
        $this->actingAs($b->user);

        try {
            Livewire::test(Paso1Preregistro::class)->set('bifurcacion', 'existente')->set('plantelId', $resA->plantelId)->call('guardar');
        } catch (Throwable) {
        }

        foreach (Escuela::where('solicitante_id', $b->id)->get() as $escuelaB) {
            $resp = $this->get(route('tramite.paso2-documentos.descargar', ['escuela' => $escuelaB->id, 'clave' => 'escritura_inmueble']));
            $this->assertNotSame('SECRETO-DE-A', $resp->isOk() ? $resp->streamedContent() : null);
        }
        $this->assertTrue(true);
    }

    // WS-1.2 — niveles cannot be selected without responsable + complete, in-date documents.
    public function test_guardar_niveles_sin_responsable_ni_documentos_no_crea_escuela_niveles(): void
    {
        $s = Solicitante::factory()->create();
        $res = $this->nuevoTramite($s);
        $this->actingAs($s->user);

        try {
            Livewire::test(Paso2Responsable::class, ['escuela' => Escuela::find($res->escuelaId)])
                ->set('nivelesSeleccionados', [NivelEducativo::educacionBasica()->value('id')])
                ->call('guardarNiveles');
        } catch (Throwable) {
        }

        $this->assertSame(0, EscuelaNivel::where('escuela_id', $res->escuelaId)->count());
    }

    // WS-1.2 — the invariant lives in the use case, not only in the component.
    public function test_registrar_niveles_rechaza_si_paso2_incompleto(): void
    {
        $s = Solicitante::factory()->create();
        $res = $this->nuevoTramite($s);

        $this->expectException(\DomainException::class);
        app(RegistrarNivelesSeleccionados::class)->ejecutar($res->escuelaId, [NivelEducativo::educacionBasica()->value('id')]);
    }

    // WS-1.2 — $fase is server-owned state.
    public function test_fase_de_paso2_no_es_escribible_por_el_cliente(): void
    {
        $s = Solicitante::factory()->create();
        $res = $this->nuevoTramite($s);
        $this->actingAs($s->user);

        $this->expectException(CannotUpdateLockedPropertyException::class);
        Livewire::test(Paso2Responsable::class, ['escuela' => Escuela::find($res->escuelaId)])->set('fase', 'niveles');
    }
}
