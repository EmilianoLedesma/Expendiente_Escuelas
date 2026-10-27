{{-- Estructura oficial: "ANEXO 2 INSTALACIONES" (SEDEQ). Una sola plantilla para todos los niveles; solo se imprime lo capturado y lo que falta queda en blanco (nunca se inventa un NO). --}}
@php
    use Illuminate\Support\Str;

    $responsable = $escuela->responsableLegal;
    $suscribe = $responsable?->personaMoral?->nombre_representante_legal ?? $responsable?->personaFisica?->nombre;
    $d = $anexo->datosGenerales;
    $s = $anexo->secciones;
    $linea = '____________________';
    $u = fn ($v, string $en = '____________________'): string => $v !== null && $v !== '' ? '<u>'.e((string) $v).'</u>' : $en;
    $marca = fn (bool $b): string => $b ? '( X )' : '( )';
    // SI/NO solo con dato guardado; sin dato quedan ambos vacíos.
    $siNo = fn (?bool $v): string => 'SI '.($v === true ? '( X )' : '( )').' NO '.($v === false ? '( X )' : '( )');
    $m2 = fn ($v): string => $v !== null && $v !== '' ? e((string) $v).' m²' : '';

    $estudios = ['inicial' => false, 'preescolar' => false, 'primaria' => false, 'secundaria' => false, 'media superior' => false, 'superior' => false];
    $otrosEstudios = [];
    foreach ($anexo->estudiosActuales as $e) {
        $n = Str::lower(Str::ascii($e['nombre']));
        $hit = false;
        foreach (array_keys($estudios) as $k) {
            if ($k === 'superior' ? ($n === 'superior' || str_contains($n, 'educacion superior')) : str_contains($n, $k)) {
                $estudios[$k] = $hit = true;
                break;
            }
        }
        if (! $hit) {
            $otrosEstudios[] = $e['nombre'];
        }
    }

    // D1: las filas de cubículo se acomodan en las dos líneas fijas del formato; lo demás, en líneas extra.
    $orientacion = null;
    $padres = null;
    $extrasCub = [];
    foreach ($s['cubiculos']['lineas'] ?? [] as $c) {
        if (! $c['capturado']) {
            continue;
        }
        $t = Str::lower(Str::ascii((string) $c['destinadoA']));
        if ($orientacion === null && str_contains($t, 'orientacion')) {
            $orientacion = $c;
        } elseif ($padres === null && str_contains($t, 'padres')) {
            $padres = $c;
        } else {
            $extrasCub[] = $c;
        }
    }

    $sanitarioNombres = [
        'alumnado_masculino' => 'Alumnado masculino', 'alumnado_femenino' => 'Alumnado femenino',
        'personal_masculino' => 'Personal masculino', 'personal_femenino' => 'Personal femenino',
        'alumnado_maternal' => 'Alumnado maternal', 'personal' => 'Personal',
    ];
    $servicios = $anexo->serviciosCercanos;
    $numServicios = max(3, count($servicios));
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 1.8cm 1.8cm 2.2cm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 8.5pt; line-height: 1.35; color: #000; }
        h1 { font-size: 13pt; text-align: center; margin: 4px 0 0; }
        h2 { font-size: 11pt; text-align: center; margin: 0 0 8px; }
        h3 { font-size: 9pt; margin: 12px 0 4px; }
        p { margin: 0 0 6px; text-align: justify; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
        th, td { border: 1px solid #000; padding: 3px 5px; vertical-align: top; text-align: left; }
        th { background: #e6e6e6; }
        tr { page-break-inside: avoid; }
        td { word-wrap: break-word; }
        table.libre td { border: none; padding: 2px 0; }
        .c { text-align: center; }
    </style>
</head>
<body>
    <img src="{{ public_path('img/formato-logo.png') }}" alt="Secretaría de Educación — Poder Ejecutivo del Estado de Querétaro" style="height: 50px;">
    <h1>ANEXO 2</h1>
    <h2>INSTALACIONES</h2>

    <p>El que suscribe {!! $u($suscribe) !!} BAJO PROTESTA DE DECIR VERDAD, manifiesta que cuenta con las instalaciones necesarias para impartir educación {!! $u($nivelNombre) !!}, de acuerdo a lo previsto por los artículos 146 y 147 de la Ley General de Educación, y los relativos a la Ley de Educación del Estado de Querétaro de conformidad con los datos siguientes:</p>

    <h3>1. DATOS GENERALES DEL INMUEBLE</h3>
    <p>Calle: {!! $u($d['calle']) !!} No. (Ext.) {!! $u($d['numeroExt'], '________') !!} No. (Int.) {!! $u($d['numeroInt'], '________') !!}</p>
    <p>Colonia: {!! $u($d['colonia']) !!} Localidad: {!! $u($d['localidad']) !!} Municipio: {!! $u($d['municipio'], '______________') !!}</p>
    <p>C.P.: {!! $u($d['codigoPostal'], '__________') !!}</p>
    <p>Teléfono(s): {!! $u($d['telefono'], '______________') !!} Fax: ______________ Correo Electrónico: {!! $u($d['correoElectronico']) !!}</p>

    <h3>2. DESCRIPCION DE INSTALACIONES</h3>
    <table>
        <tr><th colspan="2">DIMENSIONES (M²)</th></tr>
        <tr><td>Predio: {!! $u($m2($anexo->metrosPredio) ?: null, '__________') !!}</td><td>Construido: {!! $u($m2($anexo->metrosConstruidos) ?: null, '__________') !!}</td></tr>
    </table>
    {{-- D8: el área cívica se captura en todos los niveles pero no se imprime en el Anexo de Inicial. --}}
    @unless ($nivelClave === 'inicial')
        <table>
            <tr><th colspan="2">ÁREA CÍVICA</th></tr>
            <tr>
                <td>Superficie (m²): {!! $u($m2($anexo->areaCivicaM2) ?: null, '__________') !!}</td>
                <td>Asta bandera {{ $siNo($anexo->tieneAstaBandera) }}</td>
            </tr>
        </table>
    @endunless

    <table>
        <tr><th colspan="4">TIPO DE ESTUDIOS QUE IMPARTE EN EL LOCAL ACTUALMENTE (marque con una X)</th></tr>
        <tr>
            <td>Educación inicial {{ $marca($estudios['inicial']) }}</td>
            <td>Educación preescolar {{ $marca($estudios['preescolar']) }}</td>
            <td>Educación primaria {{ $marca($estudios['primaria']) }}</td>
            <td>Educación secundaria {{ $marca($estudios['secundaria']) }}</td>
        </tr>
        <tr>
            <td>Educación Media Superior {{ $marca($estudios['media superior']) }}</td>
            <td>Educación Superior {{ $marca($estudios['superior']) }}</td>
            <td colspan="2">Otro, especifique: {!! $u($otrosEstudios === [] ? null : implode(', ', $otrosEstudios), '______________') !!} {{ $marca($otrosEstudios !== []) }}</td>
        </tr>
    </table>

    @isset($s['administrativo'])
        <table>
            <tr><th colspan="2">ESPACIOS ADMINISTRATIVOS. Marque con una X los espacios con los que cuenta.</th></tr>
            @foreach (array_chunk($s['administrativo']['lineas'], 2) as $par)
                <tr>
                    @foreach ($par as $l)
                        <td>{{ $l['nombre'] }} {{ $marca($l['capturado']) }}@if ($l['capturado'] && $l['destinadoA']) ({{ $l['destinadoA'] }})@endif @if ($l['superficieM2'] !== null) {!! $m2($l['superficieM2']) !!}@endif</td>
                    @endforeach
                    @if (count($par) === 1)<td></td>@endif
                </tr>
            @endforeach
        </table>
    @endisset

    <table>
        <tr><th colspan="2">AULAS PARA EL NIVEL EDUCATIVO QUE PRETENDE INCORPORAR</th></tr>
        <tr>
            <td>Número de aulas: {!! $u($anexo->aulas['numeroAulas'] ?? null, '__________') !!}</td>
            <td>Superficie aproximada de las aulas (m²): {!! $u($m2($anexo->aulas['superficieM2'] ?? null) ?: null, '__________') !!}</td>
        </tr>
    </table>

    @isset($s['cubiculos'])
        <table>
            <tr><th colspan="3">CUBÍCULOS de atención a padres u orientación a alumnos:</th></tr>
            <tr><th>Cubículo destinado a:</th><th>Marque con una X si cuenta con el espacio</th><th>Superficie aproximada (m²)</th></tr>
            <tr><td>Orientación educativa</td><td class="c">{{ $marca($orientacion !== null) }}</td><td>{!! $m2($orientacion['superficieM2'] ?? null) !!}</td></tr>
            <tr><td>Atención a padres de familia</td><td class="c">{{ $marca($padres !== null) }}</td><td>{!! $m2($padres['superficieM2'] ?? null) !!}</td></tr>
            @foreach ($extrasCub as $c)
                <tr><td>{{ $c['destinadoA'] ?: $c['nombre'] }}</td><td class="c">{{ $marca(true) }}</td><td>{!! $m2($c['superficieM2']) !!}</td></tr>
            @endforeach
        </table>
    @endisset

    <table>
        <tr><th colspan="6">SANITARIOS (registrar la información que en cada línea se solicita)</th></tr>
        <tr><th></th><th>Número de retretes</th><th>Número de mingitorios</th><th>Número de lavabos</th><th>Ventilación natural</th><th>Iluminación natural</th></tr>
        @foreach ($anexo->sanitarios as $san)
            <tr>
                <td>{{ $sanitarioNombres[$san['categoria']] ?? $san['categoria'] }}@if ($san['cantidadBacinicas'] !== null) <br>Bacinicas: {{ $san['cantidadBacinicas'] }}@endif</td>
                <td>{{ $san['cantidadRetretes'] }}</td>
                <td>{{ $san['cantidadMingitorios'] }}</td>
                <td>{{ $san['cantidadLavabos'] }}</td>
                <td>{{ $siNo($san['ventilacionNatural']) }}</td>
                <td>{{ $siNo($san['iluminacionNatural']) }}</td>
            </tr>
        @endforeach
    </table>

    @isset($s['recreativo_deportivo'])
        <table>
            <tr><th colspan="3">INSTALACIONES PARA ACTIVIDADES FÍSICAS Y RECREATIVAS</th></tr>
            <tr><th>Descripción</th><th>SI o NO</th><th>Equipamiento y características</th></tr>
            @foreach ($s['recreativo_deportivo']['lineas'] as $l)
                <tr>
                    <td>{{ $l['nombre'] }}</td>
                    <td>{{ $siNo($l['capturado'] ? true : null) }}</td>
                    <td>
                        {{ $l['destinadoA'] }}
                        @if ($l['superficieM2'] !== null) {!! $m2($l['superficieM2']) !!}@endif
                        @if ($l['campoFutbol']) {{ trim(($l['campoFutbol']['tipoSuperficie'] ?? '').' '.($l['campoFutbol']['formato'] ?? '')) }}@endif
                    </td>
                </tr>
            @endforeach
        </table>
    @endisset

    @isset($s['especial'])
        <table>
            <tr><th colspan="3">DE LAS SIGUIENTES INSTALACIONES MENCIONAR SI CUENTA O NO CON ELLAS</th></tr>
            <tr><th>ESPACIO</th><th>SI / NO</th><th>Información específica</th></tr>
            @foreach ($s['especial']['lineas'] as $l)
                <tr>
                    <td>{{ Str::upper($l['clave'] === 'biblioteca' ? 'CENTRO DE DOCUMENTACIÓN O BIBLIOTECA' : $l['nombre']) }}</td>
                    <td>{{ $siNo($l['capturado'] ? true : null) }}</td>
                    <td>
                        {{ $l['destinadoA'] }}
                        @if ($l['superficieM2'] !== null) {!! $m2($l['superficieM2']) !!}@endif
                        @if ($l['materiales'] !== [])
                            <br>MATERIAL:
                            @foreach ($l['materiales'] as $mat)
                                <br>{{ $mat['clave'] === 'revistas_especializadas' ? 'Revistas' : $mat['nombre'] }}@if ($mat['numeroTitulos'] !== null || $mat['numeroVolumenes'] !== null): {{ $mat['numeroTitulos'] ?? '__' }} títulos, {{ $mat['numeroVolumenes'] ?? '__' }} volúmenes @endif
                            @endforeach
                        @endif
                    </td>
                </tr>
            @endforeach
        </table>
    @endisset

    @isset($s['otros'])
        <table>
            <tr><th colspan="3">OTRAS INSTALACIONES CAPTURADAS</th></tr>
            @foreach ($s['otros']['lineas'] as $l)
                <tr>
                    <td>{{ $l['nombre'] }}</td>
                    <td>{{ $siNo(true) }}</td>
                    <td>
                        {{ $l['destinadoA'] }}
                        @if ($l['superficieM2'] !== null) {!! $m2($l['superficieM2']) !!}@endif
                        @if ($l['campoFutbol']) {{ trim(($l['campoFutbol']['tipoSuperficie'] ?? '').' '.($l['campoFutbol']['formato'] ?? '')) }}@endif
                    </td>
                </tr>
            @endforeach
        </table>
    @endisset

    <h3>RELACION DE INSTITUCIONES DE SALUD ALEDAÑAS, SERVICIO DE AMBULANCIAS U OTROS SERVICIOS DE EMERGENCIA A LOS CUALES RECURRIRÁ LA INSTITUCION EN CASO DE NECESIDAD</h3>
    @for ($i = 0; $i < $numServicios; $i++)
        @php($sv = $servicios[$i] ?? null)
        <p>{{ $i + 1 }}.-@if ($sv) {{ $sv['nombre'] }}@if ($sv['distanciaValor'] !== null) ({{ $sv['distanciaValor'] }} {{ $sv['distanciaUnidad'] }})@endif @else ______________________________________________________________________________ @endif</p>
    @endfor

    <div style="margin-top: 40px; text-align: center;">
        <div style="border-top: 1px solid #000; width: 55%; margin: 0 auto 4px;"></div>
        BAJO PROTESTA DE DECIR VERDAD<br>Firma del particular o de su representante legal
    </div>
</body>
</html>
