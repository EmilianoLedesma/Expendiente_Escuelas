{{-- Shell compartido por layouts.guest, layouts.tramite y layouts.app (estilo tableros_municipales, 2026-10-06).
     Sin componentes Livewire (ver ShellTest). Datos de la barra: config('sedeq'). --}}
<a href="#contenido" class="sr-only focus:not-sr-only focus:absolute focus:left-md focus:top-md focus:z-50 focus:rounded-md focus:bg-canvas focus:px-md focus:py-xs focus:text-body-md focus:font-semibold focus:text-primary">
    Saltar al contenido
</a>

<header>
    {{-- Barra institucional --}}
    <div class="shell-oscuro bg-brand-blue text-white shadow-sm">
        <div class="mx-auto flex max-w-7xl flex-col items-center lg:flex-row lg:justify-between lg:px-xl">
            <nav aria-label="Enlaces institucionales" class="w-full border-b border-white/20 lg:w-auto lg:border-b-0">
                <ul class="flex flex-wrap justify-center">
                    @foreach (config('sedeq.enlaces_gobierno') as $enlace)
                        <li class="border-r border-white/20 first:border-l lg:first:border-l-0">
                            <a href="{{ $enlace['url'] }}" target="_blank" rel="noopener noreferrer"
                               class="inline-flex min-h-11 items-center px-md text-caption font-medium uppercase tracking-wide text-white transition-colors duration-150 hover:bg-black/10 hover:underline">
                                {{ $enlace['etiqueta'] }}<span class="sr-only"> (abre en una pestaña nueva)</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </nav>

            <x-shell.redes etiquetas class="justify-center" />
        </div>
    </div>

    {{-- Encabezado con logotipo (flujo normal: ni sticky ni fixed) --}}
    <div class="bg-canvas shadow-md">
        <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-x-lg gap-y-xs px-md py-sm sm:px-xl">
            <div class="flex min-w-0 flex-wrap items-center gap-x-md gap-y-xs lg:flex-1">
                <a href="{{ auth()->check() ? route('dashboard') : route('login') }}" class="shrink-0">
                    <img src="{{ asset('img/layout_set_logo.png') }}" alt="SEDEQ - Secretaría de Educación del Estado de Querétaro"
                         width="2359" height="444" class="h-10 w-auto sm:h-[60px]">
                </a>
                @php
                    $rolSedeq = auth()->user()?->hasRole('supervisor') ? 'Supervisor' : (auth()->user()?->hasRole('revisor') ? 'Revisor' : null);
                @endphp
                <p class="border-hairline text-title-md font-semibold leading-tight text-ink sm:border-l sm:pl-md">
                    {{ $rolSedeq ? 'Revisión de incorporación' : 'Trámite de Incorporación de Escuelas Particulares' }}
                </p>
            </div>

            @auth
                {{-- Solo la página actual lleva la barra azul inferior; el resto es texto sin subrayado. --}}
                @php
                    $enMisTramites = request()->routeIs('tramite.index');
                    $enPerfil = request()->routeIs('profile');
                    $pestana = 'inline-flex min-h-11 items-center border-b-[3px] px-sm text-ink transition-colors duration-150 ';
                    $activa = 'border-brand-blue';
                    $inactiva = 'border-transparent hover:border-hairline';
                @endphp
                <nav aria-label="Cuenta" class="flex flex-wrap items-center gap-xs lg:shrink-0 text-body-sm font-semibold uppercase tracking-wide">
                    @php
                        [$inicio, $inicioUrl, $enInicio] = match ($rolSedeq) {
                            'Supervisor' => ['Tablero', route('sedeq.tablero'), request()->routeIs('sedeq.tablero', 'sedeq.tramite')],
                            'Revisor' => ['Mis Revisiones', route('sedeq.cola'), request()->routeIs('sedeq.*')],
                            default => ['Mis trámites', route('tramite.index'), $enMisTramites],
                        };
                    @endphp
                    <a href="{{ $inicioUrl }}" @if ($enInicio) aria-current="page" @endif
                       class="{{ $pestana.($enInicio ? $activa : $inactiva) }}">
                        {{ $inicio }}
                    </a>
                    <span class="h-6 w-px bg-hairline" aria-hidden="true"></span>
                    {{-- Alpine llega con Livewire donde lo hay (perfil); sin él, el nombre queda como texto estático. --}}
                    <a href="{{ route('profile') }}" title="Mi perfil" @if ($enPerfil) aria-current="page" @endif
                       class="{{ $pestana.($enPerfil ? $activa : $inactiva) }} normal-case tracking-normal">
                        <span x-data="{{ json_encode(['name' => auth()->user()->name]) }}" x-text="name"
                              x-on:profile-updated.window="name = $event.detail.name">{{ auth()->user()->name }}</span>
                        <span class="sr-only"> — Mi perfil</span>
                    </a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="{{ $pestana.$inactiva }} cursor-pointer uppercase tracking-wide">
                            Cerrar sesión
                        </button>
                    </form>
                </nav>
            @endauth
        </div>
    </div>
</header>
