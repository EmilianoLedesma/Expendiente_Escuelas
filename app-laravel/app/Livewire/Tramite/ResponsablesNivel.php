<?php

namespace App\Livewire\Tramite;

use App\Application\Excepciones\DatosInvalidos;
use App\Application\Excepciones\PrecondicionIncumplida;
use App\Application\ResponsablesNivel\InvitarResponsableNivel;
use App\Application\ResponsablesNivel\ListarResponsablesNivel;
use App\Application\ResponsablesNivel\RevocarResponsableNivel;
use Illuminate\Auth\Access\AuthorizationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

/** Presentación pura de "Responsables por nivel"; las reglas viven en Application/ResponsablesNivel (ADR-001, ADR-015). */
#[Layout('layouts.tramite')]
class ResponsablesNivel extends Component
{
    public string $escuelaNivelId = '';

    public string $nombre = '';

    public string $correo = '';

    protected function rules(): array
    {
        return [
            'escuelaNivelId' => ['required', 'integer'],
            'nombre' => ['required', 'string', 'max:255'],
            'correo' => ['required', 'email', 'max:255'],
        ];
    }

    protected function messages(): array
    {
        return [
            'escuelaNivelId.required' => 'Elige el nivel que asignarás.',
            'nombre.required' => 'Indica el nombre del responsable.',
            'correo.required' => 'Indica el correo del responsable.',
            'correo.email' => 'Escribe un correo válido.',
        ];
    }

    public function invitar(InvitarResponsableNivel $invitar): void
    {
        $this->validate();

        try {
            $invitar->ejecutar($this->solicitanteId(), (int) $this->escuelaNivelId, $this->nombre, $this->correo);
        } catch (DatosInvalidos $e) {
            foreach ($e->errores as $campo => $mensaje) {
                $this->addError($campo, $mensaje);
            }

            return;
        }

        $this->reset('escuelaNivelId', 'nombre', 'correo');
        session()->now('status', 'Listo: le enviamos un correo a la persona asignada.');
    }

    public function revocar(int $accesoId, RevocarResponsableNivel $revocar): void
    {
        try {
            $revocar->ejecutar($this->solicitanteId(), $accesoId);
        } catch (AuthorizationException) {
            abort(403);
        } catch (PrecondicionIncumplida $e) {
            $this->addError('revocar', $e->getMessage());

            return;
        }

        session()->now('status', 'Acceso revocado. Lo que ya capturó se conserva.');
    }

    private function solicitanteId(): int
    {
        $solicitante = auth()->user()?->solicitante;
        abort_if($solicitante === null, 403);

        return (int) $solicitante->getKey();
    }

    public function render(ListarResponsablesNivel $listar)
    {
        return view('livewire.tramite.responsables-nivel', ['filas' => $listar->ejecutar($this->solicitanteId())]);
    }
}
