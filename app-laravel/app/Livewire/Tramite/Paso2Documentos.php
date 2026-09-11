<?php

namespace App\Livewire\Tramite;

use App\Application\Documentos\DocumentosCompletos;
use App\Application\Documentos\DTO\DatosDocumento;
use App\Application\Documentos\RegistrarDocumento;
use App\Livewire\Forms\AcreditacionOcupacionForm;
use App\Livewire\Forms\ConstanciaSeguridadForm;
use App\Livewire\Forms\DictamenUsoSueloForm;
use App\Models\Escuela;
use App\Models\ResponsableLegal;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Presentación pura: un sub-paso (fase) por documento de Paso 2.2. Ninguna
 * escritura directa a Eloquent — RegistrarDocumento es la única escritura
 * (ADR-001).
 */
#[Layout('layouts.tramite')]
class Paso2Documentos extends Component
{
    use WithFileUploads;

    public Escuela $escuela;

    public string $fase;

    public $archivo;

    public DictamenUsoSueloForm $dictamenForm;

    public ConstanciaSeguridadForm $constanciaForm;

    public AcreditacionOcupacionForm $acreditacionForm;

    public function mount(Escuela $escuela, DocumentosCompletos $documentosCompletos): void
    {
        $this->escuela = $escuela;
        $tipoPersona = $this->tipoPersona();
        $pendientes = $documentosCompletos->clavesPendientes($escuela->id, $tipoPersona);

        $this->fase = $pendientes[0];
    }

    public function guardarDocumentoSimple(RegistrarDocumento $registrarDocumento, DocumentosCompletos $documentosCompletos): void
    {
        $this->validate(['archivo' => ['required', 'file', 'mimes:pdf', 'max:10240']]);

        $registrarDocumento->ejecutar($this->escuela->id, $this->fase, $this->archivo, new DatosDocumento);
        $this->archivo = null;

        $this->avanzar($documentosCompletos);
    }

    public function guardarDictamen(RegistrarDocumento $registrarDocumento, DocumentosCompletos $documentosCompletos): void
    {
        $this->validate(['archivo' => ['required', 'file', 'mimes:pdf', 'max:10240']]);
        $this->dictamenForm->validate();

        $registrarDocumento->ejecutar($this->escuela->id, 'dictamen_uso_suelo', $this->archivo, new DatosDocumento(
            fechaEmision: $this->dictamenForm->fechaEmision,
        ));
        $this->archivo = null;

        $this->avanzar($documentosCompletos);
    }

    public function guardarConstancia(RegistrarDocumento $registrarDocumento, DocumentosCompletos $documentosCompletos): void
    {
        $this->validate(['archivo' => ['required', 'file', 'mimes:pdf', 'max:10240']]);
        $this->constanciaForm->validate();

        $registrarDocumento->ejecutar($this->escuela->id, 'constancia_seguridad_estructural', $this->archivo, new DatosDocumento(
            fechaEmision: $this->constanciaForm->fechaEmision,
            peritoNombre: $this->constanciaForm->peritoNombre,
            peritoCedulaProfesional: $this->constanciaForm->peritoCedulaProfesional !== '' ? $this->constanciaForm->peritoCedulaProfesional : null,
            peritoRegistroDro: $this->constanciaForm->peritoRegistroDro,
            peritoRegistroAutoridad: $this->constanciaForm->peritoRegistroAutoridad !== '' ? $this->constanciaForm->peritoRegistroAutoridad : null,
            peritoRegistroVigencia: $this->constanciaForm->peritoRegistroVigencia !== '' ? $this->constanciaForm->peritoRegistroVigencia : null,
        ));
        $this->archivo = null;

        $this->avanzar($documentosCompletos);
    }

    public function guardarAcreditacion(RegistrarDocumento $registrarDocumento, DocumentosCompletos $documentosCompletos): void
    {
        $this->validate(['archivo' => ['required', 'file', 'mimes:pdf', 'max:10240']]);
        $this->acreditacionForm->validate();

        $registrarDocumento->ejecutar($this->escuela->id, 'escritura_inmueble', $this->archivo, new DatosDocumento(
            tipoAcreditacion: $this->acreditacionForm->tipo,
            numeroEscritura: $this->acreditacionForm->numeroEscritura !== '' ? $this->acreditacionForm->numeroEscritura : null,
            notarioNombre: $this->acreditacionForm->notarioNombre !== '' ? $this->acreditacionForm->notarioNombre : null,
            notarioNumero: $this->acreditacionForm->notarioNumero !== '' ? $this->acreditacionForm->notarioNumero : null,
            notarioLocalidad: $this->acreditacionForm->notarioLocalidad !== '' ? $this->acreditacionForm->notarioLocalidad : null,
            folioRpp: $this->acreditacionForm->folioRpp !== '' ? $this->acreditacionForm->folioRpp : null,
            fechaInscripcionRpp: $this->acreditacionForm->fechaInscripcionRpp !== '' ? $this->acreditacionForm->fechaInscripcionRpp : null,
            arrendadorComodante: $this->acreditacionForm->arrendadorComodante !== '' ? $this->acreditacionForm->arrendadorComodante : null,
            arrendatarioComodatario: $this->acreditacionForm->arrendatarioComodatario !== '' ? $this->acreditacionForm->arrendatarioComodatario : null,
            fechaContrato: $this->acreditacionForm->fechaContrato !== '' ? $this->acreditacionForm->fechaContrato : null,
            vigenciaContrato: $this->acreditacionForm->vigenciaContrato !== '' ? $this->acreditacionForm->vigenciaContrato : null,
            usoAutorizado: $this->acreditacionForm->usoAutorizado !== '' ? $this->acreditacionForm->usoAutorizado : null,
            ratificadoNotario: $this->acreditacionForm->ratificadoNotario,
            otroEspecifique: $this->acreditacionForm->otroEspecifique !== '' ? $this->acreditacionForm->otroEspecifique : null,
        ));
        $this->archivo = null;

        $this->avanzar($documentosCompletos);
    }

    private function avanzar(DocumentosCompletos $documentosCompletos): void
    {
        $tipoPersona = $this->tipoPersona();
        $pendientes = $documentosCompletos->clavesPendientes($this->escuela->id, $tipoPersona);

        if ($pendientes === []) {
            $this->redirectRoute('tramite.paso2', ['escuela' => $this->escuela->id]);

            return;
        }

        $this->fase = $pendientes[0];
    }

    private function tipoPersona(): string
    {
        return ResponsableLegal::where('escuela_id', $this->escuela->id)->firstOrFail()->tipo_persona;
    }

    public function render()
    {
        return view('livewire.tramite.paso2-documentos');
    }
}
