<?php

/*
 * Marco legal del trámite (página pública /marco-legal). Única fuente: la vista lo lee de aquí.
 * Cada entrada enlaza SOLO el texto oficial o la copia que se leyó; los artículos listados son los que se leyeron
 * directamente (ver docs/COMPENDIO_MAESTRO_Sistema_Incorporacion.md §8-9). Lo que no se pudo verificar no se lista.
 * 'url' => null cuando no hay enlace verificado. Al revisar un texto, actualiza 'verificado_el' en la misma edición.
 */
return [
    'verificado_el' => '2026-11-19',

    // Etapas posteriores a la revisión documental, según los requisitos de Media Superior (enero 2025) y de Educación Inicial.
    'procedimiento' => [
        'La autoridad educativa tiene diez días hábiles para admitir la solicitud o prevenir al particular, por una sola vez; el particular tiene tres días hábiles para atender la prevención.',
        'En Media Superior, el proyecto académico se envía a CEPPEMS para su opinión técnica; con opinión RECOMENDABLE se continúa.',
        'Si el expediente cumple, la autoridad notifica por oficio la visita de inspección del plantel, que debe estar equipado como el primer día de clases.',
        'Después de la visita, el particular tiene cinco días hábiles para atender las observaciones.',
        'La autoridad dicta la resolución dentro de treinta días hábiles posteriores a la visita: acuerdo de incorporación (o RVOE, según el nivel) o dictamen negativo.',
        'El acuerdo de incorporación surte efectos desde su emisión, no se puede transferir y no autoriza otros niveles ni otros domicilios.',
        'La clave de centro de trabajo (CCT) la genera la USEBEQ después del acuerdo, a solicitud de la escuela.',
    ],

    'grupos' => [
        [
            'titulo' => 'Leyes',
            'entradas' => [
                [
                    'titulo' => 'Ley de Educación del Estado de Querétaro',
                    'tipo' => 'Ley estatal',
                    'rige' => 'Competencia de la Secretaría para otorgar, negar y revocar autorizaciones y reconocimientos; obligaciones de las escuelas particulares, inspección y sanciones.',
                    'articulos' => [
                        'Art. 13, fr. VII: la Secretaría otorga, niega y revoca la autorización a particulares.',
                        'Arts. 48 y 49: autorización o reconocimiento específico para cada plan de estudios (el reconocimiento dura cinco años) y requisitos de personal, instalaciones y planes.',
                        'Art. 52: obligaciones de quienes imparten educación con autorización o reconocimiento.',
                        'Art. 54: visitas de inspección con orden escrita, acta con dos testigos y cinco días hábiles para presentar documentación.',
                        'Art. 55: publicación de los acuerdos en el Periódico Oficial y leyenda de incorporación en documentos y publicidad.',
                        'Arts. 66 a 69: infracciones, sanciones y procedimiento previo a la revocación o retiro.',
                    ],
                    'url' => 'https://site.legislaturaqueretaro.gob.mx/CloudPLQ/InvEst/Leyes/LEY-ID-014.pdf',
                    'nota' => 'Texto con reformas hasta el P. O. del 3-mar-2023.',
                ],
                [
                    'titulo' => 'Ley General de Educación',
                    'tipo' => 'Ley federal',
                    'rige' => 'Bases nacionales de la educación que imparten los particulares: autorización o reconocimiento, publicidad, obligaciones y vigilancia.',
                    'articulos' => [
                        'Art. 146: autorización expresa para inicial, preescolar, primaria, secundaria y normal; reconocimiento para los demás estudios; efectos desde el otorgamiento en básica y media superior.',
                        'Art. 147: requisitos de personal, instalaciones y planes y programas.',
                        'Art. 148: lista pública de instituciones y leyenda de incorporación.',
                        'Art. 149: obligaciones (becas de al menos 5 %, refrendo del reconocimiento, aviso de cambio de domicilio).',
                        'Art. 150: los estudios sin reconocimiento deben indicarlo en documentos y publicidad.',
                        'Art. 151: vigilancia al menos una vez al año.',
                    ],
                    'url' => 'https://www.diputados.gob.mx/LeyesBiblio/pdf/LGE.pdf',
                    'nota' => 'Última reforma DOF 15-01-2026.',
                ],
                [
                    'titulo' => 'Ley de Procedimientos Administrativos del Estado de Querétaro',
                    'tipo' => 'Ley estatal',
                    'rige' => 'Plazos, prevenciones y representación en cualquier trámite ante la autoridad estatal.',
                    'articulos' => [
                        'Art. 16: la autoridad resuelve en un plazo que no excede de un mes; el silencio se entiende negativo salvo que otra disposición diga lo contrario.',
                        'Art. 17: la prevención se hace por escrito y por una sola vez, con tres días hábiles para atenderla.',
                        'Art. 20: representación de personas físicas y morales.',
                    ],
                    'url' => 'https://sspm.corregidora.gob.mx/images/demo/pages/docs/LPAEQ.pdf',
                    'nota' => 'Copia publicada por el Municipio de Corregidora; contrasta con el Periódico Oficial.',
                ],
            ],
        ],
        [
            'titulo' => 'Acuerdos secretariales',
            'entradas' => [
                [
                    'titulo' => 'Acuerdo número 243 por el que se establecen las bases generales de autorización o reconocimiento de validez oficial de estudios',
                    'tipo' => 'Acuerdo federal (DOF 27-may-1998)',
                    'rige' => 'Bases del procedimiento, de las visitas y de los cambios posteriores al acuerdo; los requisitos de Básica en Querétaro lo citan.',
                    'articulos' => [
                        'Art. 6: los cambios de titular, domicilio o planes y programas requieren acuerdo previo (resolución en 20 días hábiles).',
                        'Art. 7: avisos de cambios de horario, turno, alumnado, nombre y actualizaciones.',
                        'Art. 13: si la visita encuentra incumplimientos, cinco días hábiles para cumplir y una segunda visita.',
                        'Art. 22: contenido del acuerdo (particular, institución, tipo, nivel, modalidad, turnos y alumnado, inicio de vigencia).',
                    ],
                    'url' => 'https://dgb.sep.gob.mx/storage/recursos/2023/07/iyPVbEzHR1-acuerdo243.pdf',
                    'nota' => null,
                ],
                [
                    'titulo' => 'Acuerdo número 450 (bases del reconocimiento de validez oficial en educación media superior)',
                    'tipo' => 'Acuerdo federal (DOF 16-dic-2008)',
                    'rige' => 'Requisitos del inmueble y procedimiento de reconocimiento en media superior. Es la referencia federal; Querétaro aplica los requisitos de su Dirección de Educación.',
                    'articulos' => [
                        'Art. 23: acreditación de la ocupación legal del inmueble.',
                        'Arts. 43 a 49: admisión, visita de inspección y plazos de resolución.',
                        'Art. 51: contenido del acuerdo de incorporación y obligaciones del particular.',
                        'Arts. 90 a 100: visitas de inspección ordinarias y extraordinarias.',
                        'Arts. 101 a 106: cambios que requieren nuevo acuerdo y avisos.',
                    ],
                    'url' => 'https://sidof.segob.gob.mx/notas/docFuente/5074629',
                    'nota' => null,
                ],
                [
                    'titulo' => 'Acuerdo número 17/11/17 (trámites y procedimientos del reconocimiento de validez oficial, tipo superior)',
                    'tipo' => 'Acuerdo federal (DOF 13-nov-2017)',
                    'rige' => 'Procedimiento y requisitos del reconocimiento en educación superior.',
                    'articulos' => [
                        'Art. 23: acreditación de la ocupación legal del inmueble.',
                        'Art. 25: etapas del procedimiento y plazos.',
                        'Arts. 30 a 36: cambios que requieren nuevo reconocimiento, avisos y retiro.',
                    ],
                    'url' => 'https://dgair.sep.gob.mx/storage/recursos/DREOE/acuerdo_171117.pdf',
                    'nota' => null,
                ],
                [
                    'titulo' => 'Acuerdos números 357, 254 y 255 (preescolar, primaria y secundaria)',
                    'tipo' => 'Acuerdos federales',
                    'rige' => 'Requisitos de instalaciones y personal de cada nivel de educación básica; los usa el motor de validación del sistema.',
                    'articulos' => [
                        'Acuerdo 357: educación preescolar.',
                        'Acuerdo 254 (DOF 26-mar-1999): educación primaria.',
                        'Acuerdo 255 (DOF 13-abr-1999): educación secundaria.',
                    ],
                    'url' => null,
                    'nota' => 'Consulta el texto vigente en el Diario Oficial de la Federación o en la Dirección de Educación.',
                ],
            ],
        ],
        [
            'titulo' => 'Procedimientos y requisitos de la autoridad',
            'entradas' => [
                [
                    'titulo' => 'Requisitos de incorporación de la Dirección de Educación de SEDEQ',
                    'tipo' => 'Documentos de la autoridad',
                    'rige' => 'Requisitos, formatos, anexos y plazos del trámite por nivel. Son la fuente de las listas de documentos del sistema.',
                    'articulos' => [
                        'Educación Inicial, Básica (preescolar, primaria y secundaria) y Media Superior: admisión en diez días hábiles, visita y resolución en treinta días hábiles.',
                        'Media Superior: formato del proyecto educativo y opinión técnica de CEPPEMS.',
                    ],
                    'url' => null,
                    'nota' => 'Documentos internos de la Dirección de Educación (Blvd. Centro Sur 75, Colinas del Cimatario); solicítalos ahí.',
                ],
                [
                    'titulo' => 'Generación de clave para escuela incorporada (USEBEQ)',
                    'tipo' => 'Trámite de la USEBEQ',
                    'rige' => 'Clave de centro de trabajo de las escuelas particulares incorporadas.',
                    'articulos' => [
                        'Se tramita por correo electrónico ante el Departamento de Estadística de la USEBEQ.',
                        'Respuesta en seis días hábiles; es gratuito y permanente mientras se conserve la incorporación.',
                    ],
                    'url' => 'https://www.usebeq.edu.mx/Content/Estadisitica/Clave/Tr%C3%A1mite%20Alta%20Clave%20Escuela%20Incorporada.pdf',
                    'nota' => 'Documento fechado el 02-07-2024.',
                ],
            ],
        ],
        [
            'titulo' => 'Inmueble, seguridad y pagos',
            'entradas' => [
                [
                    'titulo' => 'Reglamento de Protección Civil del Municipio de Querétaro (2017)',
                    'tipo' => 'Reglamento municipal',
                    'rige' => 'Visto bueno de protección civil: los centros educativos se clasifican como de alto riesgo.',
                    'articulos' => [
                        'Art. 46: vigencia del visto bueno por año calendario.',
                        'Art. 48, fr. I, inciso m: centros educativos como alto riesgo.',
                    ],
                    'url' => 'https://webservices.municipiodequeretaro.gob.mx/mejora_regulatoria/bin/files/REGLAMENTO%20DE%20PC%20%285%29.pdf',
                    'nota' => 'Reformas hasta el 3-jun-2025. Aplica al Municipio de Querétaro; otros municipios tienen su propio reglamento.',
                ],
                [
                    'titulo' => 'Reglamento de Construcción para el Municipio de Querétaro',
                    'tipo' => 'Reglamento municipal',
                    'rige' => 'Constancia de seguridad estructural y visto bueno de seguridad y operación.',
                    'articulos' => [
                        'Art. 277: constancia de seguridad estructural (la firma el corresponsable).',
                        'Arts. 274 y 289: visto bueno de seguridad y operación (lo firma el director responsable de obra).',
                    ],
                    'url' => null,
                    'nota' => 'Texto con reformas hasta la Gaceta Municipal No. 14 (29-mar-2022); consúltalo en el portal del Municipio de Querétaro.',
                ],
                [
                    'titulo' => 'Ley de Hacienda del Estado de Querétaro',
                    'tipo' => 'Ley estatal',
                    'rige' => 'Derechos por los servicios de las autoridades de educación (pago del trámite).',
                    'articulos' => [
                        'Capítulo «Por los servicios prestados por las autoridades de educación»: las cuotas vigentes del ejercicio fiscal se confirman con la Dirección de Educación.',
                    ],
                    'url' => null,
                    'nota' => 'El recibo de pago se genera en el portal tributario del Estado.',
                ],
            ],
        ],
    ],
];
