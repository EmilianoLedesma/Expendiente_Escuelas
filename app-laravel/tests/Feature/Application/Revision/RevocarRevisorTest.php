<?php

namespace Tests\Feature\Application\Revision;

use App\Application\Excepciones\PrecondicionIncumplida;
use App\Application\Revision\AsignarTramite;
use App\Application\Revision\RevocarRevisor;
use App\Models\Revisor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\ConTramitesEnRevision;
use Tests\TestCase;

class RevocarRevisorTest extends TestCase
{
    use ConTramitesEnRevision;
    use RefreshDatabase;

    public function test_revoca_bloqueando_primero_al_revisor_y_desasigna_todo(): void
    {
        $sup = $this->supervisor();
        $rev = $this->revisor();
        $nivel = $this->nivelEnRevision();
        app(AsignarTramite::class)->ejecutar($sup->id, $nivel->id, $rev->id);

        DB::enableQueryLog();
        app(RevocarRevisor::class)->ejecutar($sup->id, $rev->id);
        $log = DB::getQueryLog();
        DB::disableQueryLog();

        $primero = collect($log)->first(fn ($q) => str_contains($q['query'], 'for update'));
        $this->assertStringContainsString('"users"', $primero['query']);
        $this->assertContains($rev->id, $primero['bindings']);
        $this->assertFalse($rev->fresh()->hasRole('revisor'));
        $this->assertNotNull(Revisor::findOrFail($rev->id)->revocado_at);
        $this->assertSame(0, DB::table('asignaciones_revision')->count());
        $this->assertSame(1, DB::table('bitacora_asignaciones')->where('accion', 'desasignar_revocacion')->count());
        $this->assertTrue(User::whereKey($rev->id)->exists());

        // Un revocado ya no puede operar como actor ni recibir trámites como destino.
        try {
            app(AsignarTramite::class)->ejecutar($rev->id, $nivel->id, $rev->id);
            $this->fail('Se esperaba PrecondicionIncumplida como actor.');
        } catch (PrecondicionIncumplida $e) {
            $this->assertSame('rol', $e->etapaFaltante);
        }
        try {
            app(AsignarTramite::class)->ejecutar($sup->id, $nivel->id, $rev->id);
            $this->fail('Se esperaba PrecondicionIncumplida como destino.');
        } catch (PrecondicionIncumplida $e) {
            $this->assertSame('revisor', $e->etapaFaltante);
        }
    }

    public function test_solo_supervisor_y_solo_revisores_activos(): void
    {
        $sup = $this->supervisor();
        $rev = $this->revisor();
        $otro = $this->revisor();

        try {
            app(RevocarRevisor::class)->ejecutar($rev->id, $otro->id);
            $this->fail('Se esperaba PrecondicionIncumplida.');
        } catch (PrecondicionIncumplida) {
            $this->assertTrue($otro->fresh()->hasRole('revisor'));
        }

        $this->expectException(PrecondicionIncumplida::class);
        app(RevocarRevisor::class)->ejecutar($sup->id, $sup->id);
    }
}
