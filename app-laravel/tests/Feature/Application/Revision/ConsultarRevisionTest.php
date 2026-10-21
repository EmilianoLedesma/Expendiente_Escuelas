<?php

namespace Tests\Feature\Application\Revision;

use App\Application\Revision\AsignarTramite;
use App\Application\Revision\ConsultarRevision;
use App\Application\Revision\DecidirPaso;
use App\Application\Revision\PasosRevision;
use App\Models\EscuelaNivel;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ConTramitesEnRevision;
use Tests\TestCase;

class ConsultarRevisionTest extends TestCase
{
    use ConTramitesEnRevision;
    use RefreshDatabase;

    private User $sup;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sup = $this->supervisor();
    }

    private function decidir(User $actor, EscuelaNivel $nivel, string $paso, string $decision = 'aceptado', ?string $motivo = null): void
    {
        app(DecidirPaso::class)->ejecutar($actor->id, $nivel->id, $paso, $decision, $motivo);
    }

    /** @return array<string, mixed> */
    private function consultar(User $actor, EscuelaNivel $nivel): array
    {
        return app(ConsultarRevision::class)->ejecutar($actor->id, $nivel->id);
    }

    public function test_lista_los_pasos_con_estado_pendiente_al_inicio(): void
    {
        $nivel = $this->nivelEnRevision();

        $r = $this->consultar($this->sup, $nivel);

        $this->assertSame($nivel->id, $r['escuelaNivelId']);
        $this->assertSame($nivel->escuela_id, $r['escuelaId']);
        $this->assertSame('en_revision', $r['estado']);
        $this->assertTrue($r['editable']);
        $this->assertSame(0, $r['revisadas']);
        $this->assertSame(10, $r['total']);
        $this->assertSame(PasosRevision::paraNivel('primaria'), array_column($r['pasos'], 'clave'));
        $this->assertSame(PasosRevision::titulo('plantel'), $r['pasos'][0]['titulo']);
        foreach ($r['pasos'] as $paso) {
            $this->assertSame('pendiente', $paso['estado']);
            $this->assertNull($paso['motivo']);
            $this->assertNull($paso['por']);
            $this->assertNull($paso['cuando']);
            $this->assertSame([], $paso['historial']);
            $this->assertSame([], $paso['otrosNiveles']);
        }
    }

    public function test_refleja_decision_actual_motivo_y_autor(): void
    {
        $nivel = $this->nivelEnRevision();
        $revisor = $this->revisor();
        app(AsignarTramite::class)->ejecutar($this->sup->id, $nivel->id, $revisor->id);
        $this->decidir($revisor, $nivel, 'plantel', 'rechazado', 'Falta croquis');

        $r = $this->consultar($revisor, $nivel);

        $this->assertSame(1, $r['revisadas']);
        $paso = $r['pasos'][0];
        $this->assertSame('rechazado', $paso['estado']);
        $this->assertSame('Falta croquis', $paso['motivo']);
        $this->assertSame($revisor->name, $paso['por']);
        $this->assertMatchesRegularExpression('#^\d{2}/\d{2}/\d{4} \d{2}:\d{2}$#', $paso['cuando']);
        $this->assertSame('pendiente', $r['pasos'][1]['estado']);
    }

    public function test_historial_conserva_las_decisiones_reemplazadas_en_orden(): void
    {
        $nivel = $this->nivelEnRevision();
        $this->decidir($this->sup, $nivel, 'plantel', 'rechazado', 'Mal');
        $this->decidir($this->sup, $nivel, 'plantel');

        $paso = $this->consultar($this->sup, $nivel)['pasos'][0];

        $this->assertSame('aceptado', $paso['estado']);
        $this->assertSame(['rechazado', 'aceptado'], array_column($paso['historial'], 'estado'));
        $this->assertSame('Mal', $paso['historial'][0]['motivo']);
        $this->assertNull($paso['historial'][1]['motivo']);
        $this->assertSame($this->sup->name, $paso['historial'][0]['por']);
        $this->assertArrayHasKey('cuando', $paso['historial'][0]);
    }

    public function test_otros_niveles_de_la_misma_escuela_aparecen_solo_como_pista(): void
    {
        $a = $this->nivelEnRevision();
        $b = $this->nivelEn($a->escuela, 'secundaria', 'en_revision');
        $this->decidir($this->sup, $b, 'plantel', 'rechazado', 'Mal');

        $r = $this->consultar($this->sup, $a);

        $this->assertSame('pendiente', $r['pasos'][0]['estado']);
        $this->assertSame(0, $r['revisadas']);
        $this->assertCount(1, $r['pasos'][0]['otrosNiveles']);
        $otro = $r['pasos'][0]['otrosNiveles'][0];
        $this->assertSame($b->nivelEducativo->nombre, $otro['nivel']);
        $this->assertSame('rechazado', $otro['estado']);
        $this->assertSame($this->sup->name, $otro['por']);
        $this->assertArrayHasKey('cuando', $otro);
        $this->assertSame([], $r['pasos'][1]['otrosNiveles']);
    }

    public function test_editable_es_falso_en_un_nivel_aprobado(): void
    {
        $nivel = $this->nivelEnRevision('aprobado');

        $this->assertFalse($this->consultar($this->sup, $nivel)['editable']);
    }

    public function test_revisor_ajeno_recibe_authorization_exception(): void
    {
        $nivel = $this->nivelEnRevision();

        $this->expectException(AuthorizationException::class);
        $this->consultar($this->revisor(), $nivel);
    }
}
