<?php

namespace Tests\Feature\Application\Revision;

use App\Application\Revision\DecidirPaso;
use App\Application\Revision\PasosRevision;
use App\Application\Revision\RevisionAprobada;
use Database\Seeders\PasosCapturaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\Concerns\ConTramitesEnRevision;
use Tests\TestCase;

/** Un nivel ya en un estado posterior a «aprobado» no impide el aviso de aprobación total de la escuela. */
class CierreRevisionNivelPosterioresTest extends TestCase
{
    use ConTramitesEnRevision;
    use RefreshDatabase;

    public function test_aprobar_el_ultimo_nivel_avisa_aunque_otro_ya_este_en_visita_programada(): void
    {
        (new PasosCapturaSeeder)->run();
        Event::fake([RevisionAprobada::class]);
        $sup = $this->supervisor();
        $a = $this->nivelEnRevision('visita_programada');
        $b = $this->nivelEn($a->escuela, 'secundaria', 'en_revision');

        foreach (PasosRevision::paraNivel('secundaria') as $paso) {
            app(DecidirPaso::class)->ejecutar($sup->id, $b->id, $paso, 'aceptado');
        }

        Event::assertDispatched(RevisionAprobada::class, fn ($e) => $e->escuelaId === $a->escuela_id);
    }
}
