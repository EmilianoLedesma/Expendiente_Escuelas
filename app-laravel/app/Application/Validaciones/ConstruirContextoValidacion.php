<?php

namespace App\Application\Validaciones;

use App\Application\Documentos\DocumentosCompletos;
use App\Application\Excepciones\PrecondicionIncumplida;
use App\Application\ResponsableLegal\TipoPersonaDeEscuela;
use App\Application\Tramite\EstadoPaso2;
use App\Domain\Validaciones\Documental\ContextoValidacion;
use App\Domain\Validaciones\Documental\Hecho;
use App\Domain\Validaciones\Documental\TipoHecho;
use App\Models\HechoDocumento;
use App\Models\PersonaFisica;
use Illuminate\Database\Query\JoinClause;

/**
 * The only place the documental engine touches the database. Reuses
 * TipoPersonaDeEscuela and DocumentosCompletos rather than re-deciding
 * which documents apply (ADR-006: one source per question).
 */
class ConstruirContextoValidacion
{
    public function __construct(
        private readonly TipoPersonaDeEscuela $tipoPersonaDeEscuela,
        private readonly DocumentosCompletos $documentosCompletos,
    ) {}

    public function ejecutar(int $escuelaId): ContextoValidacion
    {
        $tipoPersona = $this->tipoPersonaDeEscuela->ejecutar($escuelaId);

        if ($tipoPersona === null) {
            throw new PrecondicionIncumplida(EstadoPaso2::RESPONSABLE, 'Captura el responsable legal (Paso 2) antes de validar documentos.');
        }

        $requeridas = $this->documentosCompletos->clavesAplicables($tipoPersona);
        $pendientes = $this->documentosCompletos->clavesPendientes($escuelaId, $tipoPersona);

        return new ContextoValidacion(
            tipoPersona: $tipoPersona,
            hechos: [...$this->hechosDeclarados($escuelaId), ...$this->hechosDeDocumentosVigentes($escuelaId)],
            clavesRequeridas: $requeridas,
            clavesPresentes: array_values(array_diff($requeridas, $pendientes)),
        );
    }

    /** @return list<Hecho> */
    private function hechosDeclarados(int $escuelaId): array
    {
        $persona = PersonaFisica::whereHas('responsableLegal', fn ($query) => $query->where('escuela_id', $escuelaId))->first();

        if ($persona === null) {
            return [];
        }

        $hechos = [];

        foreach ([TipoHecho::NombreTitular->value => $persona->nombre, TipoHecho::Curp->value => $persona->curp] as $tipo => $valor) {
            if ($valor !== null && trim($valor) !== '') {
                $hechos[] = Hecho::declarado(TipoHecho::from($tipo), $valor);
            }
        }

        return $hechos;
    }

    /**
     * Only facts whose archivo_path is still the document's current file:
     * a replaced upload keeps the same documentos_escuela row, so matching
     * on the row alone would validate a file that no longer exists.
     *
     * @return list<Hecho>
     */
    private function hechosDeDocumentosVigentes(int $escuelaId): array
    {
        return HechoDocumento::query()
            ->join('documentos_escuela', function (JoinClause $join) {
                $join->on('documentos_escuela.escuela_id', '=', 'hechos_documento.escuela_id')
                    ->on('documentos_escuela.tipo_documento_id', '=', 'hechos_documento.tipo_documento_id')
                    ->on('documentos_escuela.archivo_path', '=', 'hechos_documento.archivo_path');
            })
            ->join('tipos_documentos', 'tipos_documentos.id', '=', 'hechos_documento.tipo_documento_id')
            ->where('hechos_documento.escuela_id', $escuelaId)
            ->orderBy('hechos_documento.id')
            ->get(['hechos_documento.tipo_hecho', 'hechos_documento.valor', 'tipos_documentos.clave'])
            ->map(fn (HechoDocumento $fila) => Hecho::deDocumento(TipoHecho::from($fila->tipo_hecho), $fila->valor, (string) $fila->getAttribute('clave')))
            ->values()
            ->all();
    }
}
