<?php

namespace App\Application\Infraestructura;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Lecturas de catálogo/captura sobre dos tablas sin modelo Eloquent
 * (`sanitarios`, `tipos_material_biblioteca`) — antes vivían como
 * DB::table() directo en InfraestructuraNivel, lo que ADR-006 prohíbe en
 * presentación. Sin modelo porque ninguna de las dos necesita relaciones
 * ni scopes; DB::table() aquí, en Application, sigue siendo la lectura más
 * simple que funciona.
 */
class CatalogosInfraestructura
{
    public function sanitariosCapturados(int $plantelId): Collection
    {
        return DB::table('sanitarios')
            ->where('plantel_id', $plantelId)
            ->orderBy('categoria')
            ->get();
    }

    /**
     * Lo capturado por el plantel en la forma del formulario de edición (WS-7b-2), con sus
     * hijos: la edición reemplaza la fila entera, así que todo campo se precarga y se
     * reenvía. Los nulos se quedan en null (con bloqueo, null = "se puede completar").
     * campos_futbol y sanitarios_bacinicas tienen la fila padre como PK: a lo más uno.
     *
     * @return array{espacios: array<int, array<string, mixed>>, sanitarios: array<string, array<string, mixed>>, materiales: array<int, array<string, mixed>>}
     */
    public function edicionCapturada(int $plantelId): array
    {
        $edicion = ['espacios' => [], 'sanitarios' => [], 'materiales' => []];

        $espacios = DB::table('instalaciones_espacios as ie')
            ->leftJoin('campos_futbol as cf', 'cf.instalacion_espacio_id', '=', 'ie.id')
            ->where('ie.plantel_id', $plantelId)
            ->select('ie.*', 'cf.instalacion_espacio_id as campo_futbol', 'cf.tipo_superficie', 'cf.formato')
            ->get();

        foreach ($espacios as $e) {
            $edicion['espacios'][(int) $e->tipo_espacio_id] = [
                'cantidad' => $e->cantidad,
                'superficieM2' => $e->superficie_m2,
                'capacidadPromedio' => $e->capacidad_promedio,
                'ventilacionNatural' => $e->ventilacion_natural,
                'iluminacionNatural' => $e->iluminacion_natural,
                'destinadoA' => $e->destinado_a,
                'campoFutbolTipoSuperficie' => $e->tipo_superficie,
                'campoFutbolFormato' => $e->formato,
                'tieneCampoFutbol' => $e->campo_futbol !== null,
            ];
        }

        foreach (DB::table('biblioteca_materiales')->whereIn('instalacion_espacio_id', $espacios->pluck('id'))->get() as $m) {
            $edicion['materiales'][(int) $m->tipo_material_id] = ['numeroTitulos' => $m->numero_titulos, 'numeroVolumenes' => $m->numero_volumenes];
        }

        $sanitarios = DB::table('sanitarios as s')
            ->leftJoin('sanitarios_bacinicas as sb', 'sb.sanitario_id', '=', 's.id')
            ->where('s.plantel_id', $plantelId)
            ->select('s.*', 'sb.cantidad_bacinicas')
            ->get();

        foreach ($sanitarios as $s) {
            $edicion['sanitarios'][$s->categoria] = [
                'cantidadRetretes' => $s->cantidad_retretes,
                'cantidadMingitorios' => $s->cantidad_mingitorios,
                'cantidadLavabos' => $s->cantidad_lavabos,
                'superficieM2' => $s->superficie_m2,
                'ventilacionNatural' => $s->ventilacion_natural,
                'iluminacionNatural' => $s->iluminacion_natural,
                'cantidadBacinicas' => $s->cantidad_bacinicas,
            ];
        }

        return $edicion;
    }

    public function materialesBibliotecaDisponibles(): Collection
    {
        return DB::table('tipos_material_biblioteca')->orderBy('id')->get();
    }
}
