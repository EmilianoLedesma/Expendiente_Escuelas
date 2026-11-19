<?php

namespace App\Application\Revision;

use App\Application\Excepciones\DatosInvalidos;
use App\Application\ProyectoEducativo\AcademiasProyectoCapturada;
use App\Application\ProyectoEducativo\BibliografiaProyectoCapturada;
use App\Application\ProyectoEducativo\BibliotecaProyectoCapturada;
use App\Application\ProyectoEducativo\CalendarioAcademicoProyectoCapturada;
use App\Application\ProyectoEducativo\CriteriosProyectoCapturada;
use App\Application\ProyectoEducativo\CurriculumProyectoCapturada;
use App\Application\ProyectoEducativo\DomicilioPlantelDelNivel;
use App\Application\ProyectoEducativo\EgresoProyectoCapturada;
use App\Application\ProyectoEducativo\EnfoqueProyectoCapturada;
use App\Application\ProyectoEducativo\EvaluacionProyectoCapturada;
use App\Application\ProyectoEducativo\FilosofiaProyectoCapturada;
use App\Application\ProyectoEducativo\FormacionContinuaProyectoCapturada;
use App\Application\ProyectoEducativo\FormacionInicialProyectoCapturada;
use App\Application\ProyectoEducativo\FormacionLaboralProyectoCapturada;
use App\Application\ProyectoEducativo\IngresoProyectoCapturada;
use App\Application\ProyectoEducativo\MapaCurricularProyectoCapturada;
use App\Application\ProyectoEducativo\ObjetivosProyectoCapturada;
use App\Application\ProyectoEducativo\PaecProyectoCapturada;
use App\Application\ProyectoEducativo\PerfilDocenteProyectoCapturada;
use App\Application\ProyectoEducativo\PlaneacionAcademicaProyectoCapturada;
use App\Application\ProyectoEducativo\PresentacionProyectoCapturada;
use App\Application\ProyectoEducativo\ProgramasEstudiosProyectoCapturada;
use App\Application\ProyectoEducativo\ReglasFormacionProyecto;
use App\Application\ProyectoEducativo\SeccionesProyectoEducativo;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Str;

/**
 * Lo que el solicitante capturó en una sección del proyecto educativo, en solo lectura, para quien la revisa
 * (evaluador y supervisor: AccesoSedeq::puedeVerProyecto). Lee con las mismas clases *Capturada del formulario,
 * que nunca exponen rutas de disco; los archivos PDF salen con su nombre y páginas, más `descargas` (clave y etiqueta, sin ruta).
 * Genérico: cada campo es una fila «Etiqueta / valor» y cada lista (acciones, referencias) un bloque de filas.
 */
final class ContenidoSeccionProyecto
{
    private const SIN_CAPTURA = 'Sin captura';

    public function __construct(private readonly AccesoSedeq $acceso, private readonly ArchivoRevisionProyecto $archivos) {}

    /**
     * @return array{titulo: string, filas: list<array{string, string}>, listas: list<array{titulo: string, filas: list<list<array{string, string}>>}>, descargas: list<array{clave: string, etiqueta: string}>}
     *
     * @throws AuthorizationException
     * @throws DatosInvalidos si la sección no existe.
     */
    public function ejecutar(int $actorId, int $escuelaNivelId, string $slug): array
    {
        if (! $this->acceso->puedeVerProyecto(User::findOrFail($actorId), $escuelaNivelId)) {
            throw new AuthorizationException('No puedes revisar este proyecto educativo.');
        }
        if (! SeccionesProyectoEducativo::existe($slug)) {
            throw new DatosInvalidos(['seccion' => 'Esa sección no existe.']);
        }

        // La Presentación no guarda domicilio: lo lee del plantel, y el evaluador lo ve igual que el solicitante.
        $filas = $slug === SeccionesProyectoEducativo::presentacion() ? app(DomicilioPlantelDelNivel::class)->ejecutar($escuelaNivelId) : [];
        $listas = [];
        foreach ($this->datos($slug, $escuelaNivelId) as $campo => $valor) {
            if (is_array($valor)) {
                $listas[] = ['titulo' => $this->etiqueta((string) $campo), 'filas' => $this->filasDeLista($valor)];
            } else {
                $filas[] = [$this->etiqueta((string) $campo), $this->texto($valor)];
            }
        }

        return [
            'titulo' => SeccionesProyectoEducativo::nombre($slug),
            'filas' => $filas,
            'listas' => $listas,
            'descargas' => $this->archivos->disponibles($actorId, $escuelaNivelId, $slug),
        ];
    }

