<?php

namespace Tests\Feature\Livewire\Sedeq;

use App\Application\Revision\AsignarTramite;
use App\Application\Revision\ListarColaDelRevisor;
use App\Application\Revision\ListarTablero;
use App\Application\Revision\PasosRevision;
use App\Livewire\Sedeq\RevisionNivel;
use App\Models\EscuelaNivel;
use App\Models\User;
use Database\Seeders\TiposDocumentosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Concerns\ConTramitesEnRevision;
use Tests\TestCase;

/** Recorrido completo: asignar, decidir cada paso por la página y ver el avance en el tablero y la cola. */
class RevisionFlujoCompletoTest extends TestCase
{
    use ConTramitesEnRevision;
    use RefreshDatabase;

    private EscuelaNivel $nivel;

    private User $revisor;

    private User $supervisor;

    protected function setUp(): void
    {
        parent::setUp();
        (new TiposDocumentosSeeder)->run();
        $this->nivel = $this->nivelEnRevision();
        $this->revisor = $this->revisor();
        $this->supervisor = $this->supervisor();
        app(AsignarTramite::class)->ejecutar($this->supervisor->id, $this->nivel->id, $this->revisor->id);
    }

    /** @return list<string> */
    private function pasos(): array
    {
        return PasosRevision::paraNivel($this->nivel->nivelEducativo->clave);
    }

    private function estadoNivel(): string
    {
        return DB::table('estados_expediente')->where('id', $this->nivel->fresh()->estado_id)->value('clave');
    }

    private function avance(): array
    {
        $p = DB::table('progreso_revision')->where('escuela_nivel_id', $this->nivel->id)->first();

        return [(int) $p->secciones_revisadas, (int) $p->secciones_total];
    }

    public function test_revisor_recorre_el_nivel_y_el_tablero_muestra_el_avance(): void
    {
        $pasos = $this->pasos();
        $n = count($pasos);
        $pagina = Livewire::actingAs($this->revisor)->test(RevisionNivel::class, ['escuelaNivel' => $this->nivel->id]);

        foreach ($pasos as $clave) {
            $pagina->call('abrir', $clave)->assertSet('pasoAbierto', $clave)->call('aceptar');
        }

        $this->assertSame('aprobado', $this->estadoNivel());
        $this->assertSame([$n, $n], $this->avance());

        $tablero = app(ListarTablero::class)->ejecutar($this->supervisor->id);
        // Un nivel aprobado deja de estar en revisión: sale del tablero y de la cola.
        $this->assertSame([], $tablero['filas']);
        $this->assertSame([], app(ListarColaDelRevisor::class)->ejecutar($this->revisor->id));
        $barra = collect($tablero['revisores'])->firstWhere('revisorId', $this->revisor->id);
        $this->assertSame(0, $barra->asignados);
    }

    public function test_el_tablero_y_la_cola_muestran_n_de_n_antes_de_cerrar_el_ultimo_paso(): void
    {
        $pasos = $this->pasos();
        $n = count($pasos);
        $pagina = Livewire::actingAs($this->revisor)->test(RevisionNivel::class, ['escuelaNivel' => $this->nivel->id]);

        foreach (array_slice($pasos, 0, $n - 1) as $clave) {
            $pagina->call('abrir', $clave)->call('aceptar');
        }

        $this->assertSame('en_revision', $this->estadoNivel());
        $fila = app(ListarTablero::class)->ejecutar($this->supervisor->id)['filas'][0];
        $this->assertSame([$n - 1, $n], [$fila->revisadas, $fila->total]);
        $this->assertCount(1, app(ListarColaDelRevisor::class)->ejecutar($this->revisor->id));
    }

    public function test_un_paso_rechazado_deja_con_observaciones_y_ya_no_se_corrige_hasta_el_reenvio(): void
    {
        $pasos = $this->pasos();
        $pagina = Livewire::actingAs($this->revisor)->test(RevisionNivel::class, ['escuelaNivel' => $this->nivel->id]);

        foreach ($pasos as $clave) {
            $pagina->call('abrir', $clave);
            if ($clave === 'responsable') {
                $pagina->set('motivo', 'Falta la identificación')->call('rechazar');
            } else {
                $pagina->call('aceptar');
            }
        }

        $this->assertSame('con_observaciones', $this->estadoNivel());
        $this->assertSame([count($pasos), count($pasos)], $this->avance());

        // Con el solicitante: los botones ya no se muestran y la decisión sigue como estaba hasta que reenvíe.
        $pagina->call('abrir', 'responsable')->assertDontSee('Aceptar paso')->assertDontSee('Rechazar paso');

        $this->assertSame('con_observaciones', $this->estadoNivel());
    }
}
