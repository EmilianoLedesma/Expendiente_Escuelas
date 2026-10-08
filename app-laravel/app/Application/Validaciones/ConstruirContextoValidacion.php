<?php

namespace App\Application\Validaciones;

use App\Application\Documentos\DocumentosCompletos;
use App\Application\Documentos\DocumentosNivelCompletos;
use App\Application\Excepciones\PrecondicionIncumplida;
use App\Application\ResponsableLegal\TipoPersonaDeEscuela;
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
use App\Models\DocumentoEscuelaNivel;
use App\Models\DocumentoPlantel;
use App\Models\Escuela;
use App\Models\EscuelaNivel;
use App\Models\Gestor;
use App\Models\InstalacionEspacio;
use App\Models\PersonaFisica;
use App\Models\PersonaMoral;
use App\Models\Plantel;
use App\Models\ReciboPagoDerechos;
use App\Models\RelacionAcervoBibliografico;
use App\Models\ResponsableLegal;
use App\Models\TipoDocumento;
use Illuminate\Support\Facades\DB;

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
 * Per escuela_nivel (paraNivel, Paso 2.4 documents):
 * - Recibo folio from recibos_pago_derechos; every other level's folio, of
 *   any trámite, as foliosAjenos.
 * - Acervo titles: declared = titles of all material in the plantel's library (ADR-010 P6)
 *   (Paso 3 infraestructura, shared by its levels per ADR-005); document =
 *   the relation's captured count.
 * - Laboratories declared = sum of laboratorio_polifuncional in the plantel.
 *
 * Reuses DocumentosCompletos / DocumentosNivelCompletos for which documents
 * apply (ADR-006).
 */
class ConstruirContextoValidacion
{
    public const ESPACIO_LABORATORIO = 'laboratorio_polifuncional';

    public function __construct(
        private readonly DocumentosCompletos $documentosCompletos,
        private readonly DocumentosNivelCompletos $documentosNivelCompletos,
        private readonly TipoPersonaDeEscuela $tipoPersonaDeEscuela,
    ) {}

    public function paraNivel(int $escuelaNivelId): ContextoValidacion
    {
        $escuelaNivel = EscuelaNivel::with('escuela')->findOrFail($escuelaNivelId);
        /** @var Escuela $escuela */
        $escuela = $escuelaNivel->escuela;
        $tipoPersona = $this->tipoPersonaDeEscuela->ejecutar($escuela->id);

        if ($tipoPersona === null) {
            throw new PrecondicionIncumplida(EstadoPaso2::RESPONSABLE, 'Captura el responsable legal (Paso 2) antes de validar documentos.');
        }
        $requeridas = $this->documentosNivelCompletos->clavesAplicables($escuelaNivelId);
        $pendientes = $this->documentosNivelCompletos->clavesPendientes($escuelaNivelId);

        $documentos = DocumentoEscuelaNivel::query()
            ->join('tipos_documentos', 'tipos_documentos.id', '=', 'documentos_escuela_nivel.tipo_documento_id')
            ->where('documentos_escuela_nivel.escuela_nivel_id', $escuelaNivelId)
            ->pluck('tipos_documentos.clave', 'documentos_escuela_nivel.id');

        $hechos = [];

        foreach (ReciboPagoDerechos::whereIn('documento_escuela_nivel_id', $documentos->keys())->whereNotNull('folio')->get() as $recibo) {
            $hechos[] = Hecho::deDocumento(TipoHecho::FolioRecibo, (string) $recibo->folio, (string) $documentos[$recibo->documento_escuela_nivel_id]);
        }

        foreach (RelacionAcervoBibliografico::whereIn('documento_escuela_nivel_id', $documentos->keys())->get() as $relacion) {
            $hechos[] = Hecho::deDocumento(TipoHecho::TitulosAcervo, (string) $relacion->numero_titulos, (string) $documentos[$relacion->documento_escuela_nivel_id]);
        }

        $biblioteca = DB::table('biblioteca_materiales')
            ->join('instalaciones_espacios', 'instalaciones_espacios.id', '=', 'biblioteca_materiales.instalacion_espacio_id')
            ->where('instalaciones_espacios.plantel_id', $escuela->plantel_id)
            ->whereNotNull('biblioteca_materiales.numero_titulos')
            ->selectRaw('COUNT(*) AS filas, COALESCE(SUM(biblioteca_materiales.numero_titulos), 0) AS titulos')
            ->first();
        if ($biblioteca !== null && (int) $biblioteca->filas > 0) {
            $hechos[] = Hecho::declarado(TipoHecho::TitulosAcervo, (string) (int) $biblioteca->titulos);
        }

        // A declared space with no quantity still counts as one.
        $laboratorios = InstalacionEspacio::query()
            ->join('tipos_espacios', 'tipos_espacios.id', '=', 'instalaciones_espacios.tipo_espacio_id')
            ->where('instalaciones_espacios.plantel_id', $escuela->plantel_id)
            ->where('tipos_espacios.clave', self::ESPACIO_LABORATORIO)
            ->sum(DB::raw('COALESCE(instalaciones_espacios.cantidad, 1)'));
        $hechos[] = Hecho::declarado(TipoHecho::LaboratoriosDeclarados, (string) (int) $laboratorios);

        // Only the other levels' folios equal to this one (same normalization
        // as ReciboNoReutilizado) — never every folio of every trámite.
        $folioPropio = ReciboPagoDerechos::whereIn('documento_escuela_nivel_id', $documentos->keys())->whereNotNull('folio')->value('folio');
        $foliosAjenos = $folioPropio === null ? [] : ReciboPagoDerechos::query()
            ->join('documentos_escuela_nivel', 'documentos_escuela_nivel.id', '=', 'recibos_pago_derechos.documento_escuela_nivel_id')
            ->where('documentos_escuela_nivel.escuela_nivel_id', '!=', $escuelaNivelId)
            ->whereRaw('UPPER(TRIM(recibos_pago_derechos.folio)) = ?', [mb_strtoupper(trim((string) $folioPropio))])
            ->pluck('recibos_pago_derechos.folio')
            ->map(fn ($folio) => (string) $folio)
            ->values()
            ->all();

        return new ContextoValidacion(
            tipoPersona: $tipoPersona,
            hechos: $hechos,
            clavesRequeridas: $requeridas,
            clavesPresentes: array_values(array_diff($requeridas, $pendientes)),
            foliosAjenos: $foliosAjenos,
        );
    }

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
