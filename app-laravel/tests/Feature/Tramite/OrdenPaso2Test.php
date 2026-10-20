<?php

namespace Tests\Feature\Tramite;

use App\Application\Documentos\DTO\DatosDocumento;
use App\Application\Documentos\RegistrarDocumento;
use App\Application\EscuelaNiveles\RegistrarDatosNivel;
use App\Application\EscuelaNiveles\RegistrarNivelesSeleccionados;
use App\Application\Excepciones\PrecondicionIncumplida;
use App\Application\ResponsableLegal\DTO\DatosResponsableLegal;
use App\Application\ResponsableLegal\RegistrarResponsableLegal;
use App\Application\Tramite\EstadoPaso2;
use App\Livewire\Tramite\Paso2Responsable;
use App\Models\DocumentoEscuela;
use App\Models\DocumentoPlantel;
use App\Models\Escuela;
use App\Models\EscuelaNivel;
use App\Models\NivelEducativo;
use App\Models\Plantel;
use App\Models\Solicitante;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CompletaPaso2;
use Tests\Concerns\ConNivelParaResponsables;
use Tests\Concerns\DictamenDeEjemplo;
use Tests\TestCase;

/**
 * Orden de Paso 2 desde 2026-10-20: responsable → niveles → documentos generales → documentos por nivel → Paso 3
 * (spec docs/specs/2026-10-20-paso2-integracion-end-to-end.md §3-§5). Cada compuerta se prueba por la página
 * (round-trip HTTP real, ADR-003) y por el caso de uso: una URL o una llamada directa no se saltan una etapa.
 */
class OrdenPaso2Test extends TestCase
{
    use CompletaPaso2;
    use ConNivelParaResponsables;
    use RefreshDatabase;

    private Solicitante $solicitante;

