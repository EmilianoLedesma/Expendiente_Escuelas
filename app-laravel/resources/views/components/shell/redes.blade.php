@props(['etiquetas' => false, 'icono' => 'h-5 w-5'])

{{-- Redes y teléfono de config('sedeq.redes'): la barra institucional (con etiqueta visible en lg) y el pie usan esta misma lista. --}}
<ul {{ $attributes->merge(['class' => 'flex flex-wrap items-center']) }}>
    @foreach (config('sedeq.redes') as $red)
        @php($externo = str_starts_with($red['url'], 'http'))
        <li>
            <a href="{{ $red['url'] }}" @if ($externo) target="_blank" rel="noopener noreferrer" @endif
               class="inline-flex min-h-11 min-w-11 items-center justify-center gap-xs px-xs text-white transition-colors duration-150 hover:bg-black/10">
                <x-ui.icon :nombre="$red['icono']" :class="$icono" />
                @if ($etiquetas)
                    <span class="hidden text-caption font-semibold uppercase tracking-wide lg:inline" aria-hidden="true">{{ $red['etiqueta'] }}</span>
                @endif
                <span class="sr-only">{{ $red['nombre'].($externo ? ' (abre en una pestaña nueva)' : '') }}</span>
            </a>
        </li>
    @endforeach
</ul>
