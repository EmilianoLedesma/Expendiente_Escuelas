<?php

namespace Tests\Feature\Application\ResponsableLegal;

use App\Application\Excepciones\DatosInvalidos;
use App\Application\ResponsableLegal\DTO\DatosResponsableLegal;
use App\Application\ResponsableLegal\RegistrarResponsableLegal;
use App\Application\Tramite\EstadoPaso2;
use App\Models\DocumentoEscuela;
use App\Models\DocumentoEscuelaNivel;
use App\Models\Escuela;
use App\Models\EscuelaNivel;
use App\Models\Gestor;
use App\Models\NivelEducativo;
use App\Models\PersonaFisica;
use App\Models\PersonaMoral;
use App\Models\Plantel;
use App\Models\ResponsableLegal;
use App\Models\Solicitante;
use App\Models\TernaNombre;
use App\Models\TipoDocumento;
use Database\Seeders\TiposDocumentosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Concerns\CompletaPaso2;
use Tests\TestCase;

class RegistrarResponsableLegalTest extends TestCase
{
    use CompletaPaso2;
    use RefreshDatabase;

    private function crearEscuela(): Escuela
    {
        $solicitante = Solicitante::factory()->create();
        $plantel = Plantel::create(['calle' => 'Calle 1', 'colonia' => 'Centro', 'municipio' => 'Querétaro', 'codigo_postal' => '76000']);

        return Escuela::create(['plantel_id' => $plantel->id, 'solicitante_id' => $solicitante->id]);
    }

    /** Estado completo y válido de un responsable del tipo dado; $cambios sustituye campos (nombres de argumento del DTO). */
    private function datos(string $tipo, array $cambios = []): DatosResponsableLegal
    {
        return new DatosResponsableLegal(...[
            'tipoPersona' => $tipo,
            'domicilioNotificaciones' => 'Calle Falsa 123, Centro',
            'personaAutorizadaRecoger' => 'María López',
            'nombrePropuesto1' => 'Colegio Reforma',
            'nombrePropuesto2' => 'Instituto Reforma',
            'nombrePropuesto3' => 'Escuela Reforma',
            'nombre' => $tipo === 'moral' ? null : 'Juana Pérez',
            'rfc' => $tipo === 'moral' ? null : 'PEJU800101ABC',
            'razonSocial' => $tipo === 'moral' ? 'Colegio Reforma S.C.' : null,
            'nombreRepresentanteLegal' => $tipo === 'moral' ? 'Miguel Torres' : null,
            'gestorNombre' => $tipo === 'fisica_con_gestor' ? 'Carlos Gómez' : null,
            ...$cambios,
        ]);
    }

    /** Nivel con un Formato firmado subido (fila + archivo en el disco falso). Devuelve la ruta. */
    private function nivelConFormato(Escuela $escuela, string $nivelClave): string
    {
        $nivel = EscuelaNivel::create([
            'escuela_id' => $escuela->id,
            'nivel_educativo_id' => NivelEducativo::where('clave', $nivelClave)->value('id'),
            'estado_id' => DB::table('estados_expediente')->where('clave', 'en_captura')->value('id'),
            'tipo_tramite' => 'alta_nueva',
        ]);
        $ruta = "escuela_nivel/{$nivel->id}/formato_solicitud-FIRMADO.pdf";
        Storage::disk('documentos')->put($ruta, 'FORMATO-FIRMADO');
        DocumentoEscuelaNivel::create([
            'escuela_nivel_id' => $nivel->id,
            'tipo_documento_id' => TipoDocumento::where('clave', 'formato_solicitud')->value('id'),
            'archivo_path' => $ruta,
        ]);

        return $ruta;
    }

    public function test_tipo_fisica_crea_responsable_y_persona_fisica(): void
    {
        $escuela = $this->crearEscuela();

        (new RegistrarResponsableLegal)->ejecutar($escuela->id, new DatosResponsableLegal(
            tipoPersona: 'fisica',
            nombre: 'Juana Pérez',
            rfc: 'PEJU800101ABC',
        ));

        $this->assertDatabaseHas('responsables_legales', ['escuela_id' => $escuela->id, 'tipo_persona' => 'fisica']);
        $this->assertDatabaseHas('personas_fisicas', ['nombre' => 'Juana Pérez', 'rfc' => 'PEJU800101ABC']);
        $this->assertDatabaseCount('personas_morales', 0);
        $this->assertDatabaseCount('gestores', 0);
    }