    private Escuela $escuela;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        Storage::fake('documentos');
        $this->solicitante = Solicitante::factory()->create();
        $plantel = Plantel::create(['calle' => 'Calle 1', 'colonia' => 'Centro', 'municipio' => 'Querétaro', 'codigo_postal' => '76000']);
        $this->escuela = Escuela::create(['plantel_id' => $plantel->id, 'solicitante_id' => $this->solicitante->id]);
    }

    private function responsable(): void
    {
        (new RegistrarResponsableLegal)->ejecutar($this->escuela->id, new DatosResponsableLegal(tipoPersona: 'fisica', nombre: 'Juana Pérez'));
    }

    private function idNivel(string $clave): int
    {
        return (int) NivelEducativo::where('clave', $clave)->value('id');
    }

    private function pdf(): UploadedFile
    {
        return UploadedFile::fake()->create('d.pdf', 10, 'application/pdf');
    }

    public function test_los_niveles_se_eligen_con_solo_el_responsable(): void
    {
        $this->responsable();

        app(RegistrarNivelesSeleccionados::class)->ejecutar($this->escuela->id, agregar: [$this->idNivel('primaria')]);

        $this->assertDatabaseHas('escuela_niveles', ['escuela_id' => $this->escuela->id, 'nivel_educativo_id' => $this->idNivel('primaria')]);
        $this->assertDatabaseCount('documentos_escuela', 0);
    }

    public function test_sin_responsable_no_se_eligen_niveles(): void
    {
        try {
            app(RegistrarNivelesSeleccionados::class)->ejecutar($this->escuela->id, agregar: [$this->idNivel('primaria')]);
            $this->fail('Debió exigir el responsable legal.');
        } catch (PrecondicionIncumplida $e) {
            $this->assertSame(EstadoPaso2::RESPONSABLE, $e->etapaFaltante);
        }

        $this->assertDatabaseCount('escuela_niveles', 0);
    }

    public function test_un_documento_general_sin_niveles_se_rechaza_en_el_caso_de_uso(): void
    {
        $this->responsable();

        try {
            app(RegistrarDocumento::class)->ejecutar($this->escuela->id, 'ine', $this->pdf(), new DatosDocumento);
            $this->fail('Debió exigir los niveles.');
        } catch (PrecondicionIncumplida $e) {
            $this->assertSame(EstadoPaso2::NIVELES, $e->etapaFaltante);
        }

        $this->assertSame(0, DocumentoEscuela::count() + DocumentoPlantel::count());
        $this->assertSame([], Storage::disk('documentos')->allFiles());
    }

    public function test_un_documento_general_con_niveles_se_acepta(): void
    {
        $this->responsable();
        $this->nivelEn($this->escuela, 'primaria');

        app(RegistrarDocumento::class)->ejecutar($this->escuela->id, 'dictamen_uso_suelo', $this->pdf(), DictamenDeEjemplo::conVigencia('sin_fecha_expresa'));

        $this->assertSame(1, DocumentoPlantel::count());
    }

    public function test_la_pagina_de_documentos_sin_niveles_manda_a_elegirlos(): void
    {
        $this->responsable();

        $this->actingAs($this->solicitante->user)
            ->get(route('tramite.paso2-documentos', ['escuela' => $this->escuela->id]))
            ->assertRedirect(route('tramite.paso2', ['escuela' => $this->escuela->id, 'seccion' => 'niveles']));
    }

    public function test_la_pagina_de_documentos_con_niveles_se_abre(): void
    {
        $this->responsable();
        $this->nivelEn($this->escuela, 'primaria');

        $this->actingAs($this->solicitante->user)
            ->get(route('tramite.paso2-documentos', ['escuela' => $this->escuela->id]))
            ->assertOk();
    }

    /** @return array<string, array{string}> */
    public static function paginasDelNivel(): array
    {
        return [
            'Paso 2.4' => ['tramite.paso2-nivel-documentos'],
            'Paso 3 inmueble' => ['tramite.paso3-inmueble'],
            'Paso 3 infraestructura' => ['tramite.paso3-infraestructura'],
            'Paso 3 matrícula' => ['tramite.paso3-matricula'],
        ];
    }

    /** El bypass que abría reutilizar puedeSeleccionarNiveles(): con niveles y sin documentos, 2.4 y Paso 3 siguen cerrados. */
    #[DataProvider('paginasDelNivel')]
    public function test_con_niveles_y_sin_documentos_generales_las_paginas_del_nivel_mandan_a_documentos(string $ruta): void
    {
        $this->responsable();
        $primaria = $this->nivelEn($this->escuela, 'primaria');

        $this->actingAs($this->solicitante->user)
            ->get(route($ruta, ['escuelaNivel' => $primaria->id]))
            ->assertRedirect(route('tramite.paso2-documentos', ['escuela' => $this->escuela->id]));

        $this->assertDatabaseCount('escuela_nivel_pasos', 0);
    }

    public function test_con_niveles_y_sin_documentos_los_datos_del_nivel_se_rechazan_en_el_caso_de_uso(): void
    {
        $this->responsable();
        $primaria = $this->nivelEn($this->escuela, 'primaria');

        try {
            app(RegistrarDatosNivel::class)->ejecutar($primaria->id, 'matutino', 'mixto');
            $this->fail('Debió exigir los documentos generales.');
        } catch (PrecondicionIncumplida $e) {
            $this->assertSame(EstadoPaso2::DOCUMENTOS, $e->etapaFaltante);
        }

        $this->assertNull($primaria->fresh()->turno);
    }

    public function test_con_generales_satisfechos_el_paso24_se_abre(): void
    {
        $primaria = $this->nivelEn($this->escuela, 'primaria');
        $this->completarPaso2($this->escuela->id);

        $this->actingAs($this->solicitante->user)
            ->get(route('tramite.paso2-nivel-documentos', ['escuelaNivel' => $primaria->id]))
            ->assertOk();
    }

    public function test_al_guardar_el_responsable_por_primera_vez_sigue_con_los_niveles(): void
    {
        $this->actingAs($this->solicitante->user);

        Livewire::test(Paso2Responsable::class, ['escuela' => $this->escuela])
            ->set('tipoPersona', 'fisica')
            ->set('domicilioNotificaciones', 'Calle Falsa 123, Centro')
            ->set('nombrePropuesto1', 'Colegio Reforma')
            ->set('nombrePropuesto2', 'Instituto Reforma')
            ->set('nombrePropuesto3', 'Escuela Reforma')
            ->set('personaFisicaForm.nombre', 'Juana Pérez')
            ->set('personaFisicaForm.fechaNacimiento', '1980-01-01')
            ->set('personaFisicaForm.rfc', 'PEJJ800101AB1')
            ->set('personaFisicaForm.curp', 'PEJJ800101MQTRRN09')
            ->call('guardarResponsable')
            ->assertHasNoErrors()
            ->assertNoRedirect()
            ->assertSet('fase', 'niveles');
    }

    public function test_al_guardar_los_niveles_por_primera_vez_sigue_con_los_documentos_generales(): void
    {
        $this->responsable();
        $this->actingAs($this->solicitante->user);

        Livewire::test(Paso2Responsable::class, ['escuela' => $this->escuela])
            ->assertSet('fase', 'niveles')
            ->set('nivelesSeleccionados', [$this->idNivel('primaria')])
            ->call('guardarNiveles')
            ->assertRedirect(route('tramite.paso2-documentos', ['escuela' => $this->escuela->id]));

        $this->assertSame(1, EscuelaNivel::where('escuela_id', $this->escuela->id)->count());
    }

    public function test_con_documentos_ya_satisfechos_guardar_niveles_vuelve_al_resumen(): void
    {
        $this->nivelEn($this->escuela, 'primaria');
        $this->completarPaso2($this->escuela->id);
        $this->actingAs($this->solicitante->user);

        Livewire::withQueryParams(['seccion' => 'niveles'])
            ->test(Paso2Responsable::class, ['escuela' => $this->escuela])
            ->set('nivelesSeleccionados', [$this->idNivel('primaria'), $this->idNivel('secundaria')])
            ->call('guardarNiveles')
            ->assertRedirect(route('tramite.resumen', ['escuela' => $this->escuela->id]));
    }

    /** Histórico: documentos completos capturados antes de que existieran niveles. Se conservan y no se duplican. */
    public function test_historico_con_documentos_sin_niveles_abre_la_eleccion_de_niveles(): void
    {
        $this->completarPaso2($this->escuela->id);
        $documentos = DocumentoEscuela::count() + DocumentoPlantel::count();
        $this->actingAs($this->solicitante->user);

        Livewire::test(Paso2Responsable::class, ['escuela' => $this->escuela])
            ->assertSet('fase', 'niveles')
            ->set('nivelesSeleccionados', [$this->idNivel('primaria')])
            ->call('guardarNiveles')
            ->assertRedirect(route('tramite.resumen', ['escuela' => $this->escuela->id]));

        $this->assertSame($documentos, DocumentoEscuela::count() + DocumentoPlantel::count());
        $this->assertTrue(app(EstadoPaso2::class)->generalesSatisfechos($this->escuela->id));
    }

    public function test_un_responsable_sin_niveles_abre_la_eleccion_de_niveles(): void
    {
        $this->responsable();

        $this->actingAs($this->solicitante->user)
            ->get(route('tramite.paso2', ['escuela' => $this->escuela->id]))
            ->assertOk()
            ->assertSee('Niveles educativos');
    }
}
