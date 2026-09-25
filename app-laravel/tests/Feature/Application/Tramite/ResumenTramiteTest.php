<?php

namespace Tests\Feature\Application\Tramite;

use App\Application\Documentos\DTO\DatosDocumento;
use App\Application\Documentos\RegistrarDocumento;
use App\Application\EscuelaNiveles\MarcarPasoCompletado;
use App\Application\ResponsableLegal\DTO\DatosResponsableLegal;
use App\Application\ResponsableLegal\RegistrarResponsableLegal;
use App\Application\Tramite\DTO\ResumenTramiteDTO;
use App\Application\Tramite\DTO\SeccionTramite;
use App\Application\Tramite\ResumenTramite;
use App\Models\Escuela;
use App\Models\EscuelaNivel;
use App\Models\NivelEducativo;
use App\Models\Plantel;
use App\Models\Solicitante;
use Database\Seeders\CatalogoMinimoSeeder;
use Database\Seeders\PasosCapturaSeeder;
use Database\Seeders\TiposDocumentosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CompletaPaso2;
use Tests\TestCase;

class ResumenTramiteTest extends TestCase
{
    use CompletaPaso2;
    use RefreshDatabase;

    private function escuela(): Escuela
    {
        (new CatalogoMinimoSeeder)->run();
        (new PasosCapturaSeeder)->run();
        (new TiposDocumentosSeeder)->run();
        Storage::fake('documentos');

        $plantel = Plantel::create(['calle' => 'Calle 1', 'numero_ext' => '10', 'colonia' => 'Centro', 'municipio' => 'Querétaro', 'codigo_postal' => '76000']);

        return Escuela::create(['plantel_id' => $plantel->id, 'solicitante_id' => Solicitante::factory()->create()->id]);
    }

    private function responsable(Escuela $escuela): void
    {
        (new RegistrarResponsableLegal)->ejecutar($escuela->id, new DatosResponsableLegal(tipoPersona: 'fisica', nombre: 'Juana Pérez'));
    }

    private function documentos(Escuela $escuela, string ...$claves): void
    {
        foreach ($claves as $clave) {
            app(RegistrarDocumento::class)->ejecutar($escuela->id, $clave, UploadedFile::fake()->create("{$clave}.pdf", 10, 'application/pdf'), new DatosDocumento);
        }
    }

    private function nivel(Escuela $escuela, string $clave, string ...$completados): EscuelaNivel
    {
        $escuelaNivel = EscuelaNivel::create([
            'escuela_id' => $escuela->id,
            'nivel_educativo_id' => NivelEducativo::where('clave', $clave)->value('id'),
            'estado_id' => DB::table('estados_expediente')->where('clave', 'en_captura')->value('id'),
            'tipo_tramite' => 'alta_nueva',
        ]);

        foreach ($completados as $paso) {
            (new MarcarPasoCompletado)->ejecutar($escuelaNivel->id, $paso);
        }

        return $escuelaNivel;
    }

    private function resumen(Escuela $escuela): ResumenTramiteDTO
    {
        return app(ResumenTramite::class)->paraEscuela($escuela->id);
    }

    /**
     * @param  array<int, SeccionTramite>  $secciones
     * @return array<string, SeccionTramite>
     */
    private function porClave(array $secciones): array
    {
        return collect($secciones)->keyBy('clave')->all();
    }

