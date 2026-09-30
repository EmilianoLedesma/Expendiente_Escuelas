<?php

namespace Tests\Feature\Application\Tramite;

use App\Application\Documentos\DocumentosCompletos;
use App\Application\Documentos\DTO\DatosDocumento;
use App\Application\Documentos\RegistrarDocumento;
use App\Application\EscuelaNiveles\MarcarPasoCompletado;
use App\Application\EscuelaNiveles\RegistrarDatosNivel;
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
use App\Models\TernaNombre;
use Database\Seeders\CatalogoMinimoSeeder;
use Database\Seeders\PasosCapturaSeeder;
use Database\Seeders\TiposDocumentosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CompletaPaso2;
use Tests\Concerns\CompletaPaso24;
use Tests\TestCase;

class ResumenTramiteTest extends TestCase
{
    use CompletaPaso2;
    use CompletaPaso24;
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

    private function nivel(Escuela $escuela, string $clave): EscuelaNivel
    {
        return EscuelaNivel::create([
            'escuela_id' => $escuela->id,
            'nivel_educativo_id' => NivelEducativo::where('clave', $clave)->value('id'),
            'estado_id' => DB::table('estados_expediente')->where('clave', 'en_captura')->value('id'),
            'tipo_tramite' => 'alta_nueva',
        ]);
    }

