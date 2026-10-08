<?php

namespace App\Livewire\Tramite;

use App\Application\Documentos\DocumentosCapturados as DocumentosCapturadosQuery;
use App\Application\Documentos\DocumentosCompletos;
use App\Application\Documentos\DTO\DatosDocumento;
use App\Application\Documentos\RegistrarDocumento;
use App\Application\Documentos\ValidarVigenciaDocumentos;
use App\Application\Excepciones\DatosInvalidos;
use App\Application\Excepciones\PrecondicionIncumplida;
use App\Application\ResponsableLegal\TipoPersonaDeEscuela;
use App\Application\Tramite\EstadoPaso2;
use App\Application\Tramite\ResumenTramite;
use App\Application\Tramite\TramiteEditable;
use App\Livewire\Concerns\ValidaEnConjunto;
use App\Livewire\Forms\AcreditacionOcupacionForm;
use App\Livewire\Forms\ConstanciaSeguridadForm;
use App\Livewire\Forms\DictamenUsoSueloForm;
use App\Livewire\Forms\IdentidadDocumentoForm;
use App\Livewire\Forms\NumeroOficialForm;
use App\Livewire\Forms\SituacionFiscalForm;
use App\Models\Escuela;
use App\Models\TipoDocumento;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
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
    use ValidaEnConjunto;
    use WithFileUploads;

    /** Claves con campos estructurados y método propio; toda otra clave aplicable va por guardarDocumentoSimple() (WS-5a M1). */
    public const CON_DATOS_ESTRUCTURADOS = [
        'escritura_inmueble', 'dictamen_uso_suelo', 'constancia_seguridad_estructural',
        // ADR-007: captured with the data the validation engine compares
        'ine', 'constancia_curp', 'constancia_situacion_fiscal', 'certificado_numero_oficial',
    ];

    /** PDF de hasta 10 MB (el texto de cada fila de la checklist lo anuncia así). */
    private const REGLAS_ARCHIVO = ['required', 'file', 'mimes:pdf', 'max:10240'];

    public Escuela $escuela;

    public array $archivos = [];

    /** @var array<string, bool> clave => true mientras se muestra el form de reemplazo. */
    public array $reemplazando = [];

    public DictamenUsoSueloForm $dictamenForm;

    public ConstanciaSeguridadForm $constanciaForm;

    public AcreditacionOcupacionForm $acreditacionForm;

    public IdentidadDocumentoForm $ineForm;

    public IdentidadDocumentoForm $constanciaCurpForm;

    public SituacionFiscalForm $situacionFiscalForm;

    public NumeroOficialForm $numeroOficialForm;

    /**
     * Set by the final validation step's "Corregir" link (ADR-007): reopens
     * that document even when Paso 2.2 is complete, and returns to the
     * validation after saving it.
     */
    #[Url]
    public ?string $corregir = null;

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

        if ($this->corregir !== null && in_array($this->corregir, $documentosCompletos->clavesAplicables($tipoPersona), true)) {
            $this->reemplazando[$this->corregir] = true;

            return;
        }
        $this->corregir = null;

        $pendientes = $documentosCompletos->clavesPendientes($escuela->id, $tipoPersona);

        if ($pendientes !== []) {
            return;
        }

        // Los documentos aplicables existen: o una vigencia venció (se vuelve a pedir
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
        $simples = array_diff($documentosCompletos->clavesAplicables($this->tipoPersona()), self::CON_DATOS_ESTRUCTURADOS);
        abort_unless(in_array($clave, $simples, true), 403);

        $this->validate(["archivos.{$clave}" => self::REGLAS_ARCHIVO]);

        if (! $this->intentarRegistrar($registrarDocumento, $clave, new DatosDocumento)) {
            return;
        }
        $this->archivos[$clave] = null;
        $this->reemplazando[$clave] = false;

        $this->avanzar($documentosCompletos);
    }

    public function guardarDictamen(RegistrarDocumento $registrarDocumento, DocumentosCompletos $documentosCompletos): void
    {
        $this->validarEnConjunto(
            fn () => $this->validate(['archivos.dictamen_uso_suelo' => self::REGLAS_ARCHIVO]),
            fn () => $this->dictamenForm->validate(),
        );

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
        $this->validarEnConjunto(
            fn () => $this->validate(['archivos.constancia_seguridad_estructural' => self::REGLAS_ARCHIVO]),
            fn () => $this->constanciaForm->validate(),
        );

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
        $this->validarEnConjunto(
            fn () => $this->validate(['archivos.escritura_inmueble' => self::REGLAS_ARCHIVO]),
            fn () => $this->acreditacionForm->validate(),
        );

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

    public function guardarIne(RegistrarDocumento $registrarDocumento, DocumentosCompletos $documentosCompletos): void
    {
        $this->guardarIdentidad('ine', $this->ineForm, $registrarDocumento, $documentosCompletos);
    }

    public function guardarConstanciaCurp(RegistrarDocumento $registrarDocumento, DocumentosCompletos $documentosCompletos): void
    {
        $this->guardarIdentidad('constancia_curp', $this->constanciaCurpForm, $registrarDocumento, $documentosCompletos);
    }

    public function guardarSituacionFiscal(RegistrarDocumento $registrarDocumento, DocumentosCompletos $documentosCompletos): void
    {
        $this->validarEnConjunto(
            fn () => $this->validate(['archivos.constancia_situacion_fiscal' => self::REGLAS_ARCHIVO]),
            fn () => $this->situacionFiscalForm->validate(),
        );

        if (! $this->intentarRegistrar($registrarDocumento, 'constancia_situacion_fiscal', new DatosDocumento(
            fiscalNombre: $this->situacionFiscalForm->nombre,
            fiscalRfc: $this->situacionFiscalForm->rfc,
        ))) {
            return;
        }
        $this->terminarGuardado('constancia_situacion_fiscal', $documentosCompletos);
    }

    public function guardarNumeroOficial(RegistrarDocumento $registrarDocumento, DocumentosCompletos $documentosCompletos): void
    {
        $this->validarEnConjunto(
            fn () => $this->validate(['archivos.certificado_numero_oficial' => self::REGLAS_ARCHIVO]),
            fn () => $this->numeroOficialForm->validate(),
        );

        if (! $this->intentarRegistrar($registrarDocumento, 'certificado_numero_oficial', new DatosDocumento(
            domicilioCalle: trim($this->numeroOficialForm->calle),
            domicilioNumeroExt: trim($this->numeroOficialForm->numeroExt) !== '' ? trim($this->numeroOficialForm->numeroExt) : null,
            domicilioColonia: trim($this->numeroOficialForm->colonia),
            domicilioMunicipio: trim($this->numeroOficialForm->municipio),
            domicilioCodigoPostal: $this->numeroOficialForm->codigoPostal,
        ))) {
            return;
        }
        $this->terminarGuardado('certificado_numero_oficial', $documentosCompletos);
    }

    private function guardarIdentidad(string $clave, IdentidadDocumentoForm $form, RegistrarDocumento $registrarDocumento, DocumentosCompletos $documentosCompletos): void
    {
        $this->validarEnConjunto(
            fn () => $this->validate(["archivos.{$clave}" => self::REGLAS_ARCHIVO]),
            fn () => $form->validate(),
        );

        if (! $this->intentarRegistrar($registrarDocumento, $clave, new DatosDocumento(
            identidadNombre: $form->nombre,
            identidadCurp: $form->curp,
        ))) {
            return;
        }
        $this->terminarGuardado($clave, $documentosCompletos);
    }

    private function terminarGuardado(string $clave, DocumentosCompletos $documentosCompletos): void
    {
        $this->archivos[$clave] = null;
        $this->reemplazando[$clave] = false;

        $this->avanzar($documentosCompletos);
    }

    /**
     * Envuelve RegistrarDocumento::ejecutar() para que un DatosInvalidos
     * (invariante de entrada violado — clave no aplicable, archivo no PDF)
     * se convierta en errores de campo en vez de un 500, incluso si el
     * caller llega a saltarse la validación de Livewire de arriba. Un
     * PrecondicionIncumplida (WS-2.4b: sin responsable legal) redirige a
     * Paso 2 en vez de 500 — mount() ya lo evita en el flujo normal.
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
        } catch (PrecondicionIncumplida $e) {
            // WS-7a §5.4: un documento del plantel no se reemplaza si otro trámite del plantel ya se envió.
            if ($e->etapaFaltante === TramiteEditable::ENVIADO) {
                $this->addError("archivos.{$clave}", $e->getMessage());

                return false;
            }

            $this->redirectRoute('tramite.paso2', ['escuela' => $this->escuela->id]);

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
     * @return Collection<string, array{archivoPath: string, nombreArchivo: string, subidoEn: Carbon}>
     */
    public function documentosCapturados(DocumentosCapturadosQuery $documentosCapturados): Collection
    {
        return $documentosCapturados->paraEscuela($this->escuela->id, $this->tipoPersona());
    }

    private function avanzar(DocumentosCompletos $documentosCompletos): void
    {
        if ($this->corregir !== null) {
            $this->redirectRoute('tramite.validacion', ['escuela' => $this->escuela->id]);

            return;
        }

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
        return app(TipoPersonaDeEscuela::class)->ejecutar($this->escuela->id)
            ?? throw new \RuntimeException("Escuela {$this->escuela->id} no tiene responsable legal — mount() debió redirigir antes de llegar aquí.");
    }

    public function render(DocumentosCompletos $documentosCompletos, ValidarVigenciaDocumentos $validarVigencia, DocumentosCapturadosQuery $documentosCapturados)
    {
        $clavesAplicables = $documentosCompletos->clavesAplicables($this->tipoPersona());
        $capturados = $this->documentosCapturados($documentosCapturados);

        return view('livewire.tramite.paso2-documentos', [
            'clavesAplicables' => $clavesAplicables,
            'capturados' => $capturados,
            'nombres' => TipoDocumento::whereIn('clave', $clavesAplicables)->pluck('nombre', 'clave')->all(),
            'totalAplicables' => count($clavesAplicables),
            'totalCompletos' => $capturados->count(),
            'encabezado' => ResumenTramite::encabezado('documentos'),
            'tipoPersona' => $this->tipoPersona(),
            // Solo para marcar la fila vencida; la regla sigue en ValidarVigenciaDocumentos.
            'vencidos' => $this->getErrorBag()->has('vigencia') ? $validarVigencia->ejecutar($this->escuela->id) : [],
        ])->layoutData(['escuelaId' => $this->escuela->id, 'seccionActual' => 'documentos']);
    }
}
