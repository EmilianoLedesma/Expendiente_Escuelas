<?php

namespace App\Application\Revision;

use App\Application\Documentos\DocumentosCompletos;
use App\Application\Documentos\DocumentosNivelCompletos;
use App\Models\AcreditacionOcupacionLegal;
use App\Models\CertificadoNumeroOficial;
use App\Models\ConstanciaCurp;
use App\Models\ConstanciaSituacionFiscal;
use App\Models\CredencialIne;
use App\Models\DocumentoEscuela;
use App\Models\DocumentoEscuelaNivel;
use App\Models\DocumentoPlantel;
use App\Models\Escuela;
use App\Models\EscuelaNivel;
use App\Models\Gestor;
use App\Models\PersonaFisica;
use App\Models\PersonaMoral;
use App\Models\ReciboPagoDerechos;
use App\Models\RelacionAcervoBibliografico;
use App\Models\ResponsableLegal;
use App\Models\TernaNombre;
use App\Models\TipoDocumento;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Lo que el solicitante capturó en Paso 2 y que el resumen de ConsultarTramiteSedeq no trae: responsable completo,
 * datos estructurados de los documentos y de los documentos del nivel, y los niveles solicitados.
 * Solo lectura. Lo invoca ContenidoPasoRevision, que ya verificó el acceso al nivel; no revalida.
 * Solo muestra lo que la captura guarda: un dato vacío no genera fila.
 */
final class ContenidoGeneralRevision
{
    public function __construct(
        private readonly DocumentosCompletos $documentos,
        private readonly DocumentosNivelCompletos $documentosNivel,
    ) {}

    /** @return list<array{titulo: string, filas: list<array{0: string, 1: string}>, documentos: list<array{nombre: string, archivo: string, href: string}>}> */
    public function responsable(int $escuelaId): array
    {
        $r = ResponsableLegal::where('escuela_id', $escuelaId)->first();
        if ($r === null) {
            return [];
        }

        $tipos = ['fisica' => 'Persona física', 'fisica_con_gestor' => 'Persona física con gestor', 'moral' => 'Persona moral'];
        $filas = [['Tipo de persona', $tipos[$r->tipo_persona] ?? $r->tipo_persona]];

        if ($r->tipo_persona === 'moral') {
            $m = PersonaMoral::find($r->id);
            $filas = [...$filas, ...self::datos([
                'Razón social' => $m?->razon_social,
                'Representante legal' => $m?->nombre_representante_legal,
                'Número de escritura constitutiva' => $m?->numero_escritura_constitutiva,
                'Fecha de escritura constitutiva' => self::fecha($m?->fecha_escritura_constitutiva),
                'Notario' => $m?->notario_nombre,
                'Número de notaría' => $m?->notario_numero,
                'Ciudad de la notaría' => $m?->notario_ciudad,
                'Folio del Registro Público' => $m?->folio_registro_publico,
                'Fecha de inscripción en el Registro Público' => self::fecha($m?->fecha_inscripcion_rpp),
            ])];
        } else {
            $f = PersonaFisica::find($r->id);
            $filas = [...$filas, ...self::datos([
                'Nombre' => $f?->nombre,
                'Fecha de nacimiento' => self::fecha($f?->fecha_nacimiento),
                'RFC' => $f?->rfc,
                'CURP' => $f?->curp,
            ])];
        }

        $filas = [...$filas, ...self::datos([
            'Domicilio para notificaciones' => $r->domicilio_notificaciones,
            'Persona autorizada a recoger' => $r->persona_autorizada_recoger,
        ])];

        $bloques = [self::bloque('Responsable legal', $filas)];

        $g = $r->tipo_persona === 'fisica_con_gestor' ? Gestor::find($r->id) : null;
        if ($g !== null) {
            $bloques[] = self::bloque('Gestor', self::datos([
                'Nombre del gestor' => $g->nombre,
                'Número de poder' => $g->numero_poder,
                'Notario' => $g->notario_nombre,
                'Número de notaría' => $g->notario_numero,
                'Fecha del poder' => self::fecha($g->fecha_poder),
                'CURP del gestor' => $g->curp,
            ]));
        }

        $terna = TernaNombre::where('escuela_id', $escuelaId)->orderBy('numero_propuesta')->get();
        if ($terna->isNotEmpty()) {
            $bloques[] = self::bloque('Terna de nombres propuestos', $terna->map(fn ($t) => ["Propuesta {$t->numero_propuesta}", $t->nombre_propuesto])->all());
        }

        return $bloques;
    }

