<?php

namespace Tests\Feature\Auditoria;

use App\Application\EscuelaNiveles\MarcarPasoCompletado;
use App\Application\Preregistro\DTO\DatosPreregistro;
use App\Application\Preregistro\DTO\ResultadoPreregistro;
use App\Application\Preregistro\IniciarTramiteNuevo;
use App\Livewire\Tramite\Paso3\InfraestructuraNivel;
use App\Models\EscuelaNivel;
use App\Models\InstalacionEspacio;
use App\Models\NivelEducativo;
use App\Models\Solicitante;
use App\Models\TipoEspacio;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Concerns\CompletaPaso2;
use Tests\TestCase;

/**
 * Regression tests from the 2026-09-23 audit. Each one FAILS on master 776ffee
 * (red for the right reason) and must pass once its workstream lands.
 */
class AuditoriaIntegridadTest extends TestCase
{
    use CompletaPaso2;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    private function nuevoTramite(Solicitante $s): ResultadoPreregistro
    {
        return app(IniciarTramiteNuevo::class)->ejecutar(new DatosPreregistro(
            bifurcacion: 'nuevo',
            calle: 'Calle',
            colonia: 'Col',
            municipio: 'Querétaro',
            codigoPostal: '76000'
        ), $s->id);
    }

    // WS-2.2 — Documentos before responsable exists must not 404.
    public function test_documentos_antes_de_responsable_redirige_a_paso2(): void
    {
        $s = Solicitante::factory()->create();
        $res = $this->nuevoTramite($s);
        $this->actingAs($s->user);

        $this->get(route('tramite.paso2-documentos', ['escuela' => $res->escuelaId]))
            ->assertRedirect(route('tramite.paso2', ['escuela' => $res->escuelaId]));
    }

    // WS-2.1 — a space declared with superficie but no cantidad must not be silently discarded.
    public function test_espacio_con_superficie_sin_cantidad_se_guarda(): void
    {
        $s = Solicitante::factory()->create();
        $res = $this->nuevoTramite($s);
        // WS-1 gates: Paso 2 complete and sub-paso 1 done before sub-paso 2 is reachable.
        $this->completarPaso2($res->escuelaId);
        $en = EscuelaNivel::create([
            'escuela_id' => $res->escuelaId,
            'nivel_educativo_id' => NivelEducativo::where('clave', 'primaria')->value('id'),
            'estado_id' => DB::table('estados_expediente')->where('clave', 'en_captura')->value('id'),
            'tipo_tramite' => 'alta_nueva',
        ]);
        (new MarcarPasoCompletado)->ejecutar($en->id, 'inmueble');
        $this->actingAs($s->user);
        $verdes = TipoEspacio::where('clave', 'areas_verdes')->value('id');

        Livewire::test(InfraestructuraNivel::class, ['escuelaNivel' => $en])
            ->set("espacios.$verdes.superficieM2", '120')
            ->set('numeroAulas', 6)
            ->call('guardar');

        $fila = InstalacionEspacio::where('plantel_id', $res->plantelId)->where('tipo_espacio_id', $verdes)->first();
        $this->assertNotNull($fila, 'La superficie declarada se descartó en silencio.');
        $this->assertEquals(120, (float) $fila->superficie_m2);
    }
}
