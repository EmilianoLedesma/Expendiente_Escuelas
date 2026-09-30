<?php

namespace Tests\Feature\Application\EscuelaNiveles;

use App\Application\EscuelaNiveles\RegistrarDatosNivel;
use App\Application\Excepciones\DatosInvalidos;
use App\Application\Excepciones\PrecondicionIncumplida;
use App\Application\Tramite\EstadoPaso24;
use App\Models\DocumentoEscuelaNivel;
use App\Models\Escuela;
use App\Models\EscuelaNivel;
use App\Models\NivelEducativo;
use App\Models\Plantel;
use App\Models\Solicitante;
use App\Models\TipoDocumento;
use Database\Seeders\TiposDocumentosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\Concerns\CompletaPaso2;
use Tests\TestCase;

class RegistrarDatosNivelTest extends TestCase
{
    use CompletaPaso2;
    use RefreshDatabase;

    private function nivel(bool $conPaso2 = true): EscuelaNivel
    {
        (new TiposDocumentosSeeder)->run();
        $plantel = Plantel::create(['calle' => 'Calle 1', 'colonia' => 'Centro', 'municipio' => 'Querétaro', 'codigo_postal' => '76000']);
        $escuela = Escuela::create(['plantel_id' => $plantel->id, 'solicitante_id' => Solicitante::factory()->create()->id]);

        if ($conPaso2) {
            $this->completarPaso2($escuela->id);
        }

        return EscuelaNivel::create([
            'escuela_id' => $escuela->id,
            'nivel_educativo_id' => NivelEducativo::where('clave', 'primaria')->value('id'),
            'estado_id' => DB::table('estados_expediente')->where('clave', 'en_captura')->value('id'),
            'tipo_tramite' => 'alta_nueva',
        ]);
    }

    /** Formato firmado ya subido: fila + archivo en el disco falso que dejó completarPaso2(). Devuelve la ruta. */
    private function subirFormato(EscuelaNivel $escuelaNivel): string
    {
        $ruta = "escuela_nivel/{$escuelaNivel->id}/formato_solicitud-FIRMADO.pdf";
        Storage::disk('documentos')->put($ruta, 'FORMATO-FIRMADO');
        DocumentoEscuelaNivel::create([
            'escuela_nivel_id' => $escuelaNivel->id,
            'tipo_documento_id' => TipoDocumento::where('clave', 'formato_solicitud')->value('id'),
            'archivo_path' => $ruta,
        ]);

        return $ruta;
    }

    public function test_guarda_turno_y_tipo_de_alumnado(): void
    {
        $escuelaNivel = $this->nivel();

        app(RegistrarDatosNivel::class)->ejecutar($escuelaNivel->id, 'vespertino', 'femenino');

        $this->assertDatabaseHas('escuela_niveles', ['id' => $escuelaNivel->id, 'turno' => 'vespertino', 'tipo_alumnado' => 'femenino']);
    }

    public function test_rechaza_valores_fuera_de_los_check_del_ddl(): void
    {
        $escuelaNivel = $this->nivel();

        try {
            app(RegistrarDatosNivel::class)->ejecutar($escuelaNivel->id, 'nocturno', 'otro');
            $this->fail('Se esperaba DatosInvalidos.');
        } catch (DatosInvalidos $e) {
            $this->assertArrayHasKey('turno', $e->errores);
            $this->assertArrayHasKey('tipoAlumnado', $e->errores);
        }

        $this->assertNull($escuelaNivel->fresh()->turno);
    }

    public function test_rechaza_con_paso2_incompleto(): void
    {
        $escuelaNivel = $this->nivel(conPaso2: false);

        $this->expectException(PrecondicionIncumplida::class);

        app(RegistrarDatosNivel::class)->ejecutar($escuelaNivel->id, 'matutino', 'mixto');
    }

