<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Extensión de documentos_plantel para el Visto Bueno / Dictamen de Protección Civil.
 * Lo declarado por el documento (tipo, autoridad, folio, riesgo, vencimiento) va separado de lo
 * que fija una disposición (`fecha_vencimiento_normativo` con su `regimen_normativo`).
 *
 * @property int $documento_plantel_id
 * @property string|null $tipo_instrumento visto_bueno | dictamen | otro; NULL = anterior a este dato
 * @property string|null $tipo_instrumento_otro
 * @property string|null $autoridad_emisora
 * @property string|null $jurisdiccion_emisora municipio_queretaro | otra
 * @property string|null $folio_documento
 * @property string|null $clasificacion_riesgo alto | medio | bajo | no_indicado, tal como la declara el documento
 * @property int|null $ejercicio año de trámite indicado por el documento (Municipio de Querétaro)
 * @property string $tipo_vigencia fecha_expresa | sin_fecha_expresa | no_capturada
 * @property string|null $fecha_vencimiento_documento
 * @property string|null $regimen_normativo disposición que origina fecha_vencimiento_normativo
 * @property string|null $fecha_vencimiento_normativo
 * @property string|null $condicionantes
 * @property string|null $fecha_calculada_30_dias_historica fecha calculada por la regla retirada de 30 días; NO verificada
 */
class VistoBuenoProteccionCivil extends Model
{
    public $timestamps = false;

    protected $table = 'vistos_buenos_proteccion_civil';

    protected $primaryKey = 'documento_plantel_id';

    public $incrementing = false;

    protected $fillable = [
        'documento_plantel_id',
        'tipo_instrumento',
        'tipo_instrumento_otro',
        'autoridad_emisora',
        'jurisdiccion_emisora',
        'folio_documento',
        'clasificacion_riesgo',
        'ejercicio',
        'tipo_vigencia',
        'fecha_vencimiento_documento',
        'regimen_normativo',
        'fecha_vencimiento_normativo',
        'condicionantes',
        'fecha_calculada_30_dias_historica',
    ];

    public function documentoPlantel(): BelongsTo
    {
        return $this->belongsTo(DocumentoPlantel::class);
    }
}
