<?php

namespace App\Application\Revision;

use App\Application\Tramite\ResumenTramite;
use App\Domain\Personal\RegistroPersonalCompleto;
use App\Models\AulaNivel;
use App\Models\CargoPuesto;
use App\Models\EscuelaNivel;
use App\Models\Grado;
use App\Models\InmuebleEstudioActual;
use App\Models\InstalacionEspacio;
use App\Models\MatriculaGrado;
use App\Models\MatriculaSala;
use App\Models\MobiliarioConcepto;
use App\Models\MobiliarioNivel;
use App\Models\Personal;
use App\Models\Plantel;
use App\Models\Sala;
use App\Models\Sanitario;
use App\Models\ServicioCercano;
use App\Models\TipoEspacio;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Contenido de solo lectura de los pasos del Paso 3 para el revisor: exactamente lo que capturó el solicitante.
 * Lo invoca ContenidoPasoRevision, que ya verificó el acceso al nivel.
 */
final class ContenidoPaso3Revision
{
    public function __construct(private readonly RegistroPersonalCompleto $registroCompleto) {}

    /**
     * ATENCIÓN: no verifica acceso. Quien la llame debe haber comprobado antes que el actor puede ver el nivel
     * (ContenidoPasoRevision lo hace con AccesoSedeq); nunca exponerla directo a presentación.
     *
     * @return list<array{titulo: string, filas: list<array{0: string, 1: string}>, documentos: list<array{nombre: string, archivo: string, href: string}>}>
     *
     * @throws InvalidArgumentException si la clave no es de un paso del Paso 3 o no aplica al nivel.
     */
    public function ejecutar(int $escuelaNivelId, string $pasoClave): array
    {
        $nivel = EscuelaNivel::with(['nivelEducativo', 'escuela'])->findOrFail($escuelaNivelId);

        return match ($pasoClave) {
            'inmueble' => $this->inmueble($nivel->escuela->plantel_id),
            'infraestructura' => $this->infraestructura($nivel),
            'mobiliario' => $this->mobiliario($nivel),
            'plan_estudios' => $this->planEstudios($nivel),
            'plantilla_docente' => $this->plantilla($nivel->id),
            'matricula' => $this->matricula($nivel),
            default => throw new InvalidArgumentException("Paso sin contenido: {$pasoClave}"),
        };
    }

    /**
     * Lo que capturó DatosInmueble: es del plantel, igual que en la pantalla de captura.
     *
     * @return list<array{titulo: string, filas: list<array{0: string, 1: string}>, documentos: list<array{nombre: string, archivo: string, href: string}>}>
     */
    private function inmueble(int $plantelId): array
    {
        $p = Plantel::findOrFail($plantelId);
        $bloques = [];

        if ($p->metros_totales !== null) {
            $bloques[] = self::bloque('Dimensiones', [
                ['Superficie del predio (m²)', self::medida($p->metros_totales, 'm²')],
                ['Superficie construida (m²)', self::medida($p->metros_construidos, 'm²')],
                ['Área cívica (m²)', self::medida($p->area_civica_m2, 'm²')],
                ['Cuenta con asta bandera', self::siNo($p->tiene_asta_bandera)],
            ]);
        }

        $colindancias = ['Norte' => $p->colindancia_norte, 'Sur' => $p->colindancia_sur, 'Este' => $p->colindancia_este, 'Oeste' => $p->colindancia_oeste];
        if (array_filter($colindancias, fn ($v) => $v !== null) !== []) {
            $bloques[] = self::bloque('Colindancias', self::filas(array_map(fn ($v) => $v ?? 'Sin dato', $colindancias)));
        }

        if ($p->latitud !== null || $p->longitud !== null) {
            $bloques[] = self::bloque('Ubicación geográfica', [['Latitud', self::coordenada($p->latitud)], ['Longitud', self::coordenada($p->longitud)]]);
        }

        $servicios = ServicioCercano::where('plantel_id', $plantelId)->orderBy('id')->get();
        if ($servicios->isNotEmpty()) {
            $bloques[] = self::bloque('Instituciones de salud y emergencia cercanas', $servicios->map(fn ($s) => [$s->nombre, implode(' · ', array_filter([
                ucfirst($s->tipo),
                $s->es_publico === null ? null : ($s->es_publico ? 'Pública' : 'Privada'),
                $s->distancia_valor === null ? null : self::medida($s->distancia_valor, (string) $s->distancia_unidad),
            ]))])->all());
        }

        $estudios = InmuebleEstudioActual::with('nivelEducativo')->where('plantel_id', $plantelId)->orderBy('id')->get();
        if ($estudios->isNotEmpty()) {
            $bloques[] = self::bloque('Estudios que el inmueble ya imparte', $estudios->map(fn ($e) => [$e->nivelEducativo->nombre ?? (string) $e->otro_nivel_texto, "{$e->numero_alumnos} alumnos"])->all());
        }

        return $bloques;
    }

