<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/** Oculta las escuelas archivadas. El código de admin lo quita con withoutGlobalScope(EscuelaNoArchivada::class). */
class EscuelaNoArchivada implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $builder->whereNull($model->qualifyColumn('archivado_at'));
    }
}
