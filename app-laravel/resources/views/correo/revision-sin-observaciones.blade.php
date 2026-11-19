<x-mail::message>
# Hola, {{ $nombre }}

La revisión documental del trámite de incorporación @if ($escuela)(**{{ $escuela }}**) @endif fue aprobada, sin observaciones.

**Siguiente paso:** la autoridad educativa te notificará la visita de inspección del plantel.

Todavía no cuentas con el acuerdo de incorporación: mientras no lo recibas, no inscribas alumnos, no cobres cuotas ni anuncies «RVOE en trámite».

<x-mail::button :url="$url">
Ver mi trámite
</x-mail::button>

Saludos,<br>
Sistema de Incorporación de Escuelas Particulares

<x-slot:subcopy>
Si tienes problemas con el botón «Ver mi trámite», copia y pega esta dirección en tu navegador: <span class="break-all">[{{ $url }}]({{ $url }})</span>
</x-slot:subcopy>
</x-mail::message>
