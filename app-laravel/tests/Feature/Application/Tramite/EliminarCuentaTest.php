<?php

namespace Tests\Feature\Application\Tramite;

use App\Application\Excepciones\PrecondicionIncumplida;
use App\Application\Revision\RolesSedeq;
use App\Application\Tramite\EliminarCuenta;
use App\Models\Escuela;
use App\Models\EscuelaNivel;
use App\Models\Plantel;
use App\Models\ResponsableNivel;
use App\Models\Revisor;
use App\Models\Solicitante;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\Concerns\CapturaExpedienteConsistente;
use Tests\TestCase;

class EliminarCuentaTest extends TestCase
{
    use CapturaExpedienteConsistente;
    use RefreshDatabase;

    private function dueno(Escuela $escuela): User
    {
        return Solicitante::findOrFail($escuela->solicitante_id)->user;
    }

    private function enviar(EscuelaNivel $nivel): void
    {
        $nivel->update(['estado_id' => DB::table('estados_expediente')->where('clave', 'en_revision')->value('id')]);
    }

    private function delegado(EscuelaNivel $nivel): User
    {
        $delegado = User::factory()->create();
        $delegado->assignRole(Role::findOrCreate('responsable_nivel'));
        ResponsableNivel::create(['user_id' => $delegado->id, 'escuela_nivel_id' => $nivel->id, 'invitado_por_solicitante_id' => $nivel->escuela->solicitante_id]);

        return $delegado;
    }

    public function test_borra_la_cuenta_del_solicitante_con_sus_tramites_en_captura(): void
    {
        $escuela = $this->crearEscuelaConPlantel();
        $this->completarTramite($escuela);
        $usuario = $this->dueno($escuela);

        app(EliminarCuenta::class)->ejecutar($usuario->id);

        $this->assertNull(User::find($usuario->id));
        $this->assertSame(0, DB::table('solicitantes')->count());
        $this->assertSame(0, DB::table('escuelas')->count());
        $this->assertNotNull(Plantel::find($escuela->plantel_id));
    }

    public function test_borra_la_cuenta_del_solicitante_sin_tramites(): void
    {
        $solicitante = Solicitante::factory()->create();

        app(EliminarCuenta::class)->ejecutar($solicitante->user_id);

        $this->assertNull(User::find($solicitante->user_id));
        $this->assertNull(Solicitante::find($solicitante->id));
    }

    public function test_rechaza_si_algun_tramite_ya_se_envio_y_no_borra_nada(): void
    {
        $escuela = $this->crearEscuelaConPlantel();
        $this->enviar($this->completarTramite($escuela));
        $usuario = $this->dueno($escuela);

        try {
            app(EliminarCuenta::class)->ejecutar($usuario->id);
            $this->fail('Debió rechazar la eliminación.');
        } catch (PrecondicionIncumplida $e) {
            $this->assertStringContainsString('enviaste', $e->getMessage());
        }

        $this->assertNotNull(User::find($usuario->id));
        $this->assertNotNull(Escuela::find($escuela->id));
    }

    public function test_si_solo_uno_de_varios_tramites_se_envio_no_borra_ninguno(): void
    {
        $escuela = $this->crearEscuelaConPlantel();
        $nivel = $this->completarTramite($escuela);
        $usuario = $this->dueno($escuela);
        $otra = Escuela::create(['plantel_id' => $escuela->plantel_id, 'solicitante_id' => $escuela->solicitante_id]);
        $copia = $nivel->replicate();
        $copia->escuela_id = $otra->id;
        $copia->save();
        $this->enviar($copia);

        try {
            app(EliminarCuenta::class)->ejecutar($usuario->id);
            $this->fail('Debió rechazar la eliminación.');
        } catch (PrecondicionIncumplida) {
            $this->assertSame(2, Escuela::count());
            $this->assertNotNull(User::find($usuario->id));
        }
    }

    public function test_el_responsable_por_nivel_pierde_su_cuenta_y_su_acceso_pero_no_lo_capturado(): void
    {
        $nivel = $this->completarTramite($this->crearEscuelaConPlantel());
        $delegado = $this->delegado($nivel);

        app(EliminarCuenta::class)->ejecutar($delegado->id);

        $this->assertNull(User::find($delegado->id));
        $this->assertSame(0, ResponsableNivel::count());
        $this->assertNotNull(EscuelaNivel::find($nivel->id));
    }

    public function test_el_responsable_por_nivel_puede_borrar_su_cuenta_aunque_el_tramite_se_haya_enviado(): void
    {
        $nivel = $this->completarTramite($this->crearEscuelaConPlantel());
        $delegado = $this->delegado($nivel);
        $this->enviar($nivel);

        app(EliminarCuenta::class)->ejecutar($delegado->id);

        $this->assertNull(User::find($delegado->id));
    }

    /** @return array<string, array{string}> */
    public static function rolesSedeq(): array
    {
        return array_combine(RolesSedeq::TODOS, array_map(fn ($r) => [$r], RolesSedeq::TODOS));
    }

    #[DataProvider('rolesSedeq')]
    public function test_no_borra_cuentas_de_sedeq(string $rol): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::findOrCreate($rol));

        try {
            app(EliminarCuenta::class)->ejecutar($admin->id);
            $this->fail('Debió rechazar la eliminación.');
        } catch (PrecondicionIncumplida) {
            $this->assertNotNull(User::find($admin->id));
        }
    }

    public function test_no_borra_a_un_revisor_revocado(): void
    {
        $usuario = User::factory()->create();
        Revisor::create(['user_id' => $usuario->id, 'revocado_at' => now()]);

        try {
            app(EliminarCuenta::class)->ejecutar($usuario->id);
            $this->fail('Debió rechazar la eliminación.');
        } catch (PrecondicionIncumplida) {
            $this->assertNotNull(User::find($usuario->id));
        }
    }
}
