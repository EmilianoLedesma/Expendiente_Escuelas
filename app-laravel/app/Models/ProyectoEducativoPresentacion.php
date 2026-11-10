<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Fechas y teléfonos se leen como cadenas ('Y-m-d', solo dígitos): las reglas de ReglasPresentacionProyecto trabajan sobre eso.
 *
 * @property int $id
 * @property int $escuela_nivel_id
 * @property string|null $fecha_entrega
 * @property string|null $nombre_institucion
 * @property string|null $fecha_apertura
 * @property string|null $domicilio_completo
 * @property string|null $representante_legal
 * @property string|null $correo
 * @property string|null $telefono
 * @property string|null $celular
 * @property string|null $pagina_web
 * @property string|null $modalidad
 * @property string|null $ciclo_escolar
 * @property string|null $ciclo_escolar_otro
 * @property string|null $duracion
 * @property string|null $duracion_otro
 * @property string|null $sintesis_factibilidad
 */
class ProyectoEducativoPresentacion extends Model
{
    protected $table = 'proyecto_educativo_presentacion';

    protected $guarded = ['id'];
}
