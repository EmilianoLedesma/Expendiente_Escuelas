<?php

namespace Tests\Feature\Archivo;

use App\Models\Escuela;
use App\Models\EscuelaNivel;
use App\Models\Scopes\EscuelaNoArchivada;
use App\Models\Scopes\NivelDeEscuelaNoArchivada;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\ConTramitesEnRevision;
use Tests\TestCase;

class ScopesArchivoTest extends TestCase
{
    use ConTramitesEnRevision;
    use RefreshDatabase;

    public function test_escuela_archivada_se_oculta_salvo_sin_scope(): void
    {
        $visible = $this->nivelEnRevision();
        $archivado = $this->nivelEnRevision();
        $escuelaId = $archivado->escuela_id;
        DB::table('escuelas')->where('id', $escuelaId)->update(['archivado_at' => now()]);

        $this->assertNull(Escuela::find($escuelaId));
        $this->assertSame([$visible->escuela_id], Escuela::all()->pluck('id')->all());
        $this->assertNotNull(Escuela::withoutGlobalScope(EscuelaNoArchivada::class)->find($escuelaId));
    }

    public function test_niveles_de_escuela_archivada_se_ocultan(): void
    {
        $visible = $this->nivelEnRevision();
        $archivado = $this->nivelEnRevision();
        DB::table('escuelas')->where('id', $archivado->escuela_id)->update(['archivado_at' => now()]);

        $this->assertNull(EscuelaNivel::find($archivado->id));
        $this->assertSame([$visible->id], EscuelaNivel::all()->pluck('id')->all());
        $this->assertNotNull(EscuelaNivel::withoutGlobalScope(NivelDeEscuelaNoArchivada::class)->find($archivado->id));
    }

    public function test_route_model_binding_de_escuela_archivada_es_null(): void
    {
        $nivel = $this->nivelEnRevision();
        DB::table('escuelas')->where('id', $nivel->escuela_id)->update(['archivado_at' => now()]);

        $this->assertNull((new Escuela)->resolveRouteBinding($nivel->escuela_id));
        $this->assertNull((new EscuelaNivel)->resolveRouteBinding($nivel->id));
    }
}
