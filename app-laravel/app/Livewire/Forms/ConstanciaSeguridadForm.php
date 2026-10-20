<?php

namespace App\Livewire\Forms;

use App\Application\Captura\ReglasCaptura;
use Closure;
use Livewire\Form;

/**
 * Captura de la Constancia de Seguridad Estructural tal como aparece en el documento
 * (docs/superpowers/specs/2026-10-20-constancia-seguridad-design.md). Datos de la
 * constancia (folio, emisión, vigencia declarada) y datos del profesional que firma
 * (calidad, cédula, registro y sus fechas). Solo consistencia de captura: la suficiencia
 * jurídica del documento y del profesional la decide SEDEQ; ninguna fecha del registro
 * se exige vigente aquí. Para cada regla el caso de uso repite la validación.
 */
class ConstanciaSeguridadForm extends Form
{
    public string $fechaEmision = '';

    public string $folioConstancia = '';

    /** 'fecha_expresa' | 'sin_fecha_expresa' | '' (sin decidir): lo que dice el documento, no el sistema. */
    public string $tipoVigencia = '';

    public string $vigenciaConstancia = '';

    public string $peritoNombre = '';

    /** 'dro' | 'corresponsable_seguridad_estructural' | 'otra' | '' (sin decidir). */
    public string $calidadProfesional = '';

    public string $calidadProfesionalOtra = '';

    public string $peritoCedulaProfesional = '';

    /** Número de registro profesional, sea cual sea la calidad (la columna conserva el nombre histórico). */
    public string $peritoRegistroDro = '';

    public string $peritoRegistroAutoridad = '';

    /** Fecha de registro o refrendo que declara el documento. */
    public string $peritoRegistroFecha = '';

    public string $peritoRegistroVencimiento = '';

    public function rules(): array
    {
        return [
            'fechaEmision' => ReglasCaptura::fechaPasada(requerido: true),
            'folioConstancia' => ReglasCaptura::texto(max: 50),
            'tipoVigencia' => ['required', 'in:fecha_expresa,sin_fecha_expresa'],
            'vigenciaConstancia' => $this->tipoVigencia === 'fecha_expresa'
                ? [...ReglasCaptura::fecha(requerido: true), $this->noAnteriorA('fechaEmision', 'La fecha de vencimiento no puede ser anterior a la fecha de emisión.')]
                : ['nullable'],
            'peritoNombre' => ReglasCaptura::nombrePersona(requerido: true, max: 200),
            'calidadProfesional' => ['required', 'in:dro,corresponsable_seguridad_estructural,otra'],
            'calidadProfesionalOtra' => $this->calidadProfesional === 'otra'
                ? ReglasCaptura::texto(requerido: true, max: 150)
                : ['nullable'],
            'peritoCedulaProfesional' => ReglasCaptura::texto(max: 50),
            'peritoRegistroDro' => ReglasCaptura::texto(requerido: true, max: 50),
            'peritoRegistroAutoridad' => ReglasCaptura::texto(max: 150),
            'peritoRegistroFecha' => ReglasCaptura::fechaPasada(),
            'peritoRegistroVencimiento' => [...ReglasCaptura::fecha(), $this->noAnteriorA('peritoRegistroFecha', 'El vencimiento del registro no puede ser anterior a su fecha de registro o refrendo.')],
        ];
    }

    public function validationAttributes(): array
    {
        return [
            'fechaEmision' => 'fecha de emisión',
            'folioConstancia' => 'folio de la constancia',
            'tipoVigencia' => 'indicación de fecha de vencimiento',
            'vigenciaConstancia' => 'fecha de vencimiento',
            'peritoNombre' => 'nombre del profesional',
            'calidadProfesional' => 'calidad profesional',
            'calidadProfesionalOtra' => 'descripción de la calidad',
            'peritoCedulaProfesional' => 'cédula profesional',
            'peritoRegistroDro' => 'número de registro',
            'peritoRegistroAutoridad' => 'autoridad del registro',
            'peritoRegistroFecha' => 'fecha de registro o refrendo',
            'peritoRegistroVencimiento' => 'vencimiento del registro',
        ];
    }

    /**
     * Valores para DatosDocumento (clave = parámetro del DTO). La fecha de vencimiento solo
     * viaja con `fecha_expresa` y la descripción de la calidad solo con `otra`; cadena vacía = null.
     *
     * @return array<string, string|null>
     */
    public function datosParaDocumento(): array
    {
        $texto = fn (string $valor): ?string => $valor !== '' ? $valor : null;

        return [
            'fechaEmision' => $texto($this->fechaEmision),
            'folioConstancia' => $texto($this->folioConstancia),
            'tipoVigencia' => $texto($this->tipoVigencia),
            'vigenciaConstancia' => $this->tipoVigencia === 'fecha_expresa' ? $texto($this->vigenciaConstancia) : null,
            'peritoNombre' => $texto($this->peritoNombre),
            'calidadProfesional' => $texto($this->calidadProfesional),
            'calidadProfesionalOtra' => $this->calidadProfesional === 'otra' ? $texto($this->calidadProfesionalOtra) : null,
            'peritoCedulaProfesional' => $texto($this->peritoCedulaProfesional),
            'peritoRegistroDro' => $texto($this->peritoRegistroDro),
            'peritoRegistroAutoridad' => $texto($this->peritoRegistroAutoridad),
            'peritoRegistroFecha' => $texto($this->peritoRegistroFecha),
            'peritoRegistroVencimiento' => $texto($this->peritoRegistroVencimiento),
        ];
    }

    /** Solo compara cuando ambas fechas ya tienen formato válido; cada una tiene su propia regla de formato. */
    private function noAnteriorA(string $propiedadInicio, string $mensaje): Closure
    {
        return function (string $atributo, mixed $fin, Closure $fallar) use ($propiedadInicio, $mensaje): void {
            $esFecha = fn (mixed $valor): bool => is_string($valor) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor) === 1;
            $inicio = $this->{$propiedadInicio};

            if ($esFecha($fin) && $esFecha($inicio) && $fin < $inicio) {
                $fallar($mensaje);
            }
        };
    }
}
