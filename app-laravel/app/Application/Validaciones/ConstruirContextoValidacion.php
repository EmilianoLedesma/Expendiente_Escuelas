<?php

namespace App\Application\Validaciones;

use App\Application\Documentos\DocumentosCompletos;
use App\Application\Excepciones\PrecondicionIncumplida;
use App\Application\Tramite\EstadoPaso2;
use App\Domain\Validaciones\Documental\CatalogoReglasDocumentales;
use App\Domain\Validaciones\Documental\ContextoValidacion;
use App\Domain\Validaciones\Documental\Hecho;
use App\Domain\Validaciones\Documental\TipoHecho;
use App\Models\CertificadoNumeroOficial;
use App\Models\ConstanciaCurp;
use App\Models\ConstanciaSituacionFiscal;
use App\Models\CredencialIne;
use App\Models\DocumentoEscuela;
use App\Models\DocumentoPlantel;
use App\Models\Escuela;
use App\Models\Gestor;
use App\Models\PersonaFisica;
use App\Models\PersonaMoral;
use App\Models\Plantel;
use App\Models\ResponsableLegal;
use App\Models\TipoDocumento;

/**
 * The only place the documental engine touches the database. It decides
 * whose data is "declared" for each fact type (ADR-007):
 *
 * - Identity (name, CURP) = the person who acts and whose INE / Constancia
 *   de CURP is uploaded: the titular (fisica), the gestor
 *   (fisica_con_gestor) or the legal representative (moral, no CURP column).
 * - Fiscal (name, RFC) = the taxpayer: the titular, or the persona moral
 *   (razón social; personas_morales has no RFC column).
 * - Address = the plantel captured in Paso 1.
 *
 * Reuses DocumentosCompletos for which documents apply (ADR-006).
 */
class ConstruirContextoValidacion
{
    public function __construct(private readonly DocumentosCompletos $documentosCompletos) {}

    public function ejecutar(int $escuelaId): ContextoValidacion
    {
        $escuela = Escuela::with(['plantel', 'responsableLegal.personaFisica', 'responsableLegal.personaMoral', 'responsableLegal.gestor'])->findOrFail($escuelaId);
        /** @var ResponsableLegal|null $responsable */
        $responsable = $escuela->responsableLegal;

        if ($responsable === null) {
            throw new PrecondicionIncumplida(EstadoPaso2::RESPONSABLE, 'Captura el responsable legal (Paso 2) antes de validar documentos.');
        }

        $tipoPersona = $responsable->tipo_persona;
        $requeridas = $this->documentosCompletos->clavesAplicables($tipoPersona);
        $pendientes = $this->documentosCompletos->clavesPendientes($escuelaId, $tipoPersona);
        /** @var Plantel $plantel */
        $plantel = $escuela->plantel;

        return new ContextoValidacion(
            tipoPersona: $tipoPersona,
            hechos: [
                ...$this->declarados($responsable, $plantel),
                ...$this->deDocumentosDeEscuela($escuelaId),
                ...$this->deCertificadoNumeroOficial($plantel->id),
            ],
            clavesRequeridas: $requeridas,
            clavesPresentes: array_values(array_diff($requeridas, $pendientes)),
        );
    }

