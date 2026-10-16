<?php

namespace Tests\Feature\Application\Revision;

use App\Application\Excepciones\PrecondicionIncumplida;
use App\Application\Revision\AsignarTramite;
use App\Application\Revision\EstablecerPrioridad;
use App\Application\Revision\ListarTablero;
use App\Application\Revision\RevocarRevisor;
use App\Models\EscuelaNivel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\ConTramitesEnRevision;
use Tests\TestCase;

class ListarTableroTest extends TestCase
{
    use ConTramitesEnRevision;
    use RefreshDatabase;

    private User $sup;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sup = $this->supervisor();
    }

    private function enRevisionDesde(EscuelaNivel $nivel, int $diasAtras): void
    {
        DB::table('historial_estados_expediente')->insert([
            'escuela_nivel_id' => $nivel->id,
            'estado_id' => DB::table('estados_expediente')->where('clave', 'en_revision')->value('id'),
            'usuario_sedeq_id' => $this->sup->id,
            'fecha' => now()->subDays($diasAtras),
        ]);
    }

    private function prioridad(EscuelaNivel $nivel, string $p): void
    {
        app(EstablecerPrioridad::class)->ejecutar($this->sup->id, $nivel->id, $p);
    }

    /** @return list<int> */
    private function orden(): array
    {
        return array_map(fn ($f) => $f->escuelaNivelId, app(ListarTablero::class)->ejecutar($this->sup->id)['filas']);
    }

    public function test_alta_antes_que_media_mas_vieja_y_alta_vieja_antes_que_alta_nueva(): void
    {
        [$mediaVieja, $altaNueva, $altaVieja, $baja] = [$this->nivelEnRevision(), $this->nivelEnRevision(), $this->nivelEnRevision(), $this->nivelEnRevision()];
        $this->enRevisionDesde($mediaVieja, 30);
        $this->enRevisionDesde($altaNueva, 2);
        $this->enRevisionDesde($altaVieja, 9);
        $this->enRevisionDesde($baja, 50);
        $this->prioridad($altaNueva, 'alta');
        $this->prioridad($altaVieja, 'alta');
        $this->prioridad($baja, 'baja');

        $this->assertSame([$altaVieja->id, $altaNueva->id, $mediaVieja->id, $baja->id], $this->orden());
    }

    public function test_dias_de_espera_usan_la_ultima_fila_en_revision(): void
    {
        $nivel = $this->nivelEnRevision();
        $this->enRevisionDesde($nivel, 20);
        $this->enRevisionDesde($nivel, 4);

        $fila = app(ListarTablero::class)->ejecutar($this->sup->id)['filas'][0];

        $this->assertSame(4, $fila->diasEspera);
        $this->assertSame('media', $fila->prioridad);
        $this->assertSame('Primaria', $fila->nivel);
        $this->assertNull($fila->revisorId);
    }

    public function test_en_captura_no_aparece_y_muestra_revisor_y_progreso(): void
    {
        $this->nivelEnRevision('en_captura');
        $nivel = $this->nivelEnRevision();
        $this->enRevisionDesde($nivel, 1);
        $rev = $this->revisor();
        app(AsignarTramite::class)->ejecutar($this->sup->id, $nivel->id, $rev->id);
        DB::table('progreso_revision')->where('escuela_nivel_id', $nivel->id)->update(['secciones_revisadas' => 3, 'secciones_total' => 7]);

        $r = app(ListarTablero::class)->ejecutar($this->sup->id);

        $this->assertCount(1, $r['filas']);
        $f = $r['filas'][0];
        $this->assertSame([$rev->id, $rev->name, 3, 7], [$f->revisorId, $f->revisorNombre, $f->revisadas, $f->total]);
        $b = collect($r['revisores'])->firstWhere('revisorId', $rev->id);
        $this->assertSame([1, 5, 3, 7], [$b->asignados, $b->limite, $b->revisadas, $b->total]);
    }

    public function test_barras_incluyen_revisores_sin_asignaciones_y_excluyen_revocados(): void
    {
        [$libre, $revocado] = [$this->revisor(), $this->revisor()];
        app(RevocarRevisor::class)->ejecutar($this->sup->id, $revocado->id);

        $ids = array_map(fn ($b) => $b->revisorId, app(ListarTablero::class)->ejecutar($this->sup->id)['revisores']);

        $this->assertSame([$libre->id], $ids);
    }

    public function test_un_revisor_no_puede_ver_el_tablero(): void
    {
        $this->expectException(PrecondicionIncumplida::class);

        app(ListarTablero::class)->ejecutar($this->revisor()->id);
    }
}
