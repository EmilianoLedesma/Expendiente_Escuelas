<?php

namespace Tests\Feature\Tramite;

use App\Application\Documentos\DTO\DatosDocumento;
use App\Application\Documentos\RegistrarDocumento;
use App\Application\Documentos\ValidarVigenciaDocumentos;
use App\Application\ResponsableLegal\DTO\DatosResponsableLegal;
use App\Application\ResponsableLegal\RegistrarResponsableLegal;
use App\Application\Tramite\EstadoPaso2;
use App\Application\Tramite\ReenviarCorrecciones;
use App\Livewire\Tramite\Paso3\PlanEstudios;
use App\Models\Escuela;
use App\Models\EscuelaNivel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Concerns\CapturaExpedienteConsistente;
use Tests\Concerns\ConTramiteDevuelto;
use Tests\TestCase;

/**
 * Durante una devolución, el Paso 2 que precede a los pasos del nivel se considera satisfecho como al envío (una
 * vigencia que vence o un cambio de tipo de persona no bloquea los pasos reabiertos ni el reenvío), salvo que el
 * paso `documentos` se haya reabierto: entonces se exige de verdad.
 */
class DevolucionPaso2CongeladoTest extends TestCase
{
    use CapturaExpedienteConsistente;
    use ConTramiteDevuelto;
    use RefreshDatabase;

    /** @param array<string, string> $rechazos */
    private function devuelto(array $rechazos): array
    {
        $escuela = $this->crearEscuelaConPlantel();
        $nivel = $this->completarTramite($escuela);
        $this->devolverTramite($escuela, [$nivel->id => $rechazos]);
        $this->actingAs($escuela->solicitante->user);

        return [$escuela, $nivel];
    }

    /** El Visto Bueno municipal vence el 31 de diciembre del año del trámite. */
    private function vencerVigencia(Escuela $escuela): void
    {
        $this->travelTo(now()->addYear()->startOfYear()->addMonth());
        $this->assertNotSame([], app(ValidarVigenciaDocumentos::class)->ejecutar($escuela->id), 'El fixture debe tener una vigencia vencida.');
    }

    private function corregirPlan(EscuelaNivel $nivel): void
    {
        Livewire::test(PlanEstudios::class, ['escuelaNivel' => $nivel])
            ->assertNoRedirect()
            ->set('modalidad', 'escolarizada')->set('planEstudiosReferencia', 'Plan 2022')
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertRedirect(route('tramite.resumen', ['escuela' => $nivel->escuela_id]));
        $this->assertSame('Plan 2022', $nivel->fresh()->plan_estudios_referencia);
    }

    private function enRevision(EscuelaNivel $nivel): bool
    {
        return DB::table('estados_expediente')->where('id', $nivel->fresh()->estado_id)->value('clave') === 'en_revision';
    }

    public function test_vigencia_vencida_no_bloquea_un_paso_del_nivel_reabierto_ni_el_reenvio(): void
    {
        [$escuela, $nivel] = $this->devuelto(['plan_estudios' => 'Falta la referencia']);
        $this->vencerVigencia($escuela);

        $this->assertNull(app(EstadoPaso2::class)->etapaFaltante($escuela->id));
        $this->corregirPlan($nivel);
        app(ReenviarCorrecciones::class)->ejecutar($escuela->id, (int) $escuela->solicitante->user->id);

        $this->assertTrue($this->enRevision($nivel));
    }

    public function test_cambiar_el_responsable_descarta_el_formato_sin_bloquear_el_paso2_y_se_reenvia_tras_recargarlo(): void
    {
        [$escuela, $nivel] = $this->devuelto(['responsable' => 'Falta el gestor', 'plan_estudios' => 'Falta la referencia']);

        // Otro nombre del titular: cambia lo que imprime el Formato (RegistrarResponsableLegal::huellaFormato), que se
        // descarta. El tipo de persona solo cuenta como moral/no moral en la huella; por sí solo no lo descartaría.
        (new RegistrarResponsableLegal)->ejecutar($escuela->id, new DatosResponsableLegal(
            tipoPersona: 'fisica_con_gestor', nombre: 'Juan Pérez Gómez Ruiz', rfc: self::RFC_TITULAR, curp: self::CURP_TITULAR,
            gestorNombre: 'Carlos Gómez Mora', gestorCurp: self::CURP_GESTOR,
        ));

        $this->assertNull(app(EstadoPaso2::class)->etapaFaltante($escuela->id));
        $this->assertFalse(DB::table('documentos_escuela_nivel')->join('tipos_documentos', 'tipos_documentos.id', '=', 'documentos_escuela_nivel.tipo_documento_id')
            ->where('escuela_nivel_id', $nivel->id)->where('tipos_documentos.clave', 'formato_solicitud')->exists(), 'El Formato debe haberse descartado.');
        // Sin Formato el Paso 2.4 del nivel está incompleto: el Paso 3 manda a recargarlo (documentos_nivel reabierto).
        Livewire::test(PlanEstudios::class, ['escuelaNivel' => $nivel])
            ->assertRedirect(route('tramite.paso2-nivel-documentos', ['escuelaNivel' => $nivel->id]));

        app(RegistrarDocumento::class)->ejecutar($escuela->id, 'formato_solicitud', UploadedFile::fake()->create('formato.pdf', 10, 'application/pdf'), new DatosDocumento, $nivel->id);
        $this->corregirPlan($nivel);
        app(ReenviarCorrecciones::class)->ejecutar($escuela->id, (int) $escuela->solicitante->user->id);

        $this->assertTrue($this->enRevision($nivel));
    }

    public function test_con_documentos_reabierto_la_vigencia_se_exige_de_verdad(): void
    {
        [$escuela, $nivel] = $this->devuelto(['documentos' => 'Visto bueno vencido', 'plan_estudios' => 'Falta la referencia']);
        $this->vencerVigencia($escuela);

        $this->assertSame(EstadoPaso2::DOCUMENTOS, app(EstadoPaso2::class)->etapaFaltante($escuela->id));
        Livewire::test(PlanEstudios::class, ['escuelaNivel' => $nivel])
            ->assertRedirect(route('tramite.paso2-documentos', ['escuela' => $escuela->id]));
    }
}
