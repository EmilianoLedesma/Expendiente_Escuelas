<?php

namespace Tests\Feature\Application\Revision;

use App\Application\Documentos\DTO\DatosDocumento;
use App\Application\Documentos\RegistrarDocumento;
use App\Application\ResponsableLegal\DTO\DatosResponsableLegal;
use App\Application\ResponsableLegal\RegistrarResponsableLegal;
use App\Application\Revision\ContenidoPasoRevision;
use App\Models\AsignacionRevision;
use App\Models\Asignatura;
use App\Models\AulaNivel;
use App\Models\CargoPuesto;
use App\Models\DocumentoEscuelaNivel;
use App\Models\EscuelaNivel;
use App\Models\Grado;
use App\Models\InmuebleEstudioActual;
use App\Models\InstalacionEspacio;
use App\Models\MatriculaGrado;
use App\Models\MatriculaSala;
use App\Models\MobiliarioNivel;
use App\Models\NivelEducativo;
use App\Models\Personal;
use App\Models\Sala;
use App\Models\Sanitario;
use App\Models\ServicioCercano;
use App\Models\TipoDocumento;
use App\Models\TipoEspacio;
use App\Models\User;
use Database\Seeders\AsignaturasSeeder;
use Database\Seeders\CargosPuestosSeeder;
use Database\Seeders\GradosSeeder;
use Database\Seeders\TiposDocumentosSeeder;
use Database\Seeders\TiposEspaciosSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Tests\Concerns\ConTramitesEnRevision;
use Tests\TestCase;

class ContenidoPasoRevisionTest extends TestCase
{
    use ConTramitesEnRevision;
    use RefreshDatabase;

    private EscuelaNivel $nivel;

