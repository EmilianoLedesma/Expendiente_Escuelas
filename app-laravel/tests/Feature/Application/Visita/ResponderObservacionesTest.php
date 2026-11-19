<?php

namespace Tests\Feature\Application\Visita;

use App\Application\Excepciones\DatosInvalidos;
use App\Application\Visita\ResponderObservaciones;
use App\Models\EscuelaNivel;
use App\Models\Solicitante;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\ConTramitesEnRevision;
use Tests\Concerns\GeneraPdfs;
use Tests\TestCase;

class ResponderObservacionesTest extends TestCase
{
    use ConTramitesEnRevision;
    use GeneraPdfs;
    use RefreshDatabase;

    private Solicitante $dueno;

    private EscuelaNivel $nivel;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('documentos');
        $this->dueno = Solicitante::factory()->create();
        $this->nivel = $this->nivelDe($this->dueno, 'primaria', 'en_subsanacion');
    }

    private function visita(string $resultado = 'con_observaciones'): int
    {
        $sup = $this->supervisor();

        return DB::table('visitas_inspeccion')->insertGetId([
            'escuela_nivel_id' => $this->nivel->id, 'numero' => 1, 'programada_para' => now()->subDays(3),
            'inspector' => 'Y', 'estado' => 'realizada', 'resultado' => $resultado, 'realizada_el' => now()->subDay()->toDateString(),
            'subsanar_hasta' => $resultado === 'con_observaciones' ? now()->addDays(4)->toDateString() : null,
            'programada_por' => $sup->id, 'registrada_por' => $sup->id,
        ]);
    }

    private function responder(User $actor, int $id, string $nota = 'Corregido', array $archivos = []): int
    {
        return app(ResponderObservaciones::class)->ejecutar($actor, $id, $nota, $archivos);
    }

    private function estadoDe(): string
    {
        return DB::table('escuela_niveles')->join('estados_expediente as e', 'e.id', '=', 'escuela_niveles.estado_id')
            ->where('escuela_niveles.id', $this->nivel->id)->value('e.clave');
    }

    private function nada(): void
    {
        $this->assertSame(0, DB::table('respuestas_visita')->count());
        $this->assertSame(0, DB::table('respuestas_visita_archivos')->count());
        $this->assertSame([], Storage::disk('documentos')->allFiles());
    }

    public function test_owner_stores_response_with_two_pdfs(): void
    {
        $id = $this->visita();

        $rid = $this->responder($this->dueno->user, $id, '  Ya lo corregimos  ', [$this->archivoPdf(1, 'a.pdf'), $this->archivoPdf(2, 'b.pdf')]);

        $this->assertDatabaseHas('respuestas_visita', ['id' => $rid, 'visita_id' => $id, 'nota' => 'Ya lo corregimos', 'creada_por' => $this->dueno->user->id]);
        $filas = DB::table('respuestas_visita_archivos')->where('respuesta_id', $rid)->orderBy('id')->get();
        $this->assertCount(2, $filas);
        $this->assertSame(['a.pdf', 'b.pdf'], $filas->pluck('archivo_nombre')->all());
        foreach ($filas as $f) {
            $this->assertStringStartsWith('visitas/'.$this->nivel->escuela_id.'/', $f->archivo_path);
            Storage::disk('documentos')->assertExists($f->archivo_path);
        }
        $this->assertSame('en_subsanacion', $this->estadoDe());
    }

    public function test_non_sequential_file_keys_pair_each_path_with_its_own_name(): void
    {
        $id = $this->visita();
        $a = UploadedFile::fake()->createWithContent('uno.pdf', $this->pdfDe(1));
        $b = UploadedFile::fake()->createWithContent('dos.pdf', $this->pdfDe(3));

        $rid = $this->responder($this->dueno->user, $id, 'x', [1 => $a, 2 => $b]);

        $filas = DB::table('respuestas_visita_archivos')->where('respuesta_id', $rid)->orderBy('id')->get();
        $this->assertSame(['uno.pdf', 'dos.pdf'], $filas->pluck('archivo_nombre')->all());
        $this->assertSame(file_get_contents($a->getRealPath()), Storage::disk('documentos')->get($filas[0]->archivo_path));
        $this->assertSame(file_get_contents($b->getRealPath()), Storage::disk('documentos')->get($filas[1]->archivo_path));
    }

    public function test_without_files_is_allowed(): void
    {
        $rid = $this->responder($this->dueno->user, $this->visita());

        $this->assertDatabaseHas('respuestas_visita', ['id' => $rid]);
    }

    public function test_other_schools_owner_forbidden(): void
    {
        $id = $this->visita();

        try {
            $this->responder(Solicitante::factory()->create()->user, $id);
            $this->fail('debía rechazar');
        } catch (AuthorizationException) {
            $this->nada();
        }
    }

    public function test_responsable_por_nivel_forbidden(): void
    {
        $id = $this->visita();

        $this->expectException(AuthorizationException::class);
        $this->responder($this->responsableDe($this->nivel), $id);
    }

    public function test_nivel_not_en_subsanacion_rejected(): void
    {
        $id = $this->visita();
        DB::table('escuela_niveles')->where('id', $this->nivel->id)
            ->update(['estado_id' => DB::table('estados_expediente')->where('clave', 'visita_aprobada')->value('id')]);

        $this->expectException(DatosInvalidos::class);
        $this->responder($this->dueno->user, $id);
    }

    public function test_visit_that_complies_rejected(): void
    {
        $id = $this->visita('cumple');

        $this->expectException(DatosInvalidos::class);
        $this->responder($this->dueno->user, $id);
    }

    public function test_blank_nota_rejected(): void
    {
        $id = $this->visita();

        try {
            $this->responder($this->dueno->user, $id, "  \n ");
            $this->fail('debía rechazar');
        } catch (DatosInvalidos $e) {
            $this->assertArrayHasKey('nota', $e->errores);
            $this->nada();
        }
    }

    public function test_nota_over_2000_rejected(): void
    {
        $id = $this->visita();

        $this->expectException(DatosInvalidos::class);
        $this->responder($this->dueno->user, $id, str_repeat('a', 2001));
    }

    public function test_six_files_rejected_with_nothing_stored(): void
    {
        $id = $this->visita();
        $pdfs = array_map(fn ($i) => $this->archivoPdf(1, "{$i}.pdf"), range(1, 6));

        try {
            $this->responder($this->dueno->user, $id, 'x', $pdfs);
            $this->fail('debía rechazar');
        } catch (DatosInvalidos $e) {
            $this->assertArrayHasKey('archivos', $e->errores);
            $this->nada();
        }
    }

    public function test_pdf_extension_with_non_pdf_content_rejected(): void
    {
        $id = $this->visita();

        try {
            $this->responder($this->dueno->user, $id, 'x', [$this->archivoPdf(1), UploadedFile::fake()->createWithContent('falso.pdf', 'no soy pdf')]);
            $this->fail('debía rechazar');
        } catch (DatosInvalidos $e) {
            $this->assertArrayHasKey('archivos', $e->errores);
            $this->nada();
        }
    }

    public function test_file_over_10_mb_rejected(): void
    {
        $id = $this->visita();

        try {
            $this->responder($this->dueno->user, $id, 'x', [$this->archivoPdf(1, 'g.pdf', 10 * 1024 * 1024)]);
            $this->fail('debía rechazar');
        } catch (DatosInvalidos $e) {
            $this->assertArrayHasKey('archivos', $e->errores);
            $this->nada();
        }
    }

    public function test_files_removed_when_transaction_fails(): void
    {
        $id = $this->visita();
        DB::unprepared('ALTER TABLE respuestas_visita_archivos ADD CONSTRAINT falla CHECK (false)');

        try {
            $this->responder($this->dueno->user, $id, 'x', [$this->archivoPdf(1), $this->archivoPdf(1)]);
            $this->fail('debía fallar');
        } catch (\Throwable $e) {
            $this->assertNotInstanceOf(DatosInvalidos::class, $e);
            $this->assertSame(0, DB::table('respuestas_visita')->count());
            $this->assertSame([], Storage::disk('documentos')->allFiles());
        }
    }
}
