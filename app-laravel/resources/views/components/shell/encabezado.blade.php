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
            <div class="flex min-w-0 flex-wrap items-center gap-x-md gap-y-xs">
                <a href="{{ auth()->check() ? route('dashboard') : route('login') }}" class="shrink-0">
                    <img src="{{ asset('img/layout_set_logo.png') }}" alt="SEDEQ - Secretaría de Educación del Estado de Querétaro"
                         width="2359" height="444" class="h-10 w-auto sm:h-[60px]">
                </a>
                <p class="border-hairline text-body-sm font-semibold leading-tight text-ink sm:border-l sm:pl-md">
                    Trámite de Incorporación de Escuelas Particulares
                </p>
            </div>

            @auth
                @php($enMisTramites = request()->routeIs('tramite.index'))
                <nav aria-label="Cuenta" class="flex flex-wrap items-center gap-xs text-body-sm font-semibold uppercase tracking-wide">
                    <a href="{{ route('tramite.index') }}" @if ($enMisTramites) aria-current="page" @endif
                       @class(['inline-flex min-h-11 items-center border-b-[3px] px-sm transition-colors duration-150', 'border-brand-blue text-ink' => $enMisTramites, 'border-transparent text-muted hover:border-hairline hover:text-ink' => ! $enMisTramites])>
                        Mis trámites
                    </a>
                    <span class="h-6 w-px bg-hairline" aria-hidden="true"></span>
                    {{-- Alpine llega con Livewire donde lo hay (perfil); sin él, el nombre queda como texto estático. --}}
                    <span class="hidden px-sm normal-case tracking-normal text-muted sm:inline"
                          x-data="{{ json_encode(['name' => auth()->user()->name]) }}" x-text="name"
                          x-on:profile-updated.window="name = $event.detail.name">{{ auth()->user()->name }}</span>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="inline-flex min-h-11 items-center px-sm uppercase tracking-wide text-primary underline underline-offset-4 hover:no-underline">
                            Cerrar sesión
                        </button>
                    </form>
                </nav>
            @endauth
        </div>
    </div>
</header>
