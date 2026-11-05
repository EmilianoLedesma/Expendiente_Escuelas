<?php

namespace App\Application\Reportes;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Reportes de solo lectura para el admin. Cada sección trae un resumen para la pantalla
 * (título, columnas, filas) y un detalle por registro para el CSV (una fila por nivel o por trámite,
 * con los datos del solicitante y del plantel). El estado vive en cada nivel del trámite
 * (escuela_niveles), así que los conteos por estado son de niveles. Los trámites archivados quedan
 * fuera de todo salvo del conteo y del detalle de "Trámites activos y archivados".
 *
 * @phpstan-type Tabla array{columnas: list<string>, filas: list<list<string|int|null>>}
 * @phpstan-type Seccion array{titulo: string, columnas: list<string>, filas: list<list<string|int|null>>, detalle: Tabla}
 */
class ConsultarReportes
{
    private const COLUMNAS_NIVEL = [
        'Trámite', 'Nivel', 'Estado', 'Solicitante', 'Correo del solicitante', 'Plantel', 'Municipio', 'C.P.',
        'Teléfono del plantel', 'Correo del plantel', 'Revisor asignado', 'En este estado desde',
    ];

    private const COLUMNAS_TRAMITE = [
        'Trámite', 'Situación', 'Archivado el', 'Motivo del archivo', 'Solicitante', 'Correo del solicitante',
        'Niveles y estado', 'Plantel', 'Municipio', 'C.P.', 'Teléfono del plantel', 'Correo del plantel', 'Creado el',
    ];

    /** @return array<string, Seccion> */
    public function secciones(): array
    {
        $niveles = $this->niveles();

        return [
            'por_estado' => [
                'titulo' => 'Niveles por estado', 'columnas' => ['Estado', 'Niveles'], 'filas' => $this->porEstado(),
                'detalle' => $this->detalleNiveles($niveles, fn ($f) => true),
            ],
            'tramites' => [
                'titulo' => 'Trámites activos y archivados', 'columnas' => ['Trámites', 'Cantidad'], 'filas' => $this->tramites(),
                'detalle' => $this->detalleTramites(false),
            ],
            'por_municipio' => [
                'titulo' => 'Trámites activos por municipio', 'columnas' => ['Municipio', 'Trámites'], 'filas' => $this->porMunicipio(),
                'detalle' => $this->detalleTramites(true),
            ],
            'esperando' => [
                'titulo' => 'Esperando al solicitante',
                'columnas' => ['Trámite', 'Solicitante', 'Nivel', 'Devuelto el', 'Días'],
                'filas' => $this->esperando($niveles),
                'detalle' => $this->detalleNiveles($niveles, fn ($f) => $f->clave === 'con_observaciones', true),
            ],
            'carga' => [
                'titulo' => 'Carga de revisión', 'columnas' => ['Concepto', 'Niveles'], 'filas' => $this->carga(),
                'detalle' => $this->detalleNiveles($niveles, fn ($f) => $f->clave === 'en_revision'),
            ],
        ];
    }

    /**
     * CSV en UTF-8 con BOM (para que Excel lea bien los acentos) del detalle de una sección.
     *
     * @param  Seccion  $seccion
     */
    public function csv(array $seccion): string
    {
        $f = fopen('php://temp', 'r+');
        fputcsv($f, array_map($this->sinFormula(...), $seccion['detalle']['columnas']), ',', '"', '\\');
        foreach ($seccion['detalle']['filas'] as $fila) {
            fputcsv($f, array_map($this->sinFormula(...), $fila), ',', '"', '\\');
        }
        rewind($f);

        return "\xEF\xBB\xBF".stream_get_contents($f);
    }

    /**
     * El CSV se abre en Excel: una celda de texto que empieza con = + - @ (o tabulador/retorno)
     * se evalúa como fórmula. La comilla inicial la deja como texto.
     */
    private function sinFormula(mixed $celda): mixed
    {
        return is_string($celda) && preg_match('/^[=+\-@\t\r]/', $celda) === 1 ? "'".$celda : $celda;
    }

    // --- Resúmenes de la pantalla ---

    /** @return list<list<string|int|null>> */
    private function porEstado(): array
    {
        $cuentas = DB::table('escuela_niveles')
            ->join('escuelas', 'escuelas.id', '=', 'escuela_niveles.escuela_id')
            ->whereNull('escuelas.archivado_at')
            ->selectRaw('escuela_niveles.estado_id, count(*) as n')->groupBy('escuela_niveles.estado_id')
            ->pluck('n', 'estado_id');

        return DB::table('estados_expediente')->orderBy('orden')->get(['id', 'nombre'])
            ->map(fn ($e) => [$e->nombre, (int) ($cuentas[$e->id] ?? 0)])->all();
    }

