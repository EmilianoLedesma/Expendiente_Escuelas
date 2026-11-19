<?php

namespace Tests\Feature\Application\Tramite;

use App\Application\Tramite\ResumenTramite;
use App\Models\Solicitante;
use Database\Seeders\PasosCapturaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
