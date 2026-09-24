<?php

namespace App\Livewire\Tramite;

use App\Application\Documentos\DocumentosCompletos;
use App\Application\Documentos\DTO\DatosDocumento;
use App\Application\Documentos\RegistrarDocumento;
use App\Application\Documentos\ValidarVigenciaDocumentos;
use App\Application\Excepciones\DatosInvalidos;
use App\Application\Tramite\EstadoPaso2;
use App\Livewire\Forms\AcreditacionOcupacionForm;
use App\Livewire\Forms\ConstanciaSeguridadForm;
use App\Livewire\Forms\DictamenUsoSueloForm;
use App\Models\DocumentoEscuela;
use App\Models\DocumentoPlantel;
use App\Models\Escuela;
use App\Models\ResponsableLegal;
use App\Models\TipoDocumento;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Presentación pura: checklist de los documentos de Paso 2.2, todos
 * renderizados a la vez, cada sección editable/solo-lectura de forma
 * independiente (docs/superpowers/specs/2026-09-22-paso2-documentos-checklist-redesign.md).
 * Ninguna escritura directa a Eloquent — RegistrarDocumento es la única
 * escritura (ADR-001).
 */
#[Layout('layouts.tramite')]
class Paso2Documentos extends Component
{
    use WithFileUploads;

    public Escuela $escuela;

    public array $archivos = [];

    /** @var array<string, bool> clave => true mientras se muestra el form de reemplazo. */
    public array $reemplazando = [];

    public DictamenUsoSueloForm $dictamenForm;

    public ConstanciaSeguridadForm $constanciaForm;

    public AcreditacionOcupacionForm $acreditacionForm;

    public function mount(Escuela $escuela, DocumentosCompletos $documentosCompletos, ValidarVigenciaDocumentos $validarVigencia, EstadoPaso2 $estadoPaso2): void
    {
        $this->escuela = $escuela;

        // Sin responsable no hay tipo_persona con qué armar la checklist:
        // se manda a capturarlo en vez de responder 404.
        if (! $estadoPaso2->responsableCapturado($escuela->id)) {
            $this->redirectRoute('tramite.paso2', ['escuela' => $escuela->id]);

            return;
        }

        $tipoPersona = $this->tipoPersona();
        $pendientes = $documentosCompletos->clavesPendientes($escuela->id, $tipoPersona);

        if ($pendientes !== []) {
            return;
        }

        // Los 6 documentos existen: o una vigencia venció (se vuelve a pedir
        // ese documento) o el paso ya terminó y esto es back-navigation.
        $violaciones = $validarVigencia->ejecutar($escuela->id);

        if ($violaciones === []) {
            $this->redirectRoute('tramite.paso2', ['escuela' => $escuela->id]);

            return;
        }

        $this->addError('vigencia', implode(' ', $violaciones));
    }

    public function guardarDocumentoSimple(string $clave, RegistrarDocumento $registrarDocumento, DocumentosCompletos $documentosCompletos): void
    {
        $simples = array_intersect(
            $documentosCompletos->clavesAplicables($this->tipoPersona()),
            ['ine', 'acta_nacimiento', 'escritura_poder_facultades', 'formato_solicitud'],
        );
        abort_unless(in_array($clave, $simples, true), 403);

        $this->validate(["archivos.{$clave}" => ['required', 'file', 'mimes:pdf', 'max:10240']]);

        if (! $this->intentarRegistrar($registrarDocumento, $clave, new DatosDocumento)) {
            return;
        }
        $this->archivos[$clave] = null;
        $this->reemplazando[$clave] = false;

        $this->avanzar($documentosCompletos);
    }

    public function guardarDictamen(RegistrarDocumento $registrarDocumento, DocumentosCompletos $documentosCompletos): void
    {
        $this->validate(['archivos.dictamen_uso_suelo' => ['required', 'file', 'mimes:pdf', 'max:10240']]);
        $this->dictamenForm->validate();

        if (! $this->intentarRegistrar($registrarDocumento, 'dictamen_uso_suelo', new DatosDocumento(
            fechaEmision: $this->dictamenForm->fechaEmision,
        ))) {
            return;
        }
        $this->archivos['dictamen_uso_suelo'] = null;
        $this->reemplazando['dictamen_uso_suelo'] = false;

        $this->avanzar($documentosCompletos);
    }

    public function guardarConstancia(RegistrarDocumento $registrarDocumento, DocumentosCompletos $documentosCompletos): void
    {
        $this->validate(['archivos.constancia_seguridad_estructural' => ['required', 'file', 'mimes:pdf', 'max:10240']]);
        $this->constanciaForm->validate();

        if (! $this->intentarRegistrar($registrarDocumento, 'constancia_seguridad_estructural', new DatosDocumento(
            fechaEmision: $this->constanciaForm->fechaEmision,
            peritoNombre: $this->constanciaForm->peritoNombre,
            peritoCedulaProfesional: $this->constanciaForm->peritoCedulaProfesional !== '' ? $this->constanciaForm->peritoCedulaProfesional : null,
            peritoRegistroDro: $this->constanciaForm->peritoRegistroDro,
            peritoRegistroAutoridad: $this->constanciaForm->peritoRegistroAutoridad !== '' ? $this->constanciaForm->peritoRegistroAutoridad : null,
            peritoRegistroVigencia: $this->constanciaForm->peritoRegistroVigencia !== '' ? $this->constanciaForm->peritoRegistroVigencia : null,
        ))) {
            return;
        }
        $this->archivos['constancia_seguridad_estructural'] = null;
        $this->reemplazando['constancia_seguridad_estructural'] = false;

        $this->avanzar($documentosCompletos);
    }

