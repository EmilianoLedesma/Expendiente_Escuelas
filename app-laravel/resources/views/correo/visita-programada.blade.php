<x-mail::message>
# Hola, {{ $nombre }}

La autoridad educativa programó la visita de inspección del nivel **{{ $nivel }}**@if ($escuela) de **{{ $escuela }}**@endif.

- **Fecha y hora:** {{ $fecha }}

La visita se realizará en el domicilio de tu plantel: {{ $domicilio }}.

Ese día la escuela debe estar equipada como el primer día de operación, y debes tener a la mano los originales de los documentos de tu trámite.

Todavía no cuentas con el acuerdo de incorporación: mientras no lo recibas, no inscribas alumnos, no cobres cuotas ni anuncies al plantel como reconocido.

<x-mail::button :url="$url">
Ver mi trámite
</x-mail::button>

Saludos,<br>
Sistema de Incorporación de Escuelas Particulares

<x-slot:subcopy>
Si tienes problemas con el botón «Ver mi trámite», copia y pega esta dirección en tu navegador: <span class="break-all">[{{ $url }}]({{ $url }})</span>
</x-slot:subcopy>
</x-mail::message>
