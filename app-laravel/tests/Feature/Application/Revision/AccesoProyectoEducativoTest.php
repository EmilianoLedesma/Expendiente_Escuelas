<?php

namespace Tests\Feature\Application\Revision;

use App\Application\Revision\AccesoSedeq;
use App\Application\Revision\AsignarTramite;
use App\Application\Revision\RolesSedeq;
use App\Models\EscuelaNivel;
use App\Models\Solicitante;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ConTramitesEnRevision;
use Tests\TestCase;

/** Quién puede leer el proyecto educativo de un nivel: el evaluador y el supervisor; el revisor nunca. */
class AccesoProyectoEducativoTest extends TestCase
{
    use ConTramitesEnRevision;
    use RefreshDatabase;

    private function evaluador(): User
    {
        (new RolesSeeder)->run();

        return User::factory()->create()->assignRole(RolesSedeq::EVALUADOR_PROYECTO);
    }

    private function media(string $estado = 'en_revision'): EscuelaNivel
    {
        return $this->nivelDe(Solicitante::factory()->create(), 'media_superior', $estado);
    }

    private function acceso(): AccesoSedeq
    {
        return app(AccesoSedeq::class);
    }

    public function test_evaluador_y_supervisor_ven_el_proyecto_de_un_nivel_de_media_superior_fuera_de_captura(): void
    {
        $nivel = $this->media();

        $this->assertTrue($this->acceso()->puedeVerProyecto($this->evaluador(), $nivel->id));
        $this->assertTrue($this->acceso()->puedeVerProyecto($this->supervisor(), $nivel->id));
    }

    public function test_tambien_lo_ven_con_observaciones_y_aprobado(): void
    {
        $evaluador = $this->evaluador();

        foreach (['con_observaciones', 'aprobado'] as $estado) {
            $this->assertTrue($this->acceso()->puedeVerProyecto($evaluador, $this->media($estado)->id), $estado);
        }
    }

    public function test_el_revisor_nunca_lo_ve_ni_asignado_al_nivel(): void
    {
        $nivel = $this->media();
        $revisor = $this->revisor();
        app(AsignarTramite::class)->ejecutar($this->supervisor()->id, $nivel->id, $revisor->id);

        $this->assertTrue($this->acceso()->puedeVerNivel($revisor, $nivel->id));
        $this->assertFalse($this->acceso()->puedeVerProyecto($revisor, $nivel->id));
    }

    public function test_nadie_ve_un_proyecto_en_captura(): void
    {
        $nivel = $this->media('en_captura');

        $this->assertFalse($this->acceso()->puedeVerProyecto($this->evaluador(), $nivel->id));
        $this->assertFalse($this->acceso()->puedeVerProyecto($this->supervisor(), $nivel->id));
    }

    public function test_otros_niveles_no_tienen_proyecto_educativo(): void
    {
        $primaria = $this->nivelEnRevision();

        $this->assertFalse($this->acceso()->puedeVerProyecto($this->evaluador(), $primaria->id));
        $this->assertFalse($this->acceso()->puedeVerProyecto($this->supervisor(), $primaria->id));
    }

    public function test_admin_solicitante_y_sin_rol_no_lo_ven(): void
    {
        $nivel = $this->media();
        (new RolesSeeder)->run();
        $admin = User::factory()->create()->assignRole(RolesSedeq::ADMIN);

        foreach ([$admin, $nivel->escuela->solicitante->user, User::factory()->create()] as $usuario) {
            $this->assertFalse($this->acceso()->puedeVerProyecto($usuario, $nivel->id));
        }
    }

    public function test_un_nivel_inexistente_no_se_ve(): void
    {
        $this->assertFalse($this->acceso()->puedeVerProyecto($this->evaluador(), 999999));
    }

    public function test_la_cola_automatica_trae_los_niveles_de_media_superior_fuera_de_captura(): void
    {
        $evaluador = $this->evaluador();
        $enRevision = $this->media();
        $devuelto = $this->media('con_observaciones');
        $this->media('en_captura');
        $this->nivelEnRevision();

        $this->assertEqualsCanonicalizing([$enRevision->id, $devuelto->id], $this->acceso()->nivelesConProyecto($evaluador));
        $this->assertEqualsCanonicalizing([$enRevision->id, $devuelto->id], $this->acceso()->nivelesConProyecto($this->supervisor()));
    }

    public function test_la_cola_automatica_esta_vacia_para_los_demas(): void
    {
        $this->media();

        $this->assertSame([], $this->acceso()->nivelesConProyecto($this->revisor()));
        $this->assertSame([], $this->acceso()->nivelesConProyecto(User::factory()->create()));
    }

    public function test_el_evaluador_sigue_sin_ver_los_pasos_del_revisor(): void
    {
        $nivel = $this->media();

        $this->assertFalse($this->acceso()->puedeVerNivel($this->evaluador(), $nivel->id));
        $this->assertFalse($this->acceso()->puedeVerEscuela($this->evaluador(), $nivel->escuela_id));
    }
}
