<?php

namespace Tests\Feature\Application\ProyectoEducativo;

use App\Application\Excepciones\DatosInvalidos;
use App\Application\Excepciones\PrecondicionIncumplida;
use App\Application\ProyectoEducativo\DTO\DatosPresentacionProyecto;
use App\Application\ProyectoEducativo\GuardarPresentacionProyecto;
use App\Application\ProyectoEducativo\PresentacionProyectoCapturada;
use App\Application\ProyectoEducativo\SeccionesProyectoEducativo;
use App\Application\Tramite\EstadoPaso3;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\PreparaPaso3;
use Tests\TestCase;

class GuardarPresentacionProyectoTest extends TestCase
{
    use PreparaPaso3;
    use RefreshDatabase;

    private function palabras(int $n): string
    {
        return trim(str_repeat('palabra ', $n));
    }

    /** @param array<string, mixed> $cambios */
    private function datos(array $cambios = []): DatosPresentacionProyecto
    {
        return new DatosPresentacionProyecto(...array_merge([
            'fecha_entrega' => '2026-11-10',
            'nombre_institucion' => 'Instituto Ejemplo',
            'fecha_apertura' => '2020-08-01',
            'domicilio_completo' => 'Calle 1, 10, Centro, Querétaro, Querétaro, 76000',
            'representante_legal' => 'Ana Pérez',
            'correo' => 'ana@ejemplo.com',
            'telefono' => '(442) 123-4567',
            'celular' => null,
            'pagina_web' => null,
            'modalidad' => 'presencial',
            'ciclo_escolar' => 'semestral',
            'ciclo_escolar_otro' => null,
            'duracion' => '3_anios',
            'duracion_otro' => null,
            'sintesis_factibilidad' => $this->palabras(500),
        ], $cambios));
    }

    private function guardar(int $nivelId, DatosPresentacionProyecto $datos): void
    {
        app(GuardarPresentacionProyecto::class)->ejecutar($nivelId, $datos);
    }

    /** @return array<string, string> */
    private function errores(int $nivelId, DatosPresentacionProyecto $datos): array
    {
        try {
            $this->guardar($nivelId, $datos);
        } catch (DatosInvalidos $e) {
            return $e->errores;
        }

        return [];
    }

    public function test_guarda_completo_normaliza_telefono_y_marca_completa(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');

        $this->guardar($nivel->id, $this->datos(['celular' => '442 765 4321']));

        $fila = DB::table('proyecto_educativo_presentacion')->where('escuela_nivel_id', $nivel->id)->first();
        $this->assertSame('4421234567', $fila->telefono);
        $this->assertSame('4427654321', $fila->celular);
        $this->assertSame('2020-08-01', $fila->fecha_apertura);
        $this->assertTrue(app(PresentacionProyectoCapturada::class)->completa($nivel->id));
        $this->assertSame('Instituto Ejemplo', app(PresentacionProyectoCapturada::class)->datos($nivel->id)['nombre_institucion']);
    }

    public function test_borrador_parcial_se_guarda_y_no_esta_completo(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');

        $this->guardar($nivel->id, new DatosPresentacionProyecto(nombre_institucion: 'Solo nombre', sintesis_factibilidad: $this->palabras(10)));

        $this->assertSame(1, DB::table('proyecto_educativo_presentacion')->count());
        $this->assertFalse(app(PresentacionProyectoCapturada::class)->completa($nivel->id));
    }

    public function test_sin_fila_no_esta_completa_y_datos_vacios(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');

        $this->assertFalse(app(PresentacionProyectoCapturada::class)->completa($nivel->id));
        $this->assertNull(app(PresentacionProyectoCapturada::class)->datos($nivel->id)['nombre_institucion']);
    }

    public function test_segundo_guardado_actualiza_la_misma_fila(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');

        $this->guardar($nivel->id, $this->datos());
        $this->guardar($nivel->id, $this->datos(['nombre_institucion' => 'Otro nombre']));

        $this->assertSame(1, DB::table('proyecto_educativo_presentacion')->count());
        $this->assertSame('Otro nombre', DB::table('proyecto_educativo_presentacion')->value('nombre_institucion'));
    }

    public function test_no_marca_el_paso_completado(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');

        $this->guardar($nivel->id, $this->datos());

        $this->assertFalse(app(EstadoPaso3::class)->completado($nivel->id, SeccionesProyectoEducativo::CLAVE_PASO));
    }

    public function test_cada_requerido_vacio_deja_la_seccion_incompleta(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');

        foreach (['fecha_entrega', 'nombre_institucion', 'fecha_apertura', 'domicilio_completo', 'representante_legal', 'correo', 'telefono', 'modalidad', 'ciclo_escolar', 'duracion', 'sintesis_factibilidad'] as $campo) {
            $this->guardar($nivel->id, $this->datos([$campo => null]));
            $this->assertFalse(app(PresentacionProyectoCapturada::class)->completa($nivel->id), $campo);
        }
    }

