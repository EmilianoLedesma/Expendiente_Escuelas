<?php

namespace App\Application\Infraestructura;

use App\Application\Captura\ReglasCaptura;
use App\Application\EscuelaNiveles\MarcarPasoCompletado;
use App\Application\Excepciones\DatosInvalidos;
use App\Application\Excepciones\PrecondicionIncumplida;
use App\Application\Infraestructura\DTO\DatosInfraestructuraNivel;
use App\Application\Tramite\EstadoPaso2;
use App\Application\Tramite\EstadoPaso3;
use App\Application\Tramite\TramiteEditable;
use App\Application\Validaciones\ConstruirDatosCapacidad;
use App\Models\AulaNivel;
use App\Models\EscuelaNivel;
use App\Models\InstalacionEspacio;
use App\Models\Sanitario;
use Illuminate\Support\Facades\DB;

/**
 * Caso de uso de PRD §5 Paso 3, sub-paso 2 (Infraestructura del nivel).
 *
 * instalaciones_espacios (+ campos_futbol, biblioteca_materiales) y
 * sanitarios (+ sanitarios_bacinicas) están anclados a plantel_id: cada fila
 * se escribe solo la primera vez que *ese* tipo_espacio / categoría de
 * sanitario se captura para el plantel (ADR-005 — guardián por-fila, no un
 * booleano por plantel, porque la aplicabilidad varía por nivel). aulas_nivel
 * es lo único genuinamente por nivel y se escribe siempre. Ambos ámbitos
 * vienen del mismo envío de formulario, así que van en una sola transacción.
 */
class RegistrarInfraestructuraNivel
{
    private const FALTA_SUPERFICIE = 'Captura la superficie: la revisión de capacidad instalada la usa.';

    public function __construct(
        private readonly InfraestructuraYaCapturada $yaCapturada,
        private readonly MarcarPasoCompletado $marcarPasoCompletado,
        private readonly EstadoPaso2 $estadoPaso2,
        private readonly EstadoPaso3 $estadoPaso3,
        private readonly CategoriasSanitariosPorNivel $categoriasSanitariosPorNivel = new CategoriasSanitariosPorNivel,
        private readonly TramiteEditable $tramiteEditable = new TramiteEditable,
    ) {}

    /** @throws PrecondicionIncumplida si Paso 2 no está completo, el sub-paso "infraestructura" aún no es alcanzable o el trámite (o, para espacios y sanitarios nuevos, un trámite del mismo plantel) ya se envió (WS-7a). */
    public function ejecutar(int $plantelId, int $escuelaNivelId, DatosInfraestructuraNivel $datos): void
    {
        $escuelaNivel = EscuelaNivel::with('nivelEducativo')->findOrFail($escuelaNivelId);
        $this->verificarPrecondicion($escuelaNivel);
        $this->validar($plantelId, $escuelaNivel, $datos);

        DB::transaction(function () use ($plantelId, $escuelaNivelId, $datos) {
            // WS-7a: orden escuela_niveles → escuelas; las guardas del plantel y de
            // MarcarPasoCompletado bloquean escuelas después.
            EscuelaNivel::whereKey($escuelaNivelId)->sharedLock()->value('id');

            // ponytail: aulas_nivel no tiene índice único sobre escuela_nivel_id,
            // así que updateOrCreate sobre esa clave es lo que evita filas dobles
            // cuando el solicitante reenvía el formulario del mismo nivel.
            AulaNivel::updateOrCreate(
                ['escuela_nivel_id' => $escuelaNivelId],
                ['numero_aulas' => $datos->numeroAulas, 'superficie_m2' => $datos->superficieAulasM2],
            );

            $this->escribirEspacios($plantelId, $datos);
            $this->escribirSanitarios($plantelId, $datos);

            $this->marcarPasoCompletado->ejecutar($escuelaNivelId, 'infraestructura');
        });
    }