    public function test_una_escuela_recien_creada_solo_tiene_el_plantel_completo(): void
    {
        $escuela = $this->escuela();

        $resumen = $this->resumen($escuela);
        $g = $this->porClave($resumen->generales);

        $this->assertSame(['plantel', 'responsable', 'documentos', 'niveles'], array_keys($g));
        $this->assertSame(['completado', 'pendiente', 'pendiente', 'pendiente'], array_map(fn (SeccionTramite $s) => $s->estado, $resumen->generales));
        $this->assertNull($g['plantel']->accion);
        $this->assertSame('comenzar', $g['responsable']->accion);
        $this->assertSame(route('tramite.paso2', ['escuela' => $escuela->id]), $g['responsable']->href);
        // Migrado de ProgresoTest::test_sin_responsable_documentos_no_es_enlace.
        $this->assertNull($g['documentos']->href);
        $this->assertNull($g['documentos']->accion);
        $this->assertSame('Completa primero: Responsable legal', $g['documentos']->motivoBloqueo);
        $this->assertSame('Completa primero: Responsable legal', $g['niveles']->motivoBloqueo);
        $this->assertSame([1, 2, 3, 4], array_map(fn (SeccionTramite $s) => $s->paso, $resumen->generales));
        $this->assertSame([4, 4, 4, 4], array_map(fn (SeccionTramite $s) => $s->totalPasos, $resumen->generales));
        $this->assertSame([], $resumen->niveles);
        $this->assertFalse($resumen->completo);
        $this->assertSame('Calle 1 #10, Centro, Querétaro, C.P. 76000', $resumen->domicilio);
        $this->assertSame('Calle 1', $resumen->plantel['Calle y número']);
        $this->assertNull($resumen->plantel['Número interior']);
    }

    public function test_con_responsable_y_sin_documentos(): void
    {
        $escuela = $this->escuela();
        $this->responsable($escuela);

        $g = $this->porClave($this->resumen($escuela)->generales);

        $this->assertSame('completado', $g['responsable']->estado);
        $this->assertNull($g['responsable']->accion, 'Responsable completo no se puede reabrir hoy (D1).');
        $this->assertSame('pendiente', $g['documentos']->estado);
        $this->assertSame('comenzar', $g['documentos']->accion);
        $this->assertSame(route('tramite.paso2-documentos', ['escuela' => $escuela->id]), $g['documentos']->href);
        $this->assertSame('Completa primero: Documentos', $g['niveles']->motivoBloqueo);
    }

    public function test_documentos_parciales_quedan_en_curso(): void
    {
        $escuela = $this->escuela();
        $this->responsable($escuela);
        $this->documentos($escuela, 'ine', 'acta_nacimiento', 'escritura_inmueble');

        $documentos = $this->porClave($this->resumen($escuela)->generales)['documentos'];

        $this->assertSame('en_curso', $documentos->estado);
        $this->assertSame('continuar', $documentos->accion);
    }

    public function test_un_dictamen_vencido_deja_documentos_en_curso_y_bloquea_paso3(): void
    {
        $escuela = $this->escuela();
        $this->responsable($escuela);
        $this->documentos($escuela, 'ine', 'acta_nacimiento', 'escritura_inmueble', 'constancia_seguridad_estructural', 'formato_solicitud');
        app(RegistrarDocumento::class)->ejecutar($escuela->id, 'dictamen_uso_suelo', UploadedFile::fake()->create('d.pdf', 10, 'application/pdf'), new DatosDocumento(
            fechaEmision: now()->subDays(60)->toDateString(),
        ));
        $this->nivel($escuela, 'primaria');

        $resumen = $this->resumen($escuela);
        $inmueble = $this->porClave($resumen->niveles[0]->secciones)['inmueble'];

        $this->assertSame('en_curso', $this->porClave($resumen->generales)['documentos']->estado);
        $this->assertSame('Completa primero: Documentos', $inmueble->motivoBloqueo);
        $this->assertNull($inmueble->accion);
        $this->assertNull($inmueble->href);
    }

    public function test_paso2_completo_sin_niveles(): void
    {
        $escuela = $this->escuela();
        $this->completarPaso2($escuela->id);

        $g = $this->porClave($this->resumen($escuela)->generales);

        // Migrado de ProgresoTest::test_con_documentos_completos_y_vigentes_documentos_es_completado.
        $this->assertSame('completado', $g['documentos']->estado);
        $this->assertNull($g['documentos']->accion);
        $this->assertSame('pendiente', $g['niveles']->estado);
        $this->assertSame('comenzar', $g['niveles']->accion);
        $this->assertSame(route('tramite.paso2', ['escuela' => $escuela->id]), $g['niveles']->href);
    }