    /** @return list<list<string|int|null>> */
    private function tramites(): array
    {
        return [
            ['Activos', DB::table('escuelas')->whereNull('archivado_at')->count()],
            ['Archivados', DB::table('escuelas')->whereNotNull('archivado_at')->count()],
        ];
    }

    /** @return list<list<string|int|null>> */
    private function porMunicipio(): array
    {
        return DB::table('escuelas')
            ->join('planteles', 'planteles.id', '=', 'escuelas.plantel_id')
            ->whereNull('escuelas.archivado_at')
            ->selectRaw('upper(trim(planteles.municipio)) as municipio, count(*) as n')
            ->groupByRaw('upper(trim(planteles.municipio))')->orderByDesc('n')->orderBy('municipio')
            ->get()
            ->map(fn ($f) => [mb_convert_case(mb_strtolower($f->municipio), MB_CASE_TITLE), (int) $f->n])->all();
    }

    /**
     * @param  list<object>  $niveles
     * @return list<list<string|int|null>>
     */
    private function esperando(array $niveles): array
    {
        $filas = array_filter($niveles, fn ($f) => $f->clave === 'con_observaciones');
        usort($filas, fn ($a, $b) => [$a->desde === null, $a->desde, $a->escuela_id] <=> [$b->desde === null, $b->desde, $b->escuela_id]);

        return array_map(fn ($f) => [
            $f->nombre, $f->solicitante ?? '—', $f->nivel, $this->fecha($f->desde), $this->dias($f->desde),
        ], $filas);
    }

    /** @return list<list<string|int|null>> */
    private function carga(): array
    {
        $enRevision = (int) DB::table('estados_expediente')->where('clave', 'en_revision')->value('id');
        $niveles = fn () => DB::table('escuela_niveles as en')
            ->join('escuelas', 'escuelas.id', '=', 'en.escuela_id')
            ->whereNull('escuelas.archivado_at')->where('en.estado_id', $enRevision);

        $filas = [
            ['Niveles en revisión', $niveles()->count()],
            ['Sin revisor asignado', $niveles()->whereNotExists(fn ($q) => $q->selectRaw('1')->from('asignaciones_revision as a')->whereColumn('a.escuela_nivel_id', 'en.id'))->count()],
        ];
        $porRevisor = $niveles()
            ->join('asignaciones_revision as a', 'a.escuela_nivel_id', '=', 'en.id')
            ->join('users', 'users.id', '=', 'a.revisor_id')
            ->selectRaw('users.name, count(*) as n')->groupBy('users.id', 'users.name')->orderByDesc('n')->orderBy('users.name')->get();
        foreach ($porRevisor as $r) {
            $filas[] = ['Revisor: '.$r->name, (int) $r->n];
        }

        return $filas;
    }

    // --- Detalle por registro (CSV) ---

