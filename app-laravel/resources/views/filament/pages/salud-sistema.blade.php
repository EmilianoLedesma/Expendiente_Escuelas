<x-filament-panels::page>
    @php $colores = ['ok' => 'success', 'aviso' => 'warning', 'error' => 'danger']; @endphp
    <x-filament::section>
        @foreach ($chequeos as $c)
            <div style="padding:.9rem 0;@if (! $loop->last)border-bottom:1px solid rgba(128,128,128,.2)@endif">
                <div style="display:flex;align-items:center;justify-content:space-between;gap:1rem">
                    <div>
                        <div style="font-weight:600">{{ $c['nombre'] }}</div>
                        <div style="font-size:.85rem;opacity:.7">{{ $c['detalle'] }}</div>
                    </div>
                    <x-filament::badge :color="$colores[$c['estado']] ?? 'gray'">{{ strtoupper($c['estado']) }}</x-filament::badge>
                </div>
                @if ($c['siguiente'])
                    <div style="margin-top:.5rem;padding:.4rem .75rem;border-left:3px solid {{ $c['estado'] === 'error' ? '#dc2626' : '#d97706' }};background:rgba(128,128,128,.1);border-radius:0 .375rem .375rem 0;font-size:.85rem">
                        <strong>Qué hacer:</strong> {{ $c['siguiente'] }}
                    </div>
                @endif
                @if (! empty($acciones[$c['nombre']]))
                    <div style="margin-top:.6rem;display:flex;flex-wrap:wrap;gap:.5rem">
                        @foreach ($acciones[$c['nombre']] as $accion)
                            {{ $this->{$accion.'Action'} }}
                        @endforeach
                    </div>
                @endif
            </div>
        @endforeach
    </x-filament::section>
    <div>
        <x-filament::button wire:click="$refresh" color="gray">Volver a revisar</x-filament::button>
    </div>
    <x-filament-actions::modals />
</x-filament-panels::page>
