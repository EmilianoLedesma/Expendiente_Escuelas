{{-- Línea de tiempo de un trámite (ConsultarHistorialTramite), dentro del panel lateral de la acción Historial.
     Estilos en línea: el panel de Filament no compila clases de Tailwind de las vistas de la app. --}}
@php
    $colores = ['accion' => '#d97706', 'estado' => '#2563eb', 'aceptado' => '#16a34a', 'rechazado' => '#dc2626', 'asignacion' => '#6b7280'];
    $dias = collect($eventos)->groupBy(fn ($e) => $e['cuando']->format('Y-m-d'));
@endphp
<div style="display:flex;flex-direction:column;gap:1.5rem">
    @forelse ($dias as $dia => $delDia)
        <section>
            <h3 style="margin:0 0 .75rem;font-size:.75rem;font-weight:600;letter-spacing:.05em;text-transform:uppercase;opacity:.6">
                {{ \Illuminate\Support\Carbon::parse($dia)->format('d/m/Y') }}
            </h3>
            <ol style="list-style:none;margin:0;padding:0 0 0 1.25rem;border-left:2px solid rgba(128,128,128,.25)">
                @foreach ($delDia as $e)
                    @php $color = $colores[$e['tipo']] ?? '#6b7280'; @endphp
                    <li style="position:relative;padding:0 0 1.1rem .75rem">
                        <span style="position:absolute;left:calc(-1.25rem - 6px);top:.3rem;width:10px;height:10px;border-radius:9999px;background:{{ $color }};box-shadow:0 0 0 3px rgba(128,128,128,.15)"></span>
                        <div style="display:flex;flex-wrap:wrap;align-items:center;gap:.5rem">
                            <span style="font-size:.9rem;font-weight:600;color:{{ $color }}">{{ $e['que'] }}</span>
                            @if ($e['nivel'])
                                <span style="font-size:.7rem;padding:.05rem .5rem;border:1px solid rgba(128,128,128,.4);border-radius:9999px;opacity:.8">{{ $e['nivel'] }}</span>
                            @endif
                        </div>
                        <div style="margin-top:.15rem;font-size:.75rem;opacity:.65">
                            {{ $e['cuando']->format('H:i') }}@if ($e['quien']) · {{ $e['quien'] }}@endif
                        </div>
                        @if ($e['detalle'])
                            <blockquote style="margin:.5rem 0 0;padding:.4rem .75rem;border-left:3px solid {{ $color }};background:rgba(128,128,128,.1);border-radius:0 .375rem .375rem 0;font-size:.85rem">
                                {{ $e['detalle'] }}
                            </blockquote>
                        @endif
                    </li>
                @endforeach
            </ol>
        </section>
    @empty
        <p style="font-size:.9rem;opacity:.6">Este trámite aún no tiene eventos registrados.</p>
    @endforelse
</div>
