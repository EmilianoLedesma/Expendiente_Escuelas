<?php

namespace App\Livewire\Forms;

use App\Application\Captura\Normalizacion;
use App\Application\Captura\Normalizar;
use App\Application\Captura\ReglasCaptura;
use Illuminate\Support\Carbon;
use Livewire\Form;

class DictamenUsoSueloForm extends Form
{
    public string $fechaEmision = '';

    public string $autoridadExpide = '';

    public string $folioDictamen = '';

    public string $giroAutorizado = '';

    #[Normalizar(Normalizacion::TextoLargo)]
    public string $condicionantes = '';

    /**
     * El solicitante ya no captura el vencimiento: se calcula como un año exacto
     * después de la fecha de emisión (ver datosParaDocumento).
     */
    public function rules(): array
    {
        return [
            'fechaEmision' => ReglasCaptura::fechaPasada(requerido: true),
            'autoridadExpide' => ReglasCaptura::texto(requerido: true, max: 200),
            'folioDictamen' => ReglasCaptura::texto(requerido: true, max: 50),
            'giroAutorizado' => ReglasCaptura::texto(requerido: true, max: 200),
            'condicionantes' => ReglasCaptura::texto(max: 1000),
        ];
    }

    public function validationAttributes(): array
    {
        return [
            'fechaEmision' => 'fecha de emisión',
            'autoridadExpide' => 'autoridad que expide',
            'folioDictamen' => 'folio del dictamen',
            'giroAutorizado' => 'uso o giro autorizado',
            'condicionantes' => 'condicionantes',
        ];
    }

    /**
     * Valores para DatosDocumento (clave = parámetro del DTO). El vencimiento es la
     * emisión + 1 año exacto (29 de febrero → 28); sin emisión válida no se calcula.
     * Cadena vacía = null.
     *
     * @return array<string, string|null>
     */
    public function datosParaDocumento(): array
    {
        $texto = fn (string $valor): ?string => $valor !== '' ? $valor : null;

        $vencimiento = null;
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->fechaEmision) === 1) {
            $emision = Carbon::createFromFormat('!Y-m-d', $this->fechaEmision);
            $vencimiento = $emision->format('Y-m-d') === $this->fechaEmision ? $emision->addYearNoOverflow()->toDateString() : null;
        }

        return [
            'fechaEmision' => $texto($this->fechaEmision),
            'autoridadExpide' => $texto($this->autoridadExpide),
            'folioDictamen' => $texto($this->folioDictamen),
            'giroAutorizado' => $texto($this->giroAutorizado),
            'tipoVigencia' => 'fecha_expresa',
            'fechaVencimientoDocumento' => $vencimiento,
            'condicionantes' => $texto($this->condicionantes),
        ];
    }
}
