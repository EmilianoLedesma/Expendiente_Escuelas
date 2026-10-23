<?php

namespace Tests\Feature\Application\Tramite;

use App\Application\Tramite\ObservacionesDeEscuela;
use App\Models\EscuelaNivel;
use App\Models\Solicitante;
use App\Models\User;
use Database\Seeders\PasosCapturaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ConTramiteDevuelto;
use Tests\TestCase;

class ObservacionesDeEscuelaTest extends TestCase
{
    use ConTramiteDevuelto;
    use RefreshDatabase;

    /** @return array{0: Solicitante, 1: EscuelaNivel, 2: EscuelaNivel} escuela con primaria y secundaria en revisión */
    private function escuela(): array
    {
        (new PasosCapturaSeeder)->run();
        $dueno = Solicitante::factory()->create();
        $primaria = $this->nivelDe($dueno, 'primaria', 'en_revision');
        $secundaria = $this->nivelEn($primaria->escuela, 'secundaria', 'en_revision');

        return [$dueno, $primaria, $secundaria];
    }

    public function test_ronda_abierta_no_expone_motivos(): void
    {
        [$dueno, $primaria] = $this->escuela();
        // Primaria devuelta pero secundaria sigue en revisión: ronda abierta.
        $this->devolverTramite($primaria->escuela, [$primaria->id => ['inmueble' => 'Falta el croquis']]);

        $this->assertSame([], app(ObservacionesDeEscuela::class)->paraUsuario($primaria->escuela_id, $dueno->user->id));
    }

    public function test_dueno_ve_todos_los_motivos(): void
    {
        [$dueno, $primaria, $secundaria] = $this->escuela();
        $this->devolverTramite($primaria->escuela, [
            $primaria->id => ['inmueble' => 'Falta el croquis', 'plantel' => 'Teléfono inválido'],
            $secundaria->id => ['matricula' => 'Cifras inconsistentes'],
        ]);

        $obs = app(ObservacionesDeEscuela::class)->paraUsuario($primaria->escuela_id, $dueno->user->id);

        $this->assertEqualsCanonicalizing(
            ['inmueble|Falta el croquis|'.$primaria->id, 'plantel|Teléfono inválido|'.$primaria->id, 'matricula|Cifras inconsistentes|'.$secundaria->id],
            array_map(fn ($o) => "{$o['paso']}|{$o['motivo']}|{$o['escuelaNivelId']}", $obs),
        );
        $inmueble = collect($obs)->firstWhere('paso', 'inmueble');
        $this->assertSame('Datos del inmueble', $inmueble['titulo']);
        $this->assertSame('Primaria', $inmueble['nivel']);
    }

    public function test_responsable_ve_solo_los_de_su_nivel(): void
    {
        [, $primaria, $secundaria] = $this->escuela();
        $this->devolverTramite($primaria->escuela, [
            $primaria->id => ['inmueble' => 'Falta el croquis', 'plantel' => 'Teléfono inválido'],
            $secundaria->id => ['matricula' => 'Cifras inconsistentes'],
        ]);
        $responsable = $this->responsableDe($primaria);

        $obs = app(ObservacionesDeEscuela::class)->paraUsuario($primaria->escuela_id, $responsable->id);

        $this->assertSame(['inmueble'], array_column($obs, 'paso'), 'ni el paso general ni el otro nivel');
    }

    public function test_un_extrano_no_ve_nada(): void
    {
        [, $primaria, $secundaria] = $this->escuela();
        $this->devolverTramite($primaria->escuela, [$primaria->id => ['inmueble' => 'x'], $secundaria->id => ['matricula' => 'y']]);

        $this->assertSame([], app(ObservacionesDeEscuela::class)->paraUsuario($primaria->escuela_id, User::factory()->create()->id));
    }
}