    /** Migrado de ProgresoTest::test_con_escuela_niveles_pero_documentos_incompletos_no_es_completado. */
    public function test_escuela_niveles_heredado_con_documentos_incompletos(): void
    {
        $escuela = $this->escuela();
        $this->responsable($escuela);
        $this->nivel($escuela, 'primaria');

        $g = $this->porClave($this->resumen($escuela)->generales);

        $this->assertNotSame('completado', $g['documentos']->estado);
        $this->assertSame('completado', $g['niveles']->estado);
    }

    public function test_un_nivel_primaria_recien_seleccionado(): void
    {
        $escuela = $this->escuela();
        $this->completarPaso2($escuela->id);
        $primaria = $this->nivel($escuela, 'primaria');

        $nivel = $this->resumen($escuela)->niveles[0];
        $s = $this->porClave($nivel->secciones);

        $this->assertSame('primaria', $nivel->clave);
        $this->assertSame('Primaria', $nivel->nombre);
        // Migrado de ProgresoTest::test_renderiza_los_seis_pasos_en_orden.
        $this->assertSame(['inmueble', 'infraestructura', 'mobiliario', 'plan_estudios', 'plantilla_docente', 'matricula'], array_keys($s));
        $this->assertSame('comenzar', $s['inmueble']->accion);
        $this->assertSame(route('tramite.paso3-inmueble', ['escuelaNivel' => $primaria->id]), $s['inmueble']->href);
        $this->assertSame([5, 6], [$s['inmueble']->paso, $s['inmueble']->totalPasos]);
        $this->assertSame('pendiente', $s['infraestructura']->estado);
        $this->assertSame('Completa primero: Datos del inmueble', $s['infraestructura']->motivoBloqueo);
        $this->assertNull($s['infraestructura']->href);
        $this->assertSame([6, 6], [$s['infraestructura']->paso, $s['infraestructura']->totalPasos]);
        $this->assertSame('no_aplica', $s['mobiliario']->estado);
        $this->assertNull($s['mobiliario']->href);
        $this->assertNull($s['mobiliario']->paso);
        foreach (['plan_estudios', 'plantilla_docente', 'matricula'] as $clave) {
            $this->assertSame('no_disponible', $s[$clave]->estado);
            $this->assertNull($s[$clave]->href);
        }
    }

    public function test_inicial_incluye_mobiliario_y_revisar_solo_en_las_revisables(): void
    {
        $escuela = $this->escuela();
        $this->completarPaso2($escuela->id);
        $inicial = $this->nivel($escuela, 'inicial', 'inmueble', 'infraestructura');

        $s = $this->porClave($this->resumen($escuela)->niveles[0]->secciones);

        $this->assertSame('completado', $s['inmueble']->estado);
        $this->assertNull($s['inmueble']->accion, 'Datos del inmueble redirige al estar completo (D1).');
        $this->assertSame('completado', $s['infraestructura']->estado);
        $this->assertSame('revisar', $s['infraestructura']->accion);
        $this->assertSame(route('tramite.paso3-infraestructura', ['escuelaNivel' => $inicial->id]), $s['infraestructura']->href);
        $this->assertSame('pendiente', $s['mobiliario']->estado);
        $this->assertSame('comenzar', $s['mobiliario']->accion);
        $this->assertSame([7, 7], [$s['mobiliario']->paso, $s['mobiliario']->totalPasos]);
    }

    public function test_un_paso_en_progreso_se_muestra_en_curso(): void
    {
        $escuela = $this->escuela();
        $this->completarPaso2($escuela->id);
        $primaria = $this->nivel($escuela, 'primaria');
        DB::table('escuela_nivel_pasos')->insert([
            'escuela_nivel_id' => $primaria->id,
            'paso_captura_id' => DB::table('pasos_captura')->where('clave', 'inmueble')->value('id'),
            'estado' => 'en_progreso',
        ]);

        $inmueble = $this->porClave($this->resumen($escuela)->niveles[0]->secciones)['inmueble'];

        $this->assertSame('en_curso', $inmueble->estado);
        $this->assertSame('continuar', $inmueble->accion);
    }

