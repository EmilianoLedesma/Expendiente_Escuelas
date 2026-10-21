<?php

namespace App\Application\Revision;

use App\Models\EscuelaNivel;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use InvalidArgumentException;

/**
 * Qué se le muestra al revisor al abrir un paso: bloques de filas etiqueta/valor y descargas de documentos.
 * El plantel y las listas de archivos salen de ConsultarTramiteSedeq; el detalle de Paso 2 lo completa ContenidoGeneralRevision
 * y los pasos del Paso 3 los resuelve ContenidoPaso3Revision.
 */
final class ContenidoPasoRevision
{
    public function __construct(
        private readonly AccesoSedeq $acceso,
        private readonly ConsultarTramiteSedeq $consultar,
        private readonly ContenidoPaso3Revision $paso3,
        private readonly ContenidoGeneralRevision $general,
    ) {}

    /**
     * @return array{titulo: string, bloques: list<array{titulo: string, filas: list<array{0: string, 1: string}>, documentos: list<array{nombre: string, archivo: string, href: string}>}>}
     *
     * @throws AuthorizationException
     * @throws InvalidArgumentException si la clave aún no tiene contenido.
     */
    public function ejecutar(int $actorId, int $escuelaNivelId, string $pasoClave): array
    {
        if (! $this->acceso->puedeVerNivel(User::findOrFail($actorId), $escuelaNivelId)) {
            throw new AuthorizationException;
        }

        $nivel = EscuelaNivel::with(['nivelEducativo', 'escuela'])->findOrFail($escuelaNivelId);
        $tramite = $this->consultar->ejecutar($actorId, $nivel->escuela_id);
        $nivelTramite = collect($tramite['niveles'])->firstWhere('nombre', $nivel->nivelEducativo->nombre);

        $bloques = match ($pasoClave) {
            'plantel' => [self::bloque('Datos del plantel', self::filas($tramite['plantel']))],
            'responsable' => $this->general->responsable($nivel->escuela_id),
            'documentos' => $this->general->documentos($nivel->escuela_id, $tramite['documentos']),
            'niveles' => $this->general->niveles($nivel->escuela_id),
            'documentos_nivel' => $this->general->documentosNivel($escuelaNivelId, $nivelTramite['documentos'] ?? []),
            'inmueble', 'infraestructura', 'mobiliario', 'plan_estudios', 'plantilla_docente', 'matricula' => $this->paso3->ejecutar($escuelaNivelId, $pasoClave),
            default => throw new InvalidArgumentException("Paso sin contenido: {$pasoClave}"),
        };

        return ['titulo' => PasosRevision::titulo($pasoClave), 'bloques' => $bloques];
    }

    /**
     * @param  array<string, string|null>  $datos
     * @return list<array{0: string, 1: string}>
     */
    private static function filas(array $datos): array
    {
        $filas = [];
        foreach ($datos as $etiqueta => $valor) {
            $filas[] = [$etiqueta, (string) $valor];
        }

        return $filas;
    }

    /**
     * @param  list<array{0: string, 1: string}>  $filas
     * @param  list<array{nombre: string, archivo: string, href: string}>  $documentos
     * @return array{titulo: string, filas: list<array{0: string, 1: string}>, documentos: list<array{nombre: string, archivo: string, href: string}>}
     */
    private static function bloque(string $titulo, array $filas, array $documentos = []): array
    {
        return ['titulo' => $titulo, 'filas' => $filas, 'documentos' => $documentos];
    }
}