    public function test_tipo_fisica_con_gestor_crea_los_tres_registros(): void
    {
        $escuela = $this->crearEscuela();

        (new RegistrarResponsableLegal)->ejecutar($escuela->id, new DatosResponsableLegal(
            tipoPersona: 'fisica_con_gestor',
            nombre: 'Juana Pérez',
            gestorNombre: 'Carlos Gómez',
            gestorNumeroPoder: 'NP-100',
        ));

        $this->assertDatabaseHas('personas_fisicas', ['nombre' => 'Juana Pérez']);
        $this->assertDatabaseHas('gestores', ['nombre' => 'Carlos Gómez', 'numero_poder' => 'NP-100']);
    }

    public function test_tipo_moral_crea_responsable_y_persona_moral(): void
    {
        $escuela = $this->crearEscuela();

        (new RegistrarResponsableLegal)->ejecutar($escuela->id, new DatosResponsableLegal(
            tipoPersona: 'moral',
            razonSocial: 'Colegio Reforma S.C.',
            nombreRepresentanteLegal: 'Miguel Torres',
        ));

        $this->assertDatabaseHas('responsables_legales', ['escuela_id' => $escuela->id, 'tipo_persona' => 'moral']);
        $this->assertDatabaseHas('personas_morales', ['razon_social' => 'Colegio Reforma S.C.', 'nombre_representante_legal' => 'Miguel Torres']);
        $this->assertDatabaseCount('personas_fisicas', 0);
    }

    public function test_guarda_la_terna_de_nombres_propuestos(): void
    {
        $escuela = $this->crearEscuela();

        (new RegistrarResponsableLegal)->ejecutar($escuela->id, $this->datos('fisica'));

        $this->assertDatabaseHas('ternas_nombres', ['escuela_id' => $escuela->id, 'numero_propuesta' => 1, 'nombre_propuesto' => 'Colegio Reforma']);
        $this->assertDatabaseHas('ternas_nombres', ['escuela_id' => $escuela->id, 'numero_propuesta' => 2, 'nombre_propuesto' => 'Instituto Reforma']);
        $this->assertDatabaseHas('ternas_nombres', ['escuela_id' => $escuela->id, 'numero_propuesta' => 3, 'nombre_propuesto' => 'Escuela Reforma']);
        $this->assertDatabaseCount('ternas_nombres', 3);
    }

    /** Reescrito (WS-7b-1): antes "un segundo envío no duplica la terna" porque era un no-op; ahora edita en el lugar. */
    public function test_editar_la_terna_actualiza_las_mismas_tres_filas(): void
    {
        $escuela = $this->crearEscuela();
        (new RegistrarResponsableLegal)->ejecutar($escuela->id, $this->datos('fisica'));
        $ids = TernaNombre::where('escuela_id', $escuela->id)->orderBy('numero_propuesta')->pluck('id')->all();

        (new RegistrarResponsableLegal)->ejecutar($escuela->id, $this->datos('fisica', ['nombrePropuesto2' => 'Liceo Reforma']));

        $this->assertDatabaseCount('ternas_nombres', 3);
        $this->assertSame($ids, TernaNombre::where('escuela_id', $escuela->id)->orderBy('numero_propuesta')->pluck('id')->all());
        $this->assertDatabaseHas('ternas_nombres', ['id' => $ids[1], 'nombre_propuesto' => 'Liceo Reforma']);
    }

    public function test_editar_la_terna_conserva_las_marcas_de_validez(): void
    {
        $escuela = $this->crearEscuela();
        (new RegistrarResponsableLegal)->ejecutar($escuela->id, $this->datos('fisica'));
        TernaNombre::where('escuela_id', $escuela->id)->update(['valido_marca_comercial' => true, 'valido_registro_sedeq' => false]);

        (new RegistrarResponsableLegal)->ejecutar($escuela->id, $this->datos('fisica', ['nombrePropuesto1' => 'Liceo Reforma']));

        $this->assertDatabaseHas('ternas_nombres', ['numero_propuesta' => 1, 'nombre_propuesto' => 'Liceo Reforma', 'valido_marca_comercial' => true, 'valido_registro_sedeq' => false]);
    }