    /** WS-2.4b: un adaptador que llame este caso de uso sin pasar por CompuertaPaso3 no debe poder saltarse el orden del wizard. */
    private function verificarPrecondicion(EscuelaNivel $escuelaNivel): void
    {
        $etapaFaltante = $this->estadoPaso2->etapaFaltante($escuelaNivel->escuela_id);
        if ($etapaFaltante !== null) {
            throw new PrecondicionIncumplida($etapaFaltante, 'Completa el Paso 2 antes de continuar.');
        }

        if (! $this->estadoPaso3->puedeAcceder($escuelaNivel->id, 'infraestructura')) {
            throw new PrecondicionIncumplida('infraestructura', 'Completa los pasos anteriores de Paso 3 antes de continuar.');
        }
    }

    private function escribirEspacios(int $plantelId, DatosInfraestructuraNivel $datos): void
    {
        $yaCapturados = $this->yaCapturada->tiposCapturados($plantelId);

        foreach ($datos->espacios as $espacio) {
            if (in_array($espacio['tipoEspacioId'], $yaCapturados, true)) {
                continue;
            }

            if (! $this->tieneDatosSignificativos($espacio)) {
                continue;
            }

            // WS-7a §5.4: los espacios son del plantel y alimentan la capacidad de cualquier trámite enviado en él.
            $this->tramiteEditable->asegurarPlantel($plantelId);

            $fila = InstalacionEspacio::create([
                'plantel_id' => $plantelId,
                'tipo_espacio_id' => $espacio['tipoEspacioId'],
                'cantidad' => $espacio['cantidad'],
                'superficie_m2' => $espacio['superficieM2'],
                'capacidad_promedio' => $espacio['capacidadPromedio'],
                'ventilacion_natural' => $espacio['ventilacionNatural'],
                'iluminacion_natural' => $espacio['iluminacionNatural'],
                'destinado_a' => $espacio['destinadoA'],
            ]);

            if ($espacio['campoFutbol'] !== null) {
                DB::table('campos_futbol')->insert([
                    'instalacion_espacio_id' => $fila->id,
                    'tipo_superficie' => $espacio['campoFutbol']['tipoSuperficie'],
                    'formato' => $espacio['campoFutbol']['formato'],
                ]);
            }

            foreach ($espacio['materialesBiblioteca'] as $material) {
                DB::table('biblioteca_materiales')->insert([
                    'instalacion_espacio_id' => $fila->id,
                    'tipo_material_id' => $material['tipoMaterialId'],
                    'numero_titulos' => $material['numeroTitulos'],
                    'numero_volumenes' => $material['numeroVolumenes'],
                ]);
            }
        }
    }

    /**
     * Única fuente de verdad de "¿este espacio trae dato?" (WS-2 item 1): el
     * componente Livewire ya no pre-filtra por su cuenta — construye la
     * entrada para todo tipo aplicable y delega la decisión de persistir
     * aquí, para que no puedan divergir dos copias de la misma regla. Un
     * booleano solo cuenta como dato cuando es `true`, y campoFutbol solo
     * cuenta si trae tipoSuperficie o formato — de lo contrario un caller de
     * API podría crear filas vacías, y por ADR-005 ese tipo quedaría
     * "capturado" para siempre sin que el solicitante haya declarado nada.
     *
     * @param  array{cantidad: int|null, superficieM2: float|null, capacidadPromedio: int|null, ventilacionNatural: bool|null, iluminacionNatural: bool|null, destinadoA: string|null, campoFutbol: array{tipoSuperficie: string|null, formato: string|null}|null, materialesBiblioteca: list<array<string, mixed>>}  $espacio
     */
    private function tieneDatosSignificativos(array $espacio): bool
    {
        return $espacio['cantidad'] !== null
            || $espacio['superficieM2'] !== null
            || $espacio['capacidadPromedio'] !== null
            || $espacio['destinadoA'] !== null
            || $espacio['ventilacionNatural'] === true
            || $espacio['iluminacionNatural'] === true
            || $espacio['materialesBiblioteca'] !== []
            || ($espacio['campoFutbol'] !== null
                && ($espacio['campoFutbol']['tipoSuperficie'] !== null || $espacio['campoFutbol']['formato'] !== null));
    }

