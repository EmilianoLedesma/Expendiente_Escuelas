<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Extensión del recibo de pago de derechos (documento de escuela_nivel).
 *
 * @property int $documento_escuela_nivel_id
 * @property string|null $folio
 * @property string|null $monto
 * @property string|null $fecha_pago
 * @property string|null $portal_referencia
 */
class ReciboPagoDerechos extends Model
{
    public $timestamps = false;

    protected $table = 'recibos_pago_derechos';

    protected $primaryKey = 'documento_escuela_nivel_id';

    public $incrementing = false;

    protected $fillable = [
        'documento_escuela_nivel_id',
        'folio',
        'monto',
        'fecha_pago',
        'portal_referencia',
    ];

    public function documentoEscuelaNivel(): BelongsTo
    {
        return $this->belongsTo(DocumentoEscuelaNivel::class);
    }
}
