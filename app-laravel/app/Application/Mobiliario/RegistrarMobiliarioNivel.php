<?php

namespace App\Application\Mobiliario;

use App\Application\EscuelaNiveles\MarcarPasoCompletado;
use App\Models\EscuelaNivel;
use App\Models\MobiliarioConcepto;
use App\Models\MobiliarioNivel;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Caso de uso de PRD §5 Paso 3, sub-paso 3 (Mobiliario). Puramente
 * escuela_nivel-scoped: no hay excepción por reutilización de plantel, el
 * mobiliario es del nivel, no del inmueble.
 *
 * Solo Educación Inicial en este MVP (decisión de alcance 2026-09-11) — es
 * el único nivel con catálogo de ratios confirmado. El componente Livewire
 * ya redirige los otros niveles antes de mostrar formulario; esta guarda
 * existe para que el caso de uso no dependa de eso.
 */
class RegistrarMobiliarioNivel
{
    public function __construct(private readonly MarcarPasoCompletado $marcarPasoCompletado) {}

    /**
     * @param  array<int, int>  $cantidadesPorConcepto  concepto_id => cantidad_declarada
     */
    public function ejecutar(int $escuelaNivelId, array $cantidadesPorConcepto): void
    {
        if ($cantidadesPorConcepto === []) {
            throw new InvalidArgumentException('Debe declararse al menos un concepto de mobiliario.');
        }

        $escuelaNivel = EscuelaNivel::with('nivelEducativo')->findOrFail($escuelaNivelId);

        if ($escuelaNivel->nivelEducativo->clave !== 'inicial') {
            throw new InvalidArgumentException('La captura de mobiliario aplica únicamente a Educación Inicial en este MVP.');
        }

        $conceptosValidos = MobiliarioConcepto::pluck('id')->all();
        $desconocidos = array_diff(array_keys($cantidadesPorConcepto), $conceptosValidos);

        if ($desconocidos !== []) {
            throw new InvalidArgumentException('Concepto de mobiliario desconocido: '.implode(', ', $desconocidos));
        }

        foreach ($cantidadesPorConcepto as $conceptoId => $cantidad) {
            if ($cantidad < 0) {
                throw new InvalidArgumentException("Cantidad negativa para el concepto {$conceptoId}.");
            }
        }

        DB::transaction(function () use ($escuelaNivelId, $cantidadesPorConcepto) {
            foreach ($cantidadesPorConcepto as $conceptoId => $cantidad) {
                // ponytail: updateOrCreate sobre el UNIQUE(escuela_nivel_id, concepto_id)
                // hace que reenviar el formulario corrija cantidades en vez de fallar.
                MobiliarioNivel::updateOrCreate(
                    ['escuela_nivel_id' => $escuelaNivelId, 'concepto_id' => $conceptoId],
                    ['cantidad_declarada' => $cantidad],
                );
            }

            $this->marcarPasoCompletado->ejecutar($escuelaNivelId, 'mobiliario');
        });
    }
}
