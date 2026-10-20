<?php

namespace App\Livewire\Forms;

use App\Application\Captura\Normalizacion;
use App\Application\Captura\Normalizar;
use App\Application\Captura\ReglasCaptura;
use Livewire\Form;

/**
 * Datos del certificado de número oficial tal como aparecen en el documento; los tamaños del domicilio
 * reflejan planteles. Domicilio (calle, colonia, municipio, CP) obligatorio; el número exterior acepta «S/N».
 * Los demás datos son opcionales: la cédula municipal TR-170200-013 no publica los campos impresos del
 * certificado y los registros anteriores no los tienen. No hay vencimiento (vigencia permanente en el
 * Municipio de Querétaro) y el domicilio capturado no sustituye al del plantel
 * (docs/superpowers/specs/2026-10-20-numero-oficial-design.md).
 */
class NumeroOficialForm extends Form
{
    public string $calle = '';

    public string $numeroExt = '';

    public string $numeroInterior = '';

    public string $colonia = '';

    public string $municipio = '';

    #[Normalizar(Normalizacion::Digitos)]
    public string $codigoPostal = '';

    /** Fecha de expedición que consta en el certificado. */
    public string $fechaExpedicion = '';

    public string $autoridadEmisora = '';

    public string $folioCertificado = '';

    public string $claveCatastral = '';

    public function rules(): array
    {
        return [
            'calle' => ReglasCaptura::texto(requerido: true, max: 150),
            'numeroExt' => ReglasCaptura::texto(max: 20),
            'numeroInterior' => ReglasCaptura::texto(max: 20),
            'colonia' => ReglasCaptura::texto(requerido: true, max: 150),
            'municipio' => ReglasCaptura::texto(requerido: true, max: 150),
            'codigoPostal' => ReglasCaptura::codigoPostal(requerido: true),
            'fechaExpedicion' => ReglasCaptura::fechaPasada(),
            'autoridadEmisora' => ReglasCaptura::texto(max: 200),
            'folioCertificado' => ReglasCaptura::texto(max: 50),
            'claveCatastral' => ReglasCaptura::texto(max: 40),
        ];
    }

    public function validationAttributes(): array
    {
        return [
            'calle' => 'calle',
            'numeroExt' => 'número exterior',
            'numeroInterior' => 'número interior',
            'colonia' => 'colonia',
            'municipio' => 'municipio',
            'codigoPostal' => 'código postal',
            'fechaExpedicion' => 'fecha de expedición',
            'autoridadEmisora' => 'autoridad emisora',
            'folioCertificado' => 'folio del certificado',
            'claveCatastral' => 'clave catastral',
        ];
    }

    /**
     * Valores para DatosDocumento (clave = parámetro del DTO). La fecha de expedición viaja en
     * `fechaEmision`, la autoridad en `autoridadEmisora` y el folio en `folioDocumento`; cadena vacía = null.
     *
     * @return array<string, string|null>
     */
    public function datosParaDocumento(): array
    {
        $texto = fn (string $valor): ?string => trim($valor) !== '' ? trim($valor) : null;

        return [
            'domicilioCalle' => trim($this->calle),
            'domicilioNumeroExt' => $texto($this->numeroExt),
            'domicilioNumeroInt' => $texto($this->numeroInterior),
            'domicilioColonia' => trim($this->colonia),
            'domicilioMunicipio' => trim($this->municipio),
            'domicilioCodigoPostal' => $this->codigoPostal,
            'fechaEmision' => $texto($this->fechaExpedicion),
            'autoridadEmisora' => $texto($this->autoridadEmisora),
            'folioDocumento' => $texto($this->folioCertificado),
            'claveCatastral' => $texto($this->claveCatastral),
        ];
    }
}
