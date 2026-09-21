<?php

namespace App\Livewire\Tramite\Paso3;

use App\Models\MobiliarioConcepto;
use Illuminate\Support\Collection;

/**
 * Valor de solo lectura para agrupar mobiliario_conceptos por sala en la
 * vista de MobiliarioNivel — un DTO nombrado en vez de stdClass anónimo
 * porque Collection<TValue> es invariante y PHPStan no acepta la
 * intersección stdClass&object{...} que produce `(object) [...]`.
 */
final class MobiliarioGrupo
{
    /**
     * @param  Collection<int, MobiliarioConcepto>  $conceptos
     */
    public function __construct(
        public readonly string $nombre,
        public readonly Collection $conceptos,
    ) {}
}
