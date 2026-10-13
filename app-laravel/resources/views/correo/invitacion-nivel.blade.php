<x-mail::message>
# Hola, {{ $nombre }}

Te asignaron como responsable de un nivel en el trámite de incorporación de escuelas.

<x-mail::panel>
**Asignado por**

{{ $solicitante }}

**Nivel educativo**

{{ $nivel }}
</x-mail::panel>

@if ($conToken)
Define tu contraseña para entrar y capturar la información de ese nivel.
@else
Entra al sistema para capturar la información de ese nivel.
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
