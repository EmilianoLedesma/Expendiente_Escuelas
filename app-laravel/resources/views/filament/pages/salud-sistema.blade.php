<x-filament-panels::page>
    @php $colores = ['ok' => 'success', 'aviso' => 'warning', 'error' => 'danger']; @endphp
    <x-filament::section>
        @foreach ($chequeos as $c)
            <div style="display:flex;align-items:center;justify-content:space-between;gap:1rem;padding:.75rem 0;@if (! $loop->last)border-bottom:1px solid rgba(128,128,128,.2)@endif">
                <div>
                    <div style="font-weight:600">{{ $c['nombre'] }}</div>
                    <div style="font-size:.85rem;opacity:.7">{{ $c['detalle'] }}</div>
                </div>
                <x-filament::badge :color="$colores[$c['estado']] ?? 'gray'">{{ strtoupper($c['estado']) }}</x-filament::badge>
            </div>
        @endforeach
    </x-filament::section>
    <div>
        <x-filament::button wire:click="$refresh" color="gray">Volver a revisar</x-filament::button>
    </div>
</x-filament-panels::page>