    /**
     * Lo que capturó InfraestructuraNivel: espacios y sanitarios del plantel, aulas del nivel.
     *
     * @return list<array{titulo: string, filas: list<array{0: string, 1: string}>, documentos: list<array{nombre: string, archivo: string, href: string}>}>
     */
    private function infraestructura(EscuelaNivel $nivel): array
    {
        $plantelId = $nivel->escuela->plantel_id;
        $espacios = InstalacionEspacio::where('plantel_id', $plantelId)->get();
        $tipos = TipoEspacio::whereIn('id', $espacios->pluck('tipo_espacio_id')->all())->get()->keyBy('id');
        $espacios = $espacios->sortBy(fn ($e) => $tipos[$e->tipo_espacio_id]->categoria.'|'.$tipos[$e->tipo_espacio_id]->nombre);
        $ids = $espacios->pluck('id')->all();
        $futbol = DB::table('campos_futbol')->whereIn('instalacion_espacio_id', $ids)->get()->keyBy('instalacion_espacio_id');
        $acervo = DB::table('biblioteca_materiales')->join('tipos_material_biblioteca', 'tipos_material_biblioteca.id', '=', 'tipo_material_id')
            ->whereIn('instalacion_espacio_id', $ids)->orderBy('tipo_material_id')->get(['instalacion_espacio_id', 'nombre', 'numero_titulos', 'numero_volumenes'])->groupBy('instalacion_espacio_id');

        $categorias = ['administrativo' => 'Espacios administrativos', 'cubiculo' => 'Cubículos', 'recreativo_deportivo' => 'Actividades físicas y recreativas', 'especial' => 'Instalaciones especiales'];
        $porCategoria = [];
        foreach ($espacios as $e) {
            $f = $futbol->get($e->id);
            $tipo = $tipos[$e->tipo_espacio_id];
            $porCategoria[$tipo->categoria][] = [$tipo->nombre, self::detalle([
                'Cantidad' => $e->cantidad,
                'Superficie' => $e->superficie_m2 === null ? null : self::medida($e->superficie_m2, 'm²'),
                'Capacidad' => $e->capacidad_promedio,
                'Destinado a' => $e->destinado_a,
                'Ventilación natural' => self::siNoONull($e->ventilacion_natural),
                'Iluminación natural' => self::siNoONull($e->iluminacion_natural),
                'Tipo de superficie' => $f->tipo_superficie ?? null,
                'Formato' => $f->formato ?? null,
            ])];
            foreach ($acervo->get($e->id, []) as $m) {
                $porCategoria[$tipo->categoria][] = [$m->nombre, ($m->numero_titulos ?? 'Sin dato').' títulos · '.($m->numero_volumenes ?? 'Sin dato').' volúmenes'];
            }
        }

        $bloques = [];
        foreach ($categorias as $clave => $titulo) {
            if (isset($porCategoria[$clave])) {
                $bloques[] = self::bloque($titulo, $porCategoria[$clave]);
            }
        }

        $sanitarios = Sanitario::where('plantel_id', $plantelId)->orderBy('categoria')->get();
        $bacinicas = DB::table('sanitarios_bacinicas')->whereIn('sanitario_id', $sanitarios->pluck('id')->all())->pluck('cantidad_bacinicas', 'sanitario_id');
        if ($sanitarios->isNotEmpty()) {
            $bloques[] = self::bloque('Sanitarios', $sanitarios->map(fn ($s) => [ucfirst(str_replace('_', ' ', $s->categoria)), self::detalle([
                'Retretes' => $s->cantidad_retretes,
                'Mingitorios' => $s->cantidad_mingitorios,
                'Lavabos' => $s->cantidad_lavabos,
                'Superficie' => $s->superficie_m2 === null ? null : self::medida($s->superficie_m2, 'm²'),
                'Bacinicas' => $bacinicas[$s->id] ?? null,
                'Ventilación natural' => self::siNoONull($s->ventilacion_natural),
                'Iluminación natural' => self::siNoONull($s->iluminacion_natural),
            ])])->all());
        }

        $aulas = AulaNivel::where('escuela_nivel_id', $nivel->id)->first();
        if ($aulas !== null) {
            $bloques[] = self::bloque('Aulas del nivel', [['Número de aulas', (string) $aulas->numero_aulas], ['Superficie total (m²)', self::medida($aulas->superficie_m2, 'm²')]]);
        }

        return $bloques;
    }

