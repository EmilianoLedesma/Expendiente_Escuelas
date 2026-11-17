<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Una referencia bibliográfica de la sección 20 del Proyecto Educativo.
 *
 * @property int $id
 * @property int $escuela_nivel_id
 * @property int $orden
 * @property string $autores
 * @property int $anio
 * @property string $titulo
 * @property string $fuente
 * @property string|null $url
 */
class ProyectoEducativoBibliografia extends Model
{
    protected $table = 'proyecto_educativo_bibliografia';

    protected $guarded = ['id'];
}
