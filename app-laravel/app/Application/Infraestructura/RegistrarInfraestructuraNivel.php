<?php

namespace App\Application\Infraestructura;

use App\Application\Captura\ReglasCaptura;
use App\Application\EscuelaNiveles\MarcarPasoCompletado;
use App\Application\Excepciones\DatosInvalidos;
use App\Application\Excepciones\PrecondicionIncumplida;
use App\Application\Infraestructura\DTO\DatosInfraestructuraNivel;
use App\Application\Tramite\EstadoPaso2;
use App\Application\Tramite\EstadoPaso3;
use App\Application\Tramite\RegistroCompartidoBloqueado;
use App\Application\Tramite\TramiteEditable;
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
    public function __construct(
        private readonly InfraestructuraYaCapturada $yaCapturada,
        private readonly MarcarPasoCompletado $marcarPasoCompletado,
        private readonly EstadoPaso2 $estadoPaso2,
        private readonly EstadoPaso3 $estadoPaso3,
        private readonly CategoriasSanitariosPorNivel $categoriasSanitariosPorNivel = new CategoriasSanitariosPorNivel,
        private readonly TramiteEditable $tramiteEditable = new TramiteEditable,
        private readonly RegistroCompartidoBloqueado $registroCompartido = new RegistroCompartidoBloqueado,
    ) {}

    /** @throws PrecondicionIncumplida si Paso 2 no está completo, el sub-paso "infraestructura" aún no es alcanzable o el trámite (o, para espacios y sanitarios nuevos, un trámite del mismo plantel) ya se envió (WS-7a). */
    public function ejecutar(int $plantelId, int $escuelaNivelId, DatosInfraestructuraNivel $datos): void
    {
        $escuelaNivel = EscuelaNivel::with('nivelEducativo')->findOrFail($escuelaNivelId);
        $this->verificarPrecondicion($escuelaNivel);
        $this->validar($plantelId, $escuelaNivel, $datos);

        $escuelaId = $escuelaNivel->escuela_id;

        DB::transaction(function () use ($plantelId, $escuelaId, $escuelaNivelId, $datos) {
            // WS-7b-2: bloqueo de todos los niveles del plantel, primero. Orden
            // escuela_niveles → escuelas; las guardas del plantel y de
            // MarcarPasoCompletado bloquean escuelas después.
            $this->registroCompartido->bloquearNiveles($plantelId);

            // ponytail: aulas_nivel no tiene índice único sobre escuela_nivel_id,
            // así que updateOrCreate sobre esa clave es lo que evita filas dobles
            // cuando el solicitante reenvía el formulario del mismo nivel.
            AulaNivel::updateOrCreate(
                ['escuela_nivel_id' => $escuelaNivelId],
                ['numero_aulas' => $datos->numeroAulas, 'superficie_m2' => $datos->superficieAulasM2],
            );

            $this->escribirEspacios($plantelId, $escuelaId, $escuelaNivelId, $datos);
            $this->escribirSanitarios($plantelId, $escuelaId, $escuelaNivelId, $datos);

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

    private function escribirEspacios(int $plantelId, int $escuelaId, int $escuelaNivelId, DatosInfraestructuraNivel $datos): void
    {
        $yaCapturados = $this->yaCapturada->tiposCapturados($plantelId);

        foreach ($datos->espacios as $espacio) {
            if (in_array($espacio['tipoEspacioId'], $yaCapturados, true)) {
                continue;
            }

            if (! FilaSignificativa::espacio($espacio)) {
                continue;
            }

            // WS-7a §5.4: los espacios son del plantel y alimentan la capacidad de cualquier trámite enviado en él.
            $this->tramiteEditable->asegurarPlantel($plantelId, 'infraestructura', $escuelaId, $escuelaNivelId);

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

    private function escribirSanitarios(int $plantelId, int $escuelaId, int $escuelaNivelId, DatosInfraestructuraNivel $datos): void
    {
        $yaCapturadas = $this->yaCapturada->categoriasCapturadas($plantelId);

        foreach ($datos->sanitarios as $sanitario) {
            if (in_array($sanitario['categoria'], $yaCapturadas, true)) {
                continue;
            }

            if (! FilaSignificativa::sanitario($sanitario)) {
                continue;
            }

            $this->tramiteEditable->asegurarPlantel($plantelId, 'infraestructura', $escuelaId, $escuelaNivelId);

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

        $fueraDeRango = ReglasFilaInfraestructura::fueraDeRango(...);

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
        $reglas = ReglasFilaInfraestructura::cargar();

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

            $errores += $reglas->espacio($espacio, $prefijo, 'materialesBiblioteca');
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

            $errores += $reglas->sanitario($sanitario, $prefijo);
        }

        if ($errores !== []) {
            throw new DatosInvalidos($errores);
        }
    }
}
