<?php

namespace App\Application\Validaciones;

use App\Domain\Personal\RegistroPersonalCompleto;
use App\Domain\Validaciones\Engine\DatosCapacidadNivel;
use App\Domain\Validaciones\Engine\LineaMobiliario;
use App\Models\AulaNivel;
use App\Models\EscuelaNivel;
use App\Models\Personal;
use App\Models\Plantel;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * The capacity engine's only database read: every magnitude and declared
 * value of one escuela_nivel. Nothing captured → null, so the rule that
 * needs it reports "no verificado" instead of a false pass or fail.
 *
 * Shared plantel data (espacios, sanitarios, biblioteca, predio) is read
 * for the plantel; enrolment and staff for the level. Only personal rows
 * that RegistroPersonalCompleto accepts are counted.
 */
class ConstruirDatosCapacidad
{
    private const ASIGNATURA_EDUCACION_FISICA = 'Educación Física';

    public function __construct(private readonly RegistroPersonalCompleto $registroCompleto) {}

    public function paraNivel(int $escuelaNivelId): DatosCapacidadNivel
    {
        $escuelaNivel = EscuelaNivel::with(['nivelEducativo', 'escuela.plantel'])->findOrFail($escuelaNivelId);
        /** @var Plantel $plantel */
        $plantel = $escuelaNivel->escuela->plantel;
        $inicial = $escuelaNivel->nivelEducativo->clave === 'inicial';

        $matriculaPorSala = $inicial ? $this->matriculaPorSala($escuelaNivelId) : null;
        $matriculaNivel = $inicial
            ? ($matriculaPorSala === null ? null : array_sum($matriculaPorSala))
            : $this->matriculaPorGrados($escuelaNivelId);
        $aulas = AulaNivel::where('escuela_nivel_id', $escuelaNivelId)->latest('id')->first();
        [$personalPorCargo, $porCargoYSala, $educacionFisica] = $this->personal($escuelaNivelId);

        return new DatosCapacidadNivel(
            matriculaNivel: $matriculaNivel,
            matriculaPlantel: $matriculaNivel === null ? null : $this->matriculaPlantel($plantel->id),
            matriculaPorSala: $matriculaPorSala,
            gradosOfertados: $inicial || $matriculaNivel === null ? null : $this->gradosOfertados($escuelaNivelId),
            numeroAulas: $aulas?->numero_aulas,
            superficieAulas: $aulas?->superficie_m2 !== null ? (float) $aulas->superficie_m2 : null,
            superficiePredio: $plantel->metros_totales !== null ? (float) $plantel->metros_totales : null,
            superficieConstruida: $plantel->metros_construidos !== null ? (float) $plantel->metros_construidos : null,
            superficieRecreativa: $this->sumaEspacios($plantel->id, fn ($q) => $q->where('tipos_espacios.categoria', 'recreativo_deportivo')),
            superficieUsosMultiples: $this->sumaEspacios($plantel->id, fn ($q) => $q->where('tipos_espacios.clave', 'salon_usos_multiples')),
            superficieSanitariosAlumnos: $this->superficieSanitariosAlumnos($plantel->id),
            acervoTitulos: $this->acervo($plantel->id),
            personalPorCargo: $personalPorCargo,
            personalPorCargoYSala: $porCargoYSala,
            docentesEducacionFisica: $educacionFisica,
            mobiliario: $inicial ? $this->mobiliario($escuelaNivelId) : [],
        );
    }

    /** @return array<string, int>|null */
    private function matriculaPorSala(int $escuelaNivelId): ?array
    {
        $filas = DB::table('matricula_salas')
            ->join('salas', 'salas.id', '=', 'matricula_salas.sala_id')
            ->where('matricula_salas.escuela_nivel_id', $escuelaNivelId)
            ->pluck('matricula_salas.cantidad_alumnos', 'salas.clave')
            ->map(fn ($alumnos) => (int) $alumnos)
            ->all();

        return $filas === [] ? null : $filas;
    }

    private function matriculaPorGrados(int $escuelaNivelId): ?int
    {
        $consulta = DB::table('matricula_grados')->where('escuela_nivel_id', $escuelaNivelId);

        return $consulta->exists() ? (int) $consulta->sum('cantidad_alumnos') : null;
    }

    /**
     * Every level of every escuela on the plantel (ambito predio; provisional
     * choice P2 of PENDIENTE-motor-capacidad-provisionales). Levels not yet
     * captured add 0.
     */
    private function matriculaPlantel(int $plantelId): int
    {
        $niveles = DB::table('escuela_niveles')
            ->join('escuelas', 'escuelas.id', '=', 'escuela_niveles.escuela_id')
            ->where('escuelas.plantel_id', $plantelId)
            ->pluck('escuela_niveles.id');

        return (int) DB::table('matricula_grados')->whereIn('escuela_nivel_id', $niveles)->sum('cantidad_alumnos')
            + (int) DB::table('matricula_salas')->whereIn('escuela_nivel_id', $niveles)->sum('cantidad_alumnos');
    }