    /**
     * Los archivos (ya listados por ConsultarTramiteSedeq) y, por documento aplicable, lo que se capturó con él.
     *
     * @param  list<array{nombre: string, archivo: string, href: string}>  $archivos
     * @return list<array{titulo: string, filas: list<array{0: string, 1: string}>, documentos: list<array{nombre: string, archivo: string, href: string}>}>
     */
    public function documentos(int $escuelaId, array $archivos): array
    {
        $bloques = [self::bloque('Documentos del plantel', [], $archivos)];
        $tipoPersona = ResponsableLegal::where('escuela_id', $escuelaId)->value('tipo_persona');
        if ($tipoPersona === null) {
            return $bloques;
        }

        $tipos = TipoDocumento::whereIn('clave', $this->documentos->clavesAplicables($tipoPersona))->orderBy('id')->get();
        $plantel = DocumentoPlantel::with(['dictamenUsoSuelo', 'constanciaSeguridadEstructural', 'acreditacionOcupacionLegal'])
            ->where('plantel_id', Escuela::findOrFail($escuelaId)->plantel_id)->get()->keyBy('tipo_documento_id');
        $escuela = DocumentoEscuela::where('escuela_id', $escuelaId)->get()->keyBy('tipo_documento_id');
        $oficial = CertificadoNumeroOficial::whereIn('documento_plantel_id', $plantel->pluck('id')->all())->get()->keyBy('documento_plantel_id');
        $ine = CredencialIne::whereIn('documento_escuela_id', $escuela->pluck('id')->all())->get()->keyBy('documento_escuela_id');
        $curp = ConstanciaCurp::whereIn('documento_escuela_id', $escuela->pluck('id')->all())->get()->keyBy('documento_escuela_id');
        $fiscal = ConstanciaSituacionFiscal::whereIn('documento_escuela_id', $escuela->pluck('id')->all())->get()->keyBy('documento_escuela_id');

        foreach ($tipos as $tipo) {
            $p = $plantel->get($tipo->id);
            $e = $escuela->get($tipo->id);
            $filas = match ($tipo->clave) {
                'escritura_inmueble' => $this->acreditacion($p?->acreditacionOcupacionLegal),
                'dictamen_uso_suelo' => self::datos([
                    'Fecha de emisión' => self::fecha($p?->fecha_emision),
                    'Autoridad que expide' => $p?->dictamenUsoSuelo?->autoridad_expide,
                    'Folio del dictamen' => $p?->dictamenUsoSuelo?->folio_dictamen,
                    'Giro que autoriza' => $p?->dictamenUsoSuelo?->giro_autorizado,
                ]),
                'constancia_seguridad_estructural' => self::datos([
                    'Fecha de emisión' => self::fecha($p?->fecha_emision),
                    'Nombre del perito' => $p?->constanciaSeguridadEstructural?->perito_nombre,
                    'Cédula profesional del perito' => $p?->constanciaSeguridadEstructural?->perito_cedula_profesional,
                    'Registro DRO' => $p?->constanciaSeguridadEstructural?->perito_registro_dro,
                    'Autoridad del registro' => $p?->constanciaSeguridadEstructural?->perito_registro_autoridad,
                    'Vigencia del registro' => self::fecha($p?->constanciaSeguridadEstructural?->perito_registro_vigencia),
                    'Vigencia de la constancia' => self::fecha($p?->constanciaSeguridadEstructural?->vigencia_constancia),
                ]),
                'visto_bueno_proteccion_civil' => self::datos(['Fecha de emisión' => self::fecha($p?->fecha_emision)]),
                'certificado_numero_oficial' => self::datos([
                    'Calle' => $oficial->get($p?->id)?->calle,
                    'Número exterior' => $oficial->get($p?->id)?->numero_ext,
                    'Colonia' => $oficial->get($p?->id)?->colonia,
                    'Municipio' => $oficial->get($p?->id)?->municipio,
                    'Código postal' => $oficial->get($p?->id)?->codigo_postal,
                ]),
                'ine' => self::datos(['Nombre en el documento' => $ine->get($e?->id)?->nombre, 'CURP' => $ine->get($e?->id)?->curp]),
                'constancia_curp' => self::datos(['Nombre en el documento' => $curp->get($e?->id)?->nombre, 'CURP' => $curp->get($e?->id)?->curp]),
                'constancia_situacion_fiscal' => self::datos(['Nombre o razón social' => $fiscal->get($e?->id)?->nombre_razon_social, 'RFC' => $fiscal->get($e?->id)?->rfc]),
                default => [],
            };

            if ($filas !== []) {
                $bloques[] = self::bloque("Datos de {$tipo->nombre}", $filas);
            }
        }

        return $bloques;
    }