    /**
     * Lo que capturó MobiliarioNivel: un bloque por sala con sus conceptos declarados.
     *
     * @return list<array{titulo: string, filas: list<array{0: string, 1: string}>, documentos: list<array{nombre: string, archivo: string, href: string}>}>
     */
    private function mobiliario(EscuelaNivel $nivel): array
    {
        if ($nivel->nivelEducativo->clave !== ResumenTramite::NIVEL_CON_MOBILIARIO) {
            throw new InvalidArgumentException('El mobiliario solo aplica a Educación Inicial.');
        }

        $declarados = MobiliarioNivel::where('escuela_nivel_id', $nivel->id)->orderBy('concepto_id')->get();
        $conceptos = MobiliarioConcepto::whereIn('id', $declarados->pluck('concepto_id')->all())->get()->keyBy('id');
        $salas = Sala::orderBy('orden')->get();

        $bloques = [];
        foreach ([...$salas->all(), null] as $sala) {
            $filas = [];
            foreach ($declarados as $m) {
                if ($conceptos[$m->concepto_id]->sala_id === $sala?->id) {
                    $filas[] = [$conceptos[$m->concepto_id]->nombre, (string) $m->cantidad_declarada];
                }
            }
            if ($filas !== []) {
                $bloques[] = self::bloque($sala->nombre ?? 'Sala de Usos Múltiples', $filas);
            }
        }

        return $bloques;
    }

    /**
     * Lo que capturó PlanEstudios: modalidad y plan; turno y tipo de alumnado se capturan en Documentos del nivel.
     *
     * @return list<array{titulo: string, filas: list<array{0: string, 1: string}>, documentos: list<array{nombre: string, archivo: string, href: string}>}>
     */
    private function planEstudios(EscuelaNivel $nivel): array
    {
        if ($nivel->modalidad === null) {
            return [];
        }

        $filas = [['Modalidad', ['escolarizada' => 'Escolarizada', 'no_escolarizada' => 'No escolarizada', 'mixta' => 'Mixta', 'virtual' => 'Virtual'][$nivel->modalidad] ?? $nivel->modalidad]];
        if ($nivel->plataforma_educativa_tipo !== null) {
            $filas[] = ['Plataforma educativa', ['propia' => 'Propia', 'rentada' => 'Rentada'][$nivel->plataforma_educativa_tipo] ?? $nivel->plataforma_educativa_tipo];
        }
        $filas[] = ['Plan de estudios', $nivel->plan_estudios_referencia ?? 'Sin dato'];
        $filas[] = ['Turno', ['matutino' => 'Matutino', 'vespertino' => 'Vespertino', 'mixto' => 'Mixto'][(string) $nivel->turno] ?? 'Sin dato'];
        $filas[] = ['Tipo de alumnado', ['mixto' => 'Mixto', 'femenino' => 'Femenino', 'masculino' => 'Masculino'][(string) $nivel->tipo_alumnado] ?? 'Sin dato'];

        return [self::bloque('Plan de estudios y modalidad', $filas)];
    }

    /**
     * Lo que capturó PlantillaDocente: un bloque por persona. Un borrador (no pasa RegistroPersonalCompleto) se marca.
     *
     * @return list<array{titulo: string, filas: list<array{0: string, 1: string}>, documentos: list<array{nombre: string, archivo: string, href: string}>}>
     */
    private function plantilla(int $escuelaNivelId): array
    {
        $personas = Personal::where('escuela_nivel_id', $escuelaNivelId)->orderBy('id')->get();
        $ids = $personas->pluck('id')->all();
        $cargos = CargoPuesto::whereIn('id', $personas->pluck('cargo_puesto_id')->filter()->all())->pluck('nombre', 'id');
        $salas = DB::table('personal_salas')->join('salas', 'salas.id', '=', 'sala_id')->whereIn('personal_id', $ids)->pluck('salas.nombre', 'personal_id');
        $asignaturas = DB::table('personal_asignaturas')->join('asignaturas', 'asignaturas.id', '=', 'asignatura_id')->whereIn('personal_id', $ids)->pluck('asignaturas.nombre', 'personal_id');

        $bloques = [];
        foreach ($personas as $p) {
            $completo = $this->registroCompleto->esCompleto($p->cargo_puesto_id, $p->nombre, $p->nacionalidad, $p->sexo, $p->estudios, $p->cedula_o_documento);
            $filas = $completo ? [] : [['Registro incompleto', 'Faltan datos obligatorios']];
            array_push($filas,
                ['Nacionalidad', self::texto($p->nacionalidad)],
                ['Sexo', self::texto($p->sexo)],
                ['Estudios', self::texto($p->estudios)],
                ['Cédula profesional o documento académico', self::texto($p->cedula_o_documento)],
            );
            if (isset($salas[$p->id])) {
                $filas[] = ['Sala asignada', $salas[$p->id]];
            }
            if (isset($asignaturas[$p->id])) {
                $filas[] = ['Asignatura que imparte', $asignaturas[$p->id]];
            }
            $bloques[] = self::bloque(($cargos[$p->cargo_puesto_id] ?? 'Sin cargo').' · '.self::texto($p->nombre, 'Sin nombre'), $filas);
        }

        return $bloques;
    }

