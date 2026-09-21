<?php

namespace App\Livewire\Tramite\Paso3;

use App\Application\EscuelaNiveles\MarcarPasoCompletado;
use App\Application\Mobiliario\RegistrarMobiliarioNivel;
use App\Models\EscuelaNivel;
use App\Models\MobiliarioConcepto;
use App\Models\MobiliarioNivel as MobiliarioNivelModel;
use App\Models\Sala;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Presentación pura de PRD §5 Paso 3, sub-paso 3 (Mobiliario).
 * RegistrarMobiliarioNivel es la única escritura (ADR-001); el salto de los
 * niveles distintos de Inicial pasa por MarcarPasoCompletado, nunca escribe
 * escuela_nivel_pasos desde aquí.
 */
#[Layout('layouts.tramite')]
class MobiliarioNivel extends Component
{
    public EscuelaNivel $escuelaNivel;

    /** @var array<int, int|string|null> concepto_id => cantidad declarada */
    public array $cantidades = [];

    public function mount(EscuelaNivel $escuelaNivel, MarcarPasoCompletado $marcarPasoCompletado): void
    {
        $this->escuelaNivel = $escuelaNivel;

        if ($escuelaNivel->nivelEducativo->clave !== 'inicial') {
            // Un paso que no aplica al nivel no está pendiente: está
            // trivialmente satisfecho (spec §4).
            $marcarPasoCompletado->ejecutar($escuelaNivel->id, 'mobiliario');
            $this->redirectRoute('tramite.paso3-proximos-pasos', ['escuelaNivel' => $escuelaNivel->id]);

            return;
        }

        $this->cantidades = MobiliarioNivelModel::where('escuela_nivel_id', $escuelaNivel->id)
            ->pluck('cantidad_declarada', 'concepto_id')
            ->map(fn ($cantidad) => (int) $cantidad)
            ->all();
    }

    public function guardar(RegistrarMobiliarioNivel $registrarMobiliarioNivel): void
    {
        $this->validate($this->reglas());

        $declaradas = [];
        foreach ($this->cantidades as $conceptoId => $cantidad) {
            if ($cantidad === '' || $cantidad === null) {
                continue;
            }
            $declaradas[(int) $conceptoId] = (int) $cantidad;
        }

        if ($declaradas === []) {
            $this->addError('cantidades', 'Declara la cantidad de al menos un concepto de mobiliario.');

            return;
        }

        $registrarMobiliarioNivel->ejecutar($this->escuelaNivel->id, $declaradas);

        $this->redirectRoute('tramite.paso3-proximos-pasos', ['escuelaNivel' => $this->escuelaNivel->id]);
    }

    /** @return array<string, array<int, string>> */
    private function reglas(): array
    {
        return ['cantidades.*' => ['nullable', 'integer', 'min:0', 'max:32767']];
    }

    /**
     * Agrupación de solo lectura para la vista: las 5 salas en orden, y al
     * final los conceptos sin sala (Sala de Usos Múltiples).
     *
     * @return Collection<int, MobiliarioGrupo>
     */
    public function grupos(): Collection
    {
        /** @var Collection<int, MobiliarioConcepto> $vacia */
        $vacia = collect();

        $conceptos = MobiliarioConcepto::orderBy('id')->get()->groupBy('sala_id');

        $grupos = Sala::orderBy('orden')->get()
            ->map(fn (Sala $sala) => new MobiliarioGrupo($sala->nombre, $conceptos->get((string) $sala->id, $vacia)));

        $sinSala = $conceptos->get('', $conceptos->get(null, $vacia));

        if ($sinSala->isNotEmpty()) {
            $grupos->push(new MobiliarioGrupo('Sala de Usos Múltiples', $sinSala));
        }

        return $grupos->values();
    }

    public function render()
    {
        return view('livewire.tramite.paso3.mobiliario-nivel', ['grupos' => $this->grupos()])
            ->layoutData(['escuelaNivelId' => $this->escuelaNivel->id]);
    }
}
