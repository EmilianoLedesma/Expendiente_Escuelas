<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ $title ?? 'Acceso' }} — SEDEQ</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased bg-surface-soft min-h-screen">
        <nav class="flex items-center h-14 bg-canvas border-b-[0.5px] border-hairline px-lg">
            <a href="/" class="font-display text-title-md font-semibold text-ink">SEDEQ</a>
        </nav>

        <main class="flex flex-col items-center justify-center px-lg py-xl min-h-[calc(100vh-56px)]">
            <div class="w-full max-w-md rounded-lg border-[0.5px] border-hairline bg-canvas p-lg">
                {{ $slot }}
            </div>
        </main>
    </body>
</html>