    /** La regla de la terna vive en el caso de uso (WS-7b-1): mayúsculas y espacios no la distinguen. */
    public function test_rechaza_una_terna_con_nombres_repetidos_sin_escribir(): void
    {
        $escuela = $this->crearEscuela();

        try {
            (new RegistrarResponsableLegal)->ejecutar($escuela->id, $this->datos('fisica', ['nombrePropuesto3' => '  colegio   REFORMA ']));
            $this->fail('Se esperaba DatosInvalidos.');
        } catch (DatosInvalidos $e) {
            $this->assertSame(['nombrePropuesto3' => RegistrarResponsableLegal::TERNA_REPETIDA], $e->errores);
        }

        $this->assertDatabaseCount('responsables_legales', 0);
        $this->assertDatabaseCount('ternas_nombres', 0);
    }

    public function test_tipo_desconocido_lanza_excepcion_sin_escribir_nada(): void
    {
        $escuela = $this->crearEscuela();

        try {
            (new RegistrarResponsableLegal)->ejecutar($escuela->id, new DatosResponsableLegal(tipoPersona: 'invalido'));
            $this->fail('Se esperaba InvalidArgumentException.');
        } catch (InvalidArgumentException $e) {
            $this->assertDatabaseCount('responsables_legales', 0);
        }
    }

    /** Reescrito (WS-7b-1): antes "un segundo envío es un no-op"; ahora actualiza en el lugar, una fila por tabla. */
    public function test_un_segundo_envio_actualiza_en_el_lugar(): void
    {
        $escuela = $this->crearEscuela();
        (new RegistrarResponsableLegal)->ejecutar($escuela->id, $this->datos('fisica'));
        $id = ResponsableLegal::where('escuela_id', $escuela->id)->value('id');

        (new RegistrarResponsableLegal)->ejecutar($escuela->id, $this->datos('fisica', [
            'nombre' => 'Juana Pérez Gómez',
            'domicilioNotificaciones' => 'Av. Universidad 200',
            'personaAutorizadaRecoger' => 'Luis Ramos',
        ]));

        $this->assertDatabaseCount('responsables_legales', 1);
        $this->assertDatabaseCount('personas_fisicas', 1);
        $this->assertDatabaseHas('responsables_legales', ['id' => $id, 'domicilio_notificaciones' => 'Av. Universidad 200', 'persona_autorizada_recoger' => 'Luis Ramos']);
        $this->assertDatabaseHas('personas_fisicas', ['responsable_legal_id' => $id, 'nombre' => 'Juana Pérez Gómez']);
    }

    public function test_guarda_la_curp_del_gestor(): void
    {
        $escuela = $this->crearEscuela();

        (new RegistrarResponsableLegal)->ejecutar($escuela->id, new DatosResponsableLegal(
            tipoPersona: 'fisica_con_gestor',
            nombre: 'Ana López',
            gestorNombre: 'Carlos Gómez',
            gestorCurp: 'GOMC800101HQTMRR01',
        ));

        $this->assertDatabaseHas('gestores', ['nombre' => 'Carlos Gómez', 'curp' => 'GOMC800101HQTMRR01']);
    }

    /** @return array<string, array{string, string, array{int, int, int}, ?string}> de, a, [personas_fisicas, gestores, personas_morales], etapa de Paso 2 esperada */
    public static function transiciones(): array
    {
        return [
            'física → moral' => ['fisica', 'moral', [0, 0, 1], EstadoPaso2::DOCUMENTOS],
            'moral → física' => ['moral', 'fisica', [1, 0, 0], null],
            'física → física con gestor' => ['fisica', 'fisica_con_gestor', [1, 1, 0], EstadoPaso2::DOCUMENTOS],
            'física con gestor → física' => ['fisica_con_gestor', 'fisica', [1, 0, 0], null],
            'moral → física con gestor' => ['moral', 'fisica_con_gestor', [1, 1, 0], EstadoPaso2::DOCUMENTOS],
            'física con gestor → moral' => ['fisica_con_gestor', 'moral', [0, 0, 1], EstadoPaso2::DOCUMENTOS],
        ];
    }

