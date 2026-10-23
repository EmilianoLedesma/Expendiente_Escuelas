<?php

namespace Tests\Feature\Application\Tramite;

use App\Application\Documentos\DTO\DatosDocumento;
use App\Application\Documentos\RegistrarDocumento;
use App\Application\Excepciones\PrecondicionIncumplida;
use App\Application\ResponsableLegal\DTO\DatosResponsableLegal;
use App\Application\ResponsableLegal\RegistrarResponsableLegal;
use App\Application\Tramite\ObservacionesDeEscuela;
use App\Application\Tramite\PasosReabiertos;
use App\Application\Tramite\ReenviarCorrecciones;
use App\Application\Tramite\TramiteEditable;
use App\Models\Escuela;
use App\Models\EscuelaNivel;
use App\Models\TipoDocumento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CapturaExpedienteConsistente;
use Tests\Concerns\ConTramiteDevuelto;
use Tests\TestCase;

/**
 * Corregir el responsable legal tras una devolución cambia lo que imprime el Formato de solicitud: solo se descarta
 * el Formato de los niveles que el solicitante puede volver a cargar, y en esos niveles `documentos_nivel` queda reabierto.
 */
class DevolucionResponsableTest extends TestCase
{
    use CapturaExpedienteConsistente;
    use ConTramiteDevuelto;
    use RefreshDatabase;

    /** @return array{0: Escuela, 1: EscuelaNivel, 2: EscuelaNivel} primaria devuelta (responsable) y secundaria aprobada, ambas con Formato */
    private function devuelto(): array
    {
        $escuela = $this->crearEscuelaConPlantel();
        $primaria = $this->completarTramite($escuela);
        $secundaria = $this->nivelEn($escuela, 'secundaria', 'aprobado');
        DB::table('documentos_escuela_nivel')->insert(['escuela_nivel_id' => $secundaria->id, 'tipo_documento_id' => $this->idFormato(), 'archivo_path' => 'formato-secundaria.pdf']);
        $this->devolverTramite($escuela, [$primaria->id => ['responsable' => 'El nombre no coincide con la INE']]);

        return [$escuela, $primaria, $secundaria];
    }

    private function idFormato(): int
    {
        return (int) TipoDocumento::where('clave', 'formato_solicitud')->value('id');
    }

    private function formatos(EscuelaNivel $nivel): int
    {
        return DB::table('documentos_escuela_nivel')->where('escuela_nivel_id', $nivel->id)->where('tipo_documento_id', $this->idFormato())->count();
    }

    private function corregirNombre(Escuela $escuela): void
    {
        (new RegistrarResponsableLegal)->ejecutar($escuela->id, new DatosResponsableLegal(
            tipoPersona: 'fisica', nombre: 'Juan Pérez López', rfc: self::RFC_TITULAR, curp: self::CURP_TITULAR,
        ));
    }

    private function reenviar(Escuela $escuela): void
    {
        app(ReenviarCorrecciones::class)->ejecutar($escuela->id, (int) $escuela->solicitante->user->id);
    }

    public function test_corregir_el_responsable_solo_descarta_el_formato_del_nivel_devuelto(): void
    {
        [$escuela, $primaria, $secundaria] = $this->devuelto();

        $this->corregirNombre($escuela);

        $this->assertSame(0, $this->formatos($primaria));
        $this->assertSame(1, $this->formatos($secundaria), 'Un nivel aprobado nunca pierde su Formato firmado.');
    }

    public function test_con_el_responsable_reabierto_los_documentos_del_nivel_devuelto_se_reabren(): void
    {
        [$escuela, $primaria, $secundaria] = $this->devuelto();
        $editable = app(TramiteEditable::class);

        $this->assertTrue($editable->esEditable($escuela->id, 'documentos_nivel', $primaria->id));
        $this->assertFalse($editable->esEditable($escuela->id, 'documentos_nivel', $secundaria->id));
        $this->assertContains(['paso' => 'documentos_nivel', 'escuelaNivelId' => $primaria->id], app(PasosReabiertos::class)->reabiertos($escuela->id));
    }

    public function test_el_motivo_del_formato_lo_ven_el_dueno_y_el_responsable_del_nivel(): void
    {
        [$escuela, $primaria] = $this->devuelto();
        $responsable = $this->responsableDe($primaria);
        $observaciones = app(ObservacionesDeEscuela::class);

        $delNivel = fn (array $todas) => array_values(array_filter($todas, fn (array $o) => $o['paso'] === 'documentos_nivel'));
        foreach ([(int) $escuela->solicitante->user->id, $responsable->id] as $usuarioId) {
            $formato = $delNivel($observaciones->paraUsuario($escuela->id, $usuarioId));
            $this->assertCount(1, $formato);
            $this->assertSame($primaria->id, $formato[0]['escuelaNivelId']);
            $this->assertSame('Primaria', $formato[0]['nivel']);
            $this->assertStringContainsString('Formato de solicitud firmado', $formato[0]['motivo']);
        }
    }

    public function test_tras_volver_a_cargar_el_formato_se_reenvia(): void
    {
        [$escuela, $primaria, $secundaria] = $this->devuelto();
        // El revisor había aceptado los documentos del nivel: con el Formato nuevo debe volver a decidirlos.
        $this->devolverTramite($escuela, [$primaria->id => ['responsable' => 'El nombre no coincide con la INE']], [$primaria->id => ['documentos_nivel']]);
        $this->corregirNombre($escuela);

        try {
            $this->reenviar($escuela);
            $this->fail('Sin el Formato no se reenvía.');
        } catch (PrecondicionIncumplida $e) {
            $this->assertStringContainsString('Documentos del nivel', $e->getMessage());
        }

        app(RegistrarDocumento::class)->ejecutar($escuela->id, 'formato_solicitud', UploadedFile::fake()->create('formato.pdf', 10, 'application/pdf'), new DatosDocumento, $primaria->id);
        $this->reenviar($escuela);

        $this->assertSame('en_revision', (string) DB::table('estados_expediente')->where('id', $primaria->fresh()->estado_id)->value('clave'));
        $this->assertSame(1, $this->formatos($primaria));
        $this->assertSame(1, $this->formatos($secundaria));
        $this->assertSame(0, DB::table('decisiones_revision')->where('escuela_nivel_id', $primaria->id)->count());
    }
}
