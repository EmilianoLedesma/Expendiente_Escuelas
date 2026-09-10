<?php

namespace App\Livewire\Tramite;

use App\Application\EscuelaNiveles\RegistrarNivelesSeleccionados;
use App\Application\ResponsableLegal\DTO\DatosResponsableLegal;
use App\Application\ResponsableLegal\RegistrarResponsableLegal;
use App\Livewire\Forms\GestorForm;
use App\Livewire\Forms\PersonaFisicaForm;
use App\Livewire\Forms\PersonaMoralForm;
use App\Models\Escuela;
use App\Models\EscuelaNivel;
use App\Models\NivelEducativo;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Presentación pura: dos fases ('responsable', 'niveles') sobre una sola
 * visita de /tramite/paso2/{escuela}. Ninguna regla de negocio ni acceso
 * a Eloquent de escritura vive aquí (ADR-001) — RegistrarResponsableLegal
 * y RegistrarNivelesSeleccionados (Task 10) son las únicas escrituras.
 */
#[Layout('layouts.tramite')]
class Paso2Responsable extends Component
{
    public Escuela $escuela;

    public string $fase = 'responsable';

    public string $tipoPersona = 'fisica';

    public PersonaFisicaForm $personaFisicaForm;

    public PersonaMoralForm $personaMoralForm;

    public GestorForm $gestorForm;

    public array $nivelesSeleccionados = [];

    public function mount(Escuela $escuela): void
    {
        $this->escuela = $escuela;

        if ($escuela->escuelaNiveles()->exists()) {
            $this->redirectRoute('tramite.paso3-placeholder', [
                'escuelaNivel' => $escuela->escuelaNiveles()->orderBy('id')->first(),
            ]);

            return;
        }

        $this->fase = $escuela->responsableLegal()->exists() ? 'niveles' : 'responsable';
    }

    public function guardarResponsable(RegistrarResponsableLegal $registrarResponsableLegal): void
    {
        if ($this->tipoPersona === 'moral') {
            $this->personaMoralForm->validate();
        } else {
            $this->personaFisicaForm->validate();

            if ($this->tipoPersona === 'fisica_con_gestor') {
                $this->gestorForm->validate();
            }
        }

        $registrarResponsableLegal->ejecutar($this->escuela->id, new DatosResponsableLegal(
            tipoPersona: $this->tipoPersona,
            nombre: $this->personaFisicaForm->nombre !== '' ? $this->personaFisicaForm->nombre : null,
            fechaNacimiento: $this->personaFisicaForm->fechaNacimiento !== '' ? $this->personaFisicaForm->fechaNacimiento : null,
            rfc: $this->personaFisicaForm->rfc !== '' ? $this->personaFisicaForm->rfc : null,
            curp: $this->personaFisicaForm->curp !== '' ? $this->personaFisicaForm->curp : null,
            razonSocial: $this->personaMoralForm->razonSocial !== '' ? $this->personaMoralForm->razonSocial : null,
            numeroEscrituraConstitutiva: $this->personaMoralForm->numeroEscrituraConstitutiva !== '' ? $this->personaMoralForm->numeroEscrituraConstitutiva : null,
            fechaEscrituraConstitutiva: $this->personaMoralForm->fechaEscrituraConstitutiva !== '' ? $this->personaMoralForm->fechaEscrituraConstitutiva : null,
            notarioNombre: $this->personaMoralForm->notarioNombre !== '' ? $this->personaMoralForm->notarioNombre : null,
            notarioNumero: $this->personaMoralForm->notarioNumero !== '' ? $this->personaMoralForm->notarioNumero : null,
            notarioCiudad: $this->personaMoralForm->notarioCiudad !== '' ? $this->personaMoralForm->notarioCiudad : null,
            folioRegistroPublico: $this->personaMoralForm->folioRegistroPublico !== '' ? $this->personaMoralForm->folioRegistroPublico : null,
            fechaInscripcionRpp: $this->personaMoralForm->fechaInscripcionRpp !== '' ? $this->personaMoralForm->fechaInscripcionRpp : null,
            nombreRepresentanteLegal: $this->personaMoralForm->nombreRepresentanteLegal !== '' ? $this->personaMoralForm->nombreRepresentanteLegal : null,
            gestorNombre: $this->gestorForm->nombre !== '' ? $this->gestorForm->nombre : null,
            gestorNumeroPoder: $this->gestorForm->numeroPoder !== '' ? $this->gestorForm->numeroPoder : null,
            gestorNotarioNombre: $this->gestorForm->notarioNombre !== '' ? $this->gestorForm->notarioNombre : null,
            gestorNotarioNumero: $this->gestorForm->notarioNumero !== '' ? $this->gestorForm->notarioNumero : null,
            gestorFechaPoder: $this->gestorForm->fechaPoder !== '' ? $this->gestorForm->fechaPoder : null,
        ));

        $this->fase = 'niveles';
    }

    public function guardarNiveles(RegistrarNivelesSeleccionados $registrarNivelesSeleccionados): void
    {
        $this->validate([
            'nivelesSeleccionados' => ['required', 'array', 'min:1'],
        ]);

        try {
            $registrarNivelesSeleccionados->ejecutar($this->escuela->id, $this->nivelesSeleccionados);
        } catch (InvalidArgumentException $e) {
            $this->addError('nivelesSeleccionados', $e->getMessage());

            return;
        }

        $primerEscuelaNivel = EscuelaNivel::where('escuela_id', $this->escuela->id)->orderBy('id')->firstOrFail();

        $this->redirectRoute('tramite.paso3-placeholder', ['escuelaNivel' => $primerEscuelaNivel]);
    }

    public function render()
    {
        return view('livewire.tramite.paso2-responsable', [
            'nivelesDisponibles' => $this->fase === 'niveles' ? NivelEducativo::educacionBasica()->get() : collect(),
        ]);
    }
}
