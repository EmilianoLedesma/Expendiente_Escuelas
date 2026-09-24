<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ $title ?? 'Trámite de Incorporación' }} — SEDEQ</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
    </head>
    <body class="flex min-h-screen flex-col bg-surface-soft font-sans text-body-md text-body antialiased">
        <x-shell.encabezado />

        <main id="contenido" tabindex="-1" class="mx-auto w-full max-w-[720px] flex-1 px-md py-xl sm:px-lg">
            <div class="rounded-sm border border-hairline bg-canvas p-md sm:p-lg">
                {{-- Se retira en la Tarea 9 junto con la clase Progreso. --}}
                <div class="mb-lg">
                    <x-tramite.progreso :escuela-id="$escuelaId ?? null" :escuela-nivel-id="$escuelaNivelId ?? null" />
                </div>

                {{ $slot }}
            </div>
        </main>

        <x-shell.pie />

        @livewireScripts
    </body>
</html>
