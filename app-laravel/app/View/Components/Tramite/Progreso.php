<?php

namespace App\View\Components\Tramite;

use App\Application\Tramite\EstadoPaso2;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\Component;

/**
 * Progress indicator for the whole trámite wizard, not just Paso 3: Preregistro
 * → Responsable legal → Documentos son escuela-scoped y no tienen fila en
 * `pasos_captura` (esa tabla es solo los 6 sub-pasos de Paso 3, PRD §5), así
 * que su estado se deriva por existencia de datos, igual que ya hacen
 * Paso2Responsable::mount() y Paso2Documentos::mount(). Reads `pasos_captura`
 * (and, given an escuela_nivel_id, `escuela_nivel_pasos`) here in the
 * component class — not in the .blade.php, which stays pure markup.
 * Read-only display data, so no Application use case; see ADR-001 and the
 * class boundary note in docs/reports/2026-09-07-paso1-preregistro.md.
 */
class Progreso extends Component
{
    /**
     * clave de pasos_captura => nombre de ruta, solo para los sub-pasos ya
     * construidos. Público: CompuertaPaso3 lo reutiliza para redirigir, así
     * que hay un solo mapa clave => ruta.
     *
     * @var array<string, string>
     */
    public const RUTAS_PASO3 = [
        'inmueble' => 'tramite.paso3-inmueble',
        'infraestructura' => 'tramite.paso3-infraestructura',
        'mobiliario' => 'tramite.paso3-mobiliario',
    ];

    /** @var Collection<int, object{nombre: string, estado: string, href: string|null}&\stdClass> */
    public readonly Collection $pasos;

    public function __construct(public readonly ?int $escuelaId = null, public readonly ?int $escuelaNivelId = null)
    {
        $this->pasos = $this->etapasDelFlujo(app(EstadoPaso2::class))->concat($this->etapasDePaso3());
    }

    private function etapasDelFlujo(EstadoPaso2 $estadoPaso2): Collection
    {
        $tieneResponsable = $this->escuelaId !== null && $estadoPaso2->responsableCapturado($this->escuelaId);
        $documentosCompletos = $this->escuelaId !== null
            && $estadoPaso2->documentosCompletos($this->escuelaId)
            && $estadoPaso2->documentosVigentes($this->escuelaId);

        return collect([
            (object) [
                'nombre' => 'Preregistro',
                'estado' => $this->escuelaId !== null ? 'completado' : 'pendiente',
                'href' => route('tramite.preregistro'),
            ],
            (object) [
                'nombre' => 'Responsable legal',
                'estado' => $tieneResponsable ? 'completado' : 'pendiente',
                'href' => $this->escuelaId !== null ? route('tramite.paso2', ['escuela' => $this->escuelaId]) : null,
            ],
            (object) [
                'nombre' => 'Documentos',
                'estado' => $documentosCompletos ? 'completado' : 'pendiente',
                'href' => $tieneResponsable ? route('tramite.paso2-documentos', ['escuela' => $this->escuelaId]) : null,
            ],
        ]);
    }

    private function etapasDePaso3(): Collection
    {
        $catalogo = DB::table('pasos_captura')->orderBy('orden')->get();

        $estadosPorPaso = $this->escuelaNivelId
            ? DB::table('escuela_nivel_pasos')
                ->where('escuela_nivel_id', $this->escuelaNivelId)
                ->pluck('estado', 'paso_captura_id')
            : collect();

        return $catalogo->map(fn ($paso) => (object) [
            'nombre' => (string) $paso->nombre,
            'estado' => (string) $estadosPorPaso->get($paso->id, 'pendiente'),
            'href' => ($this->escuelaNivelId !== null && isset(self::RUTAS_PASO3[$paso->clave]))
                ? route(self::RUTAS_PASO3[$paso->clave], ['escuelaNivel' => $this->escuelaNivelId])
                : null,
        ]);
    }

    public function render(): View
    {
        return view('components.tramite.progreso');
    }
}
