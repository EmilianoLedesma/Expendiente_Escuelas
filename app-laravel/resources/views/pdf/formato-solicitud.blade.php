<!DOCTYPE html>
<html>
<head><meta charset="utf-8"></head>
<body>
    <h1>Formato de Solicitud</h1>
    <p>Nivel educativo: {{ $escuelaNivel->nivelEducativo->nombre }}</p>
    <p>Turno: {{ ucfirst((string) $escuelaNivel->turno) }}</p>
    <p>Tipo de alumnado: {{ ucfirst((string) $escuelaNivel->tipo_alumnado) }}</p>
    @if ($escuela->responsableLegal)
        <p>Tipo de persona: {{ $escuela->responsableLegal->tipo_persona }}</p>
        <p>Domicilio para notificaciones: {{ $escuela->responsableLegal->domicilio_notificaciones }}</p>
        @if ($escuela->responsableLegal->personaFisica)
            <p>Nombre: {{ $escuela->responsableLegal->personaFisica->nombre }}</p>
        @endif
        @if ($escuela->responsableLegal->personaMoral)
            <p>Razón social: {{ $escuela->responsableLegal->personaMoral->razon_social }}</p>
        @endif
    @else
        <p>Responsable legal: pendiente de captura.</p>
    @endif
    <h2>Terna de nombres propuestos</h2>
    <ul>
        @foreach ($escuela->ternasNombres as $terna)
            <li>{{ $terna->numero_propuesta }}. {{ $terna->nombre_propuesto }}</li>
        @endforeach
    </ul>
</body>
</html>
