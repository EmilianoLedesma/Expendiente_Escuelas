<?php

namespace App\Livewire\Tramite;

use App\Models\EscuelaNivel;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Aterrizaje tras completar los sub-pasos 1-3 de Paso 3. No escribe nada:
 * el paso que lo precede ya marcó su propio avance.
 *
 * `layoutData` es lo que hace que el widget de progreso vea el
 * escuela_nivel_id — Livewire renderiza el layout con los params de
 * PageComponentConfig, no con las propiedades públicas del componente.
 *
 * ADR-005 corrigió que un segundo nivel pierda su propia infraestructura;
 * esta página cierra el hueco de navegación que ADR-005 documentó pero no
 * resolvía — el único camino a un segundo nivel era editar la URL a mano.
 * Enlaza a cada nivel hermano de la misma escuela que aún no completa sus
 * 3 sub-pasos de Paso 3. Lectura pura (igual que Progreso.php: sin caso de
 * uso de Application, ver ADR-001 y la nota de clase de ese componente).
 */
#[Layout('layouts.tramite')]
class Paso3ProximosPasos extends Component
{
    private const PASOS_PASO3 = ['inmueble', 'infraestructura', 'mobiliario'];

    public EscuelaNivel $escuelaNivel;

    public function mount(EscuelaNivel $escuelaNivel): void
    {
        $this->escuelaNivel = $escuelaNivel;
    }

    /** @return Collection<int, object{id: int, nombre: string}&\stdClass> nivel hermanos con Paso 3 aún incompleto. */
    public function nivelesPendientes(): Collection
    {
        return EscuelaNivel::where('escuela_id', $this->escuelaNivel->escuela_id)
            ->where('id', '!=', $this->escuelaNivel->id)
            ->with('nivelEducativo')
            ->get()
            ->reject(fn (EscuelaNivel $nivel) => $this->paso3Completo($nivel->id))
            ->map(fn (EscuelaNivel $nivel) => (object) [
                'id' => $nivel->id,
                'nombre' => $nivel->nivelEducativo->nombre,
            ])
            ->values();
    }

    private function paso3Completo(int $escuelaNivelId): bool
    {
        $completados = DB::table('escuela_nivel_pasos')
            ->join('pasos_captura', 'pasos_captura.id', '=', 'escuela_nivel_pasos.paso_captura_id')
            ->where('escuela_nivel_pasos.escuela_nivel_id', $escuelaNivelId)
            ->where('escuela_nivel_pasos.estado', 'completado')
            ->whereIn('pasos_captura.clave', self::PASOS_PASO3)
            ->count();

        return $completados === count(self::PASOS_PASO3);
    }

    public function render()
    {
        return view('livewire.tramite.paso3-proximos-pasos', [
            'nivelesPendientes' => $this->nivelesPendientes(),
        ])->layoutData(['escuelaNivelId' => $this->escuelaNivel->id]);
    }
}
