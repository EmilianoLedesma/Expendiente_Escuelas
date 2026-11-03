<?php

namespace Tests\Feature\Archivo;

use App\Application\Archivo\RegistrarAccion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\ConTramitesEnRevision;
use Tests\TestCase;

class RegistrarAccionTest extends TestCase
{
    use ConTramitesEnRevision;
    use RefreshDatabase;

    public function test_escribe_una_fila_con_los_campos_dados(): void
    {
        app(RegistrarAccion::class)->ejecutar(7, RegistrarAccion::ARCHIVAR_TRAMITE, 'escuela', 42, 'duplicado', ['lote' => 'x', 'n' => 3]);

        $fila = DB::table('bitacora_acciones')->first();
        $this->assertSame(1, DB::table('bitacora_acciones')->count());
        $this->assertSame(7, (int) $fila->actor_id);
        $this->assertSame('archivar_tramite', $fila->accion);
        $this->assertSame('escuela', $fila->objeto_tipo);
        $this->assertSame(42, (int) $fila->objeto_id);
        $this->assertSame('duplicado', $fila->motivo);
        $this->assertEqualsCanonicalizing(['lote' => 'x', 'n' => 3], json_decode($fila->detalle, true));
    }

    public function test_constantes(): void
    {
        $this->assertSame('purgar_cuenta', RegistrarAccion::PURGAR_CUENTA);
        $this->assertSame('restaurar_tramite', RegistrarAccion::RESTAURAR_TRAMITE);
    }

    public function test_bitacora_asignaciones_acepta_desasignar_archivo(): void
    {
        $sup = $this->supervisor();
        $nivel = $this->nivelEnRevision();

        DB::table('bitacora_asignaciones')->insert([
            'escuela_nivel_id' => $nivel->id, 'accion' => 'desasignar_archivo', 'actor_id' => $sup->id,
        ]);

        $this->assertSame(1, DB::table('bitacora_asignaciones')->where('accion', 'desasignar_archivo')->count());
    }
}
