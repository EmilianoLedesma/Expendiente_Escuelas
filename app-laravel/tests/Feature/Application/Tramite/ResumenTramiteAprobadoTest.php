<?php

namespace Tests\Feature\Application\Tramite;

use App\Application\Tramite\ResumenTramite;
use App\Models\Solicitante;
use App\Models\User;
use Database\Seeders\PasosCapturaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\ConTramiteDevuelto;
use Tests\TestCase;

/** «Mis trámites» y el hub distinguen la revisión documental aprobada de «en revisión» (Expediente, fase 0). */
class ResumenTramiteAprobadoTest extends TestCase
{
    use ConTramiteDevuelto;
    use RefreshDatabase;

    public function test_con_todos_los_niveles_aprobados_el_resumen_lo_dice(): void
    {
        (new PasosCapturaSeeder)->run();
        $dueno = Solicitante::factory()->create();
        $primaria = $this->nivelDe($dueno, 'primaria', 'aprobado');
        $this->nivelEn($primaria->escuela, 'secundaria', 'aprobado');

        $dto = app(ResumenTramite::class)->paraEscuela($primaria->escuela_id, $dueno->user->id);

        $this->assertTrue($dto->aprobado);
        $this->assertTrue($dto->enviado);
        $this->assertSame(['Primaria', 'Secundaria'], $dto->nivelesAprobados);
    }

    public function test_con_un_nivel_aun_en_revision_no_esta_aprobado_pero_nombra_el_aprobado(): void
    {
        (new PasosCapturaSeeder)->run();
        $dueno = Solicitante::factory()->create();
        $primaria = $this->nivelDe($dueno, 'primaria', 'aprobado');
        $this->nivelEn($primaria->escuela, 'secundaria', 'en_revision');

        $dto = app(ResumenTramite::class)->paraEscuela($primaria->escuela_id, $dueno->user->id);

        $this->assertFalse($dto->aprobado);
        $this->assertSame(['Primaria'], $dto->nivelesAprobados);
    }

    public function test_los_estados_posteriores_cuentan_como_aprobados_y_el_hub_dice_el_de_cada_nivel(): void
    {
        (new PasosCapturaSeeder)->run();
        $dueno = Solicitante::factory()->create();
        $primaria = $this->nivelDe($dueno, 'primaria', 'visita_programada');
        $secundaria = $this->nivelEn($primaria->escuela, 'secundaria', 'en_subsanacion');
        $this->nivelEn($primaria->escuela, 'preescolar', 'visita_aprobada');
        $this->nivelEn($primaria->escuela, 'inicial', 'aprobado');
        $visita = fn (int $nivelId, string $programada, ?string $hasta = null) => DB::table('visitas_inspeccion')->insert([
            'escuela_nivel_id' => $nivelId, 'numero' => 1, 'programada_para' => $programada, 'inspector' => 'Insp',
            'estado' => $hasta === null ? 'programada' : 'realizada', 'resultado' => $hasta === null ? null : 'con_observaciones',
            'realizada_el' => $hasta === null ? null : '2026-11-10', 'subsanar_hasta' => $hasta,
            'programada_por' => User::factory()->create()->id,
        ]);
        $visita($primaria->id, '2026-12-03 10:00:00');
        $visita($secundaria->id, '2026-11-02 10:00:00', '2026-12-17');

        $dto = app(ResumenTramite::class)->paraEscuela($primaria->escuela_id, $dueno->user->id);

        $this->assertTrue($dto->aprobado);
        $this->assertCount(4, $dto->nivelesAprobados);
        $textos = collect($dto->niveles)->mapWithKeys(fn ($n) => [$n->clave => $n->estadoTexto]);
        $this->assertSame('Visita programada para el 03/12/2026', $textos['primaria']);
        $this->assertSame('En subsanación: responde antes de 17/12/2026 (aprox.)', $textos['secundaria']);
        $this->assertSame('Visita aprobada', $textos['preescolar']);
        $this->assertSame('Revisión documental aprobada', $textos['inicial']);
    }

    public function test_un_nivel_en_revision_no_tiene_texto_de_estado_propio(): void
    {
        (new PasosCapturaSeeder)->run();
        $dueno = Solicitante::factory()->create();
        $primaria = $this->nivelDe($dueno, 'primaria', 'en_revision');

        $dto = app(ResumenTramite::class)->paraEscuela($primaria->escuela_id, $dueno->user->id);

        $this->assertNull($dto->niveles[0]->estadoTexto);
    }

    public function test_sin_niveles_aprobados_el_resumen_no_marca_nada(): void
    {
        (new PasosCapturaSeeder)->run();
        $dueno = Solicitante::factory()->create();
        $primaria = $this->nivelDe($dueno, 'primaria', 'en_revision');

        $dto = app(ResumenTramite::class)->paraEscuela($primaria->escuela_id, $dueno->user->id);

        $this->assertFalse($dto->aprobado);
        $this->assertSame([], $dto->nivelesAprobados);
    }
}
