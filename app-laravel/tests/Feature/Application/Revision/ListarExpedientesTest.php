<?php

namespace Tests\Feature\Application\Revision;

use App\Application\Excepciones\DatosInvalidos;
use App\Application\Excepciones\PrecondicionIncumplida;
use App\Application\Revision\ListarExpedientes;
use App\Models\EscuelaNivel;
use App\Models\Solicitante;
use Database\Seeders\PasosCapturaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\ConTramitesEnRevision;
use Tests\TestCase;

/** Expediente del supervisor: los niveles con la revisión documental aprobada (fase 0; otros estados vendrán después). */
class ListarExpedientesTest extends TestCase
{
    use ConTramitesEnRevision;
    use RefreshDatabase;

    private function nombrar(EscuelaNivel $nivel, string $nombre): void
    {
        DB::table('escuelas')->where('id', $nivel->escuela_id)->update(['nombre_aprobado' => $nombre]);
    }

    public function test_lista_solo_los_niveles_aprobados_de_escuelas_activas(): void
    {
        (new PasosCapturaSeeder)->run();
        $sup = $this->supervisor();
        $aprobado = $this->nivelEnRevision('aprobado');
        $this->nombrar($aprobado, 'Colegio Aprobado');
        $this->nombrar($this->nivelEnRevision('en_revision'), 'Colegio En Revision');
        $archivado = $this->nivelEnRevision('aprobado');
        $this->nombrar($archivado, 'Colegio Archivado');
        DB::table('escuelas')->where('id', $archivado->escuela_id)->update(['archivado_at' => now()]);

        $filas = app(ListarExpedientes::class)->ejecutar($sup->id);

        $this->assertSame(['Colegio Aprobado'], array_map(fn ($f) => $f->escuela, $filas));
        $this->assertSame($aprobado->id, $filas[0]->escuelaNivelId);
        $this->assertSame($aprobado->escuela_id, $filas[0]->escuelaId);
        $this->assertSame('Primaria', $filas[0]->nivel);
    }

    public function test_trae_la_fecha_de_aprobacion_y_el_revisor_asignado(): void
    {
        (new PasosCapturaSeeder)->run();
        $sup = $this->supervisor();
        $revisor = $this->revisor();
        $nivel = $this->nivelEnRevision('aprobado');
        DB::table('historial_estados_expediente')->insert([
            'escuela_nivel_id' => $nivel->id,
            'estado_id' => DB::table('estados_expediente')->where('clave', 'aprobado')->value('id'),
            'usuario_sedeq_id' => $sup->id,
            'fecha' => '2026-11-10 09:30:00',
        ]);
        DB::table('asignaciones_revision')->insert(['escuela_nivel_id' => $nivel->id, 'revisor_id' => $revisor->id, 'asignado_por' => $sup->id]);

        $fila = app(ListarExpedientes::class)->ejecutar($sup->id)[0];

        $this->assertSame('2026-11-10', $fila->desde?->format('Y-m-d'));
        $this->assertSame($revisor->name, $fila->revisorNombre);
    }

    public function test_busca_por_nombre_de_escuela_sin_distinguir_mayusculas(): void
    {
        (new PasosCapturaSeeder)->run();
        $sup = $this->supervisor();
        $this->nombrar($this->nivelEnRevision('aprobado'), 'Instituto Cumbres');
        $this->nombrar($this->nivelEnRevision('aprobado'), 'Colegio Del Valle');

        $filas = app(ListarExpedientes::class)->ejecutar($sup->id, buscar: 'cumbres');

        $this->assertSame(['Instituto Cumbres'], array_map(fn ($f) => $f->escuela, $filas));
    }

    public function test_solo_el_supervisor_puede_listar(): void
    {
        (new PasosCapturaSeeder)->run();
        $this->nivelEnRevision('aprobado');

        $this->expectException(PrecondicionIncumplida::class);
        app(ListarExpedientes::class)->ejecutar($this->revisor()->id);
    }

    public function test_un_estado_que_el_supervisor_no_ve_se_rechaza(): void
    {
        (new PasosCapturaSeeder)->run();
        $sup = $this->supervisor();

        $this->expectException(DatosInvalidos::class);
        app(ListarExpedientes::class)->ejecutar($sup->id, estado: 'en_captura'); // borrador del solicitante
    }

    public function test_filtra_por_cada_estado_que_ofrece(): void
    {
        (new PasosCapturaSeeder)->run();
        $sup = $this->supervisor();
        $esperados = ['aprobado', 'visita_programada', 'en_subsanacion', 'visita_aprobada', 'en_revision', 'con_observaciones', 'rechazado'];
        foreach ($esperados as $estado) {
            $this->nombrar($this->nivelEnRevision($estado), 'Colegio '.$estado);
        }

        foreach (array_keys(ListarExpedientes::ESTADOS) as $estado) {
            $filas = app(ListarExpedientes::class)->ejecutar($sup->id, estado: $estado);
            $this->assertSame(['Colegio '.$estado], array_map(fn ($f) => $f->escuela, $filas), $estado);
        }
        $this->assertSame($esperados, array_keys(ListarExpedientes::ESTADOS));
        $this->assertSame('Visita programada', ListarExpedientes::ESTADOS['visita_programada']);
        $this->assertSame('En subsanación', ListarExpedientes::ESTADOS['en_subsanacion']);
        $this->assertSame('Visita aprobada', ListarExpedientes::ESTADOS['visita_aprobada']);
    }

    public function test_el_solicitante_no_puede_listar(): void
    {
        (new PasosCapturaSeeder)->run();
        $this->supervisor();

        $this->expectException(PrecondicionIncumplida::class);
        app(ListarExpedientes::class)->ejecutar(Solicitante::factory()->create()->user->id);
    }
}
