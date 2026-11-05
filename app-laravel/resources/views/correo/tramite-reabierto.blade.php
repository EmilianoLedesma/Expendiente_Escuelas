<x-mail::message>
# Hola, {{ $nombre }}

Tu trámite de incorporación @if ($escuela)(**{{ $escuela }}**) @endif fue reabierto. Ya puedes entrar para continuarlo.

<x-mail::button :url="$url">
Ver mi trámite
</x-mail::button>

Saludos,<br>
Sistema de Incorporación de Escuelas Particulares

<x-slot:subcopy>
Si tienes problemas con el botón «Ver mi trámite», copia y pega esta dirección en tu navegador: <span class="break-all">[{{ $url }}]({{ $url }})</span>
</x-slot:subcopy>
</x-mail::message>