    /** @return list<Hecho> */
    private function declarados(ResponsableLegal $responsable, Plantel $plantel): array
    {
        /** @var PersonaFisica|null $fisica */
        $fisica = $responsable->personaFisica;
        /** @var PersonaMoral|null $moral */
        $moral = $responsable->personaMoral;
        /** @var Gestor|null $gestor */
        $gestor = $responsable->gestor;

        $valores = match ($responsable->tipo_persona) {
            'fisica' => [
                TipoHecho::NombreIdentidad->value => $fisica?->nombre,
                TipoHecho::Curp->value => $fisica?->curp,
                TipoHecho::NombreFiscal->value => $fisica?->nombre,
                TipoHecho::Rfc->value => $fisica?->rfc,
            ],
            'fisica_con_gestor' => [
                TipoHecho::NombreIdentidad->value => $gestor?->nombre,
                TipoHecho::Curp->value => $gestor?->curp,
                TipoHecho::NombreFiscal->value => $fisica?->nombre,
                TipoHecho::Rfc->value => $fisica?->rfc,
            ],
            default => [
                TipoHecho::NombreIdentidad->value => $moral?->nombre_representante_legal,
                TipoHecho::NombreFiscal->value => $moral?->razon_social,
            ],
        };

        $valores += [
            TipoHecho::DomicilioCalle->value => $plantel->calle,
            TipoHecho::DomicilioNumeroExt->value => $plantel->numero_ext,
            TipoHecho::DomicilioColonia->value => $plantel->colonia,
            TipoHecho::DomicilioMunicipio->value => $plantel->municipio,
            TipoHecho::DomicilioCodigoPostal->value => $plantel->codigo_postal,
        ];

        $hechos = [];
        foreach ($valores as $tipo => $valor) {
            if ($valor !== null && trim($valor) !== '') {
                $hechos[] = Hecho::declarado(TipoHecho::from($tipo), $valor);
            }
        }

        return $hechos;
    }

    /** @return list<Hecho> */
    private function deDocumentosDeEscuela(int $escuelaId): array
    {
        $documentos = DocumentoEscuela::query()
            ->join('tipos_documentos', 'tipos_documentos.id', '=', 'documentos_escuela.tipo_documento_id')
            ->where('documentos_escuela.escuela_id', $escuelaId)
            ->pluck('tipos_documentos.clave', 'documentos_escuela.id');

        $hechos = [];

        foreach (CredencialIne::whereIn('documento_escuela_id', $documentos->keys())->get() as $ine) {
            $hechos[] = Hecho::deDocumento(TipoHecho::NombreIdentidad, $ine->nombre, 'ine');
            $hechos[] = Hecho::deDocumento(TipoHecho::Curp, $ine->curp, 'ine');
        }

        foreach (ConstanciaCurp::whereIn('documento_escuela_id', $documentos->keys())->get() as $constancia) {
            $hechos[] = Hecho::deDocumento(TipoHecho::NombreIdentidad, $constancia->nombre, 'constancia_curp');
            $hechos[] = Hecho::deDocumento(TipoHecho::Curp, $constancia->curp, 'constancia_curp');
        }

        foreach (ConstanciaSituacionFiscal::whereIn('documento_escuela_id', $documentos->keys())->get() as $fiscal) {
            $hechos[] = Hecho::deDocumento(TipoHecho::NombreFiscal, $fiscal->nombre_razon_social, CatalogoReglasDocumentales::FUENTE_FISCAL);
            $hechos[] = Hecho::deDocumento(TipoHecho::Rfc, $fiscal->rfc, CatalogoReglasDocumentales::FUENTE_FISCAL);
        }

        return $hechos;
    }

    /** Plantel-scoped: the certificate belongs to the plantel and applies to each of its escuelas. @return list<Hecho> */
    private function deCertificadoNumeroOficial(int $plantelId): array
    {
        $tipoId = TipoDocumento::where('clave', CatalogoReglasDocumentales::FUENTE_DOMICILIO)->value('id');
        $documentoId = DocumentoPlantel::where('plantel_id', $plantelId)->where('tipo_documento_id', $tipoId)->value('id');
        $certificado = $documentoId === null ? null : CertificadoNumeroOficial::find($documentoId);

        if ($certificado === null) {
            return [];
        }

        $hechos = [];
        foreach ([
            TipoHecho::DomicilioCalle->value => $certificado->calle,
            TipoHecho::DomicilioNumeroExt->value => $certificado->numero_ext,
            TipoHecho::DomicilioColonia->value => $certificado->colonia,
            TipoHecho::DomicilioMunicipio->value => $certificado->municipio,
            TipoHecho::DomicilioCodigoPostal->value => $certificado->codigo_postal,
        ] as $tipo => $valor) {
            if ($valor !== null && trim($valor) !== '') {
                $hechos[] = Hecho::deDocumento(TipoHecho::from($tipo), $valor, CatalogoReglasDocumentales::FUENTE_DOMICILIO);
            }
        }

        return $hechos;
    }
}
