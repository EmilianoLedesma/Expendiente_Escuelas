<x-mail::message>
# Hola, {{ $nombre }}

SEDEQ revisó el trámite de incorporación y encontró observaciones que debes atender antes de reenviarlo.

<x-mail::panel>
@foreach ($pasos as $paso)
**{{ $paso['titulo'] }}@if ($paso['nivel']) ({{ $paso['nivel'] }})@endif**

{{ $paso['motivo'] }}

@endforeach
</x-mail::panel>

Entra al resumen de tu trámite para corregir cada punto y volver a enviarlo.

<x-mail::button :url="$url">
Ver mi trámite
</x-mail::button>

Saludos,<br>
Sistema de Incorporación de Escuelas Particulares

<x-slot:subcopy>
Si tienes problemas con el botón «Ver mi trámite», copia y pega esta dirección en tu navegador: <span class="break-all">[{{ $url }}]({{ $url }})</span>
</x-slot:subcopy>
</x-mail::message>
