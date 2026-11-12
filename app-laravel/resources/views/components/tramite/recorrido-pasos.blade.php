{{-- Lista del recorrido a partir de ResumenTramiteDTO. Estados: ver spec §3.3. --}}
@props(['resumen', 'seccionActual' => null, 'escuelaNivelId' => null, 'proyecto' => [], 'subseccionActual' => null])

@php
    $grupos = [[null, null, 'Datos generales', $resumen->generales]];
    foreach ($resumen->niveles as $nivel) {
        $grupos[] = [$nivel->escuelaNivelId, $nivel->clave, $nivel->nombre, $nivel->secciones];
    }
    $enResumen = $seccionActual === 'resumen';
@endphp

<nav aria-label="Secciones del trámite" {{ $attributes }}>
    <a href="{{ route('tramite.resumen', ['escuela' => $resumen->escuelaId]) }}" @if ($enResumen) aria-current="page" @endif
       @class(['flex min-h-11 items-center rounded-md px-sm text-body-sm font-semibold', 'bg-surface-card text-ink' => $enResumen, 'text-primary' => ! $enResumen])>
        Resumen del trámite
    </a>

    @foreach ($grupos as [$nivelId, $nivelClave, $titulo, $secciones])
        @continue($secciones === [])
        <p class="mb-sm mt-lg flex items-center gap-xs text-section-eyebrow font-semibold uppercase text-muted" aria-hidden="true">
            @if ($nivelClave)
                <x-tramite.nivel :clave="$nivelClave" como="marca" />
            @endif
            {{ $titulo }}
        </p>
        <ol class="space-y-xxs" aria-label="{{ $titulo }}">
            @foreach ($secciones as $seccion)
                @php
                    $esActual = $seccion->clave === $seccionActual && $nivelId === $escuelaNivelId;
                    $hecho = $seccion->estado === 'completado';
                    $inactivo = in_array($seccion->estado, ['no_disponible', 'no_aplica'], true);
                    $bloqueado = $seccion->motivoBloqueo !== null;
                    $nota = match (true) {
                        $esActual => 'Aquí estás',
                        $inactivo => $seccion->estado === 'no_aplica' ? 'No aplica para este nivel' : 'No disponible aún',
                        $bloqueado => $seccion->motivoBloqueo,
                        $seccion->estado === 'en_curso' => 'En curso',
                        default => null,
                    };
                    $etiqueta = $seccion->href ? 'a' : 'span';
                @endphp
                <li class="relative">
                    @unless ($loop->last)
                        <span @class(['absolute -bottom-xxs left-[15px] top-[38px] w-0.5', 'bg-success-ink' => $hecho, 'bg-hairline' => ! $hecho]) aria-hidden="true"></span>
                    @endunless
                    <{{ $etiqueta }}
                        @if ($seccion->href) href="{{ $seccion->href }}" @endif
                        @if ($esActual) aria-current="step" @endif
                        @class(['relative flex min-h-11 items-center gap-sm rounded-md py-xxs pr-xs', 'bg-surface-card' => $esActual, 'hover:bg-surface-soft' => $seccion->href && ! $esActual])
                    >
                        <span @class([
                            'relative z-10 grid h-8 w-8 shrink-0 place-items-center rounded-full text-caption font-semibold',
                            'bg-success-ink text-on-dark' => $hecho,
                            'bg-primary text-on-primary ring-4 ring-brand-accent/25' => $esActual && ! $hecho,
                            'border-2 border-dashed border-hairline bg-canvas' => $inactivo,
                            'border-2 border-hairline bg-surface-card text-muted' => $bloqueado && ! $hecho && ! $esActual,
                            'border-2 border-primary bg-canvas text-primary' => ! $hecho && ! $esActual && ! $inactivo && ! $bloqueado,
                        ]) aria-hidden="true">
                            @if ($hecho)
                                <x-ui.icon nombre="check" class="h-4 w-4" />
                            @elseif (! $inactivo)
                                {{ $seccion->paso }}
                            @endif
                        </span>
                        <span class="min-w-0 text-body-sm">
                            <span @class(['block', 'font-semibold text-ink' => $esActual, 'text-ink' => ! $esActual && ! $inactivo && ! $bloqueado, 'text-muted' => $inactivo || $bloqueado])>{{ $seccion->nombre }}</span>
                            @if ($hecho)
                                <span class="sr-only">(completado)</span>
                            @endif
                            @if ($nota)
                                <span @class(['block text-caption', 'font-semibold text-primary' => $esActual, 'text-muted' => ! $esActual])>{{ $nota }}</span>
                            @endif
                        </span>
                    </{{ $etiqueta }}>
                    @if ($seccion->clave === 'proyecto_educativo' && isset($proyecto[$nivelId]))
                        {{-- Las 20 secciones del proyecto educativo, plegables; abierto solo estando dentro del paso. --}}
                        <details data-subsecciones @if ($esActual)open @endif class="ml-10 mt-xxs">
                            <summary class="flex min-h-9 cursor-pointer items-center text-caption font-semibold text-primary">
                                Secciones ({{ count(array_filter($proyecto[$nivelId])) }} de {{ count($proyecto[$nivelId]) }})
                            </summary>
                            <ol class="mt-xxs space-y-xxs border-l border-hairline pl-sm" aria-label="Secciones del proyecto educativo">
                                @foreach (\App\Application\ProyectoEducativo\SeccionesProyectoEducativo::todas() as $slug => $nombreSub)
                                    @php
                                        $subActual = $esActual && $slug === $subseccionActual;
                                        $subHecha = $proyecto[$nivelId][$slug] ?? false;
                                        $subEtiqueta = $seccion->href ? 'a' : 'span';
                                    @endphp
                                    <li>
                                        <{{ $subEtiqueta }}
                                            @if ($seccion->href) href="{{ route('tramite.paso3-proyecto-educativo-seccion', ['escuelaNivel' => $nivelId, 'seccion' => $slug]) }}" @endif
                                            @if ($subActual) aria-current="page" data-subseccion-actual @endif
                                            @class(['flex min-h-9 items-start gap-xs rounded-md py-xxs pr-xs text-caption', 'bg-surface-card font-semibold text-ink' => $subActual, 'text-ink hover:bg-surface-soft' => ! $subActual && $seccion->href, 'text-muted' => ! $seccion->href])
                                        >
                                            {{-- Completada: palomita verde pequeña en lugar del número; pendiente: número tenue. --}}
                                            @if ($subHecha)
                                                <span data-subseccion-hecha class="grid w-5 shrink-0 place-items-center pt-0.5 text-success-ink" aria-hidden="true">
                                                    <x-ui.icon nombre="check" class="h-3.5 w-3.5" />
                                                </span>
                                                <span class="min-w-0 flex-1">{{ $nombreSub }}<span class="sr-only"> (completada)</span></span>
                                            @else
                                                <span class="w-5 shrink-0 text-right tabular-nums text-muted/70" aria-hidden="true">{{ $loop->iteration }}</span>
                                                <span class="min-w-0 flex-1">{{ $nombreSub }}</span>
                                            @endif
                                        </{{ $subEtiqueta }}>
                                    </li>
                                @endforeach
                            </ol>
                        </details>
                    @endif
                </li>
            @endforeach
        </ol>
    @endforeach
</nav>
