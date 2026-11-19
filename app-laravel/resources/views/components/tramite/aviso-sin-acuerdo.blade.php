{{-- Restricción legal mientras el trámite no tiene acuerdo de incorporación (LGE 150, LEEQ 56; requisitos SEDEQ). --}}
<x-ui.alert tipo="warning" titulo="Todavía no cuentas con el acuerdo de incorporación" {{ $attributes }}>
    Mientras la autoridad educativa no lo emita, no puedes inscribir alumnos, cobrar cuotas ni anunciar «RVOE en trámite» ni nada parecido.
    Si ya impartes estudios, indica «ESTUDIOS SIN RECONOCIMIENTO DE VALIDEZ OFICIAL» en tu documentación y publicidad.
    <a href="{{ route('marco-legal') }}" class="font-semibold">Consulta el marco legal</a>.
</x-ui.alert>
