<?php

namespace App\Application\Infraestructura;

use App\Application\Infraestructura\DTO\AnexoInstalaciones;
use App\Models\AulaNivel;
use App\Models\EscuelaNivel;
use App\Models\InmuebleEstudioActual;
use App\Models\Plantel;
use App\Models\ServicioCercano;
use App\Models\TipoEspacio;
use Illuminate\Support\Facades\DB;

/**
 * Lectura del Anexo 2 Instalaciones de un nivel. Una sola plantilla genérica: el nivel solo
 * decide qué tipos y categorías de sanitario aplican (niveles_tipos_espacios y
 * CategoriasSanitariosPorNivel), así que Inicial agrega sus filas propias (filtro, maternal,
 * personal, bacinicas) sin ramas. Espacios, sanitarios y datos del inmueble son del plantel
 * compartido; las aulas, del nivel. Lo capturado en un tipo que ya no aplica va a "otros".
 */
class ConsultarAnexoInstalaciones
{
    private const SECCIONES = [
        'administrativo' => 'Espacios administrativos',
        'cubiculo' => 'Cubículos',
        'recreativo_deportivo' => 'Actividades físicas y recreativas',
        'especial' => 'Instalaciones especiales',
        'otros' => 'Otros',
    ];

    public function __construct(
        private readonly CatalogosInfraestructura $catalogos,
        private readonly CategoriasSanitariosPorNivel $categoriasSanitarios,
    ) {}

    public function ejecutar(EscuelaNivel $escuelaNivel): AnexoInstalaciones
    {
        $escuelaNivel->loadMissing(['escuela', 'nivelEducativo']);
        $plantel = Plantel::findOrFail($escuelaNivel->escuela->plantel_id);
        $captura = $this->catalogos->edicionCapturada($plantel->id);
        $aulas = AulaNivel::where('escuela_nivel_id', $escuelaNivel->id)->first();

        return new AnexoInstalaciones(
            datosGenerales: [
                'calle' => $plantel->calle,
                'numeroExt' => $plantel->numero_ext,
                'numeroInt' => $plantel->numero_int,
                'colonia' => $plantel->colonia,
                'localidad' => $plantel->localidad,
                'municipio' => $plantel->municipio,
                'codigoPostal' => $plantel->codigo_postal,
                'telefono' => $plantel->telefono,
                'correoElectronico' => $plantel->correo_electronico,
            ],
            estudiosActuales: $this->estudiosActuales($plantel->id),
            serviciosCercanos: ServicioCercano::where('plantel_id', $plantel->id)->orderBy('id')->get()->map(fn ($s) => [
                'nombre' => $s->nombre,
                'tipo' => $s->tipo,
                'esPublico' => $s->es_publico,
                'distanciaValor' => $s->distancia_valor,
                'distanciaUnidad' => $s->distancia_unidad,
            ])->all(),
            metrosPredio: $plantel->metros_totales,
            metrosConstruidos: $plantel->metros_construidos,
            areaCivicaM2: $plantel->area_civica_m2,
            tieneAstaBandera: $plantel->tiene_asta_bandera,
            secciones: $this->secciones($escuelaNivel->nivel_educativo_id, $captura),
            sanitarios: $this->sanitarios($escuelaNivel->nivelEducativo->clave, $captura['sanitarios']),
            aulas: $aulas === null ? null : ['numeroAulas' => $aulas->numero_aulas, 'superficieM2' => $aulas->superficie_m2],
        );
    }

    /** @return list<array{nombre: string, numeroAlumnos: int}> */
    private function estudiosActuales(int $plantelId): array
    {
        return InmuebleEstudioActual::with('nivelEducativo')->where('plantel_id', $plantelId)->orderBy('id')->get()
            ->map(fn ($e) => ['nombre' => $e->nivelEducativo->nombre ?? (string) $e->otro_nivel_texto, 'numeroAlumnos' => $e->numero_alumnos])
            ->all();
    }

