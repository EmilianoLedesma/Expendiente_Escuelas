<x-filament-panels::page>
    <x-filament::tabs>
        @foreach ($secciones as $clave => $s)
            <x-filament::tabs.item :active="$seccion === $clave" wire:click="elegir('{{ $clave }}')" :badge="count($s['filas'])">
                {{ $s['titulo'] }}
            </x-filament::tabs.item>
        @endforeach
    </x-filament::tabs>

    <x-filament::section :heading="$actual['titulo']">
        <x-slot name="afterHeader">
            <x-filament::button size="sm" color="gray" icon="heroicon-m-arrow-down-tray" wire:click="descargar('{{ $seccion }}')">Descargar CSV</x-filament::button>
        </x-slot>
        @if ($total === 0)
            <p style="font-size:.9rem;opacity:.6;margin:0">Sin datos por ahora.</p>
        @else
            <div style="overflow-x:auto">
                <table style="width:100%;border-collapse:collapse;font-size:.9rem">
                    <thead>
                        <tr>
                            @foreach ($actual['columnas'] as $i => $columna)
                                <th style="text-align:{{ is_int($filas[0][$i] ?? null) ? 'right' : 'left' }};padding:.4rem .75rem;font-size:.75rem;text-transform:uppercase;letter-spacing:.04em;opacity:.65;border-bottom:2px solid rgba(128,128,128,.3)">{{ $columna }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($filas as $fila)
                            <tr style="@if ($loop->even)background:rgba(128,128,128,.07)@endif">
                                @foreach ($fila as $celda)
                                    <td style="padding:.45rem .75rem;text-align:{{ is_int($celda) ? 'right' : 'left' }};@if (is_int($celda))font-variant-numeric:tabular-nums;font-weight:600;@endif">{{ $celda }}</td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div style="display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:.75rem;margin-top:1rem;font-size:.85rem">
                <span style="opacity:.7">Mostrando {{ $desde }}–{{ $hasta }} de {{ $total }} · Página {{ $pagina }} de {{ $paginas }}</span>
                <div style="display:flex;align-items:center;gap:.5rem">
                    <label style="opacity:.7" for="por-pagina">Por página</label>
                    <select id="por-pagina" wire:model.live="porPagina" style="border-radius:.375rem;padding:.2rem 1.5rem .2rem .5rem;background:transparent;border:1px solid rgba(128,128,128,.4)">
                        @foreach ([10, 25, 50] as $n)
                            <option value="{{ $n }}">{{ $n }}</option>
                        @endforeach
                    </select>
                    <x-filament::button size="sm" color="gray" wire:click="anterior" :disabled="$pagina <= 1">Anterior</x-filament::button>
                    <x-filament::button size="sm" color="gray" wire:click="siguiente" :disabled="$pagina >= $paginas">Siguiente</x-filament::button>
                </div>
            </div>
        @endif
    </x-filament::section>
</x-filament-panels::page>
