<?php

namespace Tests\Feature\Application\Revision;

use App\Application\Excepciones\PrecondicionIncumplida;
use App\Application\Revision\AsignarTramite;
use App\Application\Revision\EstablecerPrioridad;
use App\Application\Revision\ListarColaDelRevisor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\ConTramitesEnRevision;
use Tests\TestCase;

class ListarColaDelRevisorTest extends TestCase
{
    use ConTramitesEnRevision;
    use RefreshDatabase;

    private User $sup;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sup = $this->supervisor();
    }

    private function asignado(User $revisor, int $diasAtras, ?string $prioridad = null): int
    {
        $nivel = $this->nivelEnRevision();
        DB::table('historial_estados_expediente')->insert([
            'escuela_nivel_id' => $nivel->id,
            'estado_id' => DB::table('estados_expediente')->where('clave', 'en_revision')->value('id'),
            'usuario_sedeq_id' => $this->sup->id,
            'fecha' => now()->subDays($diasAtras),
        ]);
        app(AsignarTramite::class)->ejecutar($this->sup->id, $nivel->id, $revisor->id);
        if ($prioridad) {
            app(EstablecerPrioridad::class)->ejecutar($this->sup->id, $nivel->id, $prioridad);
        }

        return $nivel->id;
    }

    public function test_solo_ve_lo_suyo_ordenado_por_prioridad_y_antiguedad(): void
    {
        [$yo, $otro] = [$this->revisor(), $this->revisor()];
        $media = $this->asignado($yo, 30);
        $alta = $this->asignado($yo, 3, 'alta');
        $this->asignado($otro, 99, 'alta');

        $cola = app(ListarColaDelRevisor::class)->ejecutar($yo->id);

        $this->assertSame([$alta, $media], array_map(fn ($f) => $f->escuelaNivelId, $cola));
        $this->assertSame([3, 30], array_map(fn ($f) => $f->diasEspera, $cola));
        $this->assertSame($yo->id, $cola[0]->revisorId);
    }

    public function test_un_nivel_devuelto_a_en_captura_no_aparece(): void
    {
        $yo = $this->revisor();
        $visible = $this->asignado($yo, 5);
        $devuelto = $this->asignado($yo, 9);
        DB::table('escuela_niveles')->where('id', $devuelto)->update(['estado_id' => DB::table('estados_expediente')->where('clave', 'en_captura')->value('id')]);

        $cola = app(ListarColaDelRevisor::class)->ejecutar($yo->id);

        $this->assertSame([$visible], array_map(fn ($f) => $f->escuelaNivelId, $cola));
    }

    public function test_un_supervisor_no_puede_ver_la_cola(): void
    {
        $this->expectException(PrecondicionIncumplida::class);

        app(ListarColaDelRevisor::class)->ejecutar($this->sup->id);
    }
}
