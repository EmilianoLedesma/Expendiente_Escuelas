<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\DB;

/** Oculta los niveles cuya escuela está archivada. */
class NivelDeEscuelaNoArchivada implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $builder->whereIn(
            $model->qualifyColumn('escuela_id'),
            DB::table('escuelas')->whereNull('archivado_at')->select('id'),
        );
    }
}
