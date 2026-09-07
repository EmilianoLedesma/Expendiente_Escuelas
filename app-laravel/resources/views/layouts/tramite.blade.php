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
    <body class="font-sans antialiased bg-surface-soft min-h-screen">
        <x-tramite.top-nav />

        <main class="max-w-[720px] mx-auto px-lg py-xl">
            <div class="mb-lg">
                <x-tramite.progreso :escuela-nivel-id="$escuelaNivelId ?? null" />
            </div>

            {{ $slot }}
        </main>

        @livewireScripts
    </body>
</html>
