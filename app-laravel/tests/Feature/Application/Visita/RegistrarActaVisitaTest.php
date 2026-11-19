<?php

namespace Tests\Feature\Application\Visita;

use App\Application\Excepciones\DatosInvalidos;
use App\Application\Excepciones\PrecondicionIncumplida;
use App\Application\Visita\ActaDeVisitaRegistrada;
use App\Application\Visita\AdjuntarActaVisita;
use App\Application\Visita\RegistrarActaVisita;
use App\Models\EscuelaNivel;
use App\Models\VisitaInspeccion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\ConTramitesEnRevision;
use Tests\Concerns\GeneraPdfs;
use Tests\TestCase;

class RegistrarActaVisitaTest extends TestCase
{
    use ConTramitesEnRevision;
    use GeneraPdfs;
    use RefreshDatabase;

    private EscuelaNivel $nivel;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('documentos');
        $this->nivel = $this->nivelEnRevision('visita_programada');
    }

    private function visita(string $estado = 'programada'): int
    {
        $sup = $this->supervisor();
        $datos = ['escuela_nivel_id' => $this->nivel->id, 'numero' => 1, 'programada_para' => now()->subDays(3),
            'inspector' => 'Y', 'estado' => $estado, 'programada_por' => $sup->id];
        if ($estado === 'realizada') {
            $datos += ['resultado' => 'cumple', 'realizada_el' => now()->subDay()->toDateString(), 'registrada_por' => $sup->id];
        }

        return DB::table('visitas_inspeccion')->insertGetId($datos);
    }

    private function registrar($actor, int $id, string $resultado = 'cumple', ?\DateTimeInterface $el = null, array $obs = [], ?UploadedFile $acta = null): void
    {
        app(RegistrarActaVisita::class)->ejecutar($actor, $id, $resultado, $el ?? now()->subDay(), $obs, $acta);
    }

    private function estadoDe(): string
    {
        return DB::table('escuela_niveles')->join('estados_expediente as e', 'e.id', '=', 'escuela_niveles.estado_id')
            ->where('escuela_niveles.id', $this->nivel->id)->value('e.clave');
    }

    public function test_revisor_forbidden(): void
    {
        $id = $this->visita();

        $this->expectException(PrecondicionIncumplida::class);
        $this->registrar($this->revisor(), $id);
    }

    public function test_cumple_approves_nivel_and_marks_visit_realizada(): void
    {
        Event::fake([ActaDeVisitaRegistrada::class]);
        $id = $this->visita();
        $sup = $this->supervisor();

        $this->registrar($sup, $id, 'cumple', now()->subDay());

        $v = VisitaInspeccion::findOrFail($id);
        $this->assertSame('realizada', $v->estado);
        $this->assertSame('cumple', $v->resultado);
        $this->assertSame($sup->id, (int) $v->registrada_por);
        $this->assertNull($v->subsanar_hasta);
        $this->assertSame('visita_aprobada', $this->estadoDe());
        $estadoId = DB::table('estados_expediente')->where('clave', 'visita_aprobada')->value('id');
        $this->assertDatabaseHas('historial_estados_expediente', [
            'escuela_nivel_id' => $this->nivel->id, 'estado_id' => $estadoId, 'usuario_sedeq_id' => $sup->id,
        ]);
        Event::assertDispatched(ActaDeVisitaRegistrada::class, fn ($e) => $e->visitaId === $id);
    }

    public function test_cumple_with_observaciones_rejected(): void
    {
        $id = $this->visita();

        try {
            $this->registrar($this->supervisor(), $id, 'cumple', null, ['algo']);
            $this->fail('Debio rechazar');
        } catch (DatosInvalidos $e) {
            $this->assertArrayHasKey('observaciones', $e->errores);
        }
        $this->assertSame('programada', VisitaInspeccion::findOrFail($id)->estado);
    }

    public function test_con_observaciones_sets_deadline_state_and_ordered_rows(): void
    {
        $id = $this->visita();
        $el = new \DateTimeImmutable('2026-11-18'); // miércoles; +5 hábiles = miércoles 2026-11-25

        $this->registrar($this->supervisor(), $id, 'con_observaciones', $el, [' Primera ', 'Segunda', 'Tercera']);

        $v = VisitaInspeccion::findOrFail($id);
        $this->assertSame('con_observaciones', $v->resultado);
        $this->assertSame('2026-11-25', $v->subsanar_hasta->toDateString());
        $this->assertSame('en_subsanacion', $this->estadoDe());
        $this->assertSame(['Primera', 'Segunda', 'Tercera'], $v->observaciones->pluck('texto')->all());
        $this->assertSame([1, 2, 3], $v->observaciones->pluck('orden')->map(fn ($o) => (int) $o)->all());
    }

    public function test_con_observaciones_blank_list_rejected_and_nothing_changes(): void
    {
        Event::fake([ActaDeVisitaRegistrada::class]);
        $id = $this->visita();

        foreach ([[], ['  ', ''], [str_repeat('a', 1001)]] as $obs) {
            try {
                $this->registrar($this->supervisor(), $id, 'con_observaciones', null, $obs);
                $this->fail('Debio rechazar');
            } catch (DatosInvalidos $e) {
                $this->assertArrayHasKey('observaciones', $e->errores);
            }
        }
        $this->assertSame('programada', VisitaInspeccion::findOrFail($id)->estado);
        $this->assertSame('visita_programada', $this->estadoDe());
        $this->assertSame(0, DB::table('observaciones_visita')->count());
        Event::assertNotDispatched(ActaDeVisitaRegistrada::class);
    }

    public function test_unknown_resultado_and_future_date_rejected(): void
    {
        $id = $this->visita();

        foreach ([['otro', now()->subDay(), 'resultado'], ['cumple', now()->addDays(2), 'realizada_el']] as [$res, $el, $campo]) {
            try {
                $this->registrar($this->supervisor(), $id, $res, $el);
                $this->fail('Debio rechazar');
            } catch (DatosInvalidos $e) {
                $this->assertArrayHasKey($campo, $e->errores);
            }
        }
    }

    public function test_visit_must_be_programada(): void
    {
        $id = $this->visita('realizada');

        $this->expectException(DatosInvalidos::class);
        $this->registrar($this->supervisor(), $id);
    }

    public function test_acta_is_stored_scoped_to_escuela_with_name(): void
    {
        $id = $this->visita();

        $this->registrar($this->supervisor(), $id, 'cumple', null, [], $this->archivoPdf(1, 'acta-firmada.pdf'));

        $v = VisitaInspeccion::findOrFail($id);
        $this->assertSame('acta-firmada.pdf', $v->acta_nombre);
        $this->assertStringStartsWith("visitas/{$this->nivel->escuela_id}/", $v->acta_path);
        Storage::disk('documentos')->assertExists($v->acta_path);
    }

    public function test_listener_failure_after_commit_keeps_row_and_stored_file(): void
    {
        $id = $this->visita();
        Event::listen(ActaDeVisitaRegistrada::class, fn () => throw new \RuntimeException('boom'));

        try {
            $this->registrar($this->supervisor(), $id, 'cumple', null, [], $this->archivoPdf(1));
            $this->fail('Debio propagar la excepcion del listener');
        } catch (\RuntimeException $e) {
            $this->assertSame('boom', $e->getMessage());
        }

        $v = VisitaInspeccion::findOrFail($id);
        $this->assertSame('realizada', $v->estado);
        $this->assertNotNull($v->acta_path);
        Storage::disk('documentos')->assertExists($v->acta_path);
    }

    public function test_non_pdf_and_oversize_acta_rejected_without_changes(): void
    {
        $id = $this->visita();

        foreach ([UploadedFile::fake()->createWithContent('acta.pdf', 'texto plano'), $this->archivoPdf(1, 'grande.pdf', 10 * 1024 * 1024)] as $acta) {
            try {
                $this->registrar($this->supervisor(), $id, 'cumple', null, [], $acta);
                $this->fail('Debio rechazar');
            } catch (DatosInvalidos $e) {
                $this->assertArrayHasKey('acta', $e->errores);
            }
        }
        $this->assertSame('programada', VisitaInspeccion::findOrFail($id)->estado);
        $this->assertSame([], Storage::disk('documentos')->allFiles());
    }

    public function test_stored_file_removed_when_transaction_fails(): void
    {
        $id = $this->visita();
        // Falla dentro de la transacción: otra visita ya ocupa el estado del nivel tras bloquearlo.
        DB::table('estados_expediente')->where('clave', 'visita_aprobada')->update(['clave' => 'visita_aprobada_x']);

        try {
            $this->registrar($this->supervisor(), $id, 'cumple', null, [], $this->archivoPdf(1));
            $this->fail('Debio fallar');
        } catch (\Throwable) {
        }

        $this->assertSame([], Storage::disk('documentos')->allFiles());
    }

    public function test_adjuntar_attaches_to_realizada_visit_and_replaces_old_file(): void
    {
        $id = $this->visita('realizada');
        $adjuntar = app(AdjuntarActaVisita::class);
        $sup = $this->supervisor();

        $adjuntar->ejecutar($sup, $id, $this->archivoPdf(1, 'a.pdf'));
        $primero = VisitaInspeccion::findOrFail($id)->acta_path;
        Storage::disk('documentos')->assertExists($primero);

        $adjuntar->ejecutar($sup, $id, $this->archivoPdf(2, 'b.pdf'));
        $v = VisitaInspeccion::findOrFail($id);

        $this->assertSame('b.pdf', $v->acta_nombre);
        $this->assertNotSame($primero, $v->acta_path);
        Storage::disk('documentos')->assertMissing($primero);
        Storage::disk('documentos')->assertExists($v->acta_path);
    }

    public function test_adjuntar_rejects_programada_visit_and_revisor(): void
    {
        $id = $this->visita('programada');

        try {
            app(AdjuntarActaVisita::class)->ejecutar($this->supervisor(), $id, $this->archivoPdf(1));
            $this->fail('Debio rechazar');
        } catch (DatosInvalidos) {
        }
        $this->assertSame([], Storage::disk('documentos')->allFiles());

        $this->expectException(PrecondicionIncumplida::class);
        app(AdjuntarActaVisita::class)->ejecutar($this->revisor(), $id, $this->archivoPdf(1));
    }
}
