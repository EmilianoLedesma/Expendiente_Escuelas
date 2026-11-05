<?php

namespace Tests\Feature\Application\Auditoria;

use App\Application\Archivo\ArchivarTramite;
use App\Application\Archivo\RestaurarTramite;
use App\Application\Auditoria\ConsultarHistorialTramite;
use App\Application\Revision\RolesSedeq;
use App\Models\Escuela;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CapturaExpedienteConsistente;
use Tests\TestCase;

class ConsultarHistorialTramiteTest extends TestCase
{
    use CapturaExpedienteConsistente;
    use RefreshDatabase;

    private User $admin;

    private Escuela $escuela;

    private int $nivelId;

    protected function setUp(): void
    {
        parent::setUp();
        (new RolesSeeder)->run();
        $this->admin = User::factory()->create(['name' => 'Ana Admin'])->assignRole(RolesSedeq::ADMIN);
        $this->escuela = $this->crearEscuelaConPlantel();
        $this->registrarResponsable($this->escuela);
        $this->nivelId = (int) DB::table('escuela_niveles')->where('escuela_id', $this->escuela->id)->value('id');
    }

    /** @return list<array{cuando: Carbon, quien: ?string, que: string, nivel: ?string, detalle: ?string}> */
    private function historial(): array
    {
        return app(ConsultarHistorialTramite::class)->ejecutar($this->escuela->id);
    }

    public function test_junta_las_cuatro_fuentes_con_quien_y_motivo(): void
    {
        $revisor = User::factory()->create(['name' => 'Rita Revisora'])->assignRole(RolesSedeq::REVISOR);
        DB::table('historial_estados_expediente')->insert([
            'escuela_nivel_id' => $this->nivelId,
            'estado_id' => DB::table('estados_expediente')->where('clave', 'en_revision')->value('id'),
            'comentario' => 'Enviado por el solicitante', 'usuario_sedeq_id' => $this->admin->id, 'fecha' => now()->subHours(3),
        ]);
        DB::table('bitacora_asignaciones')->insert(['escuela_nivel_id' => $this->nivelId, 'revisor_id' => $revisor->id, 'accion' => 'asignar', 'actor_id' => $this->admin->id, 'created_at' => now()->subHours(2)]);
        DB::table('historial_decisiones_revision')->insert([
            'escuela_nivel_id' => $this->nivelId, 'paso_clave' => 'plantel', 'estado' => 'rechazado',
            'motivo' => 'Falta el número exterior', 'decidido_por' => $revisor->id, 'decidido_at' => now()->subHour(),
        ]);
        app(ArchivarTramite::class)->ejecutar($this->escuela->id, $this->admin->id, 'Duplicado');

        $eventos = $this->historial();

        $this->assertCount(4, $eventos);
        $que = array_column($eventos, 'que');
        $this->assertSame('Archivó el trámite', $que[0]); // más reciente primero
        $this->assertSame('Ana Admin', $eventos[0]['quien']);
        $this->assertSame('Duplicado', $eventos[0]['detalle']);
        $rechazo = collect($eventos)->first(fn ($e) => str_starts_with($e['que'], 'Rechazó'));
        $this->assertSame('Rita Revisora', $rechazo['quien']);
        $this->assertSame('Falta el número exterior', $rechazo['detalle']);
        $this->assertSame('Primaria', $rechazo['nivel']);
        $this->assertContains('Asignó a Rita Revisora', $que);
        $this->assertContains('Estado: En revisión', $que);
    }

    public function test_incluye_restaurar_y_solo_lo_de_ese_tramite(): void
    {
        $otra = $this->crearEscuelaConPlantel();
        app(ArchivarTramite::class)->ejecutar($this->escuela->id, $this->admin->id);
        app(RestaurarTramite::class)->ejecutar($this->admin->id, $this->escuela->id);
        app(ArchivarTramite::class)->ejecutar($otra->id, $this->admin->id);

        $que = array_column($this->historial(), 'que');

        $this->assertSame(['Restauró el trámite', 'Archivó el trámite'], $que);
    }

    public function test_sin_eventos_devuelve_lista_vacia(): void
    {
        $this->assertSame([], $this->historial());
    }
}
