<?php

namespace App\Application\Documentos;

use App\Application\Documentos\DTO\FuenteDomicilio;
use App\Domain\Documentos\ComparadorDomicilios;
use App\Domain\Documentos\Domicilio;
use App\Domain\Documentos\ResultadoComparacion;
use App\Models\Escuela;
use App\Models\EscuelaNivel;

/**
 * Decide si un documento del catálogo es exigible para un expediente (spec 2026-10-20 §5). Hoy el único
 * documento condicionado es el Certificado de Número Oficial; todos los demás son `Obligatorio`.
 *
 * La condición «solo cuando los documentos municipales traen domicilios diferentes» proviene de los requisitos
 * de SEDEQ para Educación Básica y es *Documentado, no contrastado*: mientras `documentos.numero_oficial
 * .alcance_condicional` esté vacío (por defecto) el certificado es obligatorio. Con alcance configurado:
 * sin niveles elegidos → `PendienteDeterminar`; algún nivel fuera del alcance → `Obligatorio`; todos dentro →
 * `NoRequerido` solo con evidencia suficiente de coincidencia, `PendienteDeterminar` en cualquier otro caso.
 * Ausencia de evidencia no equivale a ausencia de discrepancia, y la coincidencia textual es evidencia, no
 * autoridad. La «discrepancia confirmada» solo puede venir de una decisión humana autorizada, aún no
 * implementada. Nada que envíe el solicitante interviene en el resultado.
 *
 * La evidencia sale de FuentesDomicilioMunicipal (sin el propio certificado, cada documento una vez). Hoy esa
 * lista es vacía porque ningún otro documento municipal guarda un domicilio estructurado: el resultado lo dice.
 */
class EvaluarAplicabilidadDocumentos
{
    public const CERTIFICADO_NUMERO_OFICIAL = 'certificado_numero_oficial';

    /** Documentos municipales con domicilio coincidente que se exigen como evidencia suficiente. */
    private const FUENTES_MINIMAS = 2;

    private const FUNDAMENTO = 'COMPENDIO_MAESTRO §5 «Fundamento normativo del Certificado de Número Oficial»; requisitos de Educación Básica §16 (documentados, no contrastados)';

    public function __construct(
        private readonly ComparadorDomicilios $comparador,
        private readonly FuentesDomicilioMunicipal $fuentes = new FuentesDomicilioMunicipal,
    ) {}

    public function para(int $escuelaId, string $clave): ResultadoAplicabilidad
    {
        if ($clave === self::CERTIFICADO_NUMERO_OFICIAL) {
            return $this->certificadoNumeroOficial($escuelaId);
        }

        return new ResultadoAplicabilidad(Aplicabilidad::Obligatorio, 'Documento obligatorio del catálogo.', 'tipos_documentos', 'Confirmado');
    }

    /** @return array<string, ResultadoAplicabilidad> solo los documentos cuya exigibilidad depende del expediente */
    public function condicionales(int $escuelaId): array
    {
        return [self::CERTIFICADO_NUMERO_OFICIAL => $this->certificadoNumeroOficial($escuelaId)];
    }

    /**
     * Fuentes independientes del certificado, una por documento (defensivo: FuentesDomicilioMunicipal ya lo garantiza).
     *
     * @return array<int, FuenteDomicilio> documento_id => fuente
     */
    private function fuentesIndependientes(int $plantelId): array
    {
        $porDocumento = [];
        foreach ($this->fuentes->para($plantelId) as $fuente) {
            if ($fuente->tipoDocumento !== self::CERTIFICADO_NUMERO_OFICIAL) {
                $porDocumento[$fuente->documentoId] ??= $fuente;
            }
        }

        return $porDocumento;
    }

    private function certificadoNumeroOficial(int $escuelaId): ResultadoAplicabilidad
    {
        /** @var list<string> $alcance */
        $alcance = config('documentos.numero_oficial.alcance_condicional', []);

        if ($alcance === []) {
            return new ResultadoAplicabilidad(
                Aplicabilidad::Obligatorio,
                'SEDEQ no ha confirmado el alcance de la condición de domicilios diferentes; se conserva la obligatoriedad.',
                self::FUNDAMENTO,
                'Pendiente de SEDEQ',
            );
        }

        $niveles = EscuelaNivel::query()
            ->join('niveles_educativos', 'niveles_educativos.id', '=', 'escuela_niveles.nivel_educativo_id')
            ->where('escuela_niveles.escuela_id', $escuelaId)
            ->pluck('niveles_educativos.clave')
            ->all();

        if ($niveles === []) {
            return new ResultadoAplicabilidad(Aplicabilidad::PendienteDeterminar, 'Aún no se eligen los niveles del plantel; no se puede saber si aplica la condición.', self::FUNDAMENTO, 'Pendiente de SEDEQ');
        }

        if (array_diff($niveles, $alcance) !== []) {
            return new ResultadoAplicabilidad(Aplicabilidad::Obligatorio, 'Algún nivel del plantel está fuera del alcance en que SEDEQ confirmó la condición.', self::FUNDAMENTO, 'Pendiente de SEDEQ');
        }

        $plantel = Escuela::findOrFail($escuelaId)->plantel;
        $referencia = new Domicilio($plantel->calle, $plantel->numero_ext, $plantel->numero_int, $plantel->colonia, $plantel->municipio, $plantel->codigo_postal);

        $coinciden = [];
        $dudas = false;
        foreach ($this->fuentesIndependientes($plantel->id) as $documentoId => $fuente) {
            if ($this->comparador->comparar($referencia, $fuente->domicilio)->resultado === ResultadoComparacion::CoincidenciaAcreditada) {
                $coinciden[] = $documentoId;
            } else {
                $dudas = true;
            }
        }

        if ($dudas || count($coinciden) < self::FUENTES_MINIMAS) {
            return new ResultadoAplicabilidad(
                Aplicabilidad::PendienteDeterminar,
                'La evidencia de coincidencia de domicilios no basta (se necesitan '.self::FUENTES_MINIMAS.' documentos municipales coincidentes y ninguno en duda; hoy se cuentan '.count($coinciden).'); se trata como exigible.',
                self::FUNDAMENTO,
                'Pendiente de SEDEQ',
                $coinciden,
            );
        }

        return new ResultadoAplicabilidad(Aplicabilidad::NoRequerido, 'Los domicilios de los documentos municipales coinciden con el del plantel.', self::FUNDAMENTO, 'Documentado, no contrastado', $coinciden);
    }
}
