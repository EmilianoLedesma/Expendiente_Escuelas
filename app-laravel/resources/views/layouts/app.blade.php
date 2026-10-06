<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ $title ?? 'Mi cuenta' }} — SEDEQ</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="flex min-h-screen flex-col bg-surface-soft font-sans text-body-md text-body antialiased">
        <x-shell.encabezado />

        <main id="contenido" tabindex="-1" class="w-full flex-1">
            @if (isset($header))
                <div class="mx-auto max-w-7xl px-md pt-xl sm:px-xl">
                    {{ $header }}
                </div>
            @endif

            {{ $slot }}
        </main>

        <x-shell.pie />
    </body>
</html>
