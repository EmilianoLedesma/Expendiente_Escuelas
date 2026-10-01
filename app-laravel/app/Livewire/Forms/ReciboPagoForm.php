<?php

namespace App\Livewire\Forms;

use App\Application\Captura\ReglasCaptura;
use Livewire\Form;

/** Campos del recibo de pago de derechos (Paso 2.4). RegistrarDocumento repite los invariantes. */
class ReciboPagoForm extends Form
{
    public string $folio = '';

    public string $monto = '';

    public string $fechaPago = '';

    public string $portalReferencia = '';

    public function rules(): array
    {
        return [
            'folio' => ReglasCaptura::texto(requerido: true, max: 50),
            // recibos_pago_derechos.monto es NUMERIC(10,2) y debe ser positivo.
            'monto' => ReglasCaptura::decimal(ReglasCaptura::MAX_NUMERIC_10_2, min: 0.01, requerido: true),
            'fechaPago' => ReglasCaptura::fechaPasada(requerido: true),
            'portalReferencia' => ReglasCaptura::texto(max: 200),
        ];
    }

    public function validationAttributes(): array
    {
        return [
            'folio' => 'folio del recibo',
            'monto' => 'monto pagado',
            'fechaPago' => 'fecha de pago',
            'portalReferencia' => 'referencia del portal de pago',
        ];
    }
}
