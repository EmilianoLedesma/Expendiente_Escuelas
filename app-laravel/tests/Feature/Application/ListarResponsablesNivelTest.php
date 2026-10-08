<?php

namespace Tests\Feature\Application;

use App\Application\ResponsablesNivel\ListarNivelesDelResponsable;
use App\Application\ResponsablesNivel\ListarResponsablesNivel;
use App\Models\Solicitante;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ConNivelParaResponsables;
use Tests\TestCase;

class ListarResponsablesNivelTest extends TestCase
{
    use ConNivelParaResponsables;
    use RefreshDatabase;

    public function test_lista_solo_niveles_del_solicitante_marcando_los_asignables(): void
    {
        $dueno = Solicitante::factory()->create();
        $enCaptura = $this->nivelDe($dueno, 'primaria');
        $enRevision = $this->nivelEn($enCaptura->escuela, 'secundaria', 'en_revision');
        $this->nivelDe(Solicitante::factory()->create(), 'preescolar');
        $responsable = $this->responsableDe($enCaptura);

        $filas = collect(app(ListarResponsablesNivel::class)->ejecutar($dueno->id))->keyBy('escuelaNivelId');

        $this->assertCount(2, $filas);
        $this->assertTrue($filas[$enCaptura->id]['asignable']);
        $this->assertFalse($filas[$enRevision->id]['asignable']);
        $this->assertSame(
            'Nº '.str_pad((string) $enCaptura->escuela_id, 4, '0', STR_PAD_LEFT).' · Primaria — '.$enCaptura->escuela->plantel->calle,
            $filas[$enCaptura->id]['etiqueta'],
        );
        $this->assertSame(
            ['id' => $responsable->accesosNivel()->value('id'), 'nombre' => $responsable->name, 'correo' => $responsable->email],
            $filas[$enCaptura->id]['responsables'][0],
        );
        $this->assertSame([], $filas[$enRevision->id]['responsables']);
    }

    public function test_niveles_del_responsable(): void
    {
        $dueno = Solicitante::factory()->create();
        $nivelA = $this->nivelDe($dueno, 'primaria');
        $this->nivelEn($nivelA->escuela, 'secundaria');
        $responsable = $this->responsableDe($nivelA);

        $niveles = app(ListarNivelesDelResponsable::class)->ejecutar($responsable->id);

        $this->assertSame([[
            'escuelaId' => $nivelA->escuela_id,
            'escuelaNivelId' => $nivelA->id,
            'nivel' => 'Primaria',
            'etiqueta' => 'Nº '.str_pad((string) $nivelA->escuela_id, 4, '0', STR_PAD_LEFT).' — '.$nivelA->escuela->plantel->calle,
        ]], $niveles);
    }
}
