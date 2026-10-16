<?php

namespace Tests\Feature\Application\Revision;

use App\Application\Excepciones\DatosInvalidos;
use App\Application\Excepciones\PrecondicionIncumplida;
use App\Application\Revision\EstablecerPrioridad;
use App\Application\Revision\EstadoRevision;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\ConTramitesEnRevision;
use Tests\TestCase;

class EstablecerPrioridadTest extends TestCase
{
    use ConTramitesEnRevision;
    use RefreshDatabase;

    public function test_sin_fila_se_lee_como_media(): void
    {
        $this->assertSame('media', EstadoRevision::prioridadDe($this->nivelEnRevision()->id));
    }

    public function test_establece_y_actualiza_la_prioridad(): void
    {
        $sup = $this->supervisor();
        $nivel = $this->nivelEnRevision();

        app(EstablecerPrioridad::class)->ejecutar($sup->id, $nivel->id, 'alta');
        app(EstablecerPrioridad::class)->ejecutar($sup->id, $nivel->id, 'baja');

        $this->assertSame('baja', EstadoRevision::prioridadDe($nivel->id));
        $fila = DB::table('prioridades_revision')->sole();
        $this->assertSame($sup->id, (int) $fila->actualizado_por);
    }

    public function test_valor_invalido_lanza_datos_invalidos(): void
    {
        $sup = $this->supervisor();

        $this->expectException(DatosInvalidos::class);
        app(EstablecerPrioridad::class)->ejecutar($sup->id, $this->nivelEnRevision()->id, 'urgente');
    }

    public function test_rechaza_un_nivel_que_no_esta_en_revision(): void
    {
        $sup = $this->supervisor();

        $this->expectException(PrecondicionIncumplida::class);
        app(EstablecerPrioridad::class)->ejecutar($sup->id, $this->nivelEnRevision('en_captura')->id, 'alta');
    }

    public function test_rechaza_un_actor_que_no_es_supervisor(): void
    {
        $rev = $this->revisor();

        $this->expectException(PrecondicionIncumplida::class);
        app(EstablecerPrioridad::class)->ejecutar($rev->id, $this->nivelEnRevision()->id, 'alta');
    }

    public function test_id_en_revision_lee_el_catalogo(): void
    {
        $this->nivelEnRevision();

        $this->assertSame((int) DB::table('estados_expediente')->where('clave', 'en_revision')->value('id'), EstadoRevision::idEnRevision());
    }
}
