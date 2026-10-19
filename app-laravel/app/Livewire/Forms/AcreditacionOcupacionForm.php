<?php

namespace App\Livewire\Forms;

use App\Application\Captura\Normalizacion;
use App\Application\Captura\Normalizar;
use App\Application\Captura\ReglasCaptura;
use Closure;
use Illuminate\Validation\Rule;
use Livewire\Form;

class AcreditacionOcupacionForm extends Form
{
    public string $tipo = 'escritura_publica';

    public string $numeroEscritura = '';

    public string $fechaEscritura = '';

    public string $propietarioNombre = '';

    public string $notarioNombre = '';

    public string $notarioNumero = '';

    /** Ids del catálogo geográfico (ADR-017); '' = sin elegir. El texto libre anterior vive solo en filas viejas. */
    public string $notarioEntidadFederativaId = '';

    public string $notarioMunicipioId = '';

    public string $folioRpp = '';

    public string $fechaInscripcionRpp = '';

    public string $arrendadorComodante = '';

    public string $arrendatarioComodatario = '';

    public string $fechaContrato = '';

    public string $fechaInicioVigencia = '';

    public string $vigenciaContrato = '';

    public bool $vigenciaIndeterminada = false;

    public string $usoAutorizado = '';

    public bool $ratificadoNotario = false;

    public string $ratificacionFecha = '';

    public string $ratificacionNotarioNombre = '';

    public string $ratificacionNotariaNumero = '';

    public bool $arrendadorEsPropietario = true;

    public string $facultadesReferencia = '';

    public string $otroEspecifique = '';

    public string $otroReferencia = '';

    public string $otroTitular = '';

    public string $otroFechaExpedicion = '';

    public string $otroAutoridad = '';

    public string $otroDerecho = '';

    #[Normalizar(Normalizacion::TextoLargo)]
    public string $observaciones = '';

    /**
     * Reglas por variante de `tipo` (los 4 valores del CHECK de
     * acreditaciones_ocupacion_legal). Solo se validan los campos de la
     * variante elegida: los de otra figura quedan fuera de las reglas y fuera
     * de datosParaTipo(). La obligatoriedad es de completitud de datos; la
     * suficiencia jurídica del instrumento la decide la autoridad. La
     * ratificación notarial se captura pero no se exige (depende del nivel
     * educativo, ver PENDIENTE-dictamenes-por-nivel.md).
     */
    public function rules(): array
    {
        $comunes = [
            'tipo' => ['required', 'in:escritura_publica,arrendamiento,comodato,otro'],
            'observaciones' => ReglasCaptura::texto(max: 1000),
        ];

        return $comunes + match ($this->tipo) {
            'escritura_publica' => $this->reglasEscritura(),
            'arrendamiento', 'comodato' => $this->reglasContrato(),
            'otro' => $this->reglasOtro(),
            default => [],
        };
    }

    /** @return array<string, list<mixed>> */
    private function reglasEscritura(): array
    {
        return [
            'numeroEscritura' => ReglasCaptura::texto(requerido: true, max: 50),
            'fechaEscritura' => ReglasCaptura::fechaPasada(requerido: true),
            'propietarioNombre' => ReglasCaptura::texto(requerido: true, max: 200),
            'notarioNombre' => ReglasCaptura::nombrePersona(requerido: true, max: 150),
            'notarioNumero' => ReglasCaptura::texto(max: 20),
            'notarioEntidadFederativaId' => ['nullable', 'integer', Rule::exists('entidades_federativas', 'id')],
            'notarioMunicipioId' => [
                'nullable', 'integer',
                Rule::exists('municipios', 'id')->where('entidad_federativa_id', $this->notarioEntidadFederativaId === '' ? 0 : (int) $this->notarioEntidadFederativaId),
            ],
            'folioRpp' => ReglasCaptura::texto(max: 50),
            'fechaInscripcionRpp' => ReglasCaptura::fechaPasada(),
        ];
    }