    public function guardarAcreditacion(RegistrarDocumento $registrarDocumento, DocumentosCompletos $documentosCompletos): void
    {
        $this->validate(['archivos.escritura_inmueble' => ['required', 'file', 'mimes:pdf', 'max:10240']]);
        $this->acreditacionForm->validate();

        if (! $this->intentarRegistrar($registrarDocumento, 'escritura_inmueble', new DatosDocumento(
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
            observaciones: $this->acreditacionForm->observaciones !== '' ? $this->acreditacionForm->observaciones : null,
        ))) {
            return;
        }
        $this->archivos['escritura_inmueble'] = null;
        $this->reemplazando['escritura_inmueble'] = false;

        $this->avanzar($documentosCompletos);
    }

    public function guardarFormatoSolicitud(RegistrarDocumento $registrarDocumento, DocumentosCompletos $documentosCompletos): void
    {
        $this->validate(['archivos.formato_solicitud' => ['required', 'file', 'mimes:pdf', 'max:10240']]);

        if (! $this->intentarRegistrar($registrarDocumento, 'formato_solicitud', new DatosDocumento)) {
            return;
        }
        $this->archivos['formato_solicitud'] = null;
        $this->reemplazando['formato_solicitud'] = false;

        $this->avanzar($documentosCompletos);
    }

    /**
     * Envuelve RegistrarDocumento::ejecutar() para que un DatosInvalidos
     * (invariante de entrada violado — clave no aplicable, archivo no PDF)
     * se convierta en errores de campo en vez de un 500, incluso si el
     * caller llega a saltarse la validación de Livewire de arriba.
     */
    private function intentarRegistrar(RegistrarDocumento $registrarDocumento, string $clave, DatosDocumento $datos): bool
    {
        try {
            $registrarDocumento->ejecutar($this->escuela->id, $clave, $this->archivos[$clave], $datos);
        } catch (DatosInvalidos $e) {
            foreach ($e->errores as $campo => $mensaje) {
                $this->addError($campo, $mensaje);
            }

            return false;
        }

        return true;
    }

    public function toggleReemplazar(string $clave): void
    {
        $this->reemplazando[$clave] = ! ($this->reemplazando[$clave] ?? false);
    }

    /**
     * Documentos ya capturados (escuela o plantel según ambito), para el
     * bloque de solo lectura de cada sección. Mismo patrón de lectura en el
     * componente que InfraestructuraNivel::espaciosCapturados() (ADR-001);
     * dispatch por ambito igual que RegistrarDocumento::ejecutar() en la
     * escritura.
     *
     * @return Collection<string, array{nombreArchivo: string, subidoEn: Carbon}>
     */
    public function documentosCapturados(DocumentosCompletos $documentosCompletos): Collection
    {
        $claves = $documentosCompletos->clavesAplicables($this->tipoPersona());
        $tipos = TipoDocumento::whereIn('clave', $claves)->get()->keyBy('clave');

        $capturados = collect();

        foreach ($claves as $clave) {
            $tipo = $tipos[$clave];

            $documento = $tipo->ambito === 'plantel'
                ? DocumentoPlantel::where('plantel_id', $this->escuela->plantel_id)->where('tipo_documento_id', $tipo->id)->first()
                : DocumentoEscuela::where('escuela_id', $this->escuela->id)->where('tipo_documento_id', $tipo->id)->first();

            if ($documento === null) {
                continue;
            }

            $capturados[$clave] = [
                'nombreArchivo' => basename((string) $documento->archivo_path),
                'subidoEn' => $documento->updated_at,
            ];
        }

        return $capturados;
    }

    private function avanzar(DocumentosCompletos $documentosCompletos): void
    {
        $tipoPersona = $this->tipoPersona();
        $pendientes = $documentosCompletos->clavesPendientes($this->escuela->id, $tipoPersona);

        if ($pendientes !== []) {
            return;
        }

        $violaciones = app(ValidarVigenciaDocumentos::class)->ejecutar($this->escuela->id);

        if ($violaciones !== []) {
            $this->addError('vigencia', implode(' ', $violaciones));

            return;
        }

        $this->redirectRoute('tramite.paso2', ['escuela' => $this->escuela->id]);
    }

    private function tipoPersona(): string
    {
        return ResponsableLegal::where('escuela_id', $this->escuela->id)->firstOrFail()->tipo_persona;
    }

    public function render(DocumentosCompletos $documentosCompletos)
    {
        $clavesAplicables = $documentosCompletos->clavesAplicables($this->tipoPersona());
        $capturados = $this->documentosCapturados($documentosCompletos);

        return view('livewire.tramite.paso2-documentos', [
            'clavesAplicables' => $clavesAplicables,
            'capturados' => $capturados,
            'totalAplicables' => count($clavesAplicables),
            'totalCompletos' => $capturados->count(),
        ])->layoutData(['escuelaId' => $this->escuela->id]);
    }
}
