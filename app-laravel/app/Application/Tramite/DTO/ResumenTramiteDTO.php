<?php

namespace App\Application\Tramite\DTO;

use DateTimeInterface;

final class ResumenTramiteDTO
{
    /**
     * @param  array<string, string|null>  $plantel  etiqueta => valor (mismas etiquetas que Paso 1)
     * @param  array<int, SeccionTramite>  $generales
     * @param  array<int, NivelDelTramite>  $niveles
     * @param  string|null  $nombre  nombre aprobado, o la propuesta con menor numero_propuesta de la terna; null sin terna
     */
    public function __construct(
        public readonly int $escuelaId,
        public readonly string $domicilio,
        public readonly array $plantel,
        public readonly array $generales,
        public readonly array $niveles,
        public readonly bool $completo,
        public readonly ?string $nombre,
        public readonly DateTimeInterface $iniciadoEl,
        /** Todos los niveles siguen en captura: el dueño puede eliminar el trámite (EliminarTramite). */
        public readonly bool $puedeEliminar = false,
        /** WS-7a: se envió a SEDEQ (al menos un nivel y ninguno en captura); ninguna sección ofrece acción. Igual en la vista restringida del responsable. */
        public readonly bool $enviado = false,
        /** Último paso a en_revision en historial_estados_expediente; null si no se ha enviado. */
        public readonly ?DateTimeInterface $fechaEnvio = null,
        /** evaluaciones_validacion.id guardado al enviar (el último); null si no se ha enviado. */
        public readonly ?int $reporteEnviadoId = null,
    ) {}

    /** Número visible del trámite (id de la escuela a 4 dígitos). No es folio_expediente: ese es por nivel y lo asigna SEDEQ. */
    public function numero(): string
    {
        return str_pad((string) $this->escuelaId, 4, '0', STR_PAD_LEFT);
    }

    /** @return array{hechas: int, total: int, porcentaje: int} solo cuentan las secciones con "Paso X de N". */
    public function avance(): array
    {
        $cuentan = array_filter($this->secciones(), fn (SeccionTramite $s) => $s->paso !== null);
        $hechas = count(array_filter($cuentan, fn (SeccionTramite $s) => $s->estado === 'completado'));
        $total = count($cuentan);

        return ['hechas' => $hechas, 'total' => $total, 'porcentaje' => $total === 0 ? 0 : intdiv(100 * $hechas, $total)];
    }

    /** Primera sección que se puede comenzar o continuar, en el orden del recorrido. */
    public function siguiente(): ?SeccionTramite
    {
        foreach ($this->secciones() as $seccion) {
            if (in_array($seccion->accion, ['comenzar', 'continuar'], true)) {
                return $seccion;
            }
        }

        return null;
    }

    /** @return array<int, SeccionTramite> generales y luego cada nivel, en orden. */
    private function secciones(): array
    {
        return array_merge($this->generales, ...array_map(fn (NivelDelTramite $nivel) => $nivel->secciones, $this->niveles));
    }
}
