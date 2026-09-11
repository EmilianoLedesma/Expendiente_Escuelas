<?php

namespace App\Livewire\Tramite;

use App\Application\Documentos\DocumentosCompletos;
use App\Application\Documentos\DTO\DatosDocumento;
use App\Application\Documentos\RegistrarDocumento;
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