    /** Migrado de Paso3ProximosPasosTest (enlace a un segundo nivel de la misma escuela). */
    public function test_varios_niveles_en_orden_de_creacion_con_estado_propio(): void
    {
        $escuela = $this->escuela();
        $this->completarPaso2($escuela->id);
        $this->nivel($escuela, 'primaria', 'inmueble', 'infraestructura', 'mobiliario');
        $inicial = $this->nivel($escuela, 'inicial');

        $niveles = $this->resumen($escuela)->niveles;

        $this->assertSame(['primaria', 'inicial'], array_map(fn ($n) => $n->clave, $niveles));
        $this->assertSame($inicial->id, $niveles[1]->escuelaNivelId);
        $inmuebleInicial = $this->porClave($niveles[1]->secciones)['inmueble'];
        $this->assertSame('comenzar', $inmuebleInicial->accion);
        $this->assertSame(route('tramite.paso3-inmueble', ['escuelaNivel' => $inicial->id]), $inmuebleInicial->href);
    }

    public function test_completo_solo_cuando_toda_seccion_disponible_esta_completada(): void
    {
        $escuela = $this->escuela();
        $this->completarPaso2($escuela->id);
        $this->nivel($escuela, 'primaria', 'inmueble', 'infraestructura');
        $inicial = $this->nivel($escuela, 'inicial', 'inmueble', 'infraestructura');

        $this->assertFalse($this->resumen($escuela)->completo);

        (new MarcarPasoCompletado)->ejecutar($inicial->id, 'mobiliario');

        $this->assertTrue($this->resumen($escuela)->completo);
    }

    public function test_una_clave_nueva_del_catalogo_se_muestra_como_no_disponible(): void
    {
        $escuela = $this->escuela();
        $this->completarPaso2($escuela->id);
        $this->nivel($escuela, 'primaria');
        DB::table('pasos_captura')->insert(['clave' => 'visita_inspeccion', 'nombre' => 'Visita de inspección', 'orden' => 7]);

        $secciones = $this->resumen($escuela)->niveles[0]->secciones;
        $ultima = end($secciones);

        $this->assertSame('visita_inspeccion', $ultima->clave);
        $this->assertSame('Visita de inspección', $ultima->nombre);
        $this->assertSame('no_disponible', $ultima->estado);
        $this->assertNull($ultima->href);
    }

    /** @return array<string, array{string, ?string, ?array{paso: int, total: int}}> */
    public static function posiciones(): array
    {
        return [
            'plantel' => ['plantel', null, ['paso' => 1, 'total' => 4]],
            'niveles' => ['niveles', null, ['paso' => 4, 'total' => 4]],
            'inmueble primaria' => ['inmueble', 'primaria', ['paso' => 5, 'total' => 6]],
            'infraestructura primaria' => ['infraestructura', 'primaria', ['paso' => 6, 'total' => 6]],
            'mobiliario inicial' => ['mobiliario', 'inicial', ['paso' => 7, 'total' => 7]],
            'mobiliario primaria' => ['mobiliario', 'primaria', null],
            'plan de estudios' => ['plan_estudios', 'primaria', null],
        ];
    }

    #[DataProvider('posiciones')]
    public function test_posicion(string $clave, ?string $nivel, ?array $esperada): void
    {
        $this->assertSame($esperada, ResumenTramite::posicion($clave, $nivel));
    }

    public function test_encabezado(): void
    {
        $this->assertSame('Paso 2 de 4 · Responsable legal', ResumenTramite::encabezado('responsable'));
        $this->assertSame('Paso 6 de 6 · Infraestructura', ResumenTramite::encabezado('infraestructura', 'primaria'));
        $this->assertNull(ResumenTramite::encabezado('mobiliario', 'primaria'));
    }
}
