{{-- DESIGN.md {component.top-nav}: 56px, white canvas, hairline bottom border, sticky, Cal Sans brand at left. --}}
<nav class="sticky top-0 z-10 flex items-center justify-between h-14 bg-canvas border-b-[0.5px] border-hairline px-lg">
    <span class="font-display text-title-md font-semibold text-ink">SEDEQ</span>

    @auth
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <x-ui.button-secondary type="submit">Cerrar sesión</x-ui.button-secondary>
        </form>
    @endauth
</nav>