    /** Nivel con su Paso 2.4 completo y los sub-pasos de Paso 3 indicados marcados. Requiere Paso 2 completo. */
    private function nivelListo(Escuela $escuela, string $clave, string ...$completados): EscuelaNivel
    {
        $escuelaNivel = $this->nivel($escuela, $clave);
        $this->completarPaso24($escuelaNivel->id);

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
        $this->documentos($escuela, ...array_diff(app(DocumentosCompletos::class)->clavesAplicables('fisica'), ['dictamen_uso_suelo']));
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

    public function test_un_nivel_primaria_recien_seleccionado_empieza_por_sus_documentos(): void
    {
        $escuela = $this->escuela();
        $this->completarPaso2($escuela->id);
        $primaria = $this->nivel($escuela, 'primaria');

        $nivel = $this->resumen($escuela)->niveles[0];
        $s = $this->porClave($nivel->secciones);

        $this->assertSame('primaria', $nivel->clave);
        $this->assertSame('Primaria', $nivel->nombre);
        $this->assertSame(['documentos_nivel', 'inmueble', 'infraestructura', 'mobiliario', 'plan_estudios', 'plantilla_docente', 'matricula'], array_keys($s));
        $this->assertSame('Documentos del nivel', $s['documentos_nivel']->nombre);
        $this->assertSame('pendiente', $s['documentos_nivel']->estado);
        $this->assertSame('comenzar', $s['documentos_nivel']->accion);
        $this->assertSame(route('tramite.paso2-nivel-documentos', ['escuelaNivel' => $primaria->id]), $s['documentos_nivel']->href);
        $this->assertSame([5, 7], [$s['documentos_nivel']->paso, $s['documentos_nivel']->totalPasos]);
        $this->assertSame([6, 7], [$s['inmueble']->paso, $s['inmueble']->totalPasos]);
        $this->assertSame([7, 7], [$s['infraestructura']->paso, $s['infraestructura']->totalPasos]);
        $this->assertSame('no_aplica', $s['mobiliario']->estado);
        $this->assertNull($s['mobiliario']->paso);
        foreach (['plan_estudios', 'plantilla_docente', 'matricula'] as $clave) {
            $this->assertSame('no_disponible', $s[$clave]->estado);
            $this->assertNull($s[$clave]->href);
        }
    }

    public function test_con_documentos_del_nivel_completos_inmueble_se_puede_comenzar(): void
    {
        $escuela = $this->escuela();
        $this->completarPaso2($escuela->id);
        $primaria = $this->nivelListo($escuela, 'primaria');

        $s = $this->porClave($this->resumen($escuela)->niveles[0]->secciones);

        $this->assertSame('completado', $s['documentos_nivel']->estado);
        $this->assertSame('revisar', $s['documentos_nivel']->accion, 'el Formato firmado se puede reemplazar');
        $this->assertSame('comenzar', $s['inmueble']->accion);
        $this->assertSame(route('tramite.paso3-inmueble', ['escuelaNivel' => $primaria->id]), $s['inmueble']->href);
        $this->assertSame('pendiente', $s['infraestructura']->estado);
        $this->assertSame('Completa primero: Datos del inmueble', $s['infraestructura']->motivoBloqueo);
        $this->assertNull($s['infraestructura']->href);
    }

    public function test_documentos_del_nivel_con_datos_pero_sin_documentos_queda_en_curso(): void
    {
        $escuela = $this->escuela();
        $this->completarPaso2($escuela->id);
        $primaria = $this->nivel($escuela, 'primaria');
        app(RegistrarDatosNivel::class)->ejecutar($primaria->id, 'matutino', 'mixto');

        $documentosNivel = $this->porClave($this->resumen($escuela)->niveles[0]->secciones)['documentos_nivel'];

        $this->assertSame('en_curso', $documentosNivel->estado);
        $this->assertSame('continuar', $documentosNivel->accion);
    }

    public function test_con_paso2_incompleto_documentos_del_nivel_queda_bloqueado(): void
    {
        $escuela = $this->escuela();
        $this->responsable($escuela);
        $this->nivel($escuela, 'primaria');

        $documentosNivel = $this->porClave($this->resumen($escuela)->niveles[0]->secciones)['documentos_nivel'];

        $this->assertSame('Completa primero: Documentos', $documentosNivel->motivoBloqueo);
        $this->assertNull($documentosNivel->href);
    }

    /**
     * Decisión del dueño (#15): tras seleccionar niveles, Paso2Responsable manda al
     * hub; el "Siguiente paso" del hub debe ser Documentos del primer nivel.
     */
    public function test_el_siguiente_paso_de_una_escuela_multinivel_recien_seleccionada_es_documentos_del_primer_nivel(): void
    {
        $escuela = $this->escuela();
        $this->completarPaso2($escuela->id);
        $primaria = $this->nivel($escuela, 'primaria');
        $this->nivel($escuela, 'secundaria');

        $siguiente = $this->resumen($escuela)->siguiente();

        $this->assertNotNull($siguiente);
        $this->assertSame('documentos_nivel', $siguiente->clave);
        $this->assertSame(route('tramite.paso2-nivel-documentos', ['escuelaNivel' => $primaria->id]), $siguiente->href);
    }

    public function test_inicial_incluye_mobiliario_y_revisar_solo_en_las_revisables(): void
    {
        $escuela = $this->escuela();
        $this->completarPaso2($escuela->id);
        $inicial = $this->nivelListo($escuela, 'inicial', 'inmueble', 'infraestructura');

        $s = $this->porClave($this->resumen($escuela)->niveles[0]->secciones);

        $this->assertSame('completado', $s['inmueble']->estado);
        $this->assertNull($s['inmueble']->accion, 'Datos del inmueble redirige al estar completo (D1).');
        $this->assertSame('completado', $s['infraestructura']->estado);
        $this->assertSame('revisar', $s['infraestructura']->accion);
        $this->assertSame(route('tramite.paso3-infraestructura', ['escuelaNivel' => $inicial->id]), $s['infraestructura']->href);
        $this->assertSame('pendiente', $s['mobiliario']->estado);
        $this->assertSame('comenzar', $s['mobiliario']->accion);
        $this->assertSame([8, 8], [$s['mobiliario']->paso, $s['mobiliario']->totalPasos]);
    }

    public function test_un_paso_en_progreso_se_muestra_en_curso(): void
    {
        $escuela = $this->escuela();
        $this->completarPaso2($escuela->id);
        $primaria = $this->nivelListo($escuela, 'primaria');
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
        $this->nivelListo($escuela, 'primaria', 'inmueble', 'infraestructura', 'mobiliario');
        $inicial = $this->nivelListo($escuela, 'inicial');

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
        $this->nivelListo($escuela, 'primaria', 'inmueble', 'infraestructura');
        $inicial = $this->nivelListo($escuela, 'inicial', 'inmueble', 'infraestructura');

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
            'documentos del nivel primaria' => ['documentos_nivel', 'primaria', ['paso' => 5, 'total' => 7]],
            'inmueble primaria' => ['inmueble', 'primaria', ['paso' => 6, 'total' => 7]],
            'infraestructura primaria' => ['infraestructura', 'primaria', ['paso' => 7, 'total' => 7]],
            'documentos del nivel inicial' => ['documentos_nivel', 'inicial', ['paso' => 5, 'total' => 8]],
            'mobiliario inicial' => ['mobiliario', 'inicial', ['paso' => 8, 'total' => 8]],
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
        $primaria = (new NivelEducativo)->forceFill(['clave' => 'primaria', 'nombre' => 'Primaria']);

        $this->assertSame('Datos generales · Paso 2 de 4', ResumenTramite::encabezado('responsable'));
        $this->assertSame('Primaria · Paso 7 de 7', ResumenTramite::encabezado('infraestructura', $primaria));
        $this->assertSame('Primaria · Paso 5 de 7', ResumenTramite::encabezado('documentos_nivel', $primaria));
        $this->assertNull(ResumenTramite::encabezado('mobiliario', $primaria));
    }

    public function test_el_nombre_es_la_primera_propuesta_de_la_terna(): void
    {
        $escuela = $this->escuela();
        TernaNombre::create(['escuela_id' => $escuela->id, 'numero_propuesta' => 2, 'nombre_propuesto' => 'Colegio Segundo']);
        TernaNombre::create(['escuela_id' => $escuela->id, 'numero_propuesta' => 1, 'nombre_propuesto' => 'Colegio Primero']);

        $this->assertSame('Colegio Primero', $this->resumen($escuela)->nombre);
    }

    public function test_el_nombre_aprobado_gana_sobre_la_terna(): void
    {
        $escuela = $this->escuela();
        TernaNombre::create(['escuela_id' => $escuela->id, 'numero_propuesta' => 1, 'nombre_propuesto' => 'Colegio Propuesto']);
        $escuela->update(['nombre_aprobado' => 'Colegio Aprobado']);

        $this->assertSame('Colegio Aprobado', $this->resumen($escuela)->nombre);
    }

    public function test_sin_terna_no_hay_nombre(): void
    {
        $this->assertNull($this->resumen($this->escuela())->nombre);
    }

    public function test_iniciado_el_es_la_creacion_de_la_escuela(): void
    {
        $escuela = $this->escuela();
        $escuela->forceFill(['created_at' => '2026-09-20 10:00:00'])->save();

        $this->assertSame('2026-09-20', $this->resumen($escuela)->iniciadoEl->format('Y-m-d'));
    }

    public function test_numero_es_el_id_a_cuatro_digitos(): void
    {
        $dto = fn (int $id) => new ResumenTramiteDTO($id, '', [], [], [], false, null, now());

        $this->assertSame('0021', $dto(21)->numero());
        $this->assertSame('12345', $dto(12345)->numero());
    }

    /** Owner feedback 2026-09-25: dev data trae colonia/calle con espacios de sobra. */
    public function test_domicilio_recorta_espacios_en_los_campos_del_plantel(): void
    {
        $plantel = Plantel::create([
            'calle' => 'Calle 1',
            'numero_ext' => '10',
            'colonia' => 'Fraccionamiento Piramides ',
            'municipio' => 'El Pueblito',
            'codigo_postal' => '76000',
        ]);

        $this->assertSame('Calle 1 #10, Fraccionamiento Piramides, El Pueblito, C.P. 76000', ResumenTramite::domicilio($plantel));
    }

    /** Same bug class as the trim above (B3), but codigo_postal was missed — Task 10 fix round 1. */
    public function test_domicilio_recorta_espacios_en_el_codigo_postal(): void
    {
        $plantel = Plantel::create([
            'calle' => 'Calle 1',
            'numero_ext' => '10',
            'colonia' => 'Centro',
            'municipio' => 'Querétaro',
            'codigo_postal' => ' 76908 ',
        ]);

        $this->assertSame('Calle 1 #10, Centro, Querétaro, C.P. 76908', ResumenTramite::domicilio($plantel));
    }

    public function test_avance_y_siguiente_seccion(): void
    {
        $escuela = $this->escuela();

        $nuevo = $this->resumen($escuela);
        $this->assertSame(['hechas' => 1, 'total' => 4, 'porcentaje' => 25], $nuevo->avance());
        $this->assertSame('responsable', $nuevo->siguiente()?->clave);

        $this->completarPaso2($escuela->id);
        $this->nivelListo($escuela, 'inicial', 'inmueble');

        $conNivel = $this->resumen($escuela);
        // 4 generales + Documentos del nivel + inmueble completos; total 4 + documentos_nivel/inmueble/infraestructura/mobiliario (Inicial).
        $this->assertSame(['hechas' => 6, 'total' => 8, 'porcentaje' => 75], $conNivel->avance());
        $this->assertSame('infraestructura', $conNivel->siguiente()?->clave);
    }

    public function test_sin_documentos_del_nivel_inmueble_queda_bloqueado(): void
    {
        $escuela = $this->escuela();
        $this->completarPaso2($escuela->id);
        $this->nivel($escuela, 'primaria');

        $inmueble = $this->porClave($this->resumen($escuela)->niveles[0]->secciones)['inmueble'];

        $this->assertSame('Completa primero: Documentos del nivel', $inmueble->motivoBloqueo);
        $this->assertNull($inmueble->accion);
        $this->assertNull($inmueble->href);
    }
}
