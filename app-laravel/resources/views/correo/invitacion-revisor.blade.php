<x-mail::message>
# Hola, {{ $nombre }}

Te dieron acceso como revisor en el trámite de incorporación de escuelas.

<x-mail::panel>
**Invitado por**

{{ $supervisor }}
</x-mail::panel>

@if ($conToken)
Define tu contraseña para entrar y revisar los trámites que te asignen.
@else
Entra al sistema para revisar los trámites que te asignen.
@endif

<x-mail::button :url="$url">
{{ $textoBoton }}
</x-mail::button>

Saludos,<br>
Sistema de Incorporación de Escuelas Particulares

<x-slot:subcopy>
Si tienes problemas con el botón «{{ $textoBoton }}», copia y pega esta dirección en tu navegador: <span class="break-all">[{{ $url }}]({{ $url }})</span>
</x-slot:subcopy>
</x-mail::message>