    /**
     * Minor 8 — mismo espíritu que tieneDatosSignificativos() para espacios:
     * un sanitario todo-null (booleanos solo cuentan si son `true`) no debe
     * crear fila, para que un caller de API que no filtre como el
     * componente no marque una categoría como "capturada" (ADR-005) sin
     * datos reales.
     *
     * @param  array{cantidadRetretes: int|null, cantidadMingitorios: int|null, cantidadLavabos: int|null, superficieM2: float|null, ventilacionNatural: bool|null, iluminacionNatural: bool|null, cantidadBacinicas: int|null}  $sanitario
     */
    private function tieneDatosSignificativosSanitario(array $sanitario): bool
    {
        return $sanitario['cantidadRetretes'] !== null
            || $sanitario['cantidadMingitorios'] !== null
            || $sanitario['cantidadLavabos'] !== null
            || $sanitario['superficieM2'] !== null
            || $sanitario['cantidadBacinicas'] !== null
            || $sanitario['ventilacionNatural'] === true
            || $sanitario['iluminacionNatural'] === true;
    }

    private function escribirSanitarios(int $plantelId, DatosInfraestructuraNivel $datos): void
    {
        $yaCapturadas = $this->yaCapturada->categoriasCapturadas($plantelId);

        foreach ($datos->sanitarios as $sanitario) {
            if (in_array($sanitario['categoria'], $yaCapturadas, true)) {
                continue;
            }

            if (! $this->tieneDatosSignificativosSanitario($sanitario)) {
                continue;
            }

            $this->tramiteEditable->asegurarPlantel($plantelId);

            $fila = Sanitario::create([
                'plantel_id' => $plantelId,
                'categoria' => $sanitario['categoria'],
                'cantidad_retretes' => $sanitario['cantidadRetretes'],
                'cantidad_mingitorios' => $sanitario['cantidadMingitorios'],
                'cantidad_lavabos' => $sanitario['cantidadLavabos'],
                'superficie_m2' => $sanitario['superficieM2'],
                'ventilacion_natural' => $sanitario['ventilacionNatural'],
                'iluminacion_natural' => $sanitario['iluminacionNatural'],
            ]);

            if ($sanitario['cantidadBacinicas'] !== null) {
                DB::table('sanitarios_bacinicas')->insert([
                    'sanitario_id' => $fila->id,
                    'cantidad_bacinicas' => $sanitario['cantidadBacinicas'],
                ]);
            }
        }
    }