    /**
     * @param  array{espacios: array<int, array<string, mixed>>, materiales: array<int, array<string, mixed>>}  $captura
     * @return array<string, array{titulo: string, lineas: list<array<string, mixed>>}>
     */
    private function secciones(int $nivelEducativoId, array $captura): array
    {
        $aplicables = DB::table('niveles_tipos_espacios')->where('nivel_educativo_id', $nivelEducativoId)->pluck('tipo_espacio_id')->map(fn ($id) => (int) $id)->all();
        $materiales = $this->catalogos->materialesBibliotecaDisponibles();

        $porSeccion = [];
        $tipos = TipoEspacio::whereIn('id', [...$aplicables, ...array_keys($captura['espacios'])])->orderBy('categoria')->orderBy('nombre')->get();
        foreach ($tipos as $tipo) {
            $fila = $captura['espacios'][$tipo->id] ?? null;
            $aplica = in_array($tipo->id, $aplicables, true);
            $conFutbol = $fila !== null && $fila['tieneCampoFutbol'];
            $porSeccion[$aplica ? $tipo->categoria : 'otros'][] = [
                'clave' => $tipo->clave,
                'nombre' => $tipo->nombre,
                'capturado' => $fila !== null,
                'cantidad' => $fila['cantidad'] ?? null,
                'superficieM2' => $fila['superficieM2'] ?? null,
                'capacidadPromedio' => $fila['capacidadPromedio'] ?? null,
                'ventilacionNatural' => $fila['ventilacionNatural'] ?? null,
                'iluminacionNatural' => $fila['iluminacionNatural'] ?? null,
                'destinadoA' => $fila['destinadoA'] ?? null,
                'campoFutbol' => $conFutbol ? ['tipoSuperficie' => $fila['campoFutbolTipoSuperficie'], 'formato' => $fila['campoFutbolFormato']] : null,
                'materiales' => $tipo->permite_material_biblioteca ? $materiales->map(fn ($m) => [
                    'clave' => $m->clave,
                    'nombre' => $m->nombre,
                    'numeroTitulos' => $captura['materiales'][(int) $m->id]['numeroTitulos'] ?? null,
                    'numeroVolumenes' => $captura['materiales'][(int) $m->id]['numeroVolumenes'] ?? null,
                ])->all() : [],
            ];
        }

        $secciones = [];
        foreach (self::SECCIONES as $categoria => $titulo) {
            if (isset($porSeccion[$categoria])) {
                $secciones[$categoria === 'cubiculo' ? 'cubiculos' : $categoria] = ['titulo' => $titulo, 'lineas' => $porSeccion[$categoria]];
            }
        }

        return $secciones;
    }

    /**
     * Las categorías del nivel primero (en blanco si no hay fila), luego las capturadas fuera de él.
     *
     * @param  array<string, array<string, mixed>>  $capturados
     * @return list<array<string, mixed>>
     */
    private function sanitarios(string $nivelClave, array $capturados): array
    {
        $categorias = $this->categoriasSanitarios->paraNivel($nivelClave);
        $categorias = [...$categorias, ...array_diff(array_keys($capturados), $categorias)];

        return array_map(fn (string $categoria) => [
            'categoria' => $categoria,
            'capturado' => isset($capturados[$categoria]),
            'cantidadRetretes' => $capturados[$categoria]['cantidadRetretes'] ?? null,
            'cantidadMingitorios' => $capturados[$categoria]['cantidadMingitorios'] ?? null,
            'cantidadLavabos' => $capturados[$categoria]['cantidadLavabos'] ?? null,
            'superficieM2' => $capturados[$categoria]['superficieM2'] ?? null,
            'ventilacionNatural' => $capturados[$categoria]['ventilacionNatural'] ?? null,
            'iluminacionNatural' => $capturados[$categoria]['iluminacionNatural'] ?? null,
            'cantidadBacinicas' => $capturados[$categoria]['cantidadBacinicas'] ?? null,
        ], $categorias);
    }
}
