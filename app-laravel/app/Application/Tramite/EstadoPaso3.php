<?php

namespace App\Application\Tramite;

use App\Application\ProyectoEducativo\SeccionesProyectoEducativo;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Única fuente de verdad para el orden de los sub-pasos de Paso 3 (WS-1.3):
 * el sub-paso N es alcanzable si y solo si el N-1 está `completado` en
 * escuela_nivel_pasos. El orden sale de pasos_captura.orden — no hay aquí
 * una segunda copia de la secuencia, así que añadir un sub-paso (WS-8) es
 * solo añadirlo al catálogo. Los componentes solo leen el resultado para
 * decidir a dónde redirigir (ADR-001). Desde WS-5b todo Paso 3 de un nivel
 * exige además su Paso 2.4 completo (EstadoPaso24); vive aquí porque
 * CompuertaPaso3, MarcarPasoCompletado, los casos de uso Registrar* de Paso 3
 * y ResumenTramite ya pasan por puedeAcceder().
 */
class EstadoPaso3
{
    public function __construct(private readonly EstadoPaso24 $estadoPaso24) {}

    /** Clave del primer sub-paso sin completar, o null si todos lo están. */
    public function primerPendiente(int $escuelaNivelId): ?string
    {
        $completados = $this->completados($escuelaNivelId);

        foreach ($this->catalogo($escuelaNivelId) as $clave) {
            if (! in_array($clave, $completados, true)) {
                return $clave;
            }
        }

        return null;
    }

    public function puedeAcceder(int $escuelaNivelId, string $clave): bool
    {
        $catalogo = $this->catalogo($escuelaNivelId);
        $posicion = array_search($clave, $catalogo, true);

        if ($posicion === false) {
            throw new InvalidArgumentException("paso_captura desconocido: {$clave}");
        }

        if ($this->estadoPaso24->etapaFaltante($escuelaNivelId) !== null) {
            return false;
        }

        return $posicion === 0 || in_array($catalogo[$posicion - 1], $this->completados($escuelaNivelId), true);
    }

    /** ¿El sub-paso `$pasoClave` de este nivel está marcado `completado`? Misma fuente que el orden del wizard. */
    public function completado(int $escuelaNivelId, string $pasoClave): bool
    {
        return in_array($pasoClave, $this->completados($escuelaNivelId), true);
    }

    /** Si el paso está en el catálogo de ese nivel (falso p. ej. si el seeder aún no corrió). */
    public function tieneEnCatalogo(int $escuelaNivelId, string $clave): bool
    {
        return in_array($clave, $this->catalogo($escuelaNivelId), true);
    }

    /** Catálogo de sub-pasos de ese nivel: el proyecto educativo solo existe donde aplica (media superior). @return list<string> */
    private function catalogo(int $escuelaNivelId): array
    {
        $aplica = SeccionesProyectoEducativo::aplicaA((string) DB::table('escuela_niveles')
            ->join('niveles_educativos', 'niveles_educativos.id', '=', 'escuela_niveles.nivel_educativo_id')
            ->where('escuela_niveles.id', $escuelaNivelId)
            ->value('niveles_educativos.clave'));

        return DB::table('pasos_captura')->orderBy('orden')
            ->when(! $aplica, fn ($q) => $q->where('clave', '!=', SeccionesProyectoEducativo::CLAVE_PASO))
            ->pluck('clave')->all();
    }

    /** @return list<string> */
    private function completados(int $escuelaNivelId): array
    {
        return DB::table('escuela_nivel_pasos')
            ->join('pasos_captura', 'pasos_captura.id', '=', 'escuela_nivel_pasos.paso_captura_id')
            ->where('escuela_nivel_pasos.escuela_nivel_id', $escuelaNivelId)
            ->where('escuela_nivel_pasos.estado', 'completado')
            ->pluck('pasos_captura.clave')
            ->all();
    }
}
