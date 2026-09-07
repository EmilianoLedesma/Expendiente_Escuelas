@props(['escuelaNivelId' => null])

@php
    // Progress indicator is a read-only display widget, not wizard business
    // logic — no Application use case exists (or is needed) just to list
    // catalog steps and their completion state for rendering.
    $pasos = \Illuminate\Support\Facades\DB::table('pasos_captura')->orderBy('orden')->get();

    $estadosPorPaso = $escuelaNivelId
        ? \Illuminate\Support\Facades\DB::table('escuela_nivel_pasos')
            ->where('escuela_nivel_id', $escuelaNivelId)
            ->pluck('estado', 'paso_captura_id')
        : collect();
@endphp

<ol class="flex flex-wrap items-center gap-sm font-sans text-nav-link">
    @foreach ($pasos as $paso)
        @php
            $estado = $estadosPorPaso->get($paso->id, 'pendiente');
            $dotClass = match ($estado) {
                'completado' => 'bg-success',
                'en_progreso' => 'bg-primary',
                default => 'bg-hairline',
            };
            $textClass = $estado === 'pendiente' ? 'text-muted' : 'text-ink';
        @endphp
        <li class="flex items-center gap-xxs {{ $textClass }}">
            <span class="inline-block w-2 h-2 rounded-full {{ $dotClass }}"></span>
            {{ $paso->nombre }}
        </li>
    @endforeach
</ol>