    /**
     * Un nivel por fila de los trámites activos, con nombre del trámite, plantel y revisor ya resueltos.
     *
     * @return list<object>
     */
    private function niveles(): array
    {
        $filas = DB::table('escuela_niveles as en')
            ->join('escuelas', 'escuelas.id', '=', 'en.escuela_id')
            ->join('estados_expediente as ee', 'ee.id', '=', 'en.estado_id')
            ->join('niveles_educativos as ne', 'ne.id', '=', 'en.nivel_educativo_id')
            ->leftJoin('solicitantes', 'solicitantes.id', '=', 'escuelas.solicitante_id')
            ->leftJoin('users', 'users.id', '=', 'solicitantes.user_id')
            ->leftJoin('planteles', 'planteles.id', '=', 'escuelas.plantel_id')
            ->leftJoin('asignaciones_revision as a', 'a.escuela_nivel_id', '=', 'en.id')
            ->leftJoin('users as rev', 'rev.id', '=', 'a.revisor_id')
            ->whereNull('escuelas.archivado_at')
            ->selectRaw('en.id as nivel_id, escuelas.id as escuela_id, escuelas.nombre_aprobado, ne.nombre as nivel, ee.nombre as estado,
                ee.clave, ee.orden as estado_orden, users.name as solicitante, users.email as correo, planteles.calle, planteles.numero_ext,
                planteles.colonia, planteles.municipio, planteles.codigo_postal, planteles.telefono, planteles.correo_electronico as correo_plantel,
                rev.name as revisor,
                (select max(h.fecha) from historial_estados_expediente h where h.escuela_nivel_id = en.id and h.estado_id = en.estado_id) as desde')
            ->orderBy('ee.orden')->orderBy('escuelas.id')->orderBy('en.id')
            ->get();
        $ternas = $this->primerNombreDeTerna($filas->pluck('escuela_id')->unique()->all());

        return $filas->map(function ($f) use ($ternas) {
            $f->nombre = $f->nombre_aprobado ?? $ternas[$f->escuela_id] ?? 'Trámite sin nombre';

            return $f;
        })->all();
    }

    /**
     * @param  list<object>  $niveles
     * @param  callable(object): bool  $filtro
     * @return Tabla
     */
    private function detalleNiveles(array $niveles, callable $filtro, bool $conDias = false): array
    {
        $columnas = $conDias ? [...self::COLUMNAS_NIVEL, 'Días en espera'] : self::COLUMNAS_NIVEL;
        $filas = [];
        foreach (array_filter($niveles, $filtro) as $f) {
            $fila = [
                $f->nombre, $f->nivel, $f->estado, $f->solicitante, $f->correo, $this->direccion($f), $f->municipio,
                $f->codigo_postal, $f->telefono, $f->correo_plantel, $f->revisor ?? 'Sin asignar', $this->fecha($f->desde),
            ];
            $filas[] = $conDias ? [...$fila, $this->dias($f->desde)] : $fila;
        }

        return ['columnas' => $columnas, 'filas' => $filas];
    }

    /**
     * Un trámite por fila, con sus niveles y estados en una celda.
     *
     * @return Tabla
     */
    private function detalleTramites(bool $soloActivos): array
    {
        $consulta = fn (): Builder => DB::table('escuelas')
            ->leftJoin('solicitantes', 'solicitantes.id', '=', 'escuelas.solicitante_id')
            ->leftJoin('users', 'users.id', '=', 'solicitantes.user_id')
            ->leftJoin('planteles', 'planteles.id', '=', 'escuelas.plantel_id')
            ->selectRaw("escuelas.id, escuelas.nombre_aprobado, escuelas.archivado_at, escuelas.archivado_motivo, escuelas.created_at,
                users.name as solicitante, users.email as correo, planteles.calle, planteles.numero_ext, planteles.colonia,
                planteles.municipio, planteles.codigo_postal, planteles.telefono, planteles.correo_electronico as correo_plantel,
                (select string_agg(ne.nombre || ' (' || ee.nombre || ')', ', ' order by ne.orden)
                   from escuela_niveles en
                   join niveles_educativos ne on ne.id = en.nivel_educativo_id
                   join estados_expediente ee on ee.id = en.estado_id
                  where en.escuela_id = escuelas.id) as niveles");
        $q = $consulta();
        if ($soloActivos) {
            $q->whereNull('escuelas.archivado_at')->orderByRaw('upper(trim(planteles.municipio))');
        }
        $filas = $q->orderBy('escuelas.id')->get();
        $ternas = $this->primerNombreDeTerna($filas->pluck('id')->all());

        return [
            'columnas' => self::COLUMNAS_TRAMITE,
            'filas' => $filas->map(fn ($f) => [
                $f->nombre_aprobado ?? $ternas[$f->id] ?? 'Trámite sin nombre',
                $f->archivado_at === null ? 'Activo' : 'Archivado',
                $this->fecha($f->archivado_at, '') ?: null,
                $f->archivado_motivo,
                $f->solicitante, $f->correo, $f->niveles, $this->direccion($f), $f->municipio, $f->codigo_postal,
                $f->telefono, $f->correo_plantel, $this->fecha($f->created_at),
            ])->all(),
        ];
    }

    /**
     * @param  list<int>  $escuelaIds
     * @return array<int, string> escuela_id => nombre de la primera propuesta de la terna
     */
    private function primerNombreDeTerna(array $escuelaIds): array
    {
        // pluck conserva la última fila de cada escuela: en orden descendente, la última es la propuesta 1.
        return DB::table('ternas_nombres')->whereIn('escuela_id', $escuelaIds)->orderByDesc('numero_propuesta')
            ->pluck('nombre_propuesto', 'escuela_id')->all();
    }

    private function direccion(object $f): string
    {
        return implode(' ', array_filter([$f->calle, $f->numero_ext])).', '.$f->colonia;
    }

    private function fecha(?string $valor, string $vacio = '—'): string
    {
        return $valor === null ? $vacio : Carbon::parse($valor)->format('d/m/Y');
    }

    private function dias(?string $valor): int|string
    {
        return $valor === null ? '—' : (int) Carbon::parse($valor)->startOfDay()->diffInDays(now()->startOfDay());
    }
}
