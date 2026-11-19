<?php

namespace App\Livewire\Tramite;

use App\Application\Excepciones\DatosInvalidos;
use App\Application\Revision\RolesSedeq;
use App\Application\Visita\ConsultarVisita;
use App\Application\Visita\ResponderObservaciones;
use App\Livewire\Concerns\NotificaToast;
use App\Models\EscuelaNivel;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Visita de inspección de un nivel para el solicitante: el dueño ve la visita, sus observaciones y las descargas, y
 * responde; el responsable de ESE nivel solo ve el estado. El supervisor tiene su propia página (Sedeq\VisitaNivel).
 * Presentación pura: la lectura y la respuesta son casos de uso de Application\Visita.
 */
#[Layout('layouts.tramite', ['title' => 'Visita de inspección'])]
class VisitaSolicitante extends Component
{
    use NotificaToast;
    use WithFileUploads;

    #[Locked]
    public int $escuelaNivelId;

    public string $nota = '';

    /** @var array<int, mixed> PDF elegidos para la respuesta */
    public array $archivos = [];

    public bool $enviada = false;

    public function mount(int $escuelaNivel): void
    {
        $this->escuelaNivelId = $escuelaNivel;
        // El personal tiene su propia página; esta es del solicitante.
        abort_if(auth()->user()->hasRole(RolesSedeq::SUPERVISOR), 403);
        $this->consultar();
    }

    public function quitarArchivo(int $indice): void
    {
        unset($this->archivos[$indice]);
        $this->archivos = array_values($this->archivos);
    }

    public function responder(): void
    {
        $this->resetErrorBag();
        $this->enviada = false;
        $visita = $this->consultar()['visita'];
        abort_if($visita === null, 404);

        try {
            app(ResponderObservaciones::class)->ejecutar(auth()->user(), (int) $visita['id'], $this->nota, $this->archivos);
        } catch (DatosInvalidos $e) {
            foreach ($e->errores as $campo => $mensaje) {
                $this->addError(in_array($campo, ['nota', 'archivos'], true) ? $campo : 'general', $mensaje);
            }
            $this->toastDatosInvalidos();

            return;
        }

        $this->nota = '';
        $this->archivos = [];
        $this->enviada = true;
    }

    public function render()
    {
        $datos = $this->consultar();
        $v = $datos['visita'];
        $clave = $datos['estado']['clave'];
        $esDueno = Gate::allows('responderVisita', EscuelaNivel::findOrFail($this->escuelaNivelId));

        return view('livewire.tramite.visita-solicitante', [
            'datos' => $datos,
            'esDueno' => $esDueno,
            'estadoTexto' => $this->estadoTexto($clave, $v, $datos['estado']['etiqueta']),
            'puedeResponder' => $esDueno && $clave === 'en_subsanacion' && $v !== null && $v['estado'] === 'realizada' && $v['resultado'] === 'con_observaciones',
        ]);
    }

    /** @return array<string, mixed> */
    private function consultar(): array
    {
        return app(ConsultarVisita::class)->ejecutar(auth()->user(), $this->escuelaNivelId);
    }

    /**
     * Mismo texto por nivel que el hub y «Mis trámites».
     *
     * @param  array<string, mixed>|null  $v
     */
    private function estadoTexto(string $clave, ?array $v, string $etiqueta): string
    {
        return match (true) {
            $clave === 'visita_programada' && $v !== null => 'Visita programada para el '.$v['programadaPara']->format('d/m/Y'),
            $clave === 'en_subsanacion' && $v !== null && $v['subsanarHasta'] !== null => 'En subsanación: responde antes de '.$v['subsanarHasta']->format('d/m/Y').' (aprox.)',
            $clave === 'visita_aprobada' => 'Visita aprobada',
            default => $etiqueta,
        };
    }
}
