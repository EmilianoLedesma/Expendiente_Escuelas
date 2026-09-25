<!DOCTYPE html>
{{-- sm:scroll-pb-28: el foco nunca queda bajo la barra de acciones fija (WCAG 2.4.11). --}}
<html lang="es" class="sm:scroll-pb-28">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ $title ?? 'Trámite de Incorporación' }} — SEDEQ</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
    </head>
    <body class="flex min-h-screen flex-col bg-canvas font-sans text-body-md text-body antialiased">
        <x-shell.encabezado />

        {{-- Con escuela: espacio de trabajo con recorrido. Livewire pasa ids por layoutData; el hub pasa su DTO. --}}
        @if (isset($resumen) || isset($escuelaId))
            <div class="mx-auto grid w-full max-w-7xl flex-1 px-md sm:px-xl lg:grid-cols-[300px_minmax(0,1fr)] lg:gap-xl">
                <x-tramite.recorrido
                    :resumen="$resumen ?? null"
                    :escuela-id="$escuelaId ?? null"
                    :escuela-nivel-id="$escuelaNivelId ?? null"
                    :seccion-actual="$seccionActual ?? null"
                    class="pt-lg lg:border-r lg:border-hairline lg:py-xl lg:pr-xl"
                />
                <main id="contenido" tabindex="-1" class="min-w-0 py-lg lg:py-xl">
                    {{ $slot }}
                </main>
            </div>
        @else
            <main id="contenido" tabindex="-1" class="mx-auto w-full max-w-7xl flex-1 px-md py-xl sm:px-xl">
                {{ $slot }}
            </main>
        @endif

        <x-shell.pie />

        @livewireScripts
    </body>
</html>
