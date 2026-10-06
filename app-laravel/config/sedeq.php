<?php

/*
 * Datos institucionales del shell (barra, encabezado y pie). Única fuente: la barra y el pie
 * los leen de aquí, nunca se copian a mano en las vistas. Origen: sedeqgit/tableros_municipales
 * (includes/institutional_bar.php y includes/footer.php), contenido confirmado por SEDEQ.
 */

// Conmutador de la Secretaría: se define una vez; la etiqueta de la barra y el texto del pie se derivan.
$directorio = '4422385000';
[$lada, $serie, $numero] = [substr($directorio, 0, 3), substr($directorio, 3, 3), substr($directorio, 6)];

return [
    'institucion' => 'Secretaría de Educación del Estado de Querétaro',

    'directorio' => $directorio,

    'enlaces_gobierno' => [
        ['etiqueta' => 'Portal Transparencia', 'url' => 'https://www.queretaro.gob.mx/transparencia'],
        ['etiqueta' => 'Portal Prensa', 'url' => 'https://portal.queretaro.gob.mx/prensa/'],
    ],

    // 'icono' es una clave de x-ui.icon.
    'redes' => [
        ['etiqueta' => 'Chatbot', 'nombre' => 'Chatbot de WhatsApp', 'url' => 'https://wa.me/524421443740', 'icono' => 'marca-chat'],
        ['etiqueta' => 'Facebook', 'nombre' => 'Facebook', 'url' => 'https://www.facebook.com/educacionqro', 'icono' => 'marca-facebook'],
        ['etiqueta' => 'X', 'nombre' => 'X (Twitter)', 'url' => 'https://x.com/educacionqro', 'icono' => 'marca-x'],
        ['etiqueta' => 'Instagram', 'nombre' => 'Instagram', 'url' => 'https://www.instagram.com/educacionqueretaro', 'icono' => 'marca-instagram'],
        ['etiqueta' => 'YouTube', 'nombre' => 'YouTube', 'url' => 'https://www.youtube.com/@SecretariadeEducacionGEQ', 'icono' => 'marca-youtube'],
        ['etiqueta' => "{$lada} {$serie} {$numero}", 'nombre' => "Teléfono {$lada} {$serie} {$numero}", 'url' => 'tel:'.$directorio, 'icono' => 'marca-telefono'],
    ],

    'contacto' => [
        'direccion' => 'Av. Luis Pasteur Sur 21, Centro, 76000 Santiago de Querétaro, Qro., México.',
        'telefonos' => ['800 237 2233', "Directorio ({$lada}) {$serie} {$numero}"],
        'atencion' => 'Preguntas dudas, comentarios sobre el contenido del portal.',
    ],

    'aviso_privacidad' => 'https://queretaro.gob.mx/web/aviso-de-privacidad',
];
