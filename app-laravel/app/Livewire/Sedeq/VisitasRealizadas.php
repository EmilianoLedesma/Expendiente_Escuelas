<?php

namespace App\Livewire\Sedeq;

use App\Application\Excepciones\DatosInvalidos;
use App\Application\Excepciones\PrecondicionIncumplida;
use App\Application\Visita\DTO\FilaVisitaRealizada;
use App\Application\Visita\ListarVisitasRealizadas;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Registro de visitas realizadas (supervisor, solo lectura): todas las escuelas, con filtros. Presentación pura: la regla
 * (rol, valores válidos) vive en ListarVisitasRealizadas. Un rol distinto es un 403; un filtro inválido se descarta sin error.
 */
#[Layout('layouts.tramite', ['title' => 'Visitas realizadas'])]
class VisitasRealizadas extends Component
{
    #[Url(as: 'q', except: '')]
    public string $buscar = '';

    #[Url(as: 'resultado', except: '')]
    public string $resultado = '';

    #[Url(as: 'desde', except: '')]
    public string $desde = '';

    #[Url(as: 'hasta', except: '')]
    public string $hasta = '';

    public function render(ListarVisitasRealizadas $listar)
    {
        $actor = auth()->user();
        try {
            $datos = $this->consultar($listar, $actor);
        } catch (PrecondicionIncumplida $e) {
            abort_if($e->etapaFaltante === 'rol', 403);
            throw $e;
        } catch (DatosInvalidos) {
            // URL o cliente manipulados: se descartan los filtros inválidos y se vuelve a listar.
            $this->resultado = in_array($this->resultado, ['cumple', 'con_observaciones'], true) ? $this->resultado : '';
            $this->desde = $this->fechaValida($this->desde);
            $this->hasta = $this->fechaValida($this->hasta);
            if ($this->desde !== '' && $this->hasta !== '' && $this->desde > $this->hasta) {
                $this->desde = $this->hasta = '';
            }
            $datos = $this->consultar($listar, $actor);
        }

        return view('livewire.sedeq.visitas-realizadas', $datos + [
            'hayFiltros' => trim($this->buscar) !== '' || $this->resultado !== '' || $this->desde !== '' || $this->hasta !== '',
        ]);
    }

    /** @return array{filas: list<FilaVisitaRealizada>, total: int} */
    private function consultar(ListarVisitasRealizadas $listar, $actor): array
    {
        return $listar->ejecutar($actor, $this->buscar, $this->resultado, $this->desde, $this->hasta);
    }

    private function fechaValida(string $valor): string
    {
        $d = \DateTimeImmutable::createFromFormat('!Y-m-d', $valor);

        return $d !== false && $d->format('Y-m-d') === $valor ? $valor : '';
    }
}