    /**
     * Los archivos del nivel, turno y tipo de alumnado (se capturan aquí) y los datos del recibo y del acervo.
     *
     * @param  list<array{nombre: string, archivo: string, href: string}>  $archivos
     * @return list<array{titulo: string, filas: list<array{0: string, 1: string}>, documentos: list<array{nombre: string, archivo: string, href: string}>}>
     */
    public function documentosNivel(int $escuelaNivelId, array $archivos): array
    {
        $bloques = [self::bloque('Documentos del nivel', [], $archivos)];
        $nivel = EscuelaNivel::findOrFail($escuelaNivelId);

        $datos = self::datos([
            'Turno' => ['matutino' => 'Matutino', 'vespertino' => 'Vespertino', 'mixto' => 'Mixto'][(string) $nivel->turno] ?? null,
            'Tipo de alumnado' => ['mixto' => 'Mixto', 'femenino' => 'Femenino', 'masculino' => 'Masculino'][(string) $nivel->tipo_alumnado] ?? null,
        ]);
        if ($datos !== []) {
            $bloques[] = self::bloque('Datos del nivel', $datos);
        }

        $tipos = TipoDocumento::whereIn('clave', $this->documentosNivel->clavesAplicables($escuelaNivelId))->orderBy('id')->get()->keyBy('id');
        $docs = DocumentoEscuelaNivel::where('escuela_nivel_id', $escuelaNivelId)->whereIn('tipo_documento_id', $tipos->keys()->all())->orderBy('id')->get();
        $recibos = ReciboPagoDerechos::whereIn('documento_escuela_nivel_id', $docs->pluck('id')->all())->get()->keyBy('documento_escuela_nivel_id');
        $acervos = RelacionAcervoBibliografico::whereIn('documento_escuela_nivel_id', $docs->pluck('id')->all())->get()->keyBy('documento_escuela_nivel_id');

        foreach ($docs as $doc) {
            $tipo = $tipos[$doc->tipo_documento_id];
            $recibo = $recibos->get($doc->id);
            $acervo = $acervos->get($doc->id);
            $filas = match (true) {
                $tipo->clave === 'recibo_pago_derechos' => self::datos([
                    'Folio del recibo' => $recibo?->folio,
                    'Monto pagado' => $recibo?->monto === null ? null : '$'.number_format((float) $recibo->monto, 2),
                    'Fecha de pago' => self::fecha($recibo?->fecha_pago),
                    'Referencia del portal de pago' => $recibo?->portal_referencia,
                ]),
                $acervo !== null => self::datos(['Número de títulos' => (string) $acervo->numero_titulos]),
                default => [],
            };

            if ($filas !== []) {
                $bloques[] = self::bloque("Datos de {$tipo->nombre}", $filas);
            }
        }

        return $bloques;
    }

    /**
     * Todos los niveles que la escuela solicitó, sin filtrar por lo que el actor puede revisar.
     *
     * @return list<array{titulo: string, filas: list<array{0: string, 1: string}>, documentos: list<array{nombre: string, archivo: string, href: string}>}>
     */
    public function niveles(int $escuelaId): array
    {
        $estados = DB::table('estados_expediente')->pluck('nombre', 'id');
        $filas = EscuelaNivel::with('nivelEducativo')->where('escuela_id', $escuelaId)->orderBy('id')->get()
            ->map(fn (EscuelaNivel $n) => [$n->nivelEducativo->nombre, (string) $estados[$n->estado_id]])->all();

        return [self::bloque('Niveles solicitados', $filas)];
    }

    /** @return list<array{0: string, 1: string}> */
    private function acreditacion(?AcreditacionOcupacionLegal $a): array
    {
        $tipos = ['escritura_publica' => 'Escritura pública', 'arrendamiento' => 'Arrendamiento', 'comodato' => 'Comodato', 'otro' => 'Otro'];

        return self::datos([
            'Tipo de acreditación' => $a === null ? null : ($tipos[$a->tipo] ?? $a->tipo),
            'Número de escritura' => $a?->numero_escritura,
            'Fecha de la escritura' => self::fecha($a?->fecha_escritura),
            'Notario' => $a?->notario_nombre,
            'Número del notario' => $a?->notario_numero,
            'Localidad del notario' => $a?->notario_localidad,
            'Folio del Registro Público de la Propiedad' => $a?->folio_rpp,
            'Fecha de inscripción RPP' => self::fecha($a?->fecha_inscripcion_rpp),
            'Arrendador / comodante' => $a?->arrendador_comodante,
            'Arrendatario / comodatario' => $a?->arrendatario_comodatario,
            'Fecha del contrato' => self::fecha($a?->fecha_contrato),
            'Vigencia del contrato' => self::fecha($a?->vigencia_contrato),
            'Uso autorizado' => $a?->uso_autorizado,
            'Ratificado ante notario' => $a?->ratificado_notario === null ? null : ($a->ratificado_notario ? 'Sí' : 'No'),
            'Especifique' => $a?->otro_especifique,
            'Observaciones' => $a?->observaciones,
        ]);
    }

    /**
     * Solo los datos con valor: lo que la captura no guardó no genera fila.
     *
     * @param  array<string, string|null>  $campos
     * @return list<array{0: string, 1: string}>
     */
    private static function datos(array $campos): array
    {
        $filas = [];
        foreach ($campos as $etiqueta => $valor) {
            if ($valor !== null && $valor !== '') {
                $filas[] = [$etiqueta, $valor];
            }
        }

        return $filas;
    }

    private static function fecha(?string $valor): ?string
    {
        return $valor === null || $valor === '' ? null : Carbon::parse($valor)->format('d/m/Y');
    }

    /**
     * @param  list<array{0: string, 1: string}>  $filas
     * @param  list<array{nombre: string, archivo: string, href: string}>  $documentos
     * @return array{titulo: string, filas: list<array{0: string, 1: string}>, documentos: list<array{nombre: string, archivo: string, href: string}>}
     */
    private static function bloque(string $titulo, array $filas, array $documentos = []): array
    {
        return ['titulo' => $titulo, 'filas' => $filas, 'documentos' => $documentos];
    }
}
