<?php

namespace Tests\Feature\Application\Preregistro;

use App\Application\Excepciones\DatosInvalidos;
use App\Application\Excepciones\PrecondicionIncumplida;
use App\Application\Preregistro\ActualizarContactoPlantel;
use App\Application\Tramite\TramiteEditable;
use App\Models\Escuela;
use App\Models\EscuelaNivel;
use App\Models\NivelEducativo;
use App\Models\Plantel;
use App\Models\Solicitante;
use Database\Seeders\CatalogoMinimoSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\ConTramiteDevuelto;
use Tests\TestCase;

/** WS-7b-1 U1: solo teléfono y correo del plantel se editan; el domicilio queda de solo lectura. */
class ActualizarContactoPlantelTest extends TestCase
{
    use ConTramiteDevuelto;
    use RefreshDatabase;

    private function escuela(?Solicitante $solicitante = null, ?int $plantelId = null): Escuela
    {
        $plantelId ??= Plantel::create([
            'calle' => 'Av. Juárez', 'numero_ext' => '12', 'colonia' => 'Centro', 'municipio' => 'Querétaro',
            'codigo_postal' => '76000', 'telefono' => '4420000000', 'correo_electronico' => 'viejo@ejemplo.mx',
        ])->id;

        return Escuela::create(['plantel_id' => $plantelId, 'solicitante_id' => ($solicitante ?? Solicitante::factory()->create())->id]);
    }

    public function test_solo_cambia_telefono_y_correo(): void
    {
        $escuela = $this->escuela();

        app(ActualizarContactoPlantel::class)->ejecutar($escuela->id, $escuela->solicitante_id, '4421234567', 'nuevo@ejemplo.mx');

        $this->assertDatabaseHas('planteles', [
            'id' => $escuela->plantel_id, 'telefono' => '4421234567', 'correo_electronico' => 'nuevo@ejemplo.mx',
            'calle' => 'Av. Juárez', 'numero_ext' => '12', 'colonia' => 'Centro', 'municipio' => 'Querétaro', 'codigo_postal' => '76000',
        ]);
    }

    public function test_vacios_se_guardan_como_null(): void
    {
        $escuela = $this->escuela();

        app(ActualizarContactoPlantel::class)->ejecutar($escuela->id, $escuela->solicitante_id, null, null);

        $this->assertDatabaseHas('planteles', ['id' => $escuela->plantel_id, 'telefono' => null, 'correo_electronico' => null]);
    }

    public function test_rechaza_formatos_invalidos_sin_escribir(): void
    {
        $escuela = $this->escuela();

        try {
            app(ActualizarContactoPlantel::class)->ejecutar($escuela->id, $escuela->solicitante_id, '123', 'no-es-correo');
            $this->fail('Se esperaba DatosInvalidos.');
        } catch (DatosInvalidos $e) {
            $this->assertSame(['telefono', 'correoElectronico'], array_keys($e->errores));
        }

        $this->assertDatabaseHas('planteles', ['id' => $escuela->plantel_id, 'telefono' => '4420000000']);
    }

    public function test_rechaza_la_escuela_de_otro_solicitante(): void
    {
        $escuela = $this->escuela();
        $otro = Solicitante::factory()->create();

        $this->expectException(AuthorizationException::class);

        app(ActualizarContactoPlantel::class)->ejecutar($escuela->id, $otro->id, '4421234567', null);
    }

    /** Review Focus 4 (ADR-009): el plantel es de una escuela hermana ya enviada. */
    public function test_con_un_tramite_hermano_enviado_se_rechaza(): void
    {
        (new CatalogoMinimoSeeder)->run();
        $escuela = $this->escuela();
        $hermana = $this->escuela($escuela->solicitante, $escuela->plantel_id);
        EscuelaNivel::create([
            'escuela_id' => $hermana->id,
            'nivel_educativo_id' => NivelEducativo::where('clave', 'primaria')->value('id'),
            'estado_id' => DB::table('estados_expediente')->where('clave', 'en_revision')->value('id'),
            'tipo_tramite' => 'alta_nueva',
        ]);

        try {
            app(ActualizarContactoPlantel::class)->ejecutar($escuela->id, $escuela->solicitante_id, '4421234567', null);
            $this->fail('Se esperaba PrecondicionIncumplida.');
        } catch (PrecondicionIncumplida $e) {
            $this->assertSame(TramiteEditable::ENVIADO, $e->etapaFaltante);
        }

        $this->assertDatabaseHas('planteles', ['id' => $escuela->plantel_id, 'telefono' => '4420000000']);
    }

    public function test_escribe_con_el_paso_plantel_reabierto(): void
    {
        (new CatalogoMinimoSeeder)->run();
        $escuela = $this->escuela();
        $nivel = $this->nivelEn($escuela, 'primaria', 'en_revision');
        $this->devolverTramite($escuela, [$nivel->id => ['plantel' => 'Teléfono equivocado']]);

        app(ActualizarContactoPlantel::class)->ejecutar($escuela->id, $escuela->solicitante_id, '4421234567', 'nuevo@ejemplo.mx');

        $this->assertDatabaseHas('planteles', ['id' => $escuela->plantel_id, 'telefono' => '4421234567', 'correo_electronico' => 'nuevo@ejemplo.mx']);
    }

    public function test_lanza_con_el_paso_plantel_aceptado(): void
    {
        (new CatalogoMinimoSeeder)->run();
        $escuela = $this->escuela();
        $nivel = $this->nivelEn($escuela, 'primaria', 'en_revision');
        $this->devolverTramite($escuela, [$nivel->id => ['responsable' => 'x']], [$nivel->id => ['plantel']]);

        try {
            app(ActualizarContactoPlantel::class)->ejecutar($escuela->id, $escuela->solicitante_id, '4421234567', null);
            $this->fail('Se esperaba PrecondicionIncumplida.');
        } catch (PrecondicionIncumplida $e) {
            $this->assertSame(TramiteEditable::ENVIADO, $e->etapaFaltante);
        }

        $this->assertDatabaseHas('planteles', ['id' => $escuela->plantel_id, 'telefono' => '4420000000']);
    }
}
