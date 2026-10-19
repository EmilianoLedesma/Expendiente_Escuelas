<?php

namespace App\Livewire\Forms;

use App\Application\Captura\Normalizacion;
use App\Application\Captura\Normalizar;
use App\Application\Captura\ReglasCaptura;
use Closure;
use Livewire\Form;

class DictamenUsoSueloForm extends Form
{
    public string $fechaEmision = '';

    public string $autoridadExpide = '';

    public string $folioDictamen = '';

    public string $giroAutorizado = '';

    /** 'fecha_expresa' | 'sin_fecha_expresa' | '' (sin decidir): lo que dice el documento, no el sistema. */
    public string $tipoVigencia = '';

    public string $fechaVencimientoDocumento = '';

    #[Normalizar(Normalizacion::TextoLargo)]
    public string $condicionantes = '';

    /**
     * La vigencia jurídica del dictamen es la que declara el documento (cédula
     * municipal TR-170100-017: "la que indique el documento"): se captura, no se
     * calcula. Sin fecha expresa no se inventa una. La antigüedad de presentación
     * no se valida aquí (sin fundamento confirmado; pregunta abierta para SEDEQ).
     */
    public function rules(): array
    {
        return [
            'fechaEmision' => ReglasCaptura::fechaPasada(requerido: true),
            'autoridadExpide' => ReglasCaptura::texto(requerido: true, max: 200),
            'folioDictamen' => ReglasCaptura::texto(requerido: true, max: 50),
            'giroAutorizado' => ReglasCaptura::texto(requerido: true, max: 200),
            'tipoVigencia' => ['required', 'in:fecha_expresa,sin_fecha_expresa'],
            'fechaVencimientoDocumento' => $this->tipoVigencia === 'fecha_expresa'
                ? [...ReglasCaptura::fecha(requerido: true), $this->noAnteriorALaEmision(...)]
                : ['nullable'],
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
            'tipoVigencia' => 'indicación de fecha de vencimiento',
            'fechaVencimientoDocumento' => 'fecha de vencimiento',
            'condicionantes' => 'condicionantes',
        ];
    }

    /**
     * Valores para DatosDocumento (clave = parámetro del DTO). La fecha de
     * vencimiento solo viaja con `fecha_expresa`; cadena vacía = null.
     *
     * @return array<string, string|null>
     */
    public function datosParaDocumento(): array
    {
        $texto = fn (string $valor): ?string => $valor !== '' ? $valor : null;

        return [
            'fechaEmision' => $texto($this->fechaEmision),
            'autoridadExpide' => $texto($this->autoridadExpide),
            'folioDictamen' => $texto($this->folioDictamen),
            'giroAutorizado' => $texto($this->giroAutorizado),
            'tipoVigencia' => $texto($this->tipoVigencia),
            'fechaVencimientoDocumento' => $this->tipoVigencia === 'fecha_expresa' ? $texto($this->fechaVencimientoDocumento) : null,
            'condicionantes' => $texto($this->condicionantes),
        ];
    }

    /** Solo compara cuando ambas fechas ya tienen formato válido; cada una tiene su propia regla de formato. */
    private function noAnteriorALaEmision(string $atributo, mixed $vencimiento, Closure $fallar): void
    {
        $esFecha = fn (mixed $valor): bool => is_string($valor) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor) === 1;

        if ($esFecha($vencimiento) && $esFecha($this->fechaEmision) && $vencimiento < $this->fechaEmision) {
            $fallar('La fecha de vencimiento no puede ser anterior a la fecha de emisión.');
        }
    }
}
