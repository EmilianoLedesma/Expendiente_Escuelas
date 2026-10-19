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

        $constanciaTipo = TipoDocumento::where('clave', 'constancia_seguridad_estructural')->first();
        $constancia = DocumentoPlantel::where('plantel_id', $escuela->plantel_id)
            ->where('tipo_documento_id', $constanciaTipo?->id)
            ->with('constanciaSeguridadEstructural')
            ->first();

        $extension = $constancia?->constanciaSeguridadEstructural;
        if ($aplica('constancia_seguridad_estructural') && $extension !== null && $extension->perito_registro_vigencia !== null && $constancia->fecha_emision !== null) {
            $anioRegistro = date('Y', strtotime($extension->perito_registro_vigencia));
            $anioEmision = date('Y', strtotime($constancia->fecha_emision));

            if ($anioRegistro !== $anioEmision) {
                $violaciones['constancia_seguridad_estructural'] = 'Constancia de Seguridad Estructural: el año del registro del perito no coincide con el año de emisión.';
            }
        }

        return $violaciones;
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
