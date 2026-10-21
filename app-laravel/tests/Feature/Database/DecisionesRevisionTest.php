<?php

namespace Tests\Feature\Database;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\ConTramitesEnRevision;
use Tests\TestCase;

class DecisionesRevisionTest extends TestCase
{
    use ConTramitesEnRevision;
    use RefreshDatabase;

    /** @return array<string, mixed> */
    private function fila(int $nivelId, int $userId, array $extra = []): array
    {
        return array_merge([
            'escuela_nivel_id' => $nivelId,
            'paso_clave' => 'plantel',
            'estado' => 'aceptado',
            'motivo' => null,
            'decidido_por' => $userId,
        ], $extra);
    }

    public function test_rechazo_sin_motivo_viola_el_check(): void
    {
        $nivel = $this->nivelEnRevision();
        $rev = $this->revisor();

        foreach (['decisiones_revision', 'historial_decisiones_revision'] as $tabla) {
            try {
                DB::transaction(fn () => DB::table($tabla)->insert($this->fila($nivel->id, $rev->id, ['estado' => 'rechazado', 'motivo' => '  '])));
                $this->fail("Se aceptó un rechazo sin motivo en {$tabla}.");
            } catch (QueryException $e) {
                $this->assertStringContainsString("{$tabla}_check", $e->getMessage());
            }
        }
    }

    public function test_paso_desconocido_viola_el_check(): void
    {
        $nivel = $this->nivelEnRevision();
        $rev = $this->revisor();

        try {
            DB::table('decisiones_revision')->insert($this->fila($nivel->id, $rev->id, ['paso_clave' => 'inexistente']));
            $this->fail('Se aceptó un paso desconocido.');
        } catch (QueryException $e) {
            $this->assertStringContainsString('decisiones_revision_paso_clave_check', $e->getMessage());
        }
    }

    public function test_aceptado_no_requiere_motivo(): void
    {
        $nivel = $this->nivelEnRevision();
        $rev = $this->revisor();

        DB::table('decisiones_revision')->insert($this->fila($nivel->id, $rev->id));
        DB::table('historial_decisiones_revision')->insert($this->fila($nivel->id, $rev->id));

        $this->assertSame(1, DB::table('decisiones_revision')->count());
        $this->assertSame(1, DB::table('historial_decisiones_revision')->count());
    }

    public function test_borrar_el_nivel_borra_sus_decisiones(): void
    {
        $nivel = $this->nivelEnRevision();
        $rev = $this->revisor();
        DB::table('decisiones_revision')->insert($this->fila($nivel->id, $rev->id));
        DB::table('historial_decisiones_revision')->insert($this->fila($nivel->id, $rev->id));

        $nivel->delete();

        $this->assertSame(0, DB::table('decisiones_revision')->count());
        $this->assertSame(0, DB::table('historial_decisiones_revision')->count());
    }
}
