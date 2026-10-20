<?php

namespace App\Livewire\Forms;

use App\Application\Captura\Normalizacion;
use App\Application\Captura\Normalizar;
use App\Application\Captura\ReglasCaptura;
use Closure;
use Livewire\Form;

/**
 * Captura del Visto Bueno / Dictamen de Protección Civil tal como aparece en el documento
 * (docs/superpowers/specs/2026-10-20-proteccion-civil-design.md). Todo lo capturado es lo que
 * declara el PDF; el sistema no valora su suficiencia jurídica. Solo consistencia de captura:
 * la regla del 31 de diciembre (Reglamento de PC de Querétaro, art. 46) se aplica en el caso de uso,
 * únicamente al Visto Bueno del Municipio de Querétaro con el año de trámite capturado.
 * El caso de uso repite cada validación.
 */
class ProteccionCivilForm extends Form
{
    /** 'visto_bueno' | 'dictamen' | 'otro' | '' (sin decidir). */
    public string $tipoInstrumento = '';

    public string $tipoInstrumentoOtro = '';

    public string $fechaEmision = '';

    public string $autoridadEmisora = '';

    /** 'municipio_queretaro' | 'otra' | '' (sin decidir): quién expide el documento. */
    public string $jurisdiccionEmisora = '';

    public string $folioDocumento = '';

    /** 'alto' | 'medio' | 'bajo' | 'no_indicado' | '' (sin decidir): la que dice el documento, no una valoración del sistema. */
    public string $clasificacionRiesgo = '';

    /** Año de trámite que indique el documento (solo Visto Bueno del Municipio de Querétaro). */
    public string $ejercicio = '';

    /** 'fecha_expresa' | 'sin_fecha_expresa' | '' (sin decidir). */
    public string $tipoVigencia = '';

    public string $fechaVencimientoDocumento = '';

    #[Normalizar(Normalizacion::TextoLargo)]
    public string $condicionantes = '';

    /** El año de trámite solo se pide (y se guarda) para el Visto Bueno del Municipio de Querétaro. */
    public function pideEjercicio(): bool
    {
        return $this->jurisdiccionEmisora === 'municipio_queretaro' && $this->tipoInstrumento === 'visto_bueno';
    }

    public function rules(): array
    {
        return [
            'tipoInstrumento' => ['required', 'in:visto_bueno,dictamen,otro'],
            'tipoInstrumentoOtro' => $this->tipoInstrumento === 'otro' ? ReglasCaptura::texto(requerido: true, max: 150) : ['nullable'],
            'fechaEmision' => ReglasCaptura::fechaPasada(requerido: true),
            'autoridadEmisora' => ReglasCaptura::texto(requerido: true, max: 200),
            'jurisdiccionEmisora' => ['required', 'in:municipio_queretaro,otra'],
            'folioDocumento' => ReglasCaptura::texto(max: 50),
            'clasificacionRiesgo' => ['required', 'in:alto,medio,bajo,no_indicado'],
            'ejercicio' => $this->pideEjercicio() ? ['nullable', 'integer', 'between:2017,'.(now()->year + 1), $this->noAnteriorAlAnioDeEmision(...)] : ['nullable'],
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
            'tipoInstrumento' => 'tipo de documento',
            'tipoInstrumentoOtro' => 'descripción del tipo de documento',
            'fechaEmision' => 'fecha de emisión',
            'autoridadEmisora' => 'autoridad emisora',
            'jurisdiccionEmisora' => 'autoridad que expide el documento',
            'folioDocumento' => 'folio del documento',
            'clasificacionRiesgo' => 'clasificación de riesgo',
            'ejercicio' => 'año del Visto Bueno',
            'tipoVigencia' => 'indicación de fecha de vencimiento',
            'fechaVencimientoDocumento' => 'fecha de vencimiento',
            'condicionantes' => 'condicionantes',
        ];
    }

    /**
     * Valores para DatosDocumento (clave = parámetro del DTO). La descripción del tipo solo viaja con
     * "otro", el año solo con el Visto Bueno de Querétaro y la fecha de vencimiento solo con fecha
     * expresa; cadena vacía = null.
     *
     * @return array<string, int|string|null>
     */
    public function datosParaDocumento(): array
    {
        $texto = fn (string $valor): ?string => $valor !== '' ? $valor : null;

        return [
            'tipoInstrumento' => $texto($this->tipoInstrumento),
            'tipoInstrumentoOtro' => $this->tipoInstrumento === 'otro' ? $texto($this->tipoInstrumentoOtro) : null,
            'fechaEmision' => $texto($this->fechaEmision),
            'autoridadEmisora' => $texto($this->autoridadEmisora),
            'jurisdiccionEmisora' => $texto($this->jurisdiccionEmisora),
            'folioDocumento' => $texto($this->folioDocumento),
            'clasificacionRiesgo' => $texto($this->clasificacionRiesgo),
            'ejercicio' => $this->pideEjercicio() && $this->ejercicio !== '' ? (int) $this->ejercicio : null,
            'tipoVigencia' => $texto($this->tipoVigencia),
            'fechaVencimientoDocumento' => $this->tipoVigencia === 'fecha_expresa' ? $texto($this->fechaVencimientoDocumento) : null,
            'condicionantes' => $texto($this->condicionantes),
        ];
    }

    private function esFecha(mixed $valor): bool
    {
        return is_string($valor) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor) === 1;
    }

    /** Solo compara cuando ambas fechas ya tienen formato válido; cada una tiene su propia regla de formato. */
    private function noAnteriorALaEmision(string $atributo, mixed $vencimiento, Closure $fallar): void
    {
        if ($this->esFecha($vencimiento) && $this->esFecha($this->fechaEmision) && $vencimiento < $this->fechaEmision) {
            $fallar('La fecha de vencimiento no puede ser anterior a la fecha de emisión.');
        }
    }

    private function noAnteriorAlAnioDeEmision(string $atributo, mixed $ejercicio, Closure $fallar): void
    {
        if (is_numeric($ejercicio) && $this->esFecha($this->fechaEmision) && (int) $ejercicio < (int) substr($this->fechaEmision, 0, 4)) {
            $fallar('El año no puede ser anterior al año de emisión.');
        }
    }
}