    /** @return array<string, list<mixed>> */
    private function reglasContrato(): array
    {
        return [
            'arrendadorComodante' => ReglasCaptura::texto(requerido: true, max: 200),
            'arrendatarioComodatario' => ReglasCaptura::texto(requerido: true, max: 200),
            'fechaContrato' => ReglasCaptura::fechaPasada(requerido: true),
            'fechaInicioVigencia' => ReglasCaptura::fecha(requerido: true),
            'vigenciaContrato' => [
                ...ReglasCaptura::fecha(requerido: ! $this->vigenciaIndeterminada),
                $this->noAnteriorA('fechaContrato', 'La vigencia del contrato no puede ser anterior a la fecha del contrato.'),
                $this->noAnteriorA('fechaInicioVigencia', 'La vigencia del contrato no puede ser anterior al inicio de la vigencia.'),
            ],
            'vigenciaIndeterminada' => ['nullable', 'boolean'],
            'usoAutorizado' => ReglasCaptura::texto(requerido: true, max: 200),
            'ratificadoNotario' => ['nullable', 'boolean'],
            'ratificacionFecha' => ReglasCaptura::fechaPasada(),
            'ratificacionNotarioNombre' => ReglasCaptura::nombrePersona(max: 150),
            'ratificacionNotariaNumero' => ReglasCaptura::texto(max: 20),
            'arrendadorEsPropietario' => ['nullable', 'boolean'],
            'facultadesReferencia' => ReglasCaptura::texto(requerido: ! $this->arrendadorEsPropietario, max: 200),
        ];
    }

    /** @return array<string, list<mixed>> */
    private function reglasOtro(): array
    {
        return [
            'otroEspecifique' => ReglasCaptura::texto(requerido: true, max: 200),
            'otroReferencia' => ReglasCaptura::texto(requerido: true, max: 100),
            'otroTitular' => ReglasCaptura::texto(requerido: true, max: 200),
            'otroFechaExpedicion' => ReglasCaptura::fechaPasada(requerido: true),
            'otroAutoridad' => ReglasCaptura::texto(requerido: true, max: 200),
            'otroDerecho' => ReglasCaptura::texto(requerido: true, max: 200),
            'usoAutorizado' => ReglasCaptura::texto(requerido: true, max: 200),
            'fechaInicioVigencia' => ReglasCaptura::fecha(),
            'vigenciaContrato' => [
                ...ReglasCaptura::fecha(),
                $this->noAnteriorA('fechaInicioVigencia', 'La vigencia no puede ser anterior a su inicio.'),
            ],
            'folioRpp' => ReglasCaptura::texto(max: 50),
            'fechaInscripcionRpp' => ReglasCaptura::fechaPasada(),
        ];
    }

    public function validationAttributes(): array
    {
        return [
            'tipo' => 'tipo de acreditación',
            'numeroEscritura' => 'número de escritura',
            'fechaEscritura' => 'fecha de la escritura',
            'propietarioNombre' => 'propietario',
            'notarioNombre' => 'nombre del notario',
            'notarioNumero' => 'número de notaría',
            'notarioEntidadFederativaId' => 'entidad federativa',
            'notarioMunicipioId' => 'municipio',
            'folioRpp' => 'folio del Registro Público de la Propiedad',
            'fechaInscripcionRpp' => 'fecha de inscripción',
            'arrendadorComodante' => 'arrendador / comodante',
            'arrendatarioComodatario' => 'arrendatario / comodatario',
            'fechaContrato' => 'fecha de celebración',
            'fechaInicioVigencia' => 'inicio de la vigencia',
            'vigenciaContrato' => 'fin de la vigencia',
            'vigenciaIndeterminada' => 'vigencia indeterminada',
            'usoAutorizado' => 'uso autorizado',
            'ratificadoNotario' => 'ratificado ante notario',
            'ratificacionFecha' => 'fecha de ratificación',
            'ratificacionNotarioNombre' => 'notario que ratificó',
            'ratificacionNotariaNumero' => 'número de notaría de la ratificación',
            'arrendadorEsPropietario' => 'el arrendador / comodante es el propietario',
            'facultadesReferencia' => 'documento que acredita las facultades',
            'otroEspecifique' => 'denominación del instrumento',
            'otroReferencia' => 'número o referencia',
            'otroTitular' => 'titular o beneficiario',
            'otroFechaExpedicion' => 'fecha de expedición',
            'otroAutoridad' => 'autoridad o fedatario',
            'otroDerecho' => 'derecho que acredita',
            'observaciones' => 'observaciones',
        ];
    }

