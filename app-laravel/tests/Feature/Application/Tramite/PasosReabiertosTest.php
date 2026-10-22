<?php

namespace Tests\Feature\Application\Tramite;

use App\Application\Tramite\PasosReabiertos;
use App\Models\Escuela;
use App\Models\EscuelaNivel;
use App\Models\Solicitante;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ConTramiteDevuelto;
use Tests\TestCase;

class PasosReabiertosTest extends TestCase
{
    use ConTramiteDevuelto;
    use RefreshDatabase;

    /** @return array{0: Escuela, 1: EscuelaNivel, 2: EscuelaNivel} escuela con primaria y secundaria en revisión */
    private function escuelaConDosNiveles(): array
    {
        $primaria = $this->nivelDe(Solicitante::factory()->create(), 'primaria', 'en_revision');
        $secundaria = $this->nivelEn($primaria->escuela, 'secundaria', 'en_revision');

        return [$primaria->escuela, $primaria, $secundaria];
    }

    private function sut(): PasosReabiertos
    {
        return new PasosReabiertos;
    }

    public function test_ronda_cerrada_solo_sin_niveles_en_revision(): void
    {
        [$escuela, $primaria, $secundaria] = $this->escuelaConDosNiveles();
        $this->assertFalse($this->sut()->rondaCerrada($escuela->id));

        $this->devolverTramite($escuela, [$primaria->id => ['inmueble' => 'x']]);
        $this->assertFalse($this->sut()->rondaCerrada($escuela->id), 'secundaria sigue en revisión');

        $this->devolverTramite($escuela, [$secundaria->id => ['matricula' => 'x']]);
        $this->assertTrue($this->sut()->rondaCerrada($escuela->id));
    }

    public function test_un_paso_aceptado_no_esta_reabierto(): void
    {
        [$escuela, $primaria, $secundaria] = $this->escuelaConDosNiveles();
        $this->devolverTramite($escuela, [$primaria->id => ['inmueble' => 'x']], [$primaria->id => ['matricula', 'plantel'], $secundaria->id => ['inmueble']]);

        $this->assertFalse($this->sut()->estaReabierto($escuela->id, 'matricula', $primaria->id));
        $this->assertFalse($this->sut()->estaReabierto($escuela->id, 'plantel'));
        $this->assertTrue($this->sut()->estaReabierto($escuela->id, 'inmueble', $primaria->id));
    }

    public function test_paso_del_nivel_se_reabre_solo_en_ese_nivel(): void
    {
        [$escuela, $primaria, $secundaria] = $this->escuelaConDosNiveles();
        $this->devolverTramite($escuela, [$primaria->id => ['inmueble' => 'x']], [$secundaria->id => ['inmueble']]);

        $this->assertTrue($this->sut()->estaReabierto($escuela->id, 'inmueble', $primaria->id));
        $this->assertFalse($this->sut()->estaReabierto($escuela->id, 'inmueble', $secundaria->id));
        $this->assertSame([['paso' => 'inmueble', 'escuelaNivelId' => $primaria->id]], $this->sut()->reabiertos($escuela->id));
    }

    public function test_paso_general_se_reabre_si_cualquier_nivel_lo_rechazo(): void
    {
        [$escuela, $primaria, $secundaria] = $this->escuelaConDosNiveles();
        $this->devolverTramite($escuela, [$secundaria->id => ['plantel' => 'x']], [$primaria->id => ['plantel']]);

        $this->assertTrue($this->sut()->estaReabierto($escuela->id, 'plantel'));
        $this->assertTrue($this->sut()->estaReabierto($escuela->id, 'plantel', $primaria->id), 'el nivel se ignora en pasos generales');
        $this->assertSame([['paso' => 'plantel', 'escuelaNivelId' => $secundaria->id]], $this->sut()->reabiertos($escuela->id));
    }

    public function test_ronda_abierta_no_reabre_nada_aunque_haya_rechazos(): void
    {
        [$escuela, $primaria] = $this->escuelaConDosNiveles();
        $this->devolverTramite($escuela, [$primaria->id => ['inmueble' => 'x', 'plantel' => 'y']]);

        $this->assertFalse($this->sut()->rondaCerrada($escuela->id));
        $this->assertFalse($this->sut()->estaReabierto($escuela->id, 'inmueble', $primaria->id));
        $this->assertFalse($this->sut()->estaReabierto($escuela->id, 'plantel'));
        $this->assertSame([], $this->sut()->reabiertos($escuela->id));
    }

    public function test_escuela_sin_niveles_devueltos_no_reabre_nada(): void
    {
        $nivel = $this->nivelDe(Solicitante::factory()->create(), 'primaria', 'en_captura');

        $this->assertTrue($this->sut()->rondaCerrada($nivel->escuela_id));
        $this->assertFalse($this->sut()->estaReabierto($nivel->escuela_id, 'inmueble', $nivel->id));
        $this->assertSame([], $this->sut()->reabiertos($nivel->escuela_id));
    }
}
