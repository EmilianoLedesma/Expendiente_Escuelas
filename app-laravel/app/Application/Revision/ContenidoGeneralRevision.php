<?php

namespace App\Application\Revision;

use App\Application\Documentos\DocumentosCompletos;
use App\Application\Documentos\DocumentosNivelCompletos;
use App\Application\Documentos\MetadatosDocumentales;
use App\Models\EscuelaNivel;
use App\Models\Gestor;
use App\Models\PersonaFisica;
use App\Models\PersonaMoral;
use App\Models\ResponsableLegal;
use App\Models\TernaNombre;
use App\Models\TipoDocumento;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Lo que el solicitante capturó en Paso 2 y que el resumen de ConsultarTramiteSedeq no trae: responsable completo,
 * datos estructurados de los documentos y de los documentos del nivel, y los niveles solicitados.
 * Solo lectura. Lo invoca ContenidoPasoRevision, que ya verificó el acceso al nivel; no revalida.
 * Los datos de los documentos los lee MetadatosDocumentales, el mismo lector de la vista de solo lectura de SEDEQ:
 * un dato que el documento no guardó se muestra «No capturado», nunca se omite ni se da por incumplido.
 */
final class ContenidoGeneralRevision
{
    public function __construct(
        private readonly DocumentosCompletos $documentos,
        private readonly DocumentosNivelCompletos $documentosNivel,
        private readonly MetadatosDocumentales $metadatos,
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
        $datos = $this->metadatos->paraEscuela($escuelaId, $tipos->pluck('clave')->all());

        foreach ($tipos as $tipo) {
            $filas = self::filasDe($datos[$tipo->clave] ?? []);
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

        $tipos = TipoDocumento::whereIn('clave', $this->documentosNivel->clavesAplicables($escuelaNivelId))->orderBy('id')->get();
        $datos = $this->metadatos->paraEscuelaNivel($escuelaNivelId);

        foreach ($tipos as $tipo) {
            $filas = self::filasDe($datos[$tipo->clave] ?? []);
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

    /**
     * Filas de MetadatosDocumentales como pares [etiqueta, valor]; un documento vencido lo dice en el valor.
     *
     * @param  list<array{etiqueta: string, valor: string, estado: string|null}>  $filas
     * @return list<array{0: string, 1: string}>
     */
    private static function filasDe(array $filas): array
    {
        return array_map(fn (array $f) => [$f['etiqueta'], $f['estado'] === 'vencido' ? "{$f['valor']} (vencido)" : $f['valor']], $filas);
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
