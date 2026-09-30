<?php

namespace App\Domain\Validaciones\Documental;

/**
 * Everything a documental rule may read. Built by
 * App\Application\Validaciones\ConstruirContextoValidacion; rules never
 * query the database or open files.
 */
final readonly class ContextoValidacion
{
    /**
     * @param  list<Hecho>  $hechos
     * @param  list<string>  $clavesRequeridas  tipos_documentos.clave applicable to this tipo_persona
     * @param  list<string>  $clavesPresentes  tipos_documentos.clave already uploaded
     */
    public function __construct(
        public string $tipoPersona,
        public array $hechos,
        public array $clavesRequeridas,
        public array $clavesPresentes,
    ) {}

    public function declarado(TipoHecho $tipo): ?Hecho
    {
        return $this->buscar($tipo, OrigenHecho::Declarado, null);
    }

    public function deDocumento(TipoHecho $tipo, string $documentoClave): ?Hecho
    {
        return $this->buscar($tipo, OrigenHecho::Documento, $documentoClave);
    }

    private function buscar(TipoHecho $tipo, OrigenHecho $origen, ?string $documentoClave): ?Hecho
    {
        foreach ($this->hechos as $hecho) {
            if ($hecho->tipo === $tipo && $hecho->origen === $origen && $hecho->documentoClave === $documentoClave) {
                return $hecho;
            }
        }

        return null;
    }
}