    /** Spec §3.5: matriz de transiciones; los documentos nunca se tocan y Paso 2 se re-deriva. */
    #[DataProvider('transiciones')]
    public function test_cambio_de_tipo_de_persona(string $de, string $a, array $filas, ?string $etapa): void
    {
        $escuela = $this->crearEscuela();
        $this->completarPaso2($escuela->id, $de);
        $documentos = DocumentoEscuela::where('escuela_id', $escuela->id)->count();

        (new RegistrarResponsableLegal)->ejecutar($escuela->id, $this->datos($a));

        $this->assertDatabaseCount('responsables_legales', 1);
        $this->assertSame($a, ResponsableLegal::where('escuela_id', $escuela->id)->value('tipo_persona'));
        $this->assertSame($filas, [PersonaFisica::count(), Gestor::count(), PersonaMoral::count()]);
        $this->assertSame($documentos, DocumentoEscuela::where('escuela_id', $escuela->id)->count(), 'ningún documento subido se borra');
        $this->assertSame($etapa, app(EstadoPaso2::class)->etapaFaltante($escuela->id));
    }

    /** Owner decision: los documentos del tipo anterior quedan ocultos y vuelven a contar al regresar. */
    public function test_los_documentos_del_tipo_anterior_se_conservan_y_vuelven_a_contar(): void
    {
        $escuela = $this->crearEscuela();
        $this->completarPaso2($escuela->id, 'fisica_con_gestor');
        $poderGestor = TipoDocumento::where('clave', 'poder_gestor')->value('id');
        $poder = fn () => DocumentoEscuela::where('escuela_id', $escuela->id)->where('tipo_documento_id', $poderGestor)->first();
        $this->assertNull(app(EstadoPaso2::class)->etapaFaltante($escuela->id));
        $ruta = $poder()->archivo_path;

        (new RegistrarResponsableLegal)->ejecutar($escuela->id, $this->datos('moral'));

        $this->assertSame('moral', ResponsableLegal::where('escuela_id', $escuela->id)->value('tipo_persona'));
        $this->assertSame(0, Gestor::count());
        $this->assertNotNull($poder(), 'el poder del gestor se conserva');
        Storage::disk('documentos')->assertExists($ruta);
        $this->assertSame(EstadoPaso2::DOCUMENTOS, app(EstadoPaso2::class)->etapaFaltante($escuela->id), 'faltan los documentos propios de moral');

        (new RegistrarResponsableLegal)->ejecutar($escuela->id, $this->datos('fisica_con_gestor'));

        $this->assertNull(app(EstadoPaso2::class)->etapaFaltante($escuela->id), 'al volver, el poder guardado cuenta otra vez');
        $this->assertNotNull($poder());
        Storage::disk('documentos')->assertExists($ruta);
    }

    /** Review Focus 5: un cambio de tipo que no pasa la validación no borra nada. */
    public function test_un_cambio_de_tipo_invalido_no_borra_el_subtipo_anterior(): void
    {
        $escuela = $this->crearEscuela();
        (new RegistrarResponsableLegal)->ejecutar($escuela->id, $this->datos('fisica'));

        try {
            (new RegistrarResponsableLegal)->ejecutar($escuela->id, $this->datos('moral', ['razonSocial' => null]));
            $this->fail('Se esperaba DatosInvalidos.');
        } catch (DatosInvalidos) {
        }

        $this->assertSame('fisica', ResponsableLegal::where('escuela_id', $escuela->id)->value('tipo_persona'));
        $this->assertDatabaseHas('personas_fisicas', ['nombre' => 'Juana Pérez']);
        $this->assertDatabaseCount('personas_morales', 0);
    }

