<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $escuela_nivel_id
 * @property string|null $biblioteca_tipo
 * @property string|null $biblioteca_virtual_tipo
 * @property bool|null $biblioteca_tiene_encargado
 * @property string|null $biblioteca_perfil_encargado
 * @property string|null $biblioteca_prestamo
 * @property bool|null $biblioteca_tiene_programa_lectura
 * @property string|null $biblioteca_programa_lectura
 * @property string|null $biblioteca_otros_programas
 * @property string|null $acervo_clasificacion
 * @property string|null $acervo_titulos_basicos
 * @property string|null $acervo_titulos_lectura
 */
class ProyectoEducativoBiblioteca extends Model
{
    protected $table = 'proyecto_educativo_biblioteca';

    protected $guarded = ['id'];

    protected $casts = [
        'biblioteca_tiene_encargado' => 'boolean',
        'biblioteca_tiene_programa_lectura' => 'boolean',
    ];
}
