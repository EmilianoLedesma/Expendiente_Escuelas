<?php

namespace App\Livewire\Tramite\Paso3;

use App\Application\Excepciones\DatosInvalidos;
use App\Application\Excepciones\PrecondicionIncumplida;
use App\Application\PlanEstudios\DTO\DatosPlanEstudios;
use App\Application\PlanEstudios\RegistrarPlanEstudios;
use App\Application\Tramite\ResumenTramite;
use App\Livewire\Tramite\Paso3\Concerns\CompuertaPaso3;
use App\Models\EscuelaNivel;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/** Paso 3, sub-step 4. Presentation only: RegistrarPlanEstudios validates and writes (ADR-001). */
#[Layout('layouts.tramite')]
class PlanEstudios extends Component
{
    use CompuertaPaso3;

    #[Locked]
    public EscuelaNivel $escuelaNivel;

    public string $modalidad = '';

    public string $planEstudiosReferencia = '';

    public string $plataformaEducativaTipo = '';

    public function mount(EscuelaNivel $escuelaNivel): void
    {
        $this->escuelaNivel = $escuelaNivel;

        if ($this->redirigirSiNoAlcanzable($escuelaNivel, 'plan_estudios')) {
            return;
        }

        $this->modalidad = (string) $escuelaNivel->modalidad;
        $this->planEstudiosReferencia = (string) $escuelaNivel->plan_estudios_referencia;
        $this->plataformaEducativaTipo = (string) $escuelaNivel->plataforma_educativa_tipo;
    }

    public function guardar(RegistrarPlanEstudios $registrarPlanEstudios): void
    {
        $this->validate([
            'modalidad' => ['required'],
        ]);

        try {
            $registrarPlanEstudios->ejecutar($this->escuelaNivel->id, new DatosPlanEstudios(
                modalidad: $this->modalidad,
                planEstudiosReferencia: $this->planEstudiosReferencia !== '' ? $this->planEstudiosReferencia : null,
                plataformaEducativaTipo: $this->plataformaEducativaTipo !== '' ? $this->plataformaEducativaTipo : null,
            ));
        } catch (DatosInvalidos $e) {
            foreach ($e->errores as $campo => $mensaje) {
                $this->addError($campo, $mensaje);
            }

            return;
        } catch (PrecondicionIncumplida) {
            $this->redirigirSiNoAlcanzable($this->escuelaNivel, 'plan_estudios');

            return;
        }

        $this->redirectRoute('tramite.paso3-plantilla', ['escuelaNivel' => $this->escuelaNivel->id]);
    }

    public function render()
    {
        return view('livewire.tramite.paso3.plan-estudios', [
            'encabezado' => ResumenTramite::encabezado('plan_estudios', $this->escuelaNivel->nivelEducativo),
        ])->layoutData(['escuelaId' => $this->escuelaNivel->escuela_id, 'escuelaNivelId' => $this->escuelaNivel->id, 'seccionActual' => 'plan_estudios']);
    }
}
