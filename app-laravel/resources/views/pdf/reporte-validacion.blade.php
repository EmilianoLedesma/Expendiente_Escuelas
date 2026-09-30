<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1a1a1a; }
        h1 { font-size: 18px; margin: 0 0 4px; }
        h2 { font-size: 13px; margin: 16px 0 6px; }
        .meta { color: #555; margin: 0 0 12px; }
        .veredicto { padding: 8px 10px; margin: 12px 0; border-left: 4px solid; }
        .lista { border-color: #1b7f3b; background: #eaf6ee; }
        .bloqueada { border-color: #b3261e; background: #fbeaea; }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; vertical-align: top; padding: 6px; border-bottom: 1px solid #ddd; }
        th { background: #f2f2f2; }
        .estado { white-space: nowrap; font-weight: bold; }
        .cumple { color: #1b7f3b; }
        .advertencia { color: #8a5a00; }
        .no_cumple { color: #b3261e; }
        .no_evaluable { color: #555; }
        ul { margin: 4px 0 0; padding-left: 14px; }
        .pie { margin-top: 16px; color: #555; font-size: 9px; }
    </style>
</head>
<body>
    <h1>Reporte de validación documental</h1>
    <p class="meta">
        Trámite Nº {{ $escuela['numero'] }} · {{ $escuela['nombre'] ?? 'Sin nombre propuesto' }}<br>
        {{ $escuela['domicilio'] }}<br>
        Generado el {{ $generadaEn->format('d/m/Y H:i') }}
    </p>

    <div class="veredicto {{ $listaParaEnvio ? 'lista' : 'bloqueada' }}">
        @if ($listaParaEnvio)
            <strong>Sin errores bloqueantes.</strong> Las alertas, si las hay, las revisará SEDEQ.
        @else
            <strong>Hay errores que deben corregirse antes de enviar la solicitud.</strong>
        @endif
    </div>

    @php($etiquetas = ['cumple' => 'Correcto', 'advertencia' => 'Alerta', 'no_cumple' => 'Debe corregirse', 'no_evaluable' => 'No verificado'])
    @php($secciones = [['titulo' => $niveles === [] ? null : 'Documentos del trámite', 'filas' => $filas], ...array_map(fn ($s) => ['titulo' => 'Documentos del nivel: '.$s->nivel, 'filas' => $s->filas], $niveles)])
    @foreach ($secciones as $seccion)
        @if ($seccion['titulo'])
            <h2>{{ $seccion['titulo'] }}</h2>
        @endif
        <table>
            <thead>
                <tr><th>Revisión</th><th>Resultado</th><th>Detalle</th></tr>
            </thead>
            <tbody>
                @foreach ($seccion['filas'] as $fila)
                    <tr>
                        <td>{{ $fila->titulo }}</td>
                        <td class="estado {{ $fila->estado }}">{{ $etiquetas[$fila->estado] ?? $fila->estado }}</td>
                        <td>
                            {{ $fila->mensaje }}
                            @if ($fila->lineas !== [])
                                <ul>
                                    @foreach ($fila->lineas as $linea)
                                        <li>{{ $linea }}</li>
                                    @endforeach
                                </ul>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endforeach

    <p class="pie">Validación automática de consistencia entre los datos capturados y los documentos cargados. No sustituye la revisión de SEDEQ ni el cotejo de originales en la visita de verificación.</p>
</body>
</html>