    public function test_maximos_formatos_y_enums(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');

        $this->assertArrayHasKey('nombre_institucion', $this->errores($nivel->id, $this->datos(['nombre_institucion' => str_repeat('a', 256)])));
        $this->assertArrayHasKey('domicilio_completo', $this->errores($nivel->id, $this->datos(['domicilio_completo' => str_repeat('a', 501)])));
        $this->assertArrayHasKey('representante_legal', $this->errores($nivel->id, $this->datos(['representante_legal' => str_repeat('a', 256)])));
        $this->assertArrayHasKey('correo', $this->errores($nivel->id, $this->datos(['correo' => 'no-es-correo'])));
        $this->assertArrayHasKey('correo', $this->errores($nivel->id, $this->datos(['correo' => str_repeat('a', 251).'@b.mx'])));
        $this->assertArrayHasKey('pagina_web', $this->errores($nivel->id, $this->datos(['pagina_web' => 'www.ejemplo.com'])));
        $this->assertArrayHasKey('pagina_web', $this->errores($nivel->id, $this->datos(['pagina_web' => 'ftp://ejemplo.com'])));
        $this->assertArrayHasKey('modalidad', $this->errores($nivel->id, $this->datos(['modalidad' => 'otra'])));
        $this->assertArrayHasKey('ciclo_escolar', $this->errores($nivel->id, $this->datos(['ciclo_escolar' => 'anual'])));
        $this->assertArrayHasKey('duracion', $this->errores($nivel->id, $this->datos(['duracion' => '4_anios'])));
        $this->assertArrayHasKey('fecha_entrega', $this->errores($nivel->id, $this->datos(['fecha_entrega' => '10/11/2026'])));
        $this->assertSame([], $this->errores($nivel->id, $this->datos(['pagina_web' => 'https://www.ejemplo.com'])));
    }

    public function test_telefono_y_celular_exigen_diez_digitos(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');

        $this->assertArrayHasKey('telefono', $this->errores($nivel->id, $this->datos(['telefono' => '123456789'])));
        $this->assertArrayHasKey('telefono', $this->errores($nivel->id, $this->datos(['telefono' => '12345678901'])));
        $this->assertArrayHasKey('celular', $this->errores($nivel->id, $this->datos(['celular' => '12345'])));
        $this->assertSame([], $this->errores($nivel->id, $this->datos(['telefono' => '4421234567', 'celular' => '(442) 000-0000'])));
    }

    public function test_apertura_en_el_futuro_se_rechaza_y_hoy_se_acepta(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');

        $this->assertArrayHasKey('fecha_apertura', $this->errores($nivel->id, $this->datos(['fecha_apertura' => now()->addDay()->toDateString()])));
        $this->assertSame([], $this->errores($nivel->id, $this->datos(['fecha_apertura' => now()->toDateString()])));
    }

    public function test_otro_exige_texto_y_si_no_es_otro_se_guarda_nulo(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');
        $capturada = app(PresentacionProyectoCapturada::class);

        $this->guardar($nivel->id, $this->datos(['ciclo_escolar' => 'otro', 'duracion' => 'otro']));
        $this->assertFalse($capturada->completa($nivel->id));

        $this->assertArrayHasKey('ciclo_escolar_otro', $this->errores($nivel->id, $this->datos(['ciclo_escolar' => 'otro', 'ciclo_escolar_otro' => str_repeat('a', 101)])));
        $this->assertArrayHasKey('duracion_otro', $this->errores($nivel->id, $this->datos(['duracion' => 'otro', 'duracion_otro' => str_repeat('a', 101)])));

        $this->guardar($nivel->id, $this->datos(['ciclo_escolar' => 'otro', 'ciclo_escolar_otro' => 'Tetramestral', 'duracion' => 'otro', 'duracion_otro' => '2.5 años']));
        $this->assertTrue($capturada->completa($nivel->id));

        $this->guardar($nivel->id, $this->datos(['ciclo_escolar' => 'mensual', 'ciclo_escolar_otro' => 'residuo', 'duracion' => '2_anios', 'duracion_otro' => 'residuo']));
        $fila = DB::table('proyecto_educativo_presentacion')->first();
        $this->assertNull($fila->ciclo_escolar_otro);
        $this->assertNull($fila->duracion_otro);
    }

    public function test_palabras_499_500_1000_1001(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');
        $capturada = app(PresentacionProyectoCapturada::class);

        // 499: el borrador se guarda, pero no está completa.
        $this->guardar($nivel->id, $this->datos(['sintesis_factibilidad' => $this->palabras(499)]));
        $this->assertFalse($capturada->completa($nivel->id));

        $this->guardar($nivel->id, $this->datos(['sintesis_factibilidad' => $this->palabras(500)]));
        $this->assertTrue($capturada->completa($nivel->id));

        $this->guardar($nivel->id, $this->datos(['sintesis_factibilidad' => '  '.str_repeat("palabra\n", 999).'palabra  ']));
        $this->assertTrue($capturada->completa($nivel->id));

        $this->assertArrayHasKey('sintesis_factibilidad', $this->errores($nivel->id, $this->datos(['sintesis_factibilidad' => $this->palabras(1001)])));
    }

    public function test_tramite_enviado_rechaza_el_guardado(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');
        $nivel->update(['estado_id' => DB::table('estados_expediente')->where('clave', 'en_revision')->value('id')]);

        $this->expectException(PrecondicionIncumplida::class);
        $this->guardar($nivel->id, $this->datos());
    }

    public function test_antes_de_matricula_rechaza_el_guardado(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'matricula');

        $this->expectException(PrecondicionIncumplida::class);
        $this->guardar($nivel->id, $this->datos());
    }

    public function test_la_base_rechaza_enums_fuera_de_catalogo(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');

        $this->expectException(QueryException::class);
        DB::table('proyecto_educativo_presentacion')->insert(['escuela_nivel_id' => $nivel->id, 'modalidad' => 'otra']);
    }
}
