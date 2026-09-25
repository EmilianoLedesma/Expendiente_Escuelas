{{-- Shell compartido por layouts.tramite y layouts.guest (Dirección B). Sin componentes Livewire (ver ShellTest). --}}
<header class="border-b border-hairline bg-canvas">
    <a href="#contenido" class="sr-only focus:not-sr-only focus:absolute focus:left-md focus:top-md focus:z-50 focus:rounded-md focus:bg-canvas focus:px-md focus:py-xs focus:text-body-md focus:font-semibold focus:text-primary">
        Saltar al contenido
    </a>

    <div class="h-1 bg-gradient-to-r from-primary via-primary to-brand-accent" aria-hidden="true"></div>

    <div class="mx-auto flex min-h-16 max-w-7xl flex-wrap items-center justify-between gap-x-md gap-y-xs px-md py-xs sm:px-xl">
        {{-- Espacio para el logotipo oficial: pendiente de que SEDEQ entregue los archivos. No inventar uno (tampoco un monograma). --}}
        <div class="min-w-0 leading-tight">
            <p class="text-body-sm font-semibold text-ink">Trámite de Incorporación de Escuelas Particulares</p>
            <p class="text-caption text-muted">SEDEQ · Secretaría de Educación del Estado de Querétaro</p>
        </div>

        @auth
            @php($enMisTramites = request()->routeIs('tramite.index'))
            <nav aria-label="Cuenta" class="flex flex-wrap items-center gap-xs text-body-sm">
                <a href="{{ route('tramite.index') }}" @if ($enMisTramites) aria-current="page" @endif
                   @class(['inline-flex min-h-11 items-center border-b-2 px-sm font-semibold transition-colors duration-150', 'border-primary text-ink' => $enMisTramites, 'border-transparent text-muted hover:text-ink' => ! $enMisTramites])>
                    Mis trámites
                </a>
                <span class="h-6 w-px bg-hairline" aria-hidden="true"></span>
                <span class="hidden px-sm text-muted sm:inline">{{ auth()->user()->name }}</span>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="inline-flex min-h-11 items-center px-sm font-semibold text-primary underline underline-offset-4 hover:no-underline">
                        Cerrar sesión
                    </button>
                </form>
            </nav>
        @endauth
    </div>
</header>
