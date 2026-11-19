<x-mail::message>
# Hola, {{ $nombre }}

@if ($cumple)
La visita de inspección del nivel **{{ $nivel }}**@if ($escuela) de **{{ $escuela }}**@endif concluyó sin observaciones.

**Siguiente paso:** la autoridad educativa emitirá la resolución de tu trámite.

Todavía no cuentas con el acuerdo de incorporación: mientras no lo recibas, no inscribas alumnos, no cobres cuotas ni anuncies al plantel como reconocido.
@else
La visita de inspección del nivel **{{ $nivel }}**@if ($escuela) de **{{ $escuela }}**@endif dejó {{ $total }} {{ $total === 1 ? 'observación' : 'observaciones' }}.

**Siguiente paso:** responde con las correcciones antes del {{ $plazo }} (aprox., 5 días hábiles). Puedes ver el detalle de cada observación en la página de la visita.

Todavía no cuentas con el acuerdo de incorporación: mientras no lo recibas, no inscribas alumnos, no cobres cuotas ni anuncies al plantel como reconocido.
@endif

<x-mail::button :url="$url">
Ver mi trámite
</x-mail::button>

Saludos,<br>
Sistema de Incorporación de Escuelas Particulares

<x-slot:subcopy>
Si tienes problemas con el botón «Ver mi trámite», copia y pega esta dirección en tu navegador: <span class="break-all">[{{ $url }}]({{ $url }})</span>
</x-slot:subcopy>
</x-mail::message>