    /** D1: un dato que imprime el Formato cambia → se descarta el Formato firmado de cada nivel, filas y archivos. */
    public function test_cambiar_un_dato_impreso_descarta_el_formato_de_cada_nivel(): void
    {
        Storage::fake('documentos');
        (new TiposDocumentosSeeder)->run();
        $escuela = $this->crearEscuela();
        (new RegistrarResponsableLegal)->ejecutar($escuela->id, $this->datos('fisica'));
        $rutas = [$this->nivelConFormato($escuela, 'preescolar'), $this->nivelConFormato($escuela, 'primaria')];

        (new RegistrarResponsableLegal)->ejecutar($escuela->id, $this->datos('fisica', ['domicilioNotificaciones' => 'Av. Universidad 200']));

        $this->assertDatabaseCount('documentos_escuela_nivel', 0);
        // afterCommit dispara al bajar al nivel de transacción del test (ver RegistrarDocumento).
        foreach ($rutas as $ruta) {
            Storage::disk('documentos')->assertMissing($ruta);
        }
    }

    /** @return array<string, array{string, array<string, string|null>}> */
    public static function cambiosQueNoSeImprimen(): array
    {
        return [
            'los mismos datos' => ['fisica', []],
            'solo la terna' => ['fisica', ['nombrePropuesto1' => 'Liceo Reforma']],
            'solo el gestor' => ['fisica_con_gestor', ['gestorNombre' => 'Pedro Ruiz']],
            'física → física con gestor' => ['fisica', ['tipoPersona' => 'fisica_con_gestor', 'gestorNombre' => 'Carlos Gómez']],
        ];
    }

    /** Review Focus 2: guardar sin cambiar nada impreso conserva el Formato firmado. */
    #[DataProvider('cambiosQueNoSeImprimen')]
    public function test_un_cambio_que_no_se_imprime_conserva_el_formato(string $tipo, array $cambios): void
    {
        Storage::fake('documentos');
        (new TiposDocumentosSeeder)->run();
        $escuela = $this->crearEscuela();
        (new RegistrarResponsableLegal)->ejecutar($escuela->id, $this->datos($tipo));
        $ruta = $this->nivelConFormato($escuela, 'primaria');

        (new RegistrarResponsableLegal)->ejecutar($escuela->id, $this->datos($tipo, $cambios));

        $this->assertDatabaseCount('documentos_escuela_nivel', 1);
        Storage::disk('documentos')->assertExists($ruta);
    }

    public function test_rollback_externo_conserva_el_formato_y_su_archivo(): void
    {
        Storage::fake('documentos');
        (new TiposDocumentosSeeder)->run();
        $escuela = $this->crearEscuela();
        (new RegistrarResponsableLegal)->ejecutar($escuela->id, $this->datos('fisica'));
        $ruta = $this->nivelConFormato($escuela, 'primaria');

        try {
            DB::transaction(function () use ($escuela) {
                (new RegistrarResponsableLegal)->ejecutar($escuela->id, $this->datos('fisica', ['nombre' => 'Otra Persona']));

                throw new RuntimeException('fuerza rollback de la transacción externa');
            });
            $this->fail('Se esperaba que la transacción externa fallara.');
        } catch (RuntimeException) {
        }

        $this->assertDatabaseCount('documentos_escuela_nivel', 1);
        Storage::disk('documentos')->assertExists($ruta);
        $this->assertDatabaseHas('personas_fisicas', ['nombre' => 'Juana Pérez']);
    }

    /** WS-7a: escuela_niveles se bloquea antes que la escuela. */
    public function test_bloquea_los_niveles_antes_que_la_escuela(): void
    {
        $escuela = $this->crearEscuela();
        (new RegistrarResponsableLegal)->ejecutar($escuela->id, $this->datos('fisica'));
        $sql = [];
        DB::listen(function ($query) use (&$sql) {
            $sql[] = $query->sql;
        });

        (new RegistrarResponsableLegal)->ejecutar($escuela->id, $this->datos('moral'));

        $primera = fn (callable $cumple): ?int => array_key_first(array_filter($sql, $cumple));
        $niveles = $primera(fn (string $s) => str_contains($s, 'from "escuela_niveles"') && str_ends_with($s, 'for update'));
        $escuelaBloqueada = $primera(fn (string $s) => str_contains($s, 'from "escuelas"') && str_ends_with($s, 'for share'));

        $this->assertNotNull($niveles);
        $this->assertNotNull($escuelaBloqueada);
        $this->assertLessThan($escuelaBloqueada, $niveles);
    }
}
