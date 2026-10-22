<?php

namespace App\Livewire\Sedeq;

use App\Application\Revision\ConsultarTramiteSedeq;
use App\Application\Revision\PasosRevision;
use App\Application\Revision\ResultadosCambiados;
use App\Application\Revision\RutaInicio;
use App\Models\Escuela;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Trámite en solo lectura para supervisor/revisor. Sin layoutData de escuela: el layout no pinta el
 * recorrido del solicitante (con enlaces de edición). Sin acciones: nada que escribir.
 */
#[Layout('layouts.tramite', ['title' => 'Trámite (solo lectura)'])]
class TramiteLectura extends Component
{
    #[Locked]
    public int $escuelaId;

    public function mount(Escuela $escuela): void
    {
        $this->escuelaId = $escuela->id;
    }

    public function render(ConsultarTramiteSedeq $consultar)
    {
        $tramite = $consultar->ejecutar((int) auth()->id(), $this->escuelaId);

        return view('livewire.sedeq.tramite-lectura', [
            'tramite' => $tramite,
            'cambiados' => $this->cambiadosPorNivel($tramite['niveles']),
            'volver' => RutaInicio::para(auth()->user()),
        ]);
    }

    /**
     * Aviso G6 por nivel visible: «Paso: regla…» de cada paso aceptado cuyo resultado cambió. Solo lectura; la página
     * no tiene acciones, así que se calcula en cada render sin persistir.
     *
     * @param  list<array{escuelaNivelId: int}>  $niveles
     * @return array<int, list<string>>
     */
    private function cambiadosPorNivel(array $niveles): array
    {
        $avisos = [];
        foreach ($niveles as $nivel) {
            foreach (app(ResultadosCambiados::class)->ejecutar((int) auth()->id(), $nivel['escuelaNivelId']) as $paso => $descripciones) {
                foreach ($descripciones as $descripcion) {
                    $avisos[$nivel['escuelaNivelId']][] = PasosRevision::titulo($paso).': '.$descripcion;
                }
            }
        }

        return $avisos;
    }
}
