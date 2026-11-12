<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Sección 11 (Formación Docente): una acción formativa de un tipo de formación ('inicial'), en orden.
 * Las horas son TIME ('HH:MM:SS'), las fechas DATE ('AAAA-MM-DD') y los días una lista de nombres.
 *
 * @property int $id
 * @property int $escuela_nivel_id
 * @property string $tipo
 * @property int $orden
 * @property string $nombre
 * @property string $proposito
 * @property string $participantes
 * @property string $formato
 * @property string|null $formato_otro
 * @property int $duracion_horas
 * @property list<string> $dias
 * @property string $hora_inicio
 * @property string $hora_fin
 * @property string $fecha_inicio
 * @property string $fecha_fin
 * @property string $temario
 * @property string $evaluacion
 * @property string $recursos
 * @property string $perfil_ponente
 * @property string $reconocimiento
 * @property string|null $otros
 */
class ProyectoEducativoFormacionAccion extends Model
{
    protected $table = 'proyecto_educativo_formacion_acciones';

    protected $guarded = ['id'];

    protected $casts = ['dias' => 'array'];
}