    /** Review Focus 4 (decisión del dueño): el Formato firmado ya no coincide, se descarta; 2.4 vuelve a pedirlo. */
    public function test_cambiar_los_datos_con_formato_subido_descarta_el_formato(): void
    {
        $escuelaNivel = $this->nivel();
        app(RegistrarDatosNivel::class)->ejecutar($escuelaNivel->id, 'matutino', 'mixto');
        $ruta = $this->subirFormato($escuelaNivel);

        app(RegistrarDatosNivel::class)->ejecutar($escuelaNivel->id, 'vespertino', 'mixto');

        $this->assertSame('vespertino', $escuelaNivel->fresh()->turno);
        $this->assertDatabaseCount('documentos_escuela_nivel', 0);
        // afterCommit dispara al bajar al nivel de transacción del test (ver RegistrarDocumento).
        Storage::disk('documentos')->assertMissing($ruta);
        $this->assertSame(EstadoPaso24::DOCUMENTOS_NIVEL, app(EstadoPaso24::class)->etapaFaltante($escuelaNivel->id));
    }

    /** Cambiar solo el tipo de alumnado también descarta. */
    public function test_cambiar_solo_el_tipo_de_alumnado_tambien_descarta_el_formato(): void
    {
        $escuelaNivel = $this->nivel();
        app(RegistrarDatosNivel::class)->ejecutar($escuelaNivel->id, 'matutino', 'mixto');
        $ruta = $this->subirFormato($escuelaNivel);

        app(RegistrarDatosNivel::class)->ejecutar($escuelaNivel->id, 'matutino', 'femenino');

        $this->assertDatabaseCount('documentos_escuela_nivel', 0);
        Storage::disk('documentos')->assertMissing($ruta);
    }

    /** Si una transacción externa hace rollback, el Formato y su archivo se conservan y los datos no cambian. */
    public function test_rollback_externo_conserva_el_formato_y_su_archivo(): void
    {
        $escuelaNivel = $this->nivel();
        app(RegistrarDatosNivel::class)->ejecutar($escuelaNivel->id, 'matutino', 'mixto');
        $ruta = $this->subirFormato($escuelaNivel);

        try {
            DB::transaction(function () use ($escuelaNivel) {
                app(RegistrarDatosNivel::class)->ejecutar($escuelaNivel->id, 'vespertino', 'mixto');

                throw new RuntimeException('fuerza rollback de la transacción externa');
            });
            $this->fail('Se esperaba que la transacción externa fallara.');
        } catch (RuntimeException) {
            // esperado
        }

        $this->assertSame('matutino', $escuelaNivel->fresh()->turno);
        $this->assertDatabaseCount('documentos_escuela_nivel', 1);
        Storage::disk('documentos')->assertExists($ruta);
    }

    /** Control de no vacuidad: reenviar los mismos datos no descarta nada. */
    public function test_reenviar_los_mismos_datos_no_descarta_el_formato(): void
    {
        $escuelaNivel = $this->nivel();
        app(RegistrarDatosNivel::class)->ejecutar($escuelaNivel->id, 'matutino', 'mixto');
        $ruta = $this->subirFormato($escuelaNivel);

        app(RegistrarDatosNivel::class)->ejecutar($escuelaNivel->id, 'matutino', 'mixto');

        $this->assertDatabaseCount('documentos_escuela_nivel', 1);
        Storage::disk('documentos')->assertExists($ruta);
    }

    /** Sin Formato subido, corregir solo guarda. */
    public function test_sin_formato_subido_los_datos_se_pueden_corregir(): void
    {
        $escuelaNivel = $this->nivel();
        app(RegistrarDatosNivel::class)->ejecutar($escuelaNivel->id, 'matutino', 'mixto');

        app(RegistrarDatosNivel::class)->ejecutar($escuelaNivel->id, 'vespertino', 'mixto');

        $this->assertSame('vespertino', $escuelaNivel->fresh()->turno);
    }
}
