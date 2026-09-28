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

    public function materialesBibliotecaDisponibles(): Collection
    {
        return DB::table('tipos_material_biblioteca')->orderBy('id')->get();
    }
}
