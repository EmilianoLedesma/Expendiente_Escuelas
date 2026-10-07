{{-- Estructura oficial: "FORMATO DE SOLICITUD EDUCACIÓN BÁSICA" (SEDEQ). Solo se imprimen los datos capturados; lo que falta queda como línea en blanco para llenar a mano. --}}
@php
    $responsable = $escuela->responsableLegal;
    $fisica = $responsable?->personaFisica;
    $moral = $responsable?->personaMoral;
    $fecha = fn (?string $f): ?string => $f ? \Carbon\Carbon::parse($f)->locale('es')->translatedFormat('j \d\e F \d\e Y') : null;
    $subrayado = fn (?string $v): string => $v !== null && $v !== '' ? '<u>'.e($v).'</u>' : '____________________';
    $suscribe = $moral?->nombre_representante_legal ?? $fisica?->nombre;
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 2cm 2.2cm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10pt; line-height: 1.45; color: #000; }
        h1 { font-size: 13pt; text-align: center; margin: 14px 0 10px; }
        p { margin: 0 0 8px; text-align: justify; }
        table.datos { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        table.datos td { border: 1px solid #000; padding: 6px 8px; vertical-align: top; }
        .destinatario { font-weight: bold; text-align: left; margin: 0; }
        .firma { margin-top: 48px; text-align: center; }
        .firma .linea { border-top: 1px solid #000; width: 60%; margin: 0 auto 4px; }
    </style>
</head>
<body>
    <img src="{{ public_path('img/formato-logo.png') }}" alt="Secretaría de Educación — Poder Ejecutivo del Estado de Querétaro" style="height: 55px;">

    <h1>FORMATO DE SOLICITUD</h1>

    <p style="text-align: right;">Santiago de Querétaro, Qro. a {{ now()->locale('es')->translatedFormat('j \d\e F \d\e Y') }}</p>

    <p class="destinatario">M. EN A.P. JUAN FRANCISCO LEDESMA MERÉ</p>
    <p class="destinatario">DIRECTOR DE EDUCACIÓN</p>
    <p class="destinatario">SECRETARÍA DE EDUCACIÓN DEL PODER EJECUTIVO</p>
    <p class="destinatario">DEL ESTADO DE QUERÉTARO</p>
    <p class="destinatario" style="margin-bottom: 12px;">P R E S E N T E</p>

    <p>El que suscribe {!! $subrayado($suscribe) !!}, señalando como domicilio para oír y recibir notificaciones el ubicado en {!! $subrayado($responsable?->domicilio_notificaciones) !!} autorizando para tal efecto, así como para recoger todo tipo de documentos a {!! $subrayado($responsable?->persona_autorizada_recoger) !!}, comparezco ante esa H. Autoridad Educativa a solicitar, con fundamento en los artículos 3o. fracción VI y 8o. de la Constitución Política de los Estados Unidos Mexicanos; 1o., 7o., 15, 35, 37, 101, 114, 146 y 147 de la Ley General de Educación; y los relativos a la Ley de Educación del Estado de Querétaro, la autorización para impartir educación {!! $subrayado($escuelaNivel->nivelEducativo->nombre) !!} en el horario {!! $subrayado(ucfirst((string) $escuelaNivel->turno)) !!}, con alumnado {!! $subrayado(ucfirst((string) $escuelaNivel->tipo_alumnado)) !!}.</p>

    <p>De conformidad con los datos siguientes:</p>

    @if ($moral)
        <table class="datos">
            <tr><td>
                <strong>DEL PROPIETARIO EN CASO DE SER PERSONA MORAL</strong><br><br>
                Nombre de la persona moral a la que representa: {!! $subrayado($moral->razon_social) !!}<br>
                Constituida según escritura pública número: {!! $subrayado($moral->numero_escritura_constitutiva) !!} de fecha {!! $subrayado($fecha($moral->fecha_escritura_constitutiva)) !!}<br>
                Pasada ante la fe del Notario Público Lic. {!! $subrayado($moral->notario_nombre) !!} Notaría Número: {!! $subrayado($moral->notario_numero) !!} de: {!! $subrayado($moral->notario_ciudad) !!}; inscrita en {!! $subrayado($moral->folio_registro_publico) !!} en fecha {!! $subrayado($fecha($moral->fecha_inscripcion_rpp)) !!}<br>
                Acreditación del Representante Legal mediante escritura pública número: {!! $subrayado(null) !!}
            </td></tr>
        </table>
    @else
        <table class="datos">
            <tr><td>
                <strong>DEL PROPIETARIO EN CASO DE SER PERSONA FÍSICA</strong><br><br>
                Nombre: {!! $subrayado($fisica?->nombre) !!}<br>
                Fecha de nacimiento: {!! $subrayado($fecha($fisica?->fecha_nacimiento)) !!}<br>
                R.F.C.: {!! $subrayado($fisica?->rfc) !!}<br>
                CURP: {!! $subrayado($fisica?->curp) !!}
            </td></tr>
        </table>
    @endif

    <p>Por lo antes expuesto y "BAJO PROTESTA DE DECIR VERDAD", declaro:</p>
    <p>1. Que los datos asentados en la presente solicitud y en los Anexos que acompaño, son ciertos.</p>
    <p>2. Que cuento con el personal directivo y docente con la preparación profesional para impartir los estudios de los que solicito la autorización (se proporcionan datos en el anexo 1 PLANTILLA DE PERSONAL DIRECTIVO Y DOCENTE).</p>
    <p>3. Que cuento con instalaciones que satisfacen las condiciones higiénicas, de seguridad y pedagógicas para impartir los estudios de los que solicito la autorización, además de que el inmueble donde se localizan dichas instalaciones lo ocupo legalmente y se encuentra libre de toda controversia administrativa o judicial y que será ocupado para impartir los estudios solicitados mientras se mantenga vigente el acuerdo de autorización.</p>
    <p>Asimismo, manifiesto que, en caso de haberme conducido con falsedad en los datos asentados en mi solicitud y anexos, acepto hacerme acreedor a cualesquiera de las sanciones penales que establecen los ordenamientos aplicables, así como a las sanciones administrativas correspondientes, incluyendo la negativa de la autorización.</p>
    <p>Manifiesto mi aceptación expresa para que en términos del artículo 32, fracción III de la Ley de Procedimientos Administrativos del Estado de Querétaro, cualquier acto derivado del presente trámite me sea notificado de forma electrónica a través de la cuenta de correo señalada en el apartado de Información General de la presente.</p>

    <div class="firma">
        <div class="linea"></div>
        Firma de la persona física o del representante legal de la persona moral.
    </div>
</body>
</html>
