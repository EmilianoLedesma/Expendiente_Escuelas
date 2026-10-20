<?php

namespace App\Application\Documentos;

use App\Application\ResponsableLegal\TipoPersonaDeEscuela;
use App\Models\DocumentoEscuelaNivel;
use App\Models\DocumentoPlantel;
use App\Models\Escuela;
use App\Models\TipoDocumento;

/**
 * Corre al avanzar de 2.2 a 2.3, no al subir cada documento
 * (docs/superpowers/specs/2026-09-10-paso2-documentos-design.md §5) —
 * evita penalizar a quien sube el Dictamen el día 1 y tarda semanas en
 * juntar el resto.
 */
class ValidarVigenciaDocumentos
{
    public function __construct(private readonly ?DocumentosNivelCompletos $documentosNivelCompletos = null) {}

    /** @return array<string, string> mensaje por clave de documento infractor */
    public function ejecutar(int $escuelaId): array
    {
        $escuela = Escuela::findOrFail($escuelaId);
        $violaciones = [];

        // WS-7b-1 U4: un documento que dejó de aplicar al tipo de persona no cuenta. Sin
        // responsable no hay tipo con qué filtrar: se revisa todo, como antes.
        $tipoPersona = app(TipoPersonaDeEscuela::class)->ejecutar($escuelaId);
        $aplicables = $tipoPersona === null ? null : app(DocumentosCompletos::class)->clavesAplicables($tipoPersona);
        $aplica = fn (string $clave): bool => $aplicables === null || in_array($clave, $aplicables, true);

        $dictamenTipo = TipoDocumento::where('clave', 'dictamen_uso_suelo')->first();
        $dictamen = DocumentoPlantel::where('plantel_id', $escuela->plantel_id)
            ->where('tipo_documento_id', $dictamenTipo?->id)
            ->with('dictamenUsoSuelo')
            ->first();
        $declarado = $dictamen?->dictamenUsoSuelo;

        // La vigencia jurídica del dictamen es la que declara el documento (cédula municipal
        // TR-170100-017: "la que indique el documento"). Sin fecha expresa, o en un dictamen
        // anterior a este dato, no hay nada que vencer. La antigüedad de presentación no se
        // juzga aquí: no tiene fundamento confirmado (COMPENDIO_MAESTRO, 2026-10-19).
        if ($aplica('dictamen_uso_suelo') && $declarado?->tipo_vigencia === 'fecha_expresa' && $declarado->fecha_vencimiento_documento !== null && now()->toDateString() > $declarado->fecha_vencimiento_documento) {
            $fecha = date('d/m/Y', strtotime($declarado->fecha_vencimiento_documento));
            $violaciones['dictamen_uso_suelo'] = "Dictamen de Uso de Suelo: la fecha de vencimiento indicada en el documento ({$fecha}) ya pasó; suba el dictamen vigente.";
        }

        // Todo otro documento del plantel con vigencia calculada al subirlo
        // (hoy, Protección Civil): sin esto vencería sin que nada lo notara.
        $vencidos = DocumentoPlantel::query()
            ->join('tipos_documentos', 'tipos_documentos.id', '=', 'documentos_plantel.tipo_documento_id')
            ->where('documentos_plantel.plantel_id', $escuela->plantel_id)
            ->where('tipos_documentos.clave', '!=', 'dictamen_uso_suelo')
            ->when($aplicables !== null, fn ($consulta) => $consulta->whereIn('tipos_documentos.clave', $aplicables))
            ->whereNotNull('documentos_plantel.fecha_vigencia')
            ->where('documentos_plantel.fecha_vigencia', '<', now()->toDateString())
            ->pluck('tipos_documentos.nombre', 'tipos_documentos.clave');
        foreach ($vencidos as $clave => $nombre) {
            $violaciones[$clave] = "{$nombre}: ha superado su vigencia máxima, debe resubirse.";
        }

        // Constancia de Seguridad Estructural: solo vence por la fecha que declara el documento
        // (documentos_plantel.fecha_vigencia, ya cubierta arriba). La regla anterior "año del registro
        // del perito = año de emisión" se retiró el 2026-10-20: ningún artículo del Reglamento de
        // Construcción de Querétaro la respalda, rechazaba registros vigentes que cruzan de año y aceptaba
        // registros ya vencidos al emitir (PENDIENTE-constancia-seguridad-estructural.md).

        return $violaciones;
    }

    /**
     * Observaciones que NO bloquean el avance: datos que SEDEQ confirmará en la revisión. El
     * vencimiento declarado del registro profesional anterior a la emisión de la constancia es una
     * inferencia de los arts. 295 y 297 del Reglamento de Construcción de Querétaro (refrendo y padrón
     * anual de DRO/Corresponsables), solo de ese municipio y sin texto que lo declare requisito:
     * por eso avisa y no impide. Comparación por fecha: el mismo día de la emisión no avisa.
     * El dato histórico perito_registro_vigencia (semántica no determinada) no se evalúa.
     *
     * @return array<string, string> aviso por clave de documento
     */
    public function avisos(int $escuelaId): array
    {
        $escuela = Escuela::findOrFail($escuelaId);

        $tipoPersona = app(TipoPersonaDeEscuela::class)->ejecutar($escuelaId);
        if ($tipoPersona !== null && ! in_array('constancia_seguridad_estructural', app(DocumentosCompletos::class)->clavesAplicables($tipoPersona), true)) {
            return [];
        }

        $constancia = DocumentoPlantel::where('plantel_id', $escuela->plantel_id)
            ->where('tipo_documento_id', TipoDocumento::where('clave', 'constancia_seguridad_estructural')->value('id'))
            ->with('constanciaSeguridadEstructural')
            ->first();

        $vencimientoRegistro = $constancia?->constanciaSeguridadEstructural?->perito_registro_vencimiento;
        if ($vencimientoRegistro === null || $constancia->fecha_emision === null || $vencimientoRegistro >= $constancia->fecha_emision) {
            return [];
        }

        $fecha = date('d/m/Y', strtotime($vencimientoRegistro));

        return ['constancia_seguridad_estructural' => "Constancia de Seguridad Estructural: el registro del profesional indica vencimiento el {$fecha}, anterior a la emisión de la constancia. SEDEQ lo confirmará en la revisión."];
    }

    /**
     * Paso 2.4 (WS-5b), genérica por catálogo: todo documento aplicable al
     * nivel cuya fecha_vigencia (calculada al subirlo desde vigencia_max_dias)
     * ya pasó. Las filas de claves que no aplican se ignoran, igual que en la
     * completitud. Hoy ningún documento por nivel sembrado tiene vigencia_max_dias.
     *
     * @return array<string, string> mensaje por clave de documento infractor
     */
    public function paraEscuelaNivel(int $escuelaNivelId): array
    {
        $aplicables = ($this->documentosNivelCompletos ?? app(DocumentosNivelCompletos::class))->clavesAplicables($escuelaNivelId);

        return DocumentoEscuelaNivel::query()
            ->join('tipos_documentos', 'tipos_documentos.id', '=', 'documentos_escuela_nivel.tipo_documento_id')
            ->where('documentos_escuela_nivel.escuela_nivel_id', $escuelaNivelId)
            ->whereIn('tipos_documentos.clave', $aplicables)
            ->whereNotNull('documentos_escuela_nivel.fecha_vigencia')
            ->where('documentos_escuela_nivel.fecha_vigencia', '<', now()->toDateString())
            ->pluck('tipos_documentos.nombre', 'tipos_documentos.clave')
            ->map(fn ($nombre) => "{$nombre}: ha superado su vigencia máxima, debe resubirse.")
            ->all();
    }
}