    /** @return array<string, mixed> campo => valor, de todas las clases *Capturada de la sección. */
    private function datos(string $slug, int $escuelaNivelId): array
    {
        if ($slug === SeccionesProyectoEducativo::bibliografia()) {
            return ['referencias' => app(BibliografiaProyectoCapturada::class)->referencias($escuelaNivelId)];
        }

        $datos = [];
        foreach ($this->fuentes($slug) as $clase) {
            $datos = [...$datos, ...app($clase)->datos($escuelaNivelId)];
        }

        return $datos;
    }

    /** @return list<class-string> */
    private function fuentes(string $slug): array
    {
        return match ($slug) {
            SeccionesProyectoEducativo::presentacion() => [PresentacionProyectoCapturada::class],
            SeccionesProyectoEducativo::filosofia() => [FilosofiaProyectoCapturada::class],
            SeccionesProyectoEducativo::objetivos() => [ObjetivosProyectoCapturada::class],
            SeccionesProyectoEducativo::enfoque() => [EnfoqueProyectoCapturada::class],
            SeccionesProyectoEducativo::evaluacion() => [EvaluacionProyectoCapturada::class],
            SeccionesProyectoEducativo::paec() => [PaecProyectoCapturada::class],
            SeccionesProyectoEducativo::curriculum() => [CurriculumProyectoCapturada::class],
            SeccionesProyectoEducativo::ingreso() => [IngresoProyectoCapturada::class],
            SeccionesProyectoEducativo::egreso() => [EgresoProyectoCapturada::class],
            SeccionesProyectoEducativo::perfilDocente() => [PerfilDocenteProyectoCapturada::class],
            SeccionesProyectoEducativo::formacion() => [FormacionInicialProyectoCapturada::class, FormacionContinuaProyectoCapturada::class],
            SeccionesProyectoEducativo::academias() => [AcademiasProyectoCapturada::class],
            SeccionesProyectoEducativo::criterios() => [CriteriosProyectoCapturada::class],
            SeccionesProyectoEducativo::planEstudios() => [FormacionLaboralProyectoCapturada::class],
            SeccionesProyectoEducativo::mapaCurricular() => [MapaCurricularProyectoCapturada::class],
            SeccionesProyectoEducativo::programasEstudios() => [ProgramasEstudiosProyectoCapturada::class],
            SeccionesProyectoEducativo::planeacionAcademica() => [PlaneacionAcademicaProyectoCapturada::class],
            SeccionesProyectoEducativo::calendarioAcademico() => [CalendarioAcademicoProyectoCapturada::class],
            SeccionesProyectoEducativo::biblioteca() => [BibliotecaProyectoCapturada::class],
            default => throw new DatosInvalidos(['seccion' => 'Esa sección no existe.']),
        };
    }

    /**
     * Una lista de filas (acciones, referencias) o, si sus elementos son textos, una sola fila por elemento.
     *
     * @param  array<mixed>  $lista
     * @return list<list<array{string, string}>>
     */
    private function filasDeLista(array $lista): array
    {
        $salida = [];
        foreach ($lista as $elemento) {
            $salida[] = is_array($elemento)
                ? array_map(fn ($campo, $valor) => [$this->etiqueta((string) $campo), $this->texto($valor)], array_keys($elemento), array_values($elemento))
                : [[$this->etiqueta('elemento'), $this->texto($elemento)]];
        }

        return $salida;
    }

    private function etiqueta(string $campo): string
    {
        return Str::headline($campo);
    }

    private function texto(mixed $valor): string
    {
        return match (true) {
            $valor === null || $valor === '' => self::SIN_CAPTURA,
            is_bool($valor) => $valor ? 'Sí' : 'No',
            // Listas de texto dentro de una acción (los días): «Lunes, Miércoles».
            is_array($valor) => $valor === [] ? self::SIN_CAPTURA : implode(', ', array_map(fn ($v) => ReglasFormacionProyecto::DIAS_ETIQUETAS[$v] ?? (string) $v, $valor)),
            default => (string) $valor,
        };
    }
}