    private function gradosOfertados(int $escuelaNivelId): int
    {
        return DB::table('matricula_grados')
            ->where('escuela_nivel_id', $escuelaNivelId)
            ->where('cantidad_alumnos', '>', 0)
            ->distinct()
            ->count('grado_id');
    }

    /** @param callable(Builder): mixed $filtro */
    private function sumaEspacios(int $plantelId, callable $filtro): ?float
    {
        $consulta = DB::table('instalaciones_espacios')
            ->join('tipos_espacios', 'tipos_espacios.id', '=', 'instalaciones_espacios.tipo_espacio_id')
            ->where('instalaciones_espacios.plantel_id', $plantelId)
            ->whereNotNull('instalaciones_espacios.superficie_m2');
        $filtro($consulta);

        return $consulta->exists() ? (float) $consulta->sum('instalaciones_espacios.superficie_m2') : null;
    }

    private function superficieSanitariosAlumnos(int $plantelId): ?float
    {
        $consulta = DB::table('sanitarios')
            ->where('plantel_id', $plantelId)
            ->where('categoria', 'like', 'alumnado\_%')
            ->whereNotNull('superficie_m2');

        return $consulta->exists() ? (float) $consulta->sum('superficie_m2') : null;
    }

    /** "Acervo bibliográfico" = book titles in the plantel's libraries. */
    private function acervo(int $plantelId): ?int
    {
        $consulta = DB::table('biblioteca_materiales')
            ->join('instalaciones_espacios', 'instalaciones_espacios.id', '=', 'biblioteca_materiales.instalacion_espacio_id')
            ->join('tipos_material_biblioteca', 'tipos_material_biblioteca.id', '=', 'biblioteca_materiales.tipo_material_id')
            ->where('instalaciones_espacios.plantel_id', $plantelId)
            ->where('tipos_material_biblioteca.clave', 'libros');

        return $consulta->exists() ? (int) $consulta->sum('biblioteca_materiales.numero_titulos') : null;
    }

    /** @return array{0: array<int, int>|null, 1: array<int, array<string, int>>, 2: int|null} */
    private function personal(int $escuelaNivelId): array
    {
        $filas = Personal::where('escuela_nivel_id', $escuelaNivelId)->get();

        if ($filas->isEmpty()) {
            return [null, [], null];
        }

        $salas = DB::table('personal_salas')->join('salas', 'salas.id', '=', 'personal_salas.sala_id')
            ->whereIn('personal_salas.personal_id', $filas->pluck('id'))->pluck('salas.clave', 'personal_salas.personal_id');
        $conEducacionFisica = DB::table('personal_asignaturas')->join('asignaturas', 'asignaturas.id', '=', 'personal_asignaturas.asignatura_id')
            ->whereIn('personal_asignaturas.personal_id', $filas->pluck('id'))
            ->where('asignaturas.nombre', self::ASIGNATURA_EDUCACION_FISICA)
            ->pluck('personal_asignaturas.personal_id')->all();

        $porCargo = [];
        $porCargoYSala = [];
        $educacionFisica = 0;

        foreach ($filas as $persona) {
            if (! $this->registroCompleto->esCompleto($persona->cargo_puesto_id, $persona->nombre, $persona->nacionalidad, $persona->sexo, $persona->estudios, $persona->cedula_o_documento)) {
                continue;
            }

            $cargo = (int) $persona->cargo_puesto_id;
            $porCargo[$cargo] = ($porCargo[$cargo] ?? 0) + 1;

            if (isset($salas[$persona->id])) {
                $porCargoYSala[$cargo][$salas[$persona->id]] = ($porCargoYSala[$cargo][$salas[$persona->id]] ?? 0) + 1;
            }
            if (in_array($persona->id, $conEducacionFisica, true)) {
                $educacionFisica++;
            }
        }

        return [$porCargo, $porCargoYSala, $educacionFisica];
    }

    /** @return list<LineaMobiliario> */
    private function mobiliario(int $escuelaNivelId): array
    {
        $declarado = DB::table('mobiliario_nivel')->where('escuela_nivel_id', $escuelaNivelId)->pluck('cantidad_declarada', 'concepto_id');

        return DB::table('mobiliario_conceptos')
            ->leftJoin('salas', 'salas.id', '=', 'mobiliario_conceptos.sala_id')
            ->orderBy('mobiliario_conceptos.id')
            ->get(['mobiliario_conceptos.id', 'mobiliario_conceptos.nombre', 'salas.clave as sala', 'mobiliario_conceptos.tipo_ratio', 'mobiliario_conceptos.valor_ratio'])
            ->map(fn ($c) => new LineaMobiliario(
                $c->nombre,
                $c->sala,
                $c->tipo_ratio,
                (float) $c->valor_ratio,
                isset($declarado[$c->id]) ? (int) $declarado[$c->id] : null,
            ))
            ->values()
            ->all();
    }
}
