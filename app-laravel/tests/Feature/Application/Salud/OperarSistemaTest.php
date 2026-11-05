<?php

namespace Tests\Feature\Application\Salud;

use App\Application\Excepciones\PrecondicionIncumplida;
use App\Application\Revision\RolesSedeq;
use App\Application\Salud\OperarSistema;
use App\Models\BitacoraAccion;
use App\Models\User;
use App\Notifications\TramiteCerrado;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class OperarSistemaTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        (new RolesSeeder)->run();
        config(['queue.default' => 'database', 'mail.default' => 'array']);
        $this->admin = User::factory()->create()->assignRole(RolesSedeq::ADMIN);
    }

    private function fallido(string $uuid): void
    {
        DB::table('failed_jobs')->insert(['uuid' => $uuid, 'connection' => 'database', 'queue' => 'default', 'payload' => '{}', 'exception' => 'x']);
    }

    private function accion(string $nombre): BitacoraAccion
    {
        return BitacoraAccion::where('accion', $nombre)->firstOrFail();
    }

    public function test_procesar_cola_envia_los_pendientes_y_deja_traza(): void
    {
        $this->admin->notify(new TramiteCerrado('Colegio X'));
        $this->assertSame(1, DB::table('jobs')->count());

        $n = app(OperarSistema::class)->procesarCola($this->admin->id);

        $this->assertSame(1, $n);
        $this->assertSame(0, DB::table('jobs')->count());
        $fila = $this->accion('procesar_cola');
        $this->assertSame('sistema', $fila->objeto_tipo);
        $this->assertSame(1, $fila->detalle['trabajos']);
    }

    public function test_reintentar_fallidos_los_vuelve_a_encolar(): void
    {
        $this->fallido('u-1');
        $this->fallido('u-2');

        $n = app(OperarSistema::class)->reintentarFallidos($this->admin->id);

        $this->assertSame(2, $n);
        $this->assertSame(0, DB::table('failed_jobs')->count());
        $this->assertSame(2, DB::table('jobs')->count());
        $this->assertSame(2, $this->accion('reintentar_fallidos')->detalle['trabajos']);
    }

    public function test_descartar_fallidos_los_borra_y_deja_traza(): void
    {
        $this->fallido('u-1');

        $n = app(OperarSistema::class)->descartarFallidos($this->admin->id);

        $this->assertSame(1, $n);
        $this->assertSame(0, DB::table('failed_jobs')->count());
        $this->assertSame(1, $this->accion('descartar_fallidos')->detalle['trabajos']);
    }

    public function test_sin_fallidos_no_hace_nada_ni_deja_traza(): void
    {
        $this->assertSame(0, app(OperarSistema::class)->reintentarFallidos($this->admin->id));
        $this->assertSame(0, app(OperarSistema::class)->descartarFallidos($this->admin->id));
        $this->assertSame(0, BitacoraAccion::count());
    }

    public function test_correo_de_prueba_va_al_correo_del_actor(): void
    {
        app(OperarSistema::class)->enviarCorreoDePrueba($this->admin->id);

        $enviados = Mail::mailer()->getSymfonyTransport()->messages();
        $this->assertCount(1, $enviados);
        $this->assertSame($this->admin->email, $enviados[0]->getEnvelope()->getRecipients()[0]->getAddress());
        $this->assertSame($this->admin->id, (int) $this->accion('correo_prueba')->actor_id);
        $this->assertArrayNotHasKey('correo', $this->accion('correo_prueba')->detalle);
    }

    public function test_solo_admin(): void
    {
        $revisor = User::factory()->create()->assignRole(RolesSedeq::REVISOR);

        $this->expectException(PrecondicionIncumplida::class);
        app(OperarSistema::class)->procesarCola($revisor->id);
    }
}
