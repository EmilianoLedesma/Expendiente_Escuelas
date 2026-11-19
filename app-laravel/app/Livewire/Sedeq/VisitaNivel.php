<?php

namespace App\Livewire\Sedeq;

use App\Application\Excepciones\DatosInvalidos;
use App\Application\Excepciones\PrecondicionIncumplida;
use App\Application\Visita\AceptarSubsanacion;
use App\Application\Visita\ActualizarVisita;
use App\Application\Visita\AdjuntarActaVisita;
use App\Application\Visita\ConsultarVisita;
use App\Application\Visita\ProgramarVisita;
use App\Application\Visita\RegistrarActaVisita;
use App\Livewire\Concerns\NotificaToast;
use Carbon\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;
use Throwable;

/**
 * Visita de inspección de un nivel, para el supervisor: programar, corregir, registrar el acta (con observaciones y PDF),
 * adjuntar el acta después y aceptar la subsanación. Presentación pura: cada método delega en un caso de uso de
 * Application\Visita y muestra sus DatosInvalidos como errores de formulario. Rol y acceso por nivel los impone
 * ConsultarVisita y las acciones (403).
 */
#[Layout('layouts.tramite', ['title' => 'Visita de inspección'])]
class VisitaNivel extends Component
{
    use NotificaToast;
    use WithFileUploads;

    #[Locked]
    public int $escuelaNivelId;

    // Programar.
    public string $fecha = '';

    public string $inspector = '';

    // Corregir la visita programada.
    public string $editFecha = '';

    public string $editInspector = '';

    // Registrar el acta.
    public string $resultado = '';

    public string $realizada_el = '';

    /** @var list<string> */
    public array $observaciones = [''];

    public $acta = null;

    // Adjuntar el acta a una visita ya realizada.
    public $actaNueva = null;

    public function mount(int $escuelaNivel): void
    {
        $this->escuelaNivelId = $escuelaNivel;
        $this->precargar($this->consultar());
        $this->realizada_el = today()->toDateString();
    }

    public function programar(): void
    {
        $this->ejecutar(function () {
            app(ProgramarVisita::class)->ejecutar(auth()->user(), $this->escuelaNivelId, $this->fechaHora($this->fecha, 'fecha'), $this->inspector);
            $this->fecha = $this->inspector = '';
            $this->precargar($this->consultar());
        }, 'Visita programada');
    }

    public function actualizar(): void
    {
        $visita = $this->visitaActual();
        $this->ejecutar(
            fn () => app(ActualizarVisita::class)->ejecutar(auth()->user(), (int) $visita['id'], $this->fechaHora($this->editFecha, 'editFecha'), $this->editInspector),
            'Visita actualizada',
            ['fecha' => 'editFecha', 'inspector' => 'editInspector'],
        );
    }

    public function registrarActa(): void
    {
        $visita = $this->visitaActual();
        $this->ejecutar(function () use ($visita) {
            app(RegistrarActaVisita::class)->ejecutar(auth()->user(), (int) $visita['id'], $this->resultado, $this->fechaHora($this->realizada_el, 'realizada_el'), $this->observaciones, $this->acta);
            $this->resultado = '';
            $this->observaciones = [''];
            $this->acta = null;
        }, 'Acta registrada');
    }

    public function adjuntarActa(): void
    {
        $visita = $this->visitaActual();
        if ($this->actaNueva === null) {
            $this->addError('actaNueva', 'Elige el PDF del acta.');

            return;
        }
        $this->ejecutar(function () use ($visita) {
            app(AdjuntarActaVisita::class)->ejecutar(auth()->user(), (int) $visita['id'], $this->actaNueva);
            $this->actaNueva = null;
        }, 'Acta adjuntada', ['acta' => 'actaNueva']);
    }

    public function aceptar(): void
    {
        $visita = $this->visitaActual();
        $this->ejecutar(fn () => app(AceptarSubsanacion::class)->ejecutar(auth()->user(), (int) $visita['id']), 'Subsanación aceptada');
    }

    public function agregarObservacion(): void
    {
        $this->observaciones[] = '';
    }

    public function quitarObservacion(int $indice): void
    {
        unset($this->observaciones[$indice]);
        $this->observaciones = $this->observaciones === [] ? [''] : array_values($this->observaciones);
    }

    public function render()
    {
        return view('livewire.sedeq.visita-nivel', ['datos' => $this->consultar()]);
    }

    /** @return array<string, mixed> */
    private function consultar(): array
    {
        return app(ConsultarVisita::class)->ejecutar(auth()->user(), $this->escuelaNivelId);
    }

    /** @return array<string, mixed> la última visita; sin visitas no hay nada que actualizar, registrar ni aceptar */
    private function visitaActual(): array
    {
        $visita = $this->consultar()['visita'];
        abort_if($visita === null, 404);

        return $visita;
    }

    /** @param array<string, mixed> $datos */
    private function precargar(array $datos): void
    {
        $v = $datos['visita'];
        if ($v !== null && $v['estado'] === 'programada') {
            $this->editFecha = $v['programadaPara']->format('Y-m-d\TH:i');
            $this->editInspector = $v['inspector'];
        }
    }

    /** Un valor ilegible de un `datetime-local` o `date` es un error del campo, no una excepción. */
    private function fechaHora(string $valor, string $campo): Carbon
    {
        // Carbon::parse('') devuelve "ahora": un valor vacío nunca debe llegar a la acción.
        if (trim($valor) === '') {
            throw new DatosInvalidos([$campo => 'Indica la fecha.']);
        }
        try {
            return Carbon::parse($valor);
        } catch (Throwable) {
            throw new DatosInvalidos([$campo => 'Indica una fecha válida.']);
        }
    }

    /**
     * Corre una acción y traduce sus errores: DatosInvalidos a errores de campo (`$mapa` renombra las claves de la
     * acción a las de este formulario; lo que no es un campo del formulario va como error general) y la
     * PrecondicionIncumplida de rol a 403.
     *
     * @param  array<string, string>  $mapa
     */
    private function ejecutar(callable $accion, string $aviso, array $mapa = []): void
    {
        $this->resetErrorBag();
        try {
            $accion();
        } catch (DatosInvalidos $e) {
            $campos = ['fecha', 'inspector', 'resultado', 'realizada_el', 'observaciones', 'acta', 'actaNueva', 'editFecha', 'editInspector'];
            foreach ($e->errores as $campo => $mensaje) {
                $campo = $mapa[$campo] ?? $campo;
                $this->addError(in_array($campo, $campos, true) ? $campo : 'general', $mensaje);
            }
            $this->toastDatosInvalidos();

            return;
        } catch (PrecondicionIncumplida $e) {
            abort_if($e->etapaFaltante === 'rol', 403);
            $this->addError('general', $e->getMessage());

            return;
        }

        $this->toast($aviso);
    }
}