    /**
     * Invariantes de entrada (WS-2.4a): un caller que se salte el formulario
     * (o un futuro adaptador de API) no debe poder escribir un tipo_espacio o
     * una categoría de sanitario que no aplica al nivel, ni un número
     * negativo. Las claves de $errores usan la misma ruta que las propiedades
     * Livewire (espacios.{tipoEspacioId}.*, sanitarios.{categoria}.*,
     * materialesBiblioteca.{tipoMaterialId}.*) para que el componente pueda
     * mapearlas 1:1 con addError().
     *
     * Un espacio/sanitario que el plantel ya capturó (en este nivel o en
     * otro) se salta aquí igual que en escribirEspacios()/escribirSanitarios():
     * no se va a escribir, así que no tiene sentido revalidar su
     * aplicabilidad contra el nivel en curso — es exactamente el escenario de
     * ADR-005 (unión por nivel) y un mismo tipo puede aplicar a un nivel y no
     * a otro.
     */
    private function validar(int $plantelId, EscuelaNivel $escuelaNivel, DatosInfraestructuraNivel $datos): void
    {
        $errores = [];

        // Además de "no negativo", el rango de la columna (SMALLINT, NUMERIC(10,2),
        // NUMERIC(8,2), INTEGER): fuera de él sería un error de base de datos.
        $fueraDeRango = fn (int|float|null $valor, int|float $maximo): bool => $valor !== null && $valor > $maximo;

        if ($datos->numeroAulas < 0) {
            $errores['numeroAulas'] = 'El número de aulas no puede ser negativo.';
        } elseif ($fueraDeRango($datos->numeroAulas, ReglasCaptura::MAX_SMALLINT)) {
            $errores['numeroAulas'] = 'El número de aulas no debe ser mayor que '.ReglasCaptura::MAX_SMALLINT.'.';
        }

        if ($datos->superficieAulasM2 === null) {
            // WS-7a §3.1: alimenta las reglas de capacidad *.superficie.aula(s).
            $errores['superficieAulasM2'] = 'Captura la superficie total de las aulas.';
        } elseif ($datos->superficieAulasM2 < 0) {
            $errores['superficieAulasM2'] = 'La superficie de aulas no puede ser negativa.';
        } elseif ($fueraDeRango($datos->superficieAulasM2, ReglasCaptura::MAX_NUMERIC_10_2)) {
            $errores['superficieAulasM2'] = 'La superficie de aulas no debe ser mayor que '.ReglasCaptura::MAX_NUMERIC_10_2.'.';
        }

        // WS-7a §3.1: espacios y sanitarios se escriben una sola vez (ADR-005). Si el dato
        // que lee una regla de capacidad queda vacío, la regla queda "falta capturar" para
        // siempre y bloquearía el envío sin forma de corregirlo: se exige solo en lo que
        // de verdad se va a escribir.
        $tiposConSuperficie = DB::table('tipos_espacios')
            ->where('categoria', ConstruirDatosCapacidad::CATEGORIA_RECREATIVA)
            ->orWhere('clave', ConstruirDatosCapacidad::CLAVE_USOS_MULTIPLES)
            ->pluck('id')
            ->all();
        $tiposBiblioteca = DB::table('tipos_espacios')->where('permite_material_biblioteca', true)->pluck('id')->all();

        $tiposAplicables = DB::table('niveles_tipos_espacios')
            ->where('nivel_educativo_id', $escuelaNivel->nivel_educativo_id)
            ->pluck('tipo_espacio_id')
            ->all();
        $tiposYaCapturados = $this->yaCapturada->tiposCapturados($plantelId);

        foreach ($datos->espacios as $espacio) {
            if (in_array($espacio['tipoEspacioId'], $tiposYaCapturados, true)) {
                continue;
            }

            $prefijo = "espacios.{$espacio['tipoEspacioId']}";

            if (! in_array($espacio['tipoEspacioId'], $tiposAplicables, true)) {
                $errores["{$prefijo}.tipoEspacioId"] = 'Este tipo de espacio no aplica al nivel educativo.';

                continue;
            }

            foreach (['cantidad' => ReglasCaptura::MAX_SMALLINT, 'superficieM2' => ReglasCaptura::MAX_NUMERIC_10_2, 'capacidadPromedio' => ReglasCaptura::MAX_SMALLINT] as $campo => $maximo) {
                if ($espacio[$campo] !== null && $espacio[$campo] < 0) {
                    $errores["{$prefijo}.{$campo}"] = 'No puede ser negativo.';
                } elseif ($fueraDeRango($espacio[$campo], $maximo)) {
                    $errores["{$prefijo}.{$campo}"] = "No debe ser mayor que {$maximo}.";
                }
            }

            if ($this->tieneDatosSignificativos($espacio)) {
                if ($espacio['superficieM2'] === null && in_array($espacio['tipoEspacioId'], $tiposConSuperficie, true)) {
                    $errores["{$prefijo}.superficieM2"] = self::FALTA_SUPERFICIE;
                }

                if ($espacio['materialesBiblioteca'] === [] && in_array($espacio['tipoEspacioId'], $tiposBiblioteca, true)) {
                    $errores['materialesBiblioteca'] = 'Captura el acervo de la biblioteca: al menos un tipo de material con su número de títulos.';
                }
            }

            foreach ($espacio['materialesBiblioteca'] as $material) {
                $prefijoMaterial = "materialesBiblioteca.{$material['tipoMaterialId']}";

                if ($material['numeroTitulos'] === null) {
                    $errores["{$prefijoMaterial}.numeroTitulos"] = 'Captura el número de títulos.';
                } elseif ($material['numeroTitulos'] < 0) {
                    $errores["{$prefijoMaterial}.numeroTitulos"] = 'No puede ser negativo.';
                } elseif ($fueraDeRango($material['numeroTitulos'], ReglasCaptura::MAX_INTEGER)) {
                    $errores["{$prefijoMaterial}.numeroTitulos"] = 'No debe ser mayor que '.ReglasCaptura::MAX_INTEGER.'.';
                }

                if ($material['numeroVolumenes'] !== null && $material['numeroVolumenes'] < 0) {
                    $errores["{$prefijoMaterial}.numeroVolumenes"] = 'No puede ser negativo.';
                } elseif ($fueraDeRango($material['numeroVolumenes'], ReglasCaptura::MAX_INTEGER)) {
                    $errores["{$prefijoMaterial}.numeroVolumenes"] = 'No debe ser mayor que '.ReglasCaptura::MAX_INTEGER.'.';
                }
            }
        }

        $categoriasAplicables = $this->categoriasSanitariosPorNivel->paraNivel($escuelaNivel->nivelEducativo->clave);
        $categoriasYaCapturadas = $this->yaCapturada->categoriasCapturadas($plantelId);

        foreach ($datos->sanitarios as $sanitario) {
            if (in_array($sanitario['categoria'], $categoriasYaCapturadas, true)) {
                continue;
            }

            $prefijo = "sanitarios.{$sanitario['categoria']}";

            if (! in_array($sanitario['categoria'], $categoriasAplicables, true)) {
                $errores["{$prefijo}.categoria"] = 'Esta categoría de sanitario no aplica al nivel educativo.';

                continue;
            }

            foreach (['cantidadRetretes', 'cantidadMingitorios', 'cantidadLavabos', 'cantidadBacinicas'] as $campo) {
                if ($sanitario[$campo] !== null && $sanitario[$campo] < 0) {
                    $errores["{$prefijo}.{$campo}"] = 'No puede ser negativo.';
                } elseif ($fueraDeRango($sanitario[$campo], ReglasCaptura::MAX_SMALLINT)) {
                    $errores["{$prefijo}.{$campo}"] = 'No debe ser mayor que '.ReglasCaptura::MAX_SMALLINT.'.';
                }
            }

            if ($sanitario['superficieM2'] !== null && $sanitario['superficieM2'] < 0) {
                $errores["{$prefijo}.superficieM2"] = 'No puede ser negativo.';
            } elseif ($fueraDeRango($sanitario['superficieM2'], ReglasCaptura::MAX_NUMERIC_8_2)) {
                $errores["{$prefijo}.superficieM2"] = 'No debe ser mayor que '.ReglasCaptura::MAX_NUMERIC_8_2.'.';
            }

            if ($sanitario['superficieM2'] === null
                && str_starts_with($sanitario['categoria'], ConstruirDatosCapacidad::PREFIJO_SANITARIOS_ALUMNOS)
                && $this->tieneDatosSignificativosSanitario($sanitario)) {
                $errores["{$prefijo}.superficieM2"] = self::FALTA_SUPERFICIE;
            }
        }

        if ($errores !== []) {
            throw new DatosInvalidos($errores);
        }
    }
}
