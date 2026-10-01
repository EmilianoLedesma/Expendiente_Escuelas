<?php

namespace App\Domain\Validaciones\Documental\Reglas;

use App\Domain\Validaciones\Documental\ContextoValidacion;
use App\Domain\Validaciones\Documental\ReglaDocumental;
use App\Domain\Validaciones\Documental\TipoHecho;
use App\Domain\Validaciones\Resultado\EstadoResultado;
use App\Domain\Validaciones\Resultado\ResultadoRegla;

/**
 * Compares one fact type across the declared value and every uploaded
 * source document that should carry it. Per document the outcome is
 * coincide / difiere / sin_datos / formato_invalido; the rule's state is
 * the worst of them. A document not uploaded at all is skipped — that is
 * DocumentosRequeridosPresentes' job.
 *
 * A source uploaded without its captured data is always NO_CUMPLE: the
 * applicant can fix it by re-uploading with the data.
 */
abstract readonly class ReglaDeCoincidencia implements ReglaDocumental
{
    /** @param list<string> $fuentes tipos_documentos.clave that carry this fact */
    public function __construct(
        protected string $clave,
        protected TipoHecho $tipo,
        protected array $fuentes,
    ) {}

    abstract protected function coinciden(string $a, string $b): bool;

    /** State when a document's value differs from the reference. */
    abstract protected function estadoSiDifiere(): EstadoResultado;

    abstract protected function etiqueta(): string;

    protected function formatoValido(string $valor): bool
    {
        return true;
    }

    /** Whether documents are compared among themselves when nothing valid was declared. */
    protected function comparaSinDeclarado(): bool
    {
        return false;
    }

    public function evaluar(ContextoValidacion $contexto): ResultadoRegla
    {
        $declarado = $contexto->declarado($this->tipo)?->valor;
        $declaradoInvalido = $declarado !== null && ! $this->formatoValido($declarado);
        $referencia = $declaradoInvalido ? null : $declarado;

        /** @var array<string, array{estado: string, valor: string|null}> $documentos */
        $documentos = [];
        foreach ($this->fuentes as $fuente) {
            if (! $contexto->presente($fuente)) {
                continue;
            }
            $valor = $contexto->deDocumento($this->tipo, $fuente)?->valor;
            $documentos[$fuente] = [
                'estado' => match (true) {
                    $valor === null => 'sin_datos',
                    ! $this->formatoValido($valor) => 'formato_invalido',
                    default => 'pendiente',
                },
                'valor' => $valor,
            ];
        }

        $detalles = ['declarado' => $declarado, 'declarado_invalido' => $declaradoInvalido];

        if ($documentos === []) {
            return new ResultadoRegla($this->clave, EstadoResultado::NoEvaluable, "No hay documentos cargados que contengan {$this->etiqueta()}.", [...$detalles, 'documentos' => []]);
        }

        $comparables = array_filter($documentos, fn (array $d) => $d['estado'] === 'pendiente');
        $sinReferenciaDeclarada = $referencia === null;

        if ($sinReferenciaDeclarada && $this->comparaSinDeclarado() && $comparables !== []) {
            $referencia = (string) reset($comparables)['valor'];
        }

        foreach ($documentos as $fuente => $documento) {
            if ($documento['estado'] === 'pendiente') {
                $documentos[$fuente]['estado'] = $referencia === null
                    ? 'sin_referencia'
                    : ($this->coinciden($referencia, (string) $documento['valor']) ? 'coincide' : 'difiere');
            }
        }

        $detalles['documentos'] = $documentos;
        $conDatosFaltantes = $this->conEstado($documentos, ['sin_datos', 'formato_invalido']);
        $difieren = $this->conEstado($documentos, ['difiere']);

        if ($conDatosFaltantes !== []) {
            return new ResultadoRegla($this->clave, EstadoResultado::NoCumple, "Falta capturar {$this->etiqueta()} de algún documento, o no tiene un formato válido; vuelve a subirlo con sus datos.", $detalles, [...$conDatosFaltantes, ...$difieren]);
        }

        if ($difieren !== []) {
            // With no declared reference, a disagreement can't be pinned on one document.
            $senalados = $sinReferenciaDeclarada ? array_keys($documentos) : $difieren;

            return new ResultadoRegla($this->clave, $this->estadoSiDifiere(), ucfirst($this->etiqueta()).' no coincide entre lo declarado y los documentos.', $detalles, $senalados);
        }

        if ($referencia === null || ($sinReferenciaDeclarada && count($comparables) < 2)) {
            return new ResultadoRegla($this->clave, EstadoResultado::NoEvaluable, "No hay con qué comparar {$this->etiqueta()}.", $detalles);
        }

        return new ResultadoRegla($this->clave, EstadoResultado::Cumple, ucfirst($this->etiqueta()).' coincide en todos los documentos.', $detalles);
    }

    /**
     * @param  array<string, array{estado: string, valor: string|null}>  $documentos
     * @param  list<string>  $estados
     * @return list<string>
     */
    private function conEstado(array $documentos, array $estados): array
    {
        return array_keys(array_filter($documentos, fn (array $d) => in_array($d['estado'], $estados, true)));
    }
}
