<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Typed data captured with the upload (ADR-007). Contains PII; not
 * encrypted yet (owner decision).
 *
 * @property int $documento_plantel_id
 * @property string $calle
 * @property string|null $numero_ext
 * @property string $colonia
 * @property string $municipio
 * @property string $codigo_postal
 * @property string|null $numero_int
 * @property string|null $autoridad_emisora
 * @property string|null $folio_certificado
 * @property string|null $clave_catastral
 * @property int|null $entidad_federativa_id catálogo geográfico (ADR-017); NULL en certificados anteriores
 * @property int|null $municipio_id catálogo geográfico (ADR-017); `municipio` (texto) se deriva de él
 *
 * El domicilio es el que dice el documento y no sustituye al del plantel. La fecha de expedición vive en
 * documentos_plantel.fecha_emision; no hay vencimiento (vigencia permanente en el Municipio de Querétaro).
 */
class CertificadoNumeroOficial extends Model
{
    public $timestamps = false;

    protected $table = 'certificados_numero_oficial';

    protected $primaryKey = 'documento_plantel_id';

    public $incrementing = false;

    protected $fillable = [
        'documento_plantel_id',
        'calle',
        'numero_ext',
        'colonia',
        'municipio',
        'codigo_postal',
        'numero_int',
        'autoridad_emisora',
        'folio_certificado',
        'clave_catastral',
        'entidad_federativa_id',
        'municipio_id',
    ];

    public function documento(): BelongsTo
    {
        return $this->belongsTo(DocumentoPlantel::class, 'documento_plantel_id');
    }
}
