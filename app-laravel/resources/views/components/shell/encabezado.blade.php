{{-- Shell institucional compartido por layouts.tramite y layouts.guest. Sin componentes Livewire (ver ShellTest). --}}
<header>
    <a href="#contenido" class="sr-only focus:not-sr-only focus:absolute focus:left-md focus:top-md focus:z-50 focus:rounded-sm focus:bg-canvas focus:px-md focus:py-xs focus:text-body-md focus:font-semibold focus:text-primary">
        Saltar al contenido
    </a>

    <div class="bg-surface-dark text-on-dark">
        <div class="mx-auto flex max-w-[960px] items-center gap-sm px-md py-xs text-body-sm sm:px-lg">
            {{-- Espacio para el logotipo oficial: pendiente de que SEDEQ entregue los archivos. No inventar uno. --}}
            <p><span class="font-semibold">SEDEQ</span> · Secretaría de Educación del Estado de Querétaro</p>
        </div>
    </div>

    <div class="border-b-[3px] border-brand-accent bg-canvas">
        <div class="mx-auto flex max-w-[960px] flex-wrap items-center justify-between gap-sm px-md py-sm sm:px-lg">
            <p class="text-title-md font-semibold text-ink">Trámite de Incorporación de Escuelas Particulares</p>

            @auth
                <nav aria-label="Cuenta" class="flex flex-wrap items-center gap-x-md gap-y-xxs text-body-sm">
                    <span class="text-muted">{{ auth()->user()->name }}</span>
                    <a href="{{ route('tramite.index') }}" class="inline-flex min-h-11 items-center font-semibold text-primary underline underline-offset-4 hover:no-underline">Mis trámites</a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="inline-flex min-h-11 items-center font-semibold text-primary underline underline-offset-4 hover:no-underline">
                            Cerrar sesión
                        </button>
                    </form>
                </nav>
            @endauth
        </div>
    </div>
</header>