    private User $sup;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('documentos');
        (new TiposDocumentosSeeder)->run();
        (new GradosSeeder)->run();
        (new CargosPuestosSeeder)->run();
        (new AsignaturasSeeder)->run();
        // Se captura mientras está en captura y luego se envía: las Actions de captura rechazan un trámite enviado.
        $this->nivel = $this->nivelEnRevision('en_captura');
        (new RegistrarResponsableLegal)->ejecutar($this->nivel->escuela_id, new DatosResponsableLegal(tipoPersona: 'fisica', domicilioNotificaciones: 'Calle 1', nombre: 'Juana Pérez'));
        (new RegistrarDocumento)->ejecutar($this->nivel->escuela_id, 'ine', UploadedFile::fake()->createWithContent('ine.pdf', 'X'), new DatosDocumento);
        DocumentoEscuelaNivel::create(['escuela_nivel_id' => $this->nivel->id, 'tipo_documento_id' => TipoDocumento::where('clave', 'formato_solicitud')->value('id'), 'archivo_path' => 'escuela_nivel/x/solicitud.pdf']);
        $this->nivel->update(['folio_expediente' => 'FOLIO-1', 'estado_id' => DB::table('estados_expediente')->where('clave', 'en_revision')->value('id')]);
        $this->sup = $this->supervisor();
    }

    /** @return array{titulo: string, bloques: list<array{titulo: string, filas: list<array{0: string, 1: string}>, documentos: list<array{nombre: string, archivo: string, href: string}>}>} */
    private function contenido(string $clave): array
    {
        return app(ContenidoPasoRevision::class)->ejecutar($this->sup->id, $this->nivel->id, $clave);
    }

    public function test_plantel_devuelve_las_filas_del_domicilio_y_contacto(): void
    {
        $c = $this->contenido('plantel');

        $this->assertSame('Datos del plantel', $c['titulo']);
        $this->assertCount(1, $c['bloques']);
        $this->assertSame(['Domicilio', 'Localidad', 'Teléfono', 'Correo electrónico'], array_column($c['bloques'][0]['filas'], 0));
        $this->assertStringContainsString($this->nivel->escuela->plantel->calle, $c['bloques'][0]['filas'][0][1]);
        $this->assertSame([], $c['bloques'][0]['documentos']);
    }

    public function test_responsable_devuelve_sus_filas(): void
    {
        $filas = $this->contenido('responsable')['bloques'][0]['filas'];

        $this->assertContains(['Nombre', 'Juana Pérez'], $filas);
        $this->assertContains(['Tipo de persona', 'Persona física'], $filas);
    }

    public function test_responsable_sin_datos_devuelve_sin_bloques(): void
    {
        $this->nivel = $this->nivelEnRevision();

        $this->assertSame([], $this->contenido('responsable')['bloques']);
    }

    public function test_documentos_lista_los_del_plantel_con_su_descarga(): void
    {
        $docs = $this->contenido('documentos')['bloques'][0]['documentos'];

        $this->assertCount(1, $docs);
        $this->assertStringStartsWith('ine', $docs[0]['archivo']);
        $this->assertSame(route('tramite.paso2-documentos.descargar', ['escuela' => $this->nivel->escuela_id, 'clave' => 'ine']), $docs[0]['href']);
    }

    public function test_niveles_lista_los_niveles_de_la_escuela_con_su_estado(): void
    {
        $this->nivelEn($this->nivel->escuela, 'secundaria', 'en_revision');

        $this->assertSame([['Primaria', 'En revisión'], ['Secundaria', 'En revisión']], $this->contenido('niveles')['bloques'][0]['filas']);
    }

    public function test_documentos_nivel_lista_solo_los_documentos_de_ese_nivel(): void
    {
        $otro = $this->nivelEn($this->nivel->escuela, 'secundaria', 'en_revision');
        DocumentoEscuelaNivel::create(['escuela_nivel_id' => $otro->id, 'tipo_documento_id' => TipoDocumento::where('clave', 'formato_solicitud')->value('id'), 'archivo_path' => 'escuela_nivel/y/otro.pdf']);

        $docs = $this->contenido('documentos_nivel')['bloques'][0]['documentos'];

        $this->assertCount(1, $docs);
        $this->assertSame(route('tramite.paso2-nivel-documentos.descargar', ['escuelaNivel' => $this->nivel->id, 'clave' => 'formato_solicitud']), $docs[0]['href']);
    }

    public function test_clave_que_aun_no_tiene_contenido_lanza_invalid_argument(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->contenido('paso_inexistente');
    }

    public function test_inmueble_muestra_dimensiones_colindancias_y_servicios(): void
    {
        $plantel = $this->nivel->escuela->plantel;
        $plantel->update(['metros_totales' => 500, 'metros_construidos' => 320.5, 'area_civica_m2' => 40, 'tiene_asta_bandera' => true, 'colindancia_norte' => 'Calle Hidalgo', 'latitud' => 20.5888, 'longitud' => -100.3899]);
        ServicioCercano::create(['plantel_id' => $plantel->id, 'nombre' => 'Hospital General', 'tipo' => 'salud', 'es_publico' => true, 'distancia_valor' => 1.5, 'distancia_unidad' => 'km']);
        InmuebleEstudioActual::create(['plantel_id' => $plantel->id, 'nivel_educativo_id' => NivelEducativo::where('clave', 'preescolar')->value('id'), 'numero_alumnos' => 30]);
        InmuebleEstudioActual::create(['plantel_id' => $plantel->id, 'otro_nivel_texto' => 'Taller de idiomas', 'numero_alumnos' => 12]);

        $c = $this->contenido('inmueble');
        $bloques = array_column($c['bloques'], null, 'titulo');

        $this->assertSame('Datos del inmueble', $c['titulo']);
        $this->assertSame(['Dimensiones', 'Colindancias', 'Ubicación geográfica', 'Instituciones de salud y emergencia cercanas', 'Estudios que el inmueble ya imparte'], array_keys($bloques));
        $this->assertContains(['Superficie del predio (m²)', '500.00 m²'], $bloques['Dimensiones']['filas']);
        $this->assertContains(['Superficie construida (m²)', '320.50 m²'], $bloques['Dimensiones']['filas']);
        $this->assertContains(['Cuenta con asta bandera', 'Sí'], $bloques['Dimensiones']['filas']);
        $this->assertSame([['Norte', 'Calle Hidalgo'], ['Sur', 'Sin dato'], ['Este', 'Sin dato'], ['Oeste', 'Sin dato']], $bloques['Colindancias']['filas']);
        $this->assertSame([['Hospital General', 'Salud · Pública · 1.50 km']], $bloques['Instituciones de salud y emergencia cercanas']['filas']);
        $this->assertSame([['Preescolar', '30 alumnos'], ['Taller de idiomas', '12 alumnos']], $bloques['Estudios que el inmueble ya imparte']['filas']);
    }

    public function test_infraestructura_muestra_espacios_sanitarios_y_aulas(): void
    {
        (new TiposEspaciosSeeder)->run();
        $plantelId = $this->nivel->escuela->plantel_id;
        $campo = InstalacionEspacio::create(['plantel_id' => $plantelId, 'tipo_espacio_id' => TipoEspacio::where('clave', 'campo_futbol')->value('id'), 'cantidad' => 1, 'superficie_m2' => 900, 'ventilacion_natural' => true, 'iluminacion_natural' => false]);
        DB::table('campos_futbol')->insert(['instalacion_espacio_id' => $campo->id, 'tipo_superficie' => 'Pasto', 'formato' => '7']);
        $biblio = InstalacionEspacio::create(['plantel_id' => $plantelId, 'tipo_espacio_id' => TipoEspacio::where('clave', 'biblioteca')->value('id'), 'cantidad' => 1, 'superficie_m2' => 60]);
        DB::table('biblioteca_materiales')->insert(['instalacion_espacio_id' => $biblio->id, 'tipo_material_id' => DB::table('tipos_material_biblioteca')->where('clave', 'libros')->value('id'), 'numero_titulos' => 200, 'numero_volumenes' => 350]);
        InstalacionEspacio::create(['plantel_id' => $plantelId, 'tipo_espacio_id' => TipoEspacio::where('clave', 'direccion')->value('id'), 'cantidad' => 1, 'superficie_m2' => 15.5, 'capacidad_promedio' => 4]);
        Sanitario::create(['plantel_id' => $plantelId, 'categoria' => 'alumnado_femenino', 'cantidad_retretes' => 6, 'cantidad_mingitorios' => 0, 'cantidad_lavabos' => 4, 'superficie_m2' => 18]);
        AulaNivel::create(['escuela_nivel_id' => $this->nivel->id, 'numero_aulas' => 8, 'superficie_m2' => 420]);

        $c = $this->contenido('infraestructura');
        $bloques = array_column($c['bloques'], null, 'titulo');

        $this->assertSame('Infraestructura', $c['titulo']);
        $this->assertSame(['Espacios administrativos', 'Actividades físicas y recreativas', 'Instalaciones especiales', 'Sanitarios', 'Aulas del nivel'], array_keys($bloques));
        $this->assertSame([['Dirección', 'Cantidad: 1 · Superficie: 15.50 m² · Capacidad: 4']], $bloques['Espacios administrativos']['filas']);
        $this->assertSame([['Campo de fútbol', 'Cantidad: 1 · Superficie: 900.00 m² · Ventilación natural: Sí · Iluminación natural: No · Tipo de superficie: Pasto · Formato: 7']], $bloques['Actividades físicas y recreativas']['filas']);
        $this->assertSame([
            ['Centro de documentación / Biblioteca', 'Cantidad: 1 · Superficie: 60.00 m²'],
            ['Libros', '200 títulos · 350 volúmenes'],
        ], $bloques['Instalaciones especiales']['filas']);
        $this->assertSame([['Alumnado femenino', 'Retretes: 6 · Mingitorios: 0 · Lavabos: 4 · Superficie: 18.00 m²']], $bloques['Sanitarios']['filas']);
        $this->assertSame([['Número de aulas', '8'], ['Superficie total (m²)', '420.00 m²']], $bloques['Aulas del nivel']['filas']);
    }

    public function test_mobiliario_muestra_conceptos_por_sala(): void
    {
        $this->nivel = $this->nivelEn($this->nivel->escuela, 'inicial', 'en_revision');
        $sala = Sala::orderBy('orden')->first();
        $conSala = DB::table('mobiliario_conceptos')->insertGetId(['sala_id' => $sala->id, 'nombre' => 'Cuna', 'tipo_ratio' => 'fijo_por_sala', 'valor_ratio' => 1]);
        $sinSala = DB::table('mobiliario_conceptos')->insertGetId(['sala_id' => null, 'nombre' => 'Pizarrón', 'tipo_ratio' => 'fijo_por_sala', 'valor_ratio' => 1]);
        DB::table('mobiliario_conceptos')->insert(['sala_id' => $sala->id, 'nombre' => 'Sin declarar', 'tipo_ratio' => 'fijo_por_sala', 'valor_ratio' => 1]);
        MobiliarioNivel::create(['escuela_nivel_id' => $this->nivel->id, 'concepto_id' => $conSala, 'cantidad_declarada' => 6]);
        MobiliarioNivel::create(['escuela_nivel_id' => $this->nivel->id, 'concepto_id' => $sinSala, 'cantidad_declarada' => 2]);

        $c = $this->contenido('mobiliario');

        $this->assertSame('Mobiliario', $c['titulo']);
        $this->assertSame([$sala->nombre, 'Sala de Usos Múltiples'], array_column($c['bloques'], 'titulo'));
        $this->assertSame([['Cuna', '6']], $c['bloques'][0]['filas']);
        $this->assertSame([['Pizarrón', '2']], $c['bloques'][1]['filas']);
    }

    public function test_mobiliario_en_primaria_es_invalido(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->contenido('mobiliario');
    }

    public function test_plan_estudios_lista_el_plan_del_nivel(): void
    {
        $this->nivel->update(['modalidad' => 'mixta', 'plataforma_educativa_tipo' => 'rentada', 'plan_estudios_referencia' => 'Plan SEP 2022', 'turno' => 'matutino', 'tipo_alumnado' => 'mixto']);

        $c = $this->contenido('plan_estudios');

        $this->assertSame('Plan de estudios', $c['titulo']);
        $this->assertCount(1, $c['bloques']);
        $this->assertSame([
            ['Modalidad', 'Mixta'],
            ['Plataforma educativa', 'Rentada'],
            ['Plan de estudios', 'Plan SEP 2022'],
            ['Turno', 'Matutino'],
            ['Tipo de alumnado', 'Mixto'],
        ], $c['bloques'][0]['filas']);
    }

    public function test_plan_estudios_sin_captura_devuelve_sin_bloques(): void
    {
        $this->nivel->update(['modalidad' => null]);

        $this->assertSame([], $this->contenido('plan_estudios')['bloques']);
    }

    public function test_plantilla_muestra_personal_con_cargo_y_asignaturas(): void
    {
        $cargo = CargoPuesto::where('nivel_educativo_id', $this->nivel->nivel_educativo_id)->orderBy('id')->firstOrFail();
        $asignatura = Asignatura::orderBy('nombre')->firstOrFail();
        $p = Personal::create(['escuela_nivel_id' => $this->nivel->id, 'cargo_puesto_id' => $cargo->id, 'nombre' => 'Ana Ruiz', 'nacionalidad' => 'Mexicana', 'sexo' => 'F', 'estudios' => 'Licenciatura en Pedagogía', 'cedula_o_documento' => 'CED-123']);
        DB::table('personal_asignaturas')->insert(['personal_id' => $p->id, 'asignatura_id' => $asignatura->id]);

        $c = $this->contenido('plantilla_docente');

        $this->assertSame('Plantilla docente', $c['titulo']);
        $this->assertCount(1, $c['bloques']);
        $this->assertSame("{$cargo->nombre} · Ana Ruiz", $c['bloques'][0]['titulo']);
        $this->assertSame([
            ['Nacionalidad', 'Mexicana'],
            ['Sexo', 'F'],
            ['Estudios', 'Licenciatura en Pedagogía'],
            ['Cédula profesional o documento académico', 'CED-123'],
            ['Asignatura que imparte', $asignatura->nombre],
        ], $c['bloques'][0]['filas']);
    }

    public function test_plantilla_marca_registros_incompletos(): void
    {
        Personal::create(['escuela_nivel_id' => $this->nivel->id, 'nombre' => 'Borrador Sin Cargo']);

        $bloque = $this->contenido('plantilla_docente')['bloques'][0];

        $this->assertSame('Sin cargo · Borrador Sin Cargo', $bloque['titulo']);
        $this->assertSame(['Registro incompleto', 'Faltan datos obligatorios'], $bloque['filas'][0]);
        $this->assertContains(['Estudios', 'Sin dato'], $bloque['filas']);
    }

    public function test_matricula_muestra_alumnos_por_grado_o_sala(): void
    {
        $g = Grado::where('nivel_educativo_id', $this->nivel->nivel_educativo_id)->orderBy('orden')->get();
        MatriculaGrado::create(['escuela_nivel_id' => $this->nivel->id, 'grado_id' => $g[1]->id, 'grupo' => 'B', 'cantidad_alumnos' => 20]);
        MatriculaGrado::create(['escuela_nivel_id' => $this->nivel->id, 'grado_id' => $g[0]->id, 'grupo' => 'A', 'cantidad_alumnos' => 25]);

        $c = $this->contenido('matricula');

        $this->assertSame('Matrícula', $c['titulo']);
        $this->assertSame('Alumnos proyectados por grado y grupo', $c['bloques'][0]['titulo']);
        $this->assertSame([["{$g[0]->nombre} · Grupo A", '25'], ["{$g[1]->nombre} · Grupo B", '20'], ['Total', '45']], $c['bloques'][0]['filas']);

        $this->nivel = $this->nivelEn($this->nivel->escuela, 'inicial', 'en_revision');
        $sala = Sala::orderBy('orden')->first();
        MatriculaSala::create(['escuela_nivel_id' => $this->nivel->id, 'sala_id' => $sala->id, 'cantidad_alumnos' => 12]);

        $porSala = $this->contenido('matricula')['bloques'][0];

        $this->assertSame('Alumnos proyectados por sala', $porSala['titulo']);
        $this->assertSame([[$sala->nombre, '12'], ['Total', '12']], $porSala['filas']);
    }

    public function test_plantilla_y_matricula_no_mezclan_niveles_hermanos(): void
    {
        $otro = $this->nivelEn($this->nivel->escuela, 'secundaria', 'en_revision');
        $cargo = CargoPuesto::where('nivel_educativo_id', $otro->nivel_educativo_id)->firstOrFail();
        $p = Personal::create(['escuela_nivel_id' => $otro->id, 'cargo_puesto_id' => $cargo->id, 'nombre' => 'Hermano', 'nacionalidad' => 'MX', 'sexo' => 'M', 'estudios' => 'X', 'cedula_o_documento' => 'Y']);
        DB::table('personal_asignaturas')->insert(['personal_id' => $p->id, 'asignatura_id' => Asignatura::value('id')]);
        MatriculaGrado::create(['escuela_nivel_id' => $otro->id, 'grado_id' => Grado::where('nivel_educativo_id', $otro->nivel_educativo_id)->value('id'), 'grupo' => 'A', 'cantidad_alumnos' => 99]);

        $this->assertSame([], $this->contenido('plantilla_docente')['bloques']);
        $this->assertSame([], $this->contenido('matricula')['bloques']);
    }

    public function test_revisor_ajeno_no_puede_ver_el_contenido(): void
    {
        $this->expectException(AuthorizationException::class);

        app(ContenidoPasoRevision::class)->ejecutar($this->revisor()->id, $this->nivel->id, 'plantel');
    }

    public function test_revisor_asignado_puede_ver_el_contenido(): void
    {
        $revisor = $this->revisor();
        AsignacionRevision::create(['escuela_nivel_id' => $this->nivel->id, 'revisor_id' => $revisor->id, 'asignado_por' => $this->sup->id, 'asignado_at' => now()]);

        $this->assertSame('Datos del plantel', app(ContenidoPasoRevision::class)->ejecutar($revisor->id, $this->nivel->id, 'plantel')['titulo']);
    }
}
