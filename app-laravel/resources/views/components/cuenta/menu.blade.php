{{-- Menú lateral de la cuenta; las opciones dependen del rol. En móvil queda arriba, en escritorio a la izquierda. --}}
@php
    $usuario = auth()->user();
    $opciones = [['Mi perfil', route('profile'), request()->routeIs('profile')]];

    if ($usuario->hasRole('sedeq')) {
        $opciones[] = ['Panel de administración', url('/admin'), false];
    } elseif ($usuario->hasRole('supervisor')) {
        $opciones[] = ['Revisores', route('sedeq.revisores'), request()->routeIs('sedeq.revisores')];
    } elseif ($usuario->hasRole('revisor')) {
        $opciones[] = ['Mis Revisiones', route('sedeq.cola'), request()->routeIs('sedeq.cola')];
    } elseif ($usuario->solicitante !== null) {
        $opciones[] = ['Responsables por nivel', route('tramite.responsables'), request()->routeIs('tramite.responsables')];
    } else {
        $opciones[] = ['Mis niveles', route('tramite.index'), false];
    }
@endphp

<nav aria-label="Mi cuenta" {{ $attributes }}>
    <ul class="flex flex-wrap gap-xs lg:flex-col">
        @foreach ($opciones as [$etiqueta, $url, $activa])
            <li>
                <a href="{{ $url }}" @if ($activa) aria-current="page" @endif
                   @class(['flex min-h-11 items-center rounded-md px-md text-body-md font-semibold transition-colors duration-150', 'bg-surface-soft text-ink' => $activa, 'text-primary hover:bg-surface-soft' => ! $activa])>
                    {{ $etiqueta }}
                </a>
            </li>
        @endforeach
    </ul>
</nav>