    /**
     * Valores para DatosDocumento (clave = parámetro del DTO) con solo los
     * campos de la variante elegida; cadena vacía = null. RegistrarDocumento
     * vuelve a aplicar este filtro, así que no depende de este método.
     *
     * @return array<string, string|bool|null>
     */
    public function datosParaTipo(): array
    {
        $texto = fn (string $valor): ?string => $valor !== '' ? $valor : null;
        $comunes = ['tipoAcreditacion' => $this->tipo, 'observaciones' => $texto($this->observaciones)];

        return $comunes + match ($this->tipo) {
            'escritura_publica' => [
                'numeroEscritura' => $texto($this->numeroEscritura),
                'fechaEscritura' => $texto($this->fechaEscritura),
                'propietarioNombre' => $texto($this->propietarioNombre),
                'notarioNombre' => $texto($this->notarioNombre),
                'notarioNumero' => $texto($this->notarioNumero),
                'notarioEntidadFederativaId' => $this->idOnulo($this->notarioEntidadFederativaId),
                'notarioMunicipioId' => $this->idOnulo($this->notarioMunicipioId),
                'folioRpp' => $texto($this->folioRpp),
                'fechaInscripcionRpp' => $texto($this->fechaInscripcionRpp),
            ],
            'arrendamiento', 'comodato' => [
                'arrendadorComodante' => $texto($this->arrendadorComodante),
                'arrendatarioComodatario' => $texto($this->arrendatarioComodatario),
                'fechaContrato' => $texto($this->fechaContrato),
                'fechaInicioVigencia' => $texto($this->fechaInicioVigencia),
                'vigenciaContrato' => $this->vigenciaIndeterminada ? null : $texto($this->vigenciaContrato),
                'vigenciaIndeterminada' => $this->vigenciaIndeterminada,
                'usoAutorizado' => $texto($this->usoAutorizado),
                'ratificadoNotario' => $this->ratificadoNotario,
                'ratificacionFecha' => $this->ratificadoNotario ? $texto($this->ratificacionFecha) : null,
                'ratificacionNotarioNombre' => $this->ratificadoNotario ? $texto($this->ratificacionNotarioNombre) : null,
                'ratificacionNotariaNumero' => $this->ratificadoNotario ? $texto($this->ratificacionNotariaNumero) : null,
                'arrendadorEsPropietario' => $this->arrendadorEsPropietario,
                'facultadesReferencia' => $this->arrendadorEsPropietario ? null : $texto($this->facultadesReferencia),
            ],
            'otro' => [
                'otroEspecifique' => $texto($this->otroEspecifique),
                'otroReferencia' => $texto($this->otroReferencia),
                'otroTitular' => $texto($this->otroTitular),
                'otroFechaExpedicion' => $texto($this->otroFechaExpedicion),
                'otroAutoridad' => $texto($this->otroAutoridad),
                'otroDerecho' => $texto($this->otroDerecho),
                'usoAutorizado' => $texto($this->usoAutorizado),
                'fechaInicioVigencia' => $texto($this->fechaInicioVigencia),
                'vigenciaContrato' => $texto($this->vigenciaContrato),
                'folioRpp' => $texto($this->folioRpp),
                'fechaInscripcionRpp' => $texto($this->fechaInscripcionRpp),
            ],
            default => [],
        };
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'notarioEntidadFederativaId.exists' => 'La entidad federativa seleccionada no existe.',
            'notarioMunicipioId.exists' => 'El municipio no pertenece a la entidad federativa seleccionada.',
        ];
    }

    private function idOnulo(string $valor): ?int
    {
        return $valor !== '' ? (int) $valor : null;
    }

    /** Solo compara cuando ambas fechas ya tienen formato válido; cada una tiene su propia regla de formato. */
    private function noAnteriorA(string $campoReferencia, string $mensaje): Closure
    {
        $esFecha = fn (mixed $valor): bool => is_string($valor) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor) === 1;

        return function (string $atributo, mixed $valor, Closure $fallar) use ($campoReferencia, $mensaje, $esFecha): void {
            $referencia = $this->{$campoReferencia};

            if ($esFecha($valor) && $esFecha($referencia) && $valor < $referencia) {
                $fallar($mensaje);
            }
        };
    }
}
