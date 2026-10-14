<?php

namespace App\Livewire\Tramite;

use App\Application\Captura\Normalizacion;
use App\Application\Captura\Normalizar;
use App\Application\Captura\ReglasCaptura;
use App\Application\Excepciones\DatosInvalidos;
use App\Application\Excepciones\PrecondicionIncumplida;
use App\Application\Preregistro\ActualizarContactoPlantel;
use App\Application\Tramite\ResumenTramite;
use App\Livewire\Concerns\NotificaToast;
use App\Models\Escuela;
use App\Models\Plantel;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Presentación pura (ADR-001): "Datos del plantel" ya capturados. El domicilio se muestra
 * como texto; solo teléfono y correo se editan, vía ActualizarContactoPlantel (WS-7b-1 U1).
 */
#[Layout('layouts.tramite')]
class DatosPlantel extends Component
{
    use NotificaToast;

    public Escuela $escuela;

    #[Normalizar(Normalizacion::Telefono)]
    public string $telefono = '';

    #[Normalizar(Normalizacion::Correo)]
    public string $correoElectronico = '';

    public function mount(Escuela $escuela): void
    {
        $this->escuela = $escuela;
        /** @var Plantel $plantel */
        $plantel = $escuela->plantel;
        $this->telefono = (string) $plantel->telefono;
        $this->correoElectronico = (string) $plantel->correo_electronico;
    }

    protected function rules(): array
    {
        return [
            'telefono' => ReglasCaptura::telefono(),
            'correoElectronico' => ReglasCaptura::correo(max: 150),
        ];
    }

    public function guardar(ActualizarContactoPlantel $actualizarContactoPlantel): void
    {
        $this->validate();

        try {
            $actualizarContactoPlantel->ejecutar(
                $this->escuela->id,
                auth()->user()->solicitante->getKey(),
                $this->telefono !== '' ? $this->telefono : null,
                $this->correoElectronico !== '' ? $this->correoElectronico : null,
            );
        } catch (DatosInvalidos $e) {
            foreach ($e->errores as $campo => $mensaje) {
                $this->addError($campo, $mensaje);
            }
            $this->toastDatosInvalidos();

            return;
        } catch (PrecondicionIncumplida $e) {
            $this->addError('plantel', $e->getMessage());
            $this->toast('No se guardó', $e->getMessage(), 'error');

            return;
        }

        $this->toast('Datos del plantel guardados');
    }

    public function render()
    {
        return view('livewire.tramite.datos-plantel', [
            'plantel' => $this->escuela->plantel,
            'encabezado' => ResumenTramite::encabezado('plantel'),
        ])->layoutData(['escuelaId' => $this->escuela->id, 'seccionActual' => 'plantel']);
    }
}