    /**
     * Lo que capturó Matrícula: por sala en Educación Inicial, por grado y grupo en los demás niveles.
     *
     * @return list<array{titulo: string, filas: list<array{0: string, 1: string}>, documentos: list<array{nombre: string, archivo: string, href: string}>}>
     */
    private function matricula(EscuelaNivel $nivel): array
    {
        $etiqueta = $nivel->tipo_tramite === 'reincorporacion' ? 'Alumnos inscritos' : 'Alumnos proyectados';
        if ($nivel->nivelEducativo->clave === ResumenTramite::NIVEL_CON_MOBILIARIO) {
            $registros = MatriculaSala::where('escuela_nivel_id', $nivel->id)->get();
            $nombres = Sala::orderBy('orden')->pluck('nombre', 'id');
            $registros = $registros->sortBy(fn ($r) => $nombres->keys()->search($r->sala_id));
            $filas = $registros->map(fn ($r) => [$nombres[$r->sala_id], (string) $r->cantidad_alumnos])->values()->all();
            $titulo = "{$etiqueta} por sala";
        } else {
            $registros = MatriculaGrado::where('escuela_nivel_id', $nivel->id)->get();
            $grados = Grado::whereIn('id', $registros->pluck('grado_id')->all())->get()->keyBy('id');
            $registros = $registros->sortBy(fn ($r) => sprintf('%05d|%s', $grados[$r->grado_id]->orden, $r->grupo));
            $filas = $registros->map(fn ($r) => ["{$grados[$r->grado_id]->nombre} · Grupo {$r->grupo}", (string) $r->cantidad_alumnos])->values()->all();
            $titulo = "{$etiqueta} por grado y grupo";
        }

        if ($filas === []) {
            return [];
        }
        $filas[] = ['Total', (string) $registros->sum('cantidad_alumnos')];

        return [self::bloque($titulo, $filas)];
    }

    private static function texto(?string $valor, string $vacio = 'Sin dato'): string
    {
        return $valor === null || $valor === '' ? $vacio : $valor;
    }

    private static function siNo(?bool $valor): string
    {
        return $valor === null ? 'Sin dato' : ($valor ? 'Sí' : 'No');
    }

    private static function siNoONull(?bool $valor): ?string
    {
        return $valor === null ? null : self::siNo($valor);
    }

    private static function medida(?string $valor, string $unidad): string
    {
        return $valor === null ? 'Sin dato' : number_format((float) $valor, 2, '.', '').' '.$unidad;
    }

    /** Las coordenadas se guardan con 7 decimales; se muestran sin ceros de relleno. */
    private static function coordenada(?string $valor): string
    {
        return $valor === null ? 'Sin dato' : rtrim(rtrim($valor, '0'), '.');
    }

    /** @param  array<string, string|int|null>  $campos */
    private static function detalle(array $campos): string
    {
        $partes = [];
        foreach ($campos as $etiqueta => $valor) {
            if ($valor !== null && $valor !== '') {
                $partes[] = "{$etiqueta}: {$valor}";
            }
        }

        return implode(' · ', $partes);
    }

    /**
     * @param  array<string, string|null>  $datos
     * @return list<array{0: string, 1: string}>
     */
    private static function filas(array $datos): array
    {
        $filas = [];
        foreach ($datos as $etiqueta => $valor) {
            $filas[] = [$etiqueta, (string) $valor];
        }

        return $filas;
    }

    /**
     * @param  list<array{0: string, 1: string}>  $filas
     * @return array{titulo: string, filas: list<array{0: string, 1: string}>, documentos: list<array{nombre: string, archivo: string, href: string}>}
     */
    private static function bloque(string $titulo, array $filas): array
    {
        return ['titulo' => $titulo, 'filas' => $filas, 'documentos' => []];
    }
}
