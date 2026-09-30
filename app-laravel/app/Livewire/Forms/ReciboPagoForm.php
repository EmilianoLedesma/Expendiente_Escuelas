<?php

namespace App\Livewire\Forms;

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
            'folio' => ['required', 'string', 'max:50'],
            'monto' => ['required', 'numeric', 'gt:0', 'max:99999999.99'],
            'fechaPago' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'portalReferencia' => ['nullable', 'string', 'max:200'],
        ];
    }
}
