<?php

namespace Tests\Concerns;

use App\Application\Documentos\DocumentosCompletos;
use App\Application\Documentos\DTO\DatosDocumento;
use App\Application\Documentos\RegistrarDocumento;
use App\Application\EscuelaNiveles\MarcarPasoCompletado;
use App\Application\ResponsableLegal\DTO\DatosResponsableLegal;
use App\Application\ResponsableLegal\RegistrarResponsableLegal;
use App\Models\Escuela;
use App\Models\EscuelaNivel;
use App\Models\NivelEducativo;
use App\Models\Plantel;
use App\Models\Solicitante;
use Database\Seeders\CatalogoMinimoSeeder;
use Database\Seeders\PasosCapturaSeeder;
use Database\Seeders\TiposDocumentosSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Fixture for the documental validation engine: a school whose declared
 * data and every uploaded document agree, so each test changes one value
 * and checks the one rule that should react.
 */
trait CapturaExpedienteConsistente
{
    protected const CURP_TITULAR = 'PEGJ800101HQTRML09';

    protected const CURP_GESTOR = 'GOMC800101HQTMRR01';

    protected const RFC_TITULAR = 'PEGJ800101AB1';

    protected function crearEscuelaConPlantel(?int $plantelId = null): Escuela
    {
        Storage::fake('documentos');
        (new TiposDocumentosSeeder)->run();

        $plantelId ??= Plantel::create([
            'calle' => 'Av. Juárez', 'numero_ext' => '12', 'colonia' => 'Centro',
            'municipio' => 'Querétaro', 'codigo_postal' => '76000',
        ])->id;

        return Escuela::create(['plantel_id' => $plantelId, 'solicitante_id' => Solicitante::factory()->create()->id]);
    }

    protected function registrarResponsable(Escuela $escuela, string $tipoPersona = 'fisica'): void
    {
        (new RegistrarResponsableLegal)->ejecutar($escuela->id, new DatosResponsableLegal(
            tipoPersona: $tipoPersona,
            nombre: $tipoPersona === 'moral' ? null : 'Juan Pérez Gómez',
            rfc: $tipoPersona === 'moral' ? null : self::RFC_TITULAR,
            curp: $tipoPersona === 'moral' ? null : self::CURP_TITULAR,
            razonSocial: $tipoPersona === 'moral' ? 'Colegio Ejemplo A.C.' : null,
            nombreRepresentanteLegal: $tipoPersona === 'moral' ? 'Juan Pérez Gómez' : null,
            gestorNombre: $tipoPersona === 'fisica_con_gestor' ? 'Carlos Gómez Mora' : null,
            gestorCurp: $tipoPersona === 'fisica_con_gestor' ? self::CURP_GESTOR : null,
        ));
    }

    /** Typed data that agrees with registrarResponsable() and the plantel, per document. */
    protected function datosConsistentes(string $clave, string $tipoPersona = 'fisica'): DatosDocumento
    {
        [$nombreIdentidad, $curp] = match ($tipoPersona) {
            'fisica_con_gestor' => ['GOMEZ MORA CARLOS', self::CURP_GESTOR],
            default => ['PEREZ GOMEZ JUAN', self::CURP_TITULAR],
        };
        [$nombreFiscal, $rfc] = $tipoPersona === 'moral'
            ? ['COLEGIO EJEMPLO A C', 'CEJ010101AB1']
            : ['JUAN PEREZ GOMEZ', self::RFC_TITULAR];

        return match ($clave) {
            'ine', 'constancia_curp' => new DatosDocumento(identidadNombre: $nombreIdentidad, identidadCurp: $curp),
            'constancia_situacion_fiscal' => new DatosDocumento(fiscalNombre: $nombreFiscal, fiscalRfc: $rfc),
            'certificado_numero_oficial' => new DatosDocumento(
                domicilioCalle: 'AVENIDA JUAREZ', domicilioNumeroExt: 'No. 12', domicilioColonia: 'Col. Centro',
                domicilioMunicipio: 'QUERETARO', domicilioCodigoPostal: '76000',
            ),
            'dictamen_uso_suelo' => new DatosDocumento(fechaEmision: now()->toDateString()),
            default => new DatosDocumento,
        };
    }

    protected function subirDocumento(Escuela $escuela, string $clave, DatosDocumento $datos): void
    {
        app(RegistrarDocumento::class)->ejecutar($escuela->id, $clave, UploadedFile::fake()->create("{$clave}.pdf", 10, 'application/pdf'), $datos);
    }

    /** @param array<string, DatosDocumento> $reemplazos clave => datos que sustituyen a los consistentes */
    protected function subirTodosConDatos(Escuela $escuela, string $tipoPersona = 'fisica', array $reemplazos = []): void
    {
        foreach (app(DocumentosCompletos::class)->clavesAplicables($tipoPersona) as $clave) {
            $this->subirDocumento($escuela, $clave, $reemplazos[$clave] ?? $this->datosConsistentes($clave, $tipoPersona));
        }
    }

    /**
     * Every hub section done (ResumenTramite::completo), so the final
     * validation step is reachable: responsable, all documents with data
     * (or the given replacements), and one primaria level with its
     * available Paso 3 sub-steps completed.
     *
     * @param  array<string, DatosDocumento>  $reemplazos
     */
    protected function completarTramite(Escuela $escuela, string $tipoPersona = 'fisica', array $reemplazos = []): void
    {
        (new CatalogoMinimoSeeder)->run();
        (new PasosCapturaSeeder)->run();
        $this->registrarResponsable($escuela, $tipoPersona);
        $this->subirTodosConDatos($escuela, $tipoPersona, $reemplazos);

        $escuelaNivel = EscuelaNivel::create([
            'escuela_id' => $escuela->id,
            'nivel_educativo_id' => NivelEducativo::where('clave', 'primaria')->value('id'),
            'estado_id' => DB::table('estados_expediente')->where('clave', 'en_captura')->value('id'),
            'tipo_tramite' => 'alta_nueva',
        ]);

        foreach (['inmueble', 'infraestructura'] as $paso) {
            (new MarcarPasoCompletado)->ejecutar($escuelaNivel->id, $paso);
        }
    }
}
