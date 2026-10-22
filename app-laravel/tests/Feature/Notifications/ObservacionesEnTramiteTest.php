<?php

namespace Tests\Feature\Notifications;

use App\Application\Revision\DecidirPaso;
use App\Application\Revision\PasosRevision;
use App\Application\Revision\RondaCerrada;
use App\Models\EscuelaNivel;
use App\Models\ResponsableNivel;
use App\Models\Solicitante;
use App\Models\User;
use App\Notifications\ObservacionesEnTramite;
use Database\Seeders\PasosCapturaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\ConTramiteDevuelto;
use Tests\TestCase;

class ObservacionesEnTramiteTest extends TestCase
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

    /** @return list<array{nivel: ?string, titulo: string, motivo: string}> */
    private function pasosEnviados(User $usuario): array
    {
        $enviadas = Notification::sent($usuario, ObservacionesEnTramite::class);
        $this->assertCount(1, $enviadas, 'un correo por destinatario por ronda');

        return $enviadas->first()->pasos;
    }

    public function test_el_dueno_recibe_todos_los_motivos(): void
    {
        Notification::fake();
        [$dueno, $primaria, $secundaria] = $this->escuela();
        $this->devolverTramite($primaria->escuela, [
            $primaria->id => ['inmueble' => 'Falta el croquis', 'plantel' => 'Teléfono inválido'],
            $secundaria->id => ['matricula' => 'Cifras inconsistentes'],
        ]);

        event(new RondaCerrada($primaria->escuela_id));

        $this->assertEqualsCanonicalizing(
            ['Primaria|Falta el croquis', 'Primaria|Teléfono inválido', 'Secundaria|Cifras inconsistentes'],
            array_map(fn ($p) => "{$p['nivel']}|{$p['motivo']}", $this->pasosEnviados($dueno->user)),
        );
    }

    public function test_el_responsable_recibe_solo_los_de_su_nivel(): void
    {
        Notification::fake();
        [, $primaria, $secundaria] = $this->escuela();
        $this->devolverTramite($primaria->escuela, [
            $primaria->id => ['inmueble' => 'Falta el croquis', 'plantel' => 'Teléfono inválido'],
            $secundaria->id => ['matricula' => 'Cifras inconsistentes'],
        ]);
        $responsable = $this->responsableDe($primaria);

        event(new RondaCerrada($primaria->escuela_id));

        $pasos = $this->pasosEnviados($responsable);
        $this->assertSame(['Falta el croquis'], array_column($pasos, 'motivo'), 'ni el paso general ni el otro nivel');
        $this->assertSame('Primaria', $pasos[0]['nivel']);
    }

    public function test_responsable_sin_pasos_rechazados_no_recibe_correo(): void
    {
        Notification::fake();
        [, $primaria, $secundaria] = $this->escuela();
        // Secundaria sin rechazos propios: su responsable no tiene nada que atender.
        $this->devolverTramite($primaria->escuela, [$primaria->id => ['inmueble' => 'Falta el croquis']], [$secundaria->id => ['matricula']]);
        $sinNada = $this->responsableDe($secundaria);

        event(new RondaCerrada($primaria->escuela_id));

        Notification::assertNotSentTo($sinNada, ObservacionesEnTramite::class);
        Notification::assertCount(1); // solo el dueño
    }

    public function test_no_hay_correo_con_la_ronda_abierta(): void
    {
        Notification::fake();
        [, $primaria] = $this->escuela(); // secundaria sigue en revisión
        $this->devolverTramite($primaria->escuela, [$primaria->id => ['inmueble' => 'Falta el croquis']]);
        $this->responsableDe($primaria);

        event(new RondaCerrada($primaria->escuela_id));

        Notification::assertNothingSent();
    }

    public function test_nueva_ronda_envia_otra_vez(): void
    {
        Notification::fake();
        [$dueno, $primaria, $secundaria] = $this->escuela();
        $this->devolverTramite($primaria->escuela, [$primaria->id => ['inmueble' => 'Primer motivo'], $secundaria->id => ['matricula' => 'x']]);
        event(new RondaCerrada($primaria->escuela_id));

        $this->devolverTramite($primaria->escuela, [$primaria->id => ['inmueble' => 'Segundo motivo']]);
        event(new RondaCerrada($primaria->escuela_id));

        $enviadas = Notification::sent($dueno->user, ObservacionesEnTramite::class);
        $this->assertCount(2, $enviadas);
        $this->assertSame('Segundo motivo', $enviadas->last()->pasos[0]['motivo']);
    }

    public function test_quien_es_dueno_y_responsable_recibe_un_solo_correo(): void
    {
        Notification::fake();
        [$dueno, $primaria, $secundaria] = $this->escuela();
        $this->devolverTramite($primaria->escuela, [$primaria->id => ['inmueble' => 'a'], $secundaria->id => ['matricula' => 'b']]);
        ResponsableNivel::create(['user_id' => $dueno->user_id, 'escuela_nivel_id' => $primaria->id, 'invitado_por_solicitante_id' => $dueno->id]);

        event(new RondaCerrada($primaria->escuela_id));

        $this->assertCount(2, $this->pasosEnviados($dueno->user), 'la vista del dueño, con todo');
    }

    public function test_el_correo_lleva_los_motivos_y_el_enlace_al_resumen(): void
    {
        [$dueno, $primaria] = $this->escuela();
        $notificacion = new ObservacionesEnTramite($primaria->escuela_id, [
            ['nivel' => 'Primaria', 'titulo' => 'Datos del inmueble', 'motivo' => 'Falta el croquis'],
            ['nivel' => null, 'titulo' => 'Datos del plantel', 'motivo' => 'Teléfono inválido'],
        ]);

        $correo = $notificacion->toMail($dueno->user);
        $html = (string) $correo->render();

        $this->assertSame('Tu trámite tiene observaciones', $correo->subject);
        $this->assertStringContainsString('Falta el croquis', $html);
        $this->assertStringContainsString('Teléfono inválido', $html);
        $this->assertStringContainsString('Datos del inmueble', $html);
        $this->assertStringContainsString('Primaria', $html);
        $this->assertStringContainsString(route('tramite.resumen', ['escuela' => $primaria->escuela_id]), $html);
    }

    public function test_una_decision_real_que_cierra_la_ronda_notifica(): void
    {
        Notification::fake();
        (new PasosCapturaSeeder)->run();
        $dueno = Solicitante::factory()->create();
        $nivel = $this->nivelDe($dueno, 'primaria', 'en_revision');
        $sup = $this->supervisor();
        $pasos = PasosRevision::paraNivel('primaria');

        foreach (array_slice($pasos, 1) as $paso) {
            app(DecidirPaso::class)->ejecutar($sup->id, $nivel->id, $paso, 'aceptado');
        }
        Notification::assertNothingSent();
        app(DecidirPaso::class)->ejecutar($sup->id, $nivel->id, $pasos[0], 'rechazado', 'Motivo real');

        $this->assertSame(['Motivo real'], array_column($this->pasosEnviados($dueno->user), 'motivo'));
    }
}
