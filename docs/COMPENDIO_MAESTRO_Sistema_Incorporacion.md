# Compendio Maestro — Sistema de Incorporación de Escuelas (SEDEQ)

> Documento vivo. Se actualiza conforme avanza la definición del proyecto y se comparten nuevos documentos normativos.
> Última actualización (2026-10-20): **auditoría normativa de la carga documental del Paso 2** — se agregó fundamento legal confirmado para identidad/representación (LEEQ, LPAEQ), pago de derechos (LHEQ), Constancia de Seguridad Estructural/DRO (Reglamento de Construcción del Municipio de Querétaro) y Visto Bueno de Protección Civil (Reglamento de Protección Civil del Municipio de Querétaro). Se corrigieron dos reglas sin fundamento verificado ("año de registro = año de emisión" y la vigencia de 30 días de Protección Civil, ambas ahora "Pendiente de SEDEQ"). Ver sección 9, "Hallazgos normativos y decisiones pendientes de formalización".
> Anterior: obtenidos y documentados los Acuerdos Secretariales 357 (Preescolar), 254 (Primaria) y 255 (Secundaria) directamente de fuentes oficiales SEP. El Motor de Validación de Capacidad Instalada queda completo para los 4 niveles de Educación Básica (superficie + personal).

---

## 1. Objetivo del proyecto

Digitalizar y centralizar el proceso de **incorporación de escuelas** ante SEDEQ (Secretaría de Educación del Poder Ejecutivo del Estado de Querétaro), actualmente manual y burocrático. El sistema debe generar un **expediente digital por escuela** que sirva como fuente única de verdad, eliminando el retrabajo causado por la falta de comunicación entre áreas internas de SEDEQ.

### Principio de alcance (límite explícito)

El sistema **no reemplaza trámites burocráticos externos** (notarías, protección civil, uso de suelo, peritos DRO, etc.). Actúa como **centralizador y validador documental**: recibe, ordena y valida los documentos resultantes de esos trámites externos, pero no los ejecuta.

### Canal: todo el flujo es la versión web del proceso presencial actual

El proceso de incorporación **hoy se realiza de forma presencial** en las oficinas de SEDEQ. El fin último de este proyecto es que **todo el flujo documentado (Pasos 1, 2, 3 y subsecuentes) opere vía web**, de inicio a fin, como reemplazo digital del proceso actual — no como un canal adicional o complementario.

**Única excepción confirmada**: la **visita de verificación** (ver Paso 3, sección de flujo/plazos) sigue requiriendo presencia física, ya que ahí se coteja el inmueble en persona y se muestran los originales de ciertos documentos (credencial de elector, escritura del inmueble) que fueron capturados digitalmente durante el trámite web. Este es, hasta ahora, el único punto de contacto presencial que la normativa exige y que el sistema no puede eliminar — solo puede coordinar/agendar dicha cita desde la plataforma.

### Dolor central identificado

La información de una escuela ya validada por un área (ej. uso de suelo) no es visible para otra área (ej. identificadores), por lo que al solicitar un nuevo nivel educativo se piden todos los documentos desde cero, generando retrabajo. El sistema ataca esto centralizando el expediente.

### Objetivo técnico complementario

Una vez consolidado el compendio completo (flujo, entidades y reglas de negocio), se generará el **DDL de base de datos respetando atomicidad y Tercera Forma Normal (3FN)**. El modelo de datos se derivará formalmente (entidades, atributos, dependencias funcionales, claves) antes de escribir el esquema definitivo, para evitar rediseños a medio camino.

> **Uso adicional del compendio**: el nivel de detalle capturado por documento (campos exactos del Formato de Solicitud, estructura tabular del Anexo 1, secciones y reglas de validación del Anexo 2) funciona también como **especificación funcional para los formularios dinámicos del sistema** — cada campo, su tipo de dato y su regla de validación quedan ya documentados aquí, sin necesidad de regresar a los documentos fuente originales al momento de construir los formularios reales.

---

## 2. Modelo de entidades

- **Plantel**: espacio físico / infraestructura (un inmueble, una dirección).
- **Escuela**: institución educativa que opera dentro de un plantel.
- **Nivel educativo**: cada nivel que oferta una escuela (Inicial, Preescolar, Primaria, Secundaria, Media Superior, Superior, **Posgrado**), cada uno con su propio expediente/checklist independiente. **Confirmado: son 7 niveles**, no 6 como se documentó originalmente — Posgrado se agregó tras confirmación del usuario.

Relaciones:
- Un **plantel** puede alojar **varias escuelas**.
- Una **escuela** puede ofertar **varios niveles educativos**.
- Cada combinación **escuela + nivel** tiene expediente propio (los checklists no se combinan).

### Implicación de diseño clave
Los documentos/datos de **infraestructura (plantel)** deben modelarse separados de los documentos/datos **específicos de escuela+nivel**, para permitir precargar información ya validada de un plantel cuando se dé de alta una nueva escuela o nivel en el mismo inmueble (ver Etapa 3).

---

## 3. Flujo del proceso (definido hasta ahora)

### Paso 1 — Preregistro
Realizado por el solicitante **vía web**. Como primera bifurcación, el sistema pregunta si el trámite corresponde a:
- **Plantel/escuela nuevo** (primer trámite del solicitante en el sistema), o
- **Plantel/escuela ya existente** en el sistema (agregar un nuevo nivel educativo, o dar de alta una nueva escuela en un plantel ya registrado).

Si es plantel/escuela existente, el sistema debe **precargar los datos del plantel ya validados** en trámites anteriores (dirección, documentos de infraestructura ya aprobados), evitando resolicitar información — esta bifurcación es el punto de entrada que activa la lógica de reutilización de datos definida en la Etapa 3.

Tras la bifurcación, el solicitante realiza el preregistro con datos básicos. SEDEQ entrega los requisitos previos e información general a reunir.

> **Corrección de secuencia**: la selección formal de los niveles educativos a tramitar **no ocurre en este paso** — se confirmó que ocurre hasta el final del Paso 2, después de capturar los documentos del responsable legal. El Paso 1 no debe determinar ni mostrar checklists por nivel todavía.

> **Pendiente**: la ventana de recepción de solicitudes (¿todo el año o solo septiembre-diciembre?) sigue sin resolverse — ver sección de Pendientes. Por ahora el Paso 1 se documenta sin restricción de fecha, a definir más adelante.

### Paso 2 — Responsable legal, documentos y selección de niveles

**2.1 — Selección de tipo de responsable.** El solicitante elige entre:
1. **Persona física** (actuando directamente).
2. **Persona física representada por gestor/tercero con poder notarial** (caso común, debe contemplarse).
3. **Persona moral** (IAP, A.C., S.A. de C.V.) a través de representante legal.

Esta elección **activa dinámicamente** qué documentos son obligatorios.

**2.2 — Captura de documentos.** Patrón de captura confirmado para cada documento: **captura de campos de datos estructurados + carga del archivo PDF de respaldo** (no es solo "subir archivo"). Ambos tipos de persona requieren 7 documentos, con estructura casi idéntica:

| # | Documento | Física | Moral | Nota especial |
|---|---|---|---|---|
| 1 | Credencial de elector (INE) | ✔️ | ✔️ | Original se coteja en la visita de verificación |
| 2 | Acta de nacimiento | ✔️ | — | — |
| 2m | Escritura o poder notarial de facultades del representante legal (sobre inmueble y trámites de funcionamiento) | — | ✔️ | — |
| 3 | Escritura del bien inmueble | ✔️ (valida si está a nombre del solicitante) | ✔️ | Original se coteja en la visita de verificación |
| 4 | Dictamen de Uso de Suelo vigente | ✔️ | ✔️ | **Vigencia: la que indique el propio documento** (cédula municipal TR-170100-017, Municipio de Querétaro, actualizada 06/nov/2024). El sistema captura la fecha de vencimiento si el documento la trae y no inventa una si no la trae. La "antigüedad máxima de 1 mes" que antes se aplicaba como bloqueo era una regla operativa confirmada por el dueño del proyecto que **no aparece en los documentos normativos fuente**; **desde 2026-10-19 ya no bloquea** y queda como pregunta abierta para SEDEQ (ver `docs/decisions/PENDIENTE-dictamenes-por-nivel.md` y `docs/reports/2026-10-19-dictamen-uso-suelo.md`). |
| 5 | Constancia de Seguridad Estructural y de Ocupación (incluye datos del perito/DRO) | ✔️ | ✔️ | Un solo documento a cargar; el formulario de captura incluye campos del perito (nombre, cédula profesional, número de registro, calidad en que firma, folio, vigencia declarada) además de los datos de la propia constancia. **Actualizado 2026-10-20**: el Reglamento de Construcción del Municipio de Querétaro no nombra un instrumento llamado literalmente así — ver "Fundamento normativo de la Constancia de Seguridad Estructural y de Ocupación / DRO" más abajo; distingue Visto Bueno de Seguridad y Operación (art. 274, firma el DRO) de Constancia de Seguridad Estructural (art. 277, renovación quinquenal, firma el Corresponsable en Seguridad Estructural) — hoy el sistema los trata como un solo documento. La regla "el año del registro del perito debe coincidir con el año de emisión de la constancia" **no se encontró en ningún artículo revisado**; queda como **Pendiente de SEDEQ** (ver `docs/decisions/PENDIENTE-constancia-seguridad-estructural.md`) — ya no es un bloqueo en el sistema. |
| 6 | Formato de pago de derechos (RecaudaNet) | ✔️ | ✔️ | — |

> **Decisión de diseño — fusión de documentos**: se había manejado "Constancia de Seguridad Estructural" y "Carta responsiva del DRO" como dos documentos separados. Al contrastar con `req_inicial.docx` ("Constancia... emitida por perito que compruebe su calidad de Director Responsable de Obra") y con el checklist Excel (que solo tiene una entrada para esto), se confirma que son el **mismo documento**. Se modela como **un solo PDF a cargar**, con un formulario de captura que incluye todos los campos relevantes de la constancia y del perito/DRO. **Pendiente de confirmar con SEDEQ** si en la práctica real se manejan como archivos separados — de ser así, es un ajuste menor al formulario, no al modelo de datos.

> **Corrección respecto a versión anterior**: se había marcado que "Escritura del inmueble" no aplicaba a persona moral — es incorrecto, sí aplica en ambos casos, como documento independiente de la escritura/poder de facultades del representante.
> **Confirmación**: Dictamen de Uso de Suelo, Constancia de Seguridad Estructural, Carta DRO y Pago de derechos son verdaderamente **requisitos base independientes del tipo de persona** — la única diferencia real entre física y moral está en los documentos de identidad/representación (puntos 2/2m).
> **Visita de verificación**: se confirmó que el cotejo de originales (credencial de elector, escritura) ocurre en la **misma** visita de verificación del inmueble ya documentada en la sección de flujo/plazos — no es una cita separada.

El **Formato de Solicitud** se genera como PDF prellenado a partir de los datos capturados (nombre, domicilio, tipo de persona, datos notariales si aplica), para descarga, **firma autógrafa** (requisito legal confirmado) y resubida escaneada.

> **Campos adicionales detectados en el Formato 1 de Educación Inicial** (posiblemente aplicables también a otros niveles, confirmar): **turno** solicitado, y **tipo de alumnado** (mixto, femenino o masculino) — sugiere que el sistema debe contemplar la posibilidad de escuelas de un solo sexo, no solo mixtas.

**2.3 — Selección de niveles educativos (última acción del Paso 2).** El solicitante selecciona, a modo de checklist, los niveles educativos que la escuela impartirá. Esta selección es la que **dispara los checklists documentales específicos por nivel** (que antes se había asumido, incorrectamente, que ocurría desde el Paso 1).

#### Regla de validación cruzada (normativa)
> "Todos los documentos deberán coincidir en los datos de identificación de la persona física o moral solicitante, así como el domicilio oficial (tal cual el certificado de número oficial)."

El sistema debería validar automáticamente consistencia de nombre/domicilio entre documentos cargados.

#### Otras notas del procedimiento oficial
- Trámite se presenta ante el **Departamento de Incorporación, Revalidación y Certificación** de SEDEQ.
- La terna de nombres del plantel debe validarse contra tres mecanismos: no ser marca comercial registrada, no estar ya registrada ante la propia SEDEQ (validable directamente contra la base de datos del sistema), y consulta de referencia a Antecedentes Fonéticos de Secretaría de Economía — ver detalle completo en sección 4.
- Presentar la solicitud no garantiza aprobación (sujeta a revisión y visita de verificación).

### Paso 3 — Captura de información por nivel educativo (Anexos 1 y 2 + datos adicionales)

**Disparador**: una vez que el solicitante seleccionó los niveles educativos al final del Paso 2, se repite esta captura **una vez por cada nivel seleccionado** (cada combinación escuela+nivel tiene su propio expediente independiente, ya establecido en el modelo de entidades).

**Orden de captura confirmado (idéntico en Inicial, Preescolar, Primaria y Secundaria)**:

1. **Datos del inmueble** — corresponde a la sección "Datos generales del inmueble" del Anexo 2/Formato 3 ya desglosada, y en el caso de Inicial también incluye la Acreditación de Ocupación Legal del Inmueble. **Desglose de campos confirmado (granularidad completa)**:
   - Domicilio completo: número interior, número exterior, colonia, localidad, municipio, código postal, teléfono(s), correo(s) electrónico(s).
   - Metros totales del predio (m²) y metros construidos totales (m²).
   - **Colindancias**: Norte, Sur, Este, Oeste (campo nuevo, no capturado en versiones anteriores del compendio).
   - **Coordenadas geográficas**: latitud, longitud (campo nuevo).
   - **Servicios de emergencia cercanos**: distancia especificada en kilómetros (refina la "Relación de instituciones de salud aledañas" que antes solo pedía nombre, sin distancia).
   - **Servicios de salud y de emergencia cercanos** (lista, por cada uno): nombre, tipo de servicio (público o privado), distancia en metros o kilómetros.
   - **Denominación del/los centro(s) educativo(s)** — terna de nombres (Propuesta 1, 2, 3): es el **mismo dato** capturado en el Formato de Solicitud del Paso 2 (confirmado, no es una segunda captura independiente; se referencia/muestra en este punto del flujo).
   - **Modalidad** (Escolarizada, No Escolarizada, Mixta, Virtual): es el **mismo campo** documentado como paso 4 del flujo general de captura por nivel (confirmado), pero para los niveles de **Media Superior y Superior** se captura desde este punto (paso 1), no hasta el paso 4. Nota: aquí incluye una cuarta opción, "Virtual", que no habíamos registrado antes (el paso 4 genérico solo contemplaba Escolarizada/No Escolarizada/Mixta).
   - **Proyecto académico** (documento, exclusivo de Media Superior) — ya lo teníamos del checklist (2 memorias USB).
   - **Formato de autoevaluación CEPPEMS** (exclusivo de Media Superior) — **dato nuevo, sin fuente documental confirmada aún** en los documentos revisados; solo lo tenemos por esta descripción del usuario. Pendiente localizar el documento fuente o el acuerdo que lo define.
   - **Formato de autoevaluación COEPES/ENOPEES** (exclusivo de Superior) — ya lo teníamos del checklist, ahora confirmado también en este punto del flujo.
   - **Plataforma educativa para modalidad Mixta** (propia o rentada, con documento que acredite) — generaliza lo que antes solo conocíamos como "contrato de plataforma tecnológica" exclusivo de Media Superior No Escolarizado; ahora aplica a cualquier nivel que opere en modalidad mixta.
2. **Infraestructura del nivel educativo** — instalaciones y servicios: aulas, sanitarios, oficinas, y en Preescolar/Primaria/Secundaria también patios y canchas (Inicial los agrupa como "áreas recreativas"). **Desglose de campos confirmado (granularidad completa)**:

   - **Espacios administrativos** (cada uno con superficie en m² — refinamiento respecto a la versión anterior, que solo marcaba sí/no): Dirección, Subdirección, Oficinas administrativas, Control escolar, Atención al público, Bodega (con **tipo**: limpieza, general u otro — dato nuevo), Sala de maestros.
   - **Aulas para el nivel educativo que pretende incorporar**: número de aulas didácticas, superficie en m².
   - **Cubículos** (atención a padres, orientación a alumnos, etc.): número de cubículos, superficie en m², "destinado a" (actividad en texto libre) — más flexible que la versión anterior, que solo contemplaba 2 categorías fijas (orientación educativa, atención a padres).
   - **Sanitarios** (por categoría: alumnado masculino, alumnado femenino, personal masculino, personal femenino): cantidad, superficie en m² (dato nuevo, antes no se pedía superficie de sanitarios), cantidad de lavabos.
   - **Instalaciones para actividades físicas y recreativas** (cada una con superficie en m²): cancha de usos múltiples, chapoteadero, arenero, zona de juegos, áreas verdes, área de recreo, **campo de fútbol** (tipo de superficie, formato: 11/7/5/baby fut — dato nuevo, sin fuente documental, aportado por el usuario), otras.
   - **Instalaciones adicionales** (cada una con superficie en m²): centro de documentación/biblioteca (física o **virtual** — si es virtual, requiere captura del contrato de servicio como documento; dato nuevo sin fuente documental confirmada, aportado por el usuario; detalle de material si es física: libros, periódicos, revistas especializadas, diapositivas, videos, películas, discos compactos, software, otro), taller(es) (con asignatura asociada), **laboratorio polifuncional**, **salón de usos múltiples**, **auditorio o aula magna**, cocina, comedor, sala de artes (con asignatura asociada), otras.
     > **Resuelto (2026-09-11)**: "Auditorio o Aula Magna" se reclasificó de actividades físicas y recreativas a instalaciones especiales/adicionales, per la estructura de tabla real de `ANEXO_2_Instalaciones.docx` — la fuente documental lo agrupa ahí, no junto a cancha/chapoteadero/arenero/zona de juegos/áreas verdes/área de recreo. Ya no es una discrepancia abierta.
3. **Mobiliario** — captura **separada** de la infraestructura física, no mezclada. El formulario inicia con un selector del **nivel educativo que se pretende incorporar** (confirmado: son 7 opciones — Inicial, Preescolar, Primaria, Secundaria, Media Superior, Superior, Posgrado), que determina qué catálogo de mobiliario se muestra. Para Inicial ya tenemos el catálogo completo de ratios, verificado palabra por palabra contra la fuente (ver Motor de Validación, sección 5). **Decisión de alcance del MVP (2026-09-11, confirmada por el usuario, no una pregunta abierta)**: la captura de mobiliario aplica únicamente a Educación Inicial en este MVP — es el único nivel con catálogo de ratios confirmado. Preescolar/Primaria/Secundaria/Media Superior/Superior/Posgrado quedan deliberadamente fuera de alcance de este MVP para mobiliario (no es un vacío accidental), a la espera de que SEDEQ confirme un catálogo equivalente (probablemente en los Acuerdos 357/254/255 para Básica; sin fuente aún para los demás) antes de cualquier expansión.
4. **Plan de estudios y modalidad** — dato nuevo no capturado antes: la **modalidad** (escolarizada / no escolarizada / mixta; en Inicial casi siempre escolarizada) y el **plan de estudios** (referencia al currículo oficial que sigue el nivel). No teníamos ningún campo para esto en el compendio previo.
5. **Plantilla docente** — corresponde al Anexo 1 ya documentado. **Especificación confirmada y cerrada**:
   - **Campos del formulario** (verificado contra `ANEXO_1_Plantilla.docx`, tabla repetible por persona): Nombre, Nacionalidad, Sexo (M/F), Estudios, Cédula Profesional o Documento Académico, Cargo o Puesto a desempeñar. **No incluye CURP**. No requiere adjuntar PDF de la cédula/certificado — es solo captura de datos, declarada "bajo protesta de decir verdad" (a diferencia del patrón de documentos del Paso 2, que sí requiere PDF).
   - **Tres estructuras distintas según el nivel** (confirmado):
     - **Por sala (Inicial)**: Director Técnico (1 por plantel), Responsable por cada sala (Lactantes A/B/C, Maternal A/B — fijo, no proporcional), Asistentes por sala (proporcional: 1/5 en Lactantes, 1/10 en Maternal), Responsable del filtro y fomento a la salud (obligatorio), personal adicional opcional/condicionado a servicios.
     - **Por grupo/grado (Preescolar, Primaria)**: Director, Docente titular por grupo, docentes condicionados (Educación Física, Inglés, Computación según capacidad/grado — ver Profesiogramas), docentes extracurriculares si aplica.
     - **Por asignatura (Secundaria)**: Director, Docente titular por cada asignatura (Biología, Español, Matemáticas, etc.) — el campo "Cargo o Puesto" en este caso registra "el nombre de la asignatura tal y como está en el plan de estudios" (instrucción textual del Anexo 1).
   - **Validación dinámica confirmada**: el formulario primero solicita el **Cargo/Puesto (o Asignatura)** de un catálogo cerrado según el nivel, y **después filtra qué "Estudios" son válidos** para ese cargo, cruzando contra el Profesiograma correspondiente.
   - **Los Profesiogramas no son formularios de captura, son catálogos de validación en segundo plano** — no se le muestran ni se le piden datos al solicitante directamente; su estructura fija es: **Función → Área de Conocimiento → Perfil Profesional (catálogo cerrado de carreras) → Documento que lo Acredita** (Título y Cédula Profesional, o Certificado, según el puesto).
6. **Matrícula** — cantidad de alumnos, con dos variantes según el tipo de trámite (confirmado): **proyección/capacidad solicitada** si es alta nueva, o **matrícula real ya inscrita** si es reincorporación/renovación. Este dato **alimenta directamente el Motor de Validación de Capacidad Instalada** (m² por alumno, personal por alumno, mobiliario por alumno).

   > **Sin fuente documental**: se buscó "matrícula", "matricula", "inscripción" y variantes en los cinco documentos `.docx` revisados (checklist, ambos requisitos, ambos Anexos, formato de solicitud) y **no hay ninguna mención** de este concepto como campo o sección formal — es información operativa aportada directamente por el usuario, no de la normativa documentada.

   **Estructura de captura confirmada, por nivel** (selector inicial de nivel — mismos 7: Inicial, Preescolar, Primaria, Secundaria, Media Superior, Superior, Posgrado):
   - **Inicial**: cantidad por sala — Lactantes A, Lactantes B, Lactantes C, Maternal A, Maternal B. (Coincide exactamente con la estructura de salas ya documentada, permitiendo validar directamente los ratios de superficie/personal/mobiliario por sala.)
   - **Preescolar**: cantidad por grado (1°, 2°, 3°), y dentro de cada grado, por grupo (A, B, C, Otro).
   - **Primaria**: cantidad por grado (1° a 6°), con grupos (A hasta K — hasta 11 grupos por grado).
   - **Secundaria**: mismo patrón que Primaria (grado 1° a 3°, con grupos).
   - **Media Superior**: cantidad por semestre (1 a 6), separado por modalidad — Escolarizado, No Escolarizado, Mixto (cada modalidad con su propio conteo de semestre 1-6).
   - **Cuatrimestre**: cantidad por cuatrimestre (1 a 6) — aplicable probablemente a Superior/Posgrado (nivel exacto pendiente de confirmar), corrige un error de escritura inicial donde aparecía como "semestre" duplicado.
   - **Superior / Posgrado**: estructura de captura aún no detallada más allá de la referencia a Cuatrimestre.

> **Implicación de diseño**: los pasos 1-3 (inmueble, infraestructura, mobiliario) probablemente deban reutilizar/precargar datos ya capturados de un plantel existente (ver Etapa 3 y bifurcación del Paso 1), mientras que los pasos 4-6 (plan de estudios, plantilla docente, matrícula) son inherentemente específicos de cada escuela+nivel y no se precargan.

---

Ambos Anexos se definen como **formularios nativos del sistema**, no documentos Word a cargar, ya que los datos que contienen ya son conocidos por el solicitante (no requieren trámite externo). Al final, se genera PDF para firma autógrafa y resubida (igual que el Formato de Solicitud).

**Anexo 1 — Plantilla de Personal Directivo y Docente**
Tabla repetible: Nombre | Nacionalidad | Sexo | Estudios | Cédula Profesional o Doc. Académico | Cargo/Puesto. Candidato a tabla editable dinámica (agregar/quitar filas) con validación de formato de cédula.

**Anexo 2 — Instalaciones**
Formulario extenso: domicilio, dimensiones (predio/construido), área cívica, tipo de estudios que ya imparte el local, espacios administrativos, aulas para el nivel a incorporar (número y superficie), cubículos de atención, sanitarios (conteos por tipo de usuario), áreas recreativas, instalaciones especiales (biblioteca, laboratorio, taller, auditorio, cocina, comedor, sala de artes), instituciones de salud cercanas.

> **Nota de diseño**: la mayoría de los campos de Anexo 2 describen el **plantel**, no la escuela+nivel específica (excepto la sección de "aulas para el nivel que se pretende incorporar"). Debe modelarse de forma que los datos de plantel se puedan reutilizar/precargar en trámites futuros del mismo inmueble.

> **Nota de nomenclatura**: en el documento de Educación Inicial, estos mismos formularios se llaman "Formato 1 (Solicitud)", "Formato 2 (Plantilla de Personal)" y "Formato 3 (Instalaciones)", mientras que en Básica se llaman "Formato de Solicitud", "Anexo 1" y "Anexo 2". Son equivalentes funcionales con nomenclatura distinta según el nivel — el sistema puede unificar el nombre interno aunque el PDF generado respete el nombre oficial de cada nivel.

### Desglose de campos — Sección "Acreditación de Ocupación Legal del Inmueble" (Formato 3/Anexo 2)

Esta subsección del formulario tiene **cuatro variantes mutuamente excluyentes** según el tipo de documento de ocupación, cada una con sus propios campos:

**a) Escritura Pública**: número de escritura, fecha, nombre del Notario Público, número de Notaría, localidad de la Notaría, folio del Registro Público de la Propiedad, fecha de inscripción en el Registro.

**b) Contrato de Arrendamiento**: arrendador (nombre), arrendatario (nombre), fecha de contrato, vigencia, uso autorizado del inmueble (texto libre, debe indicar destino educativo).

**c) Contrato de Comodato**: comodante (nombre), comodatario (nombre), fecha de contrato, vigencia, uso autorizado del inmueble (texto libre, debe indicar destino educativo).

**d) Otro** (especificar) + campo de Observaciones libre.

> **Implicación de diseño**: en el formulario del sistema, esta sección debe implementarse como un selector de tipo de documento (a/b/c/d) que muestre dinámicamente solo los campos correspondientes — no los cuatro conjuntos a la vez. Es un patrón de formulario condicional similar al de tipo de responsable (física/moral).

### Desglose de campos — resto de secciones del Formato 3/Anexo 2 (Instalaciones, fuente: Educación Inicial)

**1. Datos generales del inmueble**: calle, número exterior, número interior, colonia o localidad, municipio, teléfono(s), correo electrónico.

**3. Constancia de Seguridad Estructural y de Ocupación**: nombre del perito, fecha de expedición de la constancia, vigencia de la constancia, número de cédula profesional del perito, número de registro del perito como DRO (o similar), autoridad que expide el registro, vigencia del registro.

**4. Dictamen de Uso de Suelo**: autoridad que expide, folio del dictamen, fecha de expedición, uso o giro autorizado tal como aparece en el documento, vigencia declarada por el documento (fecha de vencimiento, o "sin fecha expresa" si el documento no la trae) y condicionantes urbanísticas (opcional). La factibilidad de giro es un documento distinto (cédula TR-170100-044: vigencia de 1 año calendario solo para la factibilidad; el dictamen de uso de suelo no indica plazo) y no se captura en este formulario.

**5. Descripción de instalaciones — subcampos**:
- Dimensiones: predio (m²), construido (m²).
- Tipo de estudios que imparte el local actualmente: educación básica, media, superior, u otro (especificar), cada uno con número de alumnos.
- Instalaciones administrativas (marcar con cuáles cuenta): dirección, control escolar, oficinas administrativas, filtro/recepción, otras.
- Aulas/salas de atención (tabla repetible por aula): número/identificador, capacidad promedio (cupo de alumnos), superficie (m²), altura, ventilación natural (sí/no), iluminación natural (sí/no).
- Sanitarios y control de esfínter (separado por alumnado de maternales y por personal administrativo/docente): número de bacinicas, número de lavabos, número de retretes, ventilación natural (sí/no), iluminación natural (sí/no).
- Áreas recreativas y deportivas (marcar con cuáles cuenta): áreas verdes, patio de recreo, arenero, zona de juegos mecánicos, chapoteadero, otras (especificar).

**6. Relación de instituciones de salud aledañas / servicios de emergencia**: lista de hasta 3 entradas (nombre de institución o servicio).

> **Confirmado — el Anexo 2 genérico de Básica tiene estructura distinta al Formato 3 de Inicial**, no son el mismo formulario con nombre distinto:
> - El Anexo 2 genérico **no incluye** la sección de "Acreditación de Ocupación Legal del Inmueble" (escritura/arrendamiento/comodato), ni Constancia de Seguridad Estructural, ni Dictamen de Uso de Suelo — en Básica esos datos se acreditan con documentos/secciones separadas, no embebidos en el formulario de instalaciones.
> - Área cívica (con asta bandera): presente solo en el Anexo 2 genérico.
> - Espacios administrativos con catálogo distinto: genérico incluye Subdirección, Atención al público, Bodega de intendencia, Sala de maestros; Inicial incluye Filtro/Recepción (que el genérico no tiene).
> - Cubículos de atención a padres/orientación educativa: exclusivos del Anexo 2 genérico.
> - Sanitarios con mayor granularidad en el genérico: separados por alumnado/personal **y por sexo** (incluye mingitorios); Inicial separa solo por "alumnado maternales" y "personal" (sin distinción de sexo, coherente con la edad de los usuarios).
> - Instalaciones especiales exclusivas de Básica: biblioteca (solo Primaria/Secundaria), taller de tecnológica, laboratorio polifuncional, salón de usos múltiples, auditorio, cocina, comedor, sala de artes.
> - El Anexo 2 genérico indica "Página 2 de 4" — sugiere que forma parte de un documento de varias páginas junto con el Anexo 1 de personal.
>
> **Implicación de diseño**: el formulario de "Instalaciones" del sistema no puede ser un único esquema fijo — debe variar su estructura según el nivel educativo (Inicial vs. Básica), similar a como ya varía el checklist documental.

### Desglose de campos — Anexo 2 genérico (Básica: Preescolar/Primaria/Secundaria/Media Superior/Superior)

**1. Datos generales del inmueble**: calle, número exterior, número interior, colonia, localidad, municipio, código postal, teléfono(s), fax, correo electrónico.

**2. Descripción de instalaciones**:
- Dimensiones: predio (m²), construido (m²).
- Área cívica: superficie (m²), asta bandera (sí/no).
- Tipo de estudios que imparte el local actualmente (marcar con X): Inicial, Preescolar, Primaria, Secundaria, Media Superior, Superior, Otro (especificar).
- Espacios administrativos (marcar con X): Dirección, Subdirección, Oficinas administrativas, Control escolar, Atención al público, Bodega para intendencia, Sala de maestros.
- Aulas para el nivel educativo que pretende incorporar: número de aulas, superficie aproximada (m²).
- Cubículos de atención (marcar con X si cuenta + superficie aproximada m²): orientación educativa, atención a padres de familia.
- Sanitarios (por categoría — alumnado masculino, alumnado femenino, personal masculino, personal femenino): número de retretes, número de mingitorios, número de lavabos, ventilación natural (sí/no), iluminación natural (sí/no).
- Instalaciones para actividades físicas y recreativas (sí/no + descripción de equipamiento): cancha de usos múltiples, chapoteadero, arenero, zona de juegos, áreas verdes, área de recreo, otras.
- Instalaciones especiales (sí/no + información específica): centro de documentación/biblioteca (solo Primaria y Secundaria, con detalle de tipo de material: libros, periódicos, revistas, videos, software, etc.), taller para asignatura tecnológica (indicar para qué asignatura), laboratorio polifuncional (indicar para qué asignatura), salón de usos múltiples, auditorio o aula magna, cocina, comedor, sala de artes (indicar para qué asignatura), otras instalaciones (indicar uso).

**Relación de instituciones de salud aledañas / servicios de emergencia**: lista de hasta 3 entradas.

---

### Flujo/plazos de revisión interna (detalle de Educación Inicial — referencia para Etapa 2)

1. Recibida la solicitud, la autoridad tiene **10 días hábiles** para admitirla o prevenir (solicitar correcciones) por oficio o correo.
2. Si hay prevención por documentos faltantes o pago no acreditado: el solicitante tiene **3 días hábiles** para subsanar, o la solicitud se desecha (puede iniciar un nuevo trámite después).
3. El solicitante tiene derecho a **consultar el estatus de su expediente** en cualquier momento.
4. Si la documentación es correcta, se notifica una **visita de verificación física** al inmueble — debe estar completamente equipado como si fuera el primer día de clases.
5. Tras la visita: **30 días hábiles** para la resolución final (Acuerdo de Incorporación o dictamen negativo).

> **Nota de discrepancia en la fuente**: el mismo documento indica en un punto que el periodo de recepción de solicitudes es "de septiembre a diciembre de cada año" (previo al ciclo escolar) y en otro punto que es "todos los días hábiles del año" — contradicción del documento original que conviene aclarar directamente con SEDEQ, no asumir una de las dos.

### Tipos de modificación de expediente ya reconocidos por la normativa (alimenta Etapa 3)
- Los Acuerdos de Incorporación son **intransferibles** (no se pueden vender/enajenar).
- Sí se permite, mediante solicitud y autorización previa de la Dirección de Educación: **cambio de titular** del Acuerdo y **cambio de domicilio** del plantel donde se imparten los estudios.
- Un Acuerdo de Incorporación de un nivel **no autoriza automáticamente** otros niveles en el mismo domicilio o en otros domicilios — cada nivel requiere su propio trámite, confirmando el modelo de expediente independiente por escuela+nivel.

---

## 4. Catálogo documental (Checklist de Incorporación — análisis)

Basado en `CHECK_LIST_DOCUMENTOS_INCORPORACIÓN.xlsx` (expedientes reales, formato de folio `IN-AAAA-NNN`).

### Documentos comunes a todos los niveles

**Identidad/legal del solicitante**: Formato de solicitud, acta de nacimiento, identificación oficial, acta constitutiva (si persona moral), poder notarial (si aplica).

**Inmueble/infraestructura**: acreditación legal del inmueble (escritura/arrendamiento/comodato), dictamen de uso de suelo, certificado de número oficial, constancia de seguridad estructural (DRO/CSE), cédula y registro del perito, visto bueno de Protección Civil, plano del inmueble.

**Administrativo**: recibo de pago de derechos (RecaudaNet).

**Académico/institucional**: propuesta de denominación (terna de nombres), Anexo 1 (personal), Anexo 2 (instalaciones).

### Documentos adicionales específicos por nivel

| Nivel | Documentos adicionales |
|---|---|
| Primaria / Secundaria | Acervo bibliográfico (300 títulos: 50/grado en Primaria, 100/grado en Secundaria) |
| Secundaria / Media Superior | Inventario de laboratorio polifuncional |
| Media Superior | Proyecto académico (2 memorias USB) |
| Media Superior No Escolarizado | Contrato de plataforma tecnológica, biblioteca virtual acreditada |
| Superior (todas modalidades) | Anexo 4, Anexo 5 (instalaciones especiales), Formato de autoevaluación COEPES/ENOPEES |

### Observaciones importantes
1. **El checklist no es fijo en el tiempo** — el orden y presencia de ítems varía entre expedientes de distintos años del mismo nivel. El checklist debe ser **versionable/configurable**, no hardcodeado.
2. **Persona física vs. moral** afecta qué documentos aplican — lógica condicional necesaria además del nivel educativo.
3. **Escolarizado vs. no escolarizado / modalidad mixta** es una segunda dimensión de variabilidad (visible en Media Superior y Superior).
4. El folio `IN-AAAA-NNN` es la convención de expediente ya usada por SEDEQ — considerar respetarla o replicarla.

### Documentos y requisitos adicionales detectados (revisión completa de Requisitos de Educación Inicial)

- **Accesibilidad**: el inmueble debe garantizar acceso y libre desplazamiento a personas con discapacidad — requisito legal explícito, no listado antes.
- **Plano del inmueble = croquis simple**: la norma aclara que **no se requiere plano arquitectónico profesional**, basta un croquis completo con dimensiones y uso de cada área. Esto simplifica lo asumido previamente.
- **Recibo de pago de derechos**: únicamente es válido el generado y descargado desde el portal tributario oficial del estado (`portal-tributario.queretaro.gob.mx`, rubro correspondiente al nivel). Pases de caja, tickets de tienda de conveniencia o de banco **no se aceptan** como comprobante.
- **Terna de nombres del plantel** — al releer la oración completa de la fuente con cuidado, en realidad son **tres verificaciones distintas combinadas**, no una sola (corrección respecto a lo documentado antes):
  1. **No debe ser marca comercial registrada** "en términos de las leyes respectivas" — equivalente al IMPI que menciona explícitamente el documento de Básica; no eran fuentes contradictorias, fue una lectura incompleta de la oración en la primera revisión.
  2. **No debe estar ya registrado ante la propia SEDEQ** — dato nuevo e importante: SEDEQ mantiene su propio registro interno de denominaciones ya incorporadas. **Esta validación la puede hacer el sistema directamente contra su propia base de datos**, sin depender de ningún servicio externo.
  3. **Antecedentes Fonéticos de Secretaría de Economía** (`mua.economia.gob.mx`) se menciona únicamente como referencia sugerida ("como referencia consultar..."), no como mecanismo oficial obligatorio.
  - Reglas de formato: nombres completos sin abreviaturas, anteponer "escuela/colegio/instituto" según aplique; si el nombre alude a un nivel educativo debe corresponder al nivel realmente ofertado.
  - **Excepción**: si el solicitante ya usa una denominación en otro plantel incorporado, puede reutilizarla en un nuevo plantel — relevante para el modelo de datos Plantel/Escuela.
- **Propuesta de emblema y sello** del plantel — documento no capturado en revisiones anteriores.
- **Documento de protocolos para prevenir y erradicar conductas de riesgo** — política interna del plantel que también se presenta como requisito.
- **Documento de acreditación de personalidad jurídica — detalle de identificaciones aceptadas** (persona física): acta de nacimiento + identificación oficial con fotografía (credencial de elector, pasaporte, cartilla militar o cédula profesional).
- **Acta constitutiva (persona moral)**: debe estar inscrita en el Registro Público de la Propiedad y establecer expresamente que el objeto social incluye la impartición de Educación Inicial o Básica.

### Hallazgos adicionales — revisión completa de Requisitos y Procedimiento de Básica

- **Documento de ocupación legal del inmueble**: puede ser escritura pública, contrato de arrendamiento, contrato de comodato, u "otro instrumento jurídico" — en todos los casos debe señalar fecha de firma, vigencia mínima de un ciclo escolar completo, mencionar expresamente el uso educativo, especificar la superficie del predio, y estar firmado por el solicitante o representante legal según el tipo de persona.
- **Comodato con requisito más estricto para Básica**: a diferencia de Inicial, el contrato de comodato para Básica debe estar **ratificado ante Notario Público** — diferencia real entre niveles, no un dato faltante anterior.
- **Nueva regla de validación cruzada (fechas)**: la vigencia del registro del perito debe **coincidir con el año de emisión de la constancia de seguridad estructural** (ej. constancia de enero 2011 → registro del perito debe ser también de 2011).
- **Certificado de Número Oficial es condicional**, no un documento común siempre obligatorio: solo se exige "en los casos donde los documentos emitidos por la autoridad municipal presenten domicilios diferentes" entre sí.
- **Accesibilidad ampliada**: el inmueble debe permitir acceso mediante rampas, elevadores u otro elemento en plantas superiores, garantizando el acceso a áreas comunes, académicas y sanitarias en todos los niveles del inmueble.
- **Confirmación explícita del modelo Plantel/Escuela**: el Anexo 2 debe indicar expresamente si en la misma infraestructura ya se imparten otros niveles educativos — confirma directamente que un plantel puede alojar múltiples escuelas/niveles.
- **Pista sobre alcance del Acuerdo 357**: se cita como fundamento legal de requisitos que aplican a *todos* los niveles de Básica (no solo Preescolar), como el acta de nacimiento del docente — sugiere que el Acuerdo 357 podría contener disposiciones generales más allá de solo ratios de Preescolar. Confirmar alcance completo cuando se obtenga el documento.
- **Refinamiento de la ventana de recepción de solicitudes**: este documento confirma "todos los días hábiles del año" para Preescolar — la misma frase que aparece como segunda mención en el documento de Inicial. Esto sugiere que es una **frase plantilla reutilizada** entre documentos, y que la mención de "septiembre a diciembre" (solo en el documento de Inicial) podría ser texto desactualizado. Sigue pendiente confirmar con SEDEQ.

### Fundamento normativo de la acreditación de ocupación legal del inmueble (consultado 2026-10-19)

Las cuatro figuras (escritura pública, arrendamiento, comodato, otro instrumento) tienen fundamento por nivel. Cada regla lleva su grado de certeza; lo no confirmado no debe imponerse como bloqueo.

| Nivel / ámbito | Fuente | Qué dice | Certeza |
|---|---|---|---|
| Media superior (particulares, RVOE federal) | Acuerdo 450, art. 23 (DOF 16-dic-2008; https://sidof.segob.gob.mx/notas/docFuente/5074629) | I: escritura pública **a nombre del particular**, registrada en el Registro Público de la Propiedad. II: arrendamiento **con ratificación de contenido y de firmas ante notario**. III: comodato **con ratificación de contenido y de firmas ante notario**. IV: otro instrumento jurídico que cumpla las formalidades aplicables y acredite posesión legal, con datos del inmueble, fecha de expedición, objeto, vigencia y, en su caso, autoridad que lo expidió. Las fracciones II–IV deben precisar que el uso será la prestación del servicio educativo. Art. 29: declaración bajo protesta de inmueble libre de controversias. | Texto leído directamente. DOF 16-dic-2008 (firmado 8-dic-2008), entra en vigor al día siguiente; abroga el Acuerdo 330. **No se halló reforma ni abrogación** en búsquedas de 2024–2025 (ausencia de evidencia, no confirmación; falta revisar el índice del DOF). Ámbito: RVOE federal de media superior; **no se aplica a Básica/Inicial estatal sin fundamento propio**, y no se verificó si SEDEQ lo aplica por delegación. |
| Superior (RVOE federal) | Acuerdo 17/11/17, art. 23, fr. I (https://sidof.segob.gob.mx/notas/docFuente/5504348) | Manifestación bajo protesta de contar con la documentación que acredite la ocupación legal, en propiedad o posesión: a) escritura pública a nombre del particular (inmuebles propios), b) arrendamiento, c) comodato, d) otro instrumento jurídico que cumpla las formalidades aplicables. El domicilio debe coincidir con el del Formato 1. El inmueble debe estar libre de controversias (art. 17). El artículo 23 **no menciona** ratificación notarial ni plazo mínimo de contrato; eso **no equivale a una exención**: remite a "las formalidades previstas en las disposiciones jurídicas aplicables" (derecho civil y normas estatales), que no se revisaron. | Publicado en DOF 13-nov-2017. Reformas verificadas: Acuerdo 08/04/20 (DOF 30-abr-2020) y Acuerdo 02/02/24 (DOF 28-feb-2024, abroga el 08/04/20 y el 23/09/20) modifican **solo el art. 22, párrafo 2** (meses de recepción de solicitudes); **ninguno toca el art. 23**. Falta consolidado vigente completo. |
| Básica / Inicial (SEDEQ) | Requisitos SEDEQ (ver arriba, "Hallazgos adicionales") | Cuatro figuras; fecha de firma, vigencia mínima de un ciclo escolar, uso educativo, superficie del predio, firma del solicitante o representante. Comodato ratificado ante notario **solo en Básica**. | Tomado de los documentos de requisitos ya incorporados; la página pública de trámites de SEDEQ (queretaro.gob.mx) no los publica en su vista principal y no se pudo contrastar. |
| Media superior (guía DGB 2025) | https://dgb.sep.gob.mx/storage/recursos/2025/01/i7hizRp2YK-2_GUIA_2025.pdf | PDF de imágenes; el texto no se pudo extraer. | **No verificado.** |

Consecuencias de diseño:
- La **ratificación notarial** es obligatoria para arrendamiento y comodato en media superior, para comodato en Básica, y no se exige en Superior ni (según lo documentado) en Inicial. Como la acreditación se captura por plantel, **siempre se captura el indicador y los datos de ratificación, y su obligatoriedad se resuelve por nivel en la revisión** (ver `PENDIENTE-dictamenes-por-nivel.md`).
- **No hay plazo mínimo de contrato** común a todos los niveles: el "ciclo escolar completo" proviene de los requisitos de Básica; media superior pide "vigencia" sin cifra, y Superior no fija plazo.
- La suficiencia jurídica de "otro instrumento" y de las facultades del arrendador o comodante la decide la autoridad; el sistema solo captura metadatos.

### Fundamento normativo de identidad y representación legal del solicitante (consultado 2026-10-20)

La Ley de Educación del Estado de Querétaro (LEEQ) **no regula** qué documentos de identidad o de representación debe presentar el solicitante — sus arts. 48-49 (los que rigen la autorización) solo exigen personal con preparación adecuada, instalaciones higiénicas/seguras/pedagógicas (con autorización por cada plantel) y planes y programas (salvo en básica). Los documentos de identidad concretos (INE, acta de nacimiento, CURP, Constancia de Situación Fiscal) son enteramente administrativos — provienen del documento interno de SEDEQ "Requisitos Autorización Educación Básica 2024", que no se pudo consultar directamente en esta investigación (no se logró abrir el PDF desde el portal público) — y deben tratarse como **"Pendiente de SEDEQ"**, no como requisito legal, mientras no se obtenga y cite textualmente.

**Representación (sí tiene fundamento legal confirmado)** — Ley de Procedimientos Administrativos del Estado de Querétaro (LPAEQ), Periódico Oficial No. 15, 27-feb-2009, vigente (reformas verificadas hasta 2022; no se verificó cambio en 2023-2026):

| Figura | Fundamento | Requisito exacto | Certeza |
|---|---|---|---|
| Representación de persona moral | LPAEQ art. 20, párr. 2 | "La representación de las personas físicas o morales ante las autoridades administrativas, deberá acreditarse mediante instrumento público." | Confirmado (texto leído; versión Prontuario 2023, no se contrastó la de 2026) |
| Representación de persona física (alternativa al poder notarial) | LPAEQ art. 20, párrs. 1-3 | Puede actuar "por sí o por medio de representante o apoderado"; para persona física también es válida una **carta poder firmada ante dos testigos, con firmas ratificadas ante la propia autoridad o ante fedatario público** — no exige poder notarial en todos los casos | Confirmado |
| Gestor / tercero autorizado | LPAEQ art. 20 | Se puede "autorizar, por escrito, a quien se estime pertinente para realizar trámites, gestiones y comparecencias"; la ley **no usa el término "gestor"** ni distingue poder general de poder especial | Confirmado |
| Límite a los requisitos exigibles | LPAEQ arts. 2 y 15, fr. VI | La autoridad no puede exigir requisitos no previstos en la norma aplicable al trámite | Confirmado |
| Reglamento de la LEEQ o de incorporación/revalidación | — | **No se encontró ningún reglamento vigente**; el art. 17 original de la LEEQ remitía a un reglamento del Gobernador, sustituido en la reforma de 2014 por una remisión genérica a "leyes federales y estatales y demás ordenamientos aplicables" | Histórico/no vigente (la remisión a reglamento del Gobernador ya no aplica) |

**Implicación de diseño**: el documento `poder_gestor` del catálogo (exigido hoy como "Poder general para actos de administración") podría ser más estricto de lo que exige la ley para persona física representada por tercero — la LPAEQ admite también carta poder ratificada ante dos testigos. Esto es una discrepancia a revisar con SEDEQ, no un cambio automático del catálogo.

**Pago de derechos y Formato de Solicitud** — fundamento fiscal (antes ausente en el Compendio):

| Documento | Fundamento | Requisito exacto | Certeza |
|---|---|---|---|
| Recibo de pago de derechos | Ley de Hacienda del Estado de Querétaro (LHEQ), P.O. No. 41, 21-jul-2014, Título Cuarto, Capítulo Sexto "Por los servicios prestados por las autoridades de educación" | En la versión consolidada revisada (no la de 2026), art. 130: autorización de educación básica/normal/formación de maestros = 64 UMA; RVOE media superior/formación para el trabajo = 100 UMA; RVOE superior = 170 UMA; cambio de titular = 64 UMA. **No se confirmó el número de artículo ni las cuotas en UMA vigentes para el ejercicio fiscal 2026** (hubo reforma fiscal en diciembre de 2025 que pudo modificarlas) | Documentado, no contrastado — requiere verificar contra el Prontuario 2026 antes de usarse como cifra definitiva |
| Pago exclusivo vía portal-tributario.queretaro.gob.mx | LHEQ art. 7 | Los pagos pueden hacerse "en las Administraciones Regionales, Oficinas Recaudadoras o a través de medios electrónicos" — la ley **no dice que el portal sea el único canal válido**; que lo sea en la práctica sería una regla operativa de SEDEQ/Finanzas, no legal | Confirmado el texto del art. 7; la exclusividad del portal es decisión administrativa, no legal |
| Firma autógrafa vs. electrónica | Ley de Firma Electrónica Avanzada del Estado de Querétaro y su Reglamento | La FEA "produce los mismos efectos jurídicos que la firma autógrafa", pero el usuario puede optar por firma autógrafa salvo que el trámite esté incorporado al Registro Estatal de Trámites para vía electrónica — **no se verificó si el trámite de incorporación de SEDEQ está habilitado para FEA** | Documentado, no contrastado |
| Autorización (SEDEQ) vs. RVOE (SEP/SEDEQ) son actos distintos | LEEQ arts. 13-VII, 14-III y 48; Ley General de Educación art. 146 (DOF 30-sep-2019) | Básica requiere "autorización expresa" de SEDEQ (exclusiva); para "estudios distintos" se otorga RVOE (concurrente con SEP), vigente 5 años. El art. 13-VII incluye Inicial como materia de autorización, pero el art. 56 la trata como servicio sin RVOE — hay una tensión interna en la ley, sin resolver aquí | Confirmado el texto; la tensión interna queda como observación |

---

## 5. Motor de Validación de Capacidad Instalada (funcionalidad — Etapa 2)

> Al capturar datos de instalaciones (Anexo 2) y personal (Anexo 1), el sistema calcula en tiempo real si la superficie y plantilla declaradas cumplen los mínimos normativos según el número de alumnos que se pretende atender, devolviendo advertencias/bloqueos automáticos.

### Cobertura actual: Educación Inicial (completa)

**Capacidad instalada por tipo de plantel** (determinada por superficie y equipamiento — fórmula derivada, no solo catálogo fijo):

| Tipo | Capacidad de alumnos |
|---|---|
| Tipo 1 | Hasta 10 |
| Tipo 2 | 11 a 50 |
| Tipo 3 | 51 a 100 |
| Tipo 4 | Más de 100 |

**Superficie de aulas**
- Lactantes (1 a 10 alumnos): mínimo 25 m² por aula.
- Maternales (1 a 15 alumnos): mínimo 25 m² por aula.
- Máximo: planta baja + 1 nivel.

**Áreas recreativas**: 1 m² por alumno (incluye lactantes), debe ubicarse en planta baja.

**Sala de usos múltiples**: 1.2 m² por niño (considerando lactantes B, C y maternales A, B). Si no caben todos: operar en 2 turnos.

**Sanitarios / control de esfínter**: 0.80 m² por infante + 1 bacinica por niño. Maternal B: 1 retrete por cada 15 alumnos, 1 lavabo por cada 30 alumnos.

**Pasillos**: ancho base 1.20 m; +0.60 m por cada 100 alumnos adicionales sobre 160.

**Puertas**: medidas mínimas — acceso principal y sala de usos múltiples: 1.20 m de ancho x 2.1 m de alto; aulas: 0.90 m de ancho x 2.1 m de alto.

**Escaleras y barandales**: prohibidas las escaleras helicoidales (de caracol). Pasamanos a 40 cm de altura (para niños) además del pasamanos para adultos; si el ancho de la escalera es mayor a 1.20 m, pasamanos en ambos lados; si es igual o mayor a 2.40 m, pasamanos intermedios adicionales. Barrotes verticales con separación máxima de 10 cm. Huella antiderrapante mínima de 25 cm, peralte máximo de 10 a 18 cm.

**Sanitarios para personal administrativo y docente**: 1 sanitario completo e independiente de los sanitarios de alumnos, para uso de adultos (hombres y mujeres).

**Filtro sanitario para Lactantes y Maternales**: área de recepción y revisión de los niños, equipada con mesa de cambio.

### Clasificación de estratos de edad por sala (Educación Inicial — dato de referencia)

| Sala | Rango de edad |
|---|---|
| Lactantes A | 45 días a 6 meses |
| Lactantes B | 7 a 12 meses |
| Lactantes C | 1 año a 1 año 6 meses |
| Maternal A | 1 año 7 meses a 2 años |
| Maternal B | 2 años a 2 años 11 meses |

### Tercera dimensión del motor de validación: mobiliario y equipo obligatorio (nuevo)

Además de superficie y personal, la norma de Educación Inicial exige **mobiliario y equipo específico por tipo de sala**, con ratios propios que también son cruzables contra el número de alumnos declarado.

**Principios generales cualitativos (no numéricos, pero sí verificables como checklist)**
- Cada sala debe contar con mobiliario y equipo suficiente para el número de usuarios que atenderá.
- El mobiliario, equipamiento y material didáctico debe ser apropiado a la edad, ligero, cómodo, de fácil aseo, en colores/materiales que favorezcan el desarrollo y eviten riesgos.
- Debe mantenerse en buenas condiciones de uso; lo dañado debe retirarse.
- Debe promover la manipulación, el descubrimiento y la actividad mental autónoma de los niños.
- **Todo mobiliario con riesgo de caer sobre menores o personal debe fijarse/anclarse** a piso, muro o techo.
- En áreas recreativas: mobiliario y juegos de materiales no tóxicos ni susceptibles de roturas, acordes a la edad, que estimulen la motricidad; preferentemente piso de caucho en juegos de motricidad amplia; bancos sin aristas ni acabados peligrosos.

**Ratios cuantitativos por sala** (verificado palabra por palabra contra `req_inicial.docx`, líneas 133-190 — corrige la versión anterior, que agrupaba Lactantes B y C de forma imprecisa)

| Sala | Artículo | Criterio (ratio) |
|---|---|---|
| Lactantes A | Cuna con barandal | 1 por cada 2 niños |
| Lactantes A | Colchoneta con forro de vinil (gateo) | 1 por niño |
| Lactantes A | Mueble para cambio de pañal con colchoneta | 1 por sala |
| Lactantes A | Silla para adulto con antebrazo (espacio de lactancia materna) | 1 por sala |
| Lactantes A | Silla porta bebé | 1 por niño |
| Lactantes A | Baño de artesa (incluye regadera de teléfono) | 1 por sala |
| Lactantes B | Cuna con barandal | 1 por cada 2 niños |
| Lactantes B | Colchoneta con forro de vinil (gateo) | 1 por niño |
| Lactantes B | Mueble para cambio de pañal con colchoneta | 1 por sala |
| Lactantes B | Espejo infantil (60x100 cm, puntas redondeadas) | 1 por sala |
| Lactantes B | Barra de apoyo | 1 por sala |
| Lactantes B | Silla porta bebé | 1 por niño |
| Lactantes B | Baño de artesa (incluye regadera de teléfono) | 1 por sala |
| Lactantes B | Repisa/mueble para material didáctico | 1 por sala |
| Lactantes B | Material didáctico adecuado a la edad | Suficiente para los menores de la sala |
| Lactantes C | Colchoneta con forro de vinil (gateo) | 1 por niño |
| Lactantes C | Mueble para cambio de pañal con colchoneta | 1 por sala |
| Lactantes C | Espejo infantil (60x100 cm) | 1 por sala |
| Lactantes C | Barra de apoyo | 1 por sala |
| Lactantes C | Baño de artesa (incluye regadera de teléfono) | 1 por sala |
| Lactantes C | Repisa/mueble para material didáctico | 1 por sala |
| Lactantes C | Material didáctico adecuado a la edad | Suficiente para los niños de la sala |
| Maternal A y B | Colchoneta con forro de vinil | 1 por niño |
| Maternal A y B | Silla infantil | 1 por niño |
| Maternal A y B | Mesa infantil | 1 por cada 6 niños |
| Maternal A y B | Mueble cambio de pañal con colchoneta, espejo infantil, repisa material didáctico, mueble guarda-mochilas, material didáctico | 1 por sala |
| Sala de Usos Múltiples | Silla infantil con cinturón (lactantes) | 1 por niño lactante |
| Sala de Usos Múltiples | Silla infantil (maternal) | 1 por niño maternal |
| Sala de Usos Múltiples | Mesa infantil | 1 por cada 6 niños |

> **Correcciones respecto a la versión anterior de esta tabla**: (1) Lactantes B también requiere **cuna con barandal** (antes solo se le atribuía a Lactantes A); (2) se agrega el ítem contable **"material didáctico adecuado a la edad"** en Lactantes B, Lactantes C y Maternal A/B (antes solo era un principio cualitativo general, no un ítem de equipo obligatorio); (3) se precisa que el baño de artesa incluye regadera de teléfono, y la silla para adulto de Lactantes A es específicamente para el espacio de lactancia materna.
> Este catálogo de mobiliario/equipo es específico de Educación Inicial **por decisión de alcance del MVP** (no una pregunta abierta) — ver la sección "Paso 3" (Mobiliario) para el razonamiento completo.

### Requisitos cualitativos de instalaciones — Anexo B (checklist, no numéricos)

Complementan los ratios de superficie ya documentados; son verificables como checklist sí/no dentro del Anexo 2:

- Las salas de atención deben ser amplias, ventiladas e iluminadas de forma natural, con áreas seguras para guardar equipo y materiales de uso diario.
- **Los patios o zonas de juego/recreo no pueden usarse como estacionamiento ni almacenamiento.**
- Debe señalizarse la prohibición de acceso de menores a áreas de riesgo (cocina, depósitos, almacenes, etc.).
- El inmueble debe contar con suministro de servicios básicos: agua, energía eléctrica, servicio telefónico, drenaje, y gas solo si se preparan alimentos.
- **Las salas de atención no pueden albergar funciones directivas o administrativas, ni usarse como almacén o bodega** — regla de uso exclusivo del espacio educativo.
- Base legal específica de protección civil para Educación Inicial: Artículos 41 al 49 Bis de la Ley General de Prestación de Servicios para la Atención, Cuidado y Desarrollo Integral Infantil, y correlativos de su Reglamento y de la ley estatal equivalente.

### Reglas de personal (doble regla cruzada)

1. **Por sala existente** (fijo, no proporcional): 1 responsable calificado por cada sala de Lactantes A, B, C, Maternal A y B. Más 1 Director Técnico obligatorio por plantel.
2. **Por número de alumnos en la sala** (proporcional): 1 asistente por cada 5 menores en salas de lactantes; 1 asistente por cada 10 menores en salas de maternales (redondeo hacia arriba).

**Perfiles válidos (Profesiograma — Anexo A)**

| Puesto | Documento que acredita |
|---|---|
| Director Técnico | Cédula profesional (licenciatura/posgrado) |
| Responsable de sala | Cédula profesional (licenciatura/posgrado) |
| Asistente | Documento oficial de formación académica (cédula, certificado técnico o certificación EC0435 CONOCER) |

Cada puesto tiene además un catálogo de carreras/certificaciones específicas válidas (útil para validar el campo "Estudios"/"Cargo" del Anexo 1 contra un catálogo permitido).

### Pendiente: Preescolar, Primaria y Secundaria (ratios de m²/alumno)

El documento de requisitos de Básica **no incluye** las proporciones de m²/alumno — remite a acuerdos secretariales externos aún no disponibles en el proyecto:
- **Acuerdo 357** (Preescolar)
- **Acuerdo 254** (Primaria)
- **Acuerdo 255** (Secundaria)

Sin estos acuerdos, el motor de validación automática de **superficie** solo cubre Educación Inicial. Las reglas de **personal** (sección 5.1 siguiente) sí están cubiertas para Inicial, Preescolar y Primaria vía Profesiogramas.

---

### Fundamento normativo de la Constancia de Seguridad Estructural y de Ocupación / DRO (consultado 2026-10-20; corregido 2026-10-20 contra una segunda lectura más profunda del mismo Reglamento)

Fuente: Reglamento de Construcción para el Municipio de Querétaro, texto con reformas hasta la Gaceta Municipal No. 14 (29-mar-2022) — no se halló reforma posterior en ninguna de las dos lecturas (no concluyente). **Solo cubre el Municipio de Querétaro (capital)**; no se investigaron otros municipios. Detalle completo y las 9 preguntas abiertas para SEDEQ: `docs/decisions/PENDIENTE-constancia-seguridad-estructural.md`.

> **Corrección respecto a una primera lectura parcial del mismo día**: una primera pasada de esta investigación (limitada a los artículos 1-142 y a la cédula de trámite TR-170100-002) concluyó erróneamente que el "Visto Bueno de Seguridad y Operación" no existe en Querétaro y que no hay renovación quinquenal. Una lectura posterior y más completa del Reglamento (arts. 273-297) corrige ambos puntos — ver tabla abajo. El Comprobante de Terminación de Obra (TR-170100-002) sigue siendo un trámite real y distinto, pero no es el equivalente de la Constancia de Seguridad Estructural.

| Hallazgo | Fundamento (artículo) | Certeza |
|---|---|---|
| Escuelas son construcción **Grupo A** ("cuya falla estructural podría causar la pérdida de un porcentaje elevado de vidas") | Reglamento de Construcción, art. 120 | **Confirmado**, solo Municipio de Querétaro |
| Las escuelas, por ser Grupo A, **requieren Visto Bueno de Seguridad y Operación** | Reglamento de Construcción, art. 274, inciso a) | **Confirmado** — corrige la conclusión anterior de que era terminología ajena a Querétaro |
| Grupo A: debe registrarse una **Constancia de Seguridad Estructural, renovada cada cinco años**, en la que un **Corresponsable en Seguridad Estructural** hace constar las condiciones de seguridad | Reglamento de Construcción, art. 277 | **Confirmado** — corrige la conclusión anterior de que no había renovación quinquenal |
| Cambio de uso del inmueble: Visto Bueno en 90 días naturales, con la Constancia de Seguridad Estructural "en su caso" | Reglamento de Construcción, art. 279 | **Confirmado** |
| El **DRO** da su responsiva al suscribir el Visto Bueno de Seguridad y Operación | Reglamento de Construcción, art. 289, fr. III | **Confirmado** — el DRO firma el VoBo; la Constancia de Seguridad Estructural la firma el **Corresponsable en Seguridad Estructural** (art. 277). **DRO y Corresponsable en Seguridad Estructural no son la misma calidad ni firman el mismo documento** |
| Registro, refrendo y padrón anual de DRO y Corresponsables | Reglamento de Construcción, arts. 291, 293-295, 297 | **Confirmado** el texto; aplicación práctica de refrendo pendiente de detalle |
| El Reglamento **no nombra** ningún instrumento llamado literalmente "Constancia de Seguridad Estructural **y de Ocupación**" | — | El nombre compuesto es de SEDEQ, no del Reglamento municipal — puede corresponder a la CSE del art. 277, al VoBo del art. 274, a la autorización de ocupación del art. 275, o a un formato propio que combine datos de varios |
| **Punto crítico — "el año de vigencia del registro del perito debe coincidir con el año de emisión de la constancia"** | — | **Ningún artículo del Reglamento revisado lo establece.** La regla proviene de los requisitos de SEDEQ de Básica citados por el propio Compendio, cuyos PDF no se pudieron leer directamente (error del servidor en ambas lecturas). **Pendiente de SEDEQ**, no regla jurídica confirmada |
| Sanciones al DRO/Corresponsable/propietario (incluye multa del 10% del valor catastral en ciertos supuestos) | Reglamento de Construcción, art. 333 | **Confirmado** |

**Implicación de diseño**: el catálogo (`constancia_seguridad_estructural`) probablemente debería distinguir, cuando SEDEQ lo confirme, entre el Visto Bueno de Seguridad y Operación (firma el DRO, art. 274/289-III) y la Constancia de Seguridad Estructural propiamente dicha (firma el Corresponsable en Seguridad Estructural, renovación quinquenal, art. 277) — hoy se capturan como un solo documento con un solo firmante, criterio que el Compendio ya marcaba como pendiente de confirmar con SEDEQ y que esta investigación no cierra. El refactor técnico correspondiente (campos de calidad del firmante, folio, vigencia declarada, retiro de la regla bloqueante de año) ya se implementó en paralelo — ver `docs/reports/2026-10-20-constancia-seguridad.md` y `docs/decisions/PENDIENTE-constancia-seguridad-estructural.md`.

**Implicación de diseño**: el nombre `constancia_seguridad_estructural` en el catálogo (`TiposDocumentosSeeder`) probablemente corresponde, en la práctica, al Comprobante de Terminación de Obra municipal (u a otro documento que SEDEQ exige específicamente y que no coincide con ningún trámite público municipal localizado) — esto debe confirmarse con SEDEQ antes de asumir que ambos son el mismo documento. La regla de "año de registro = año de emisión" **no tiene fundamento verificado** y no debería seguir operando como validación bloqueante sin que SEDEQ la confirme explícitamente (paralelo exacto al caso ya resuelto del plazo de 30 días del Dictamen de Uso de Suelo).

### Fundamento normativo del Visto Bueno de Protección Civil (consultado 2026-10-20)

Fuente: Reglamento de Protección Civil para el Municipio de Querétaro (texto consultado vía `municipiodequeretaro.gob.mx`; última reforma identificada en el texto: Periódico Oficial No. 62, 21-nov-2008 — **no se halló evidencia de una reforma posterior en esta búsqueda**, lo cual es ausencia de evidencia, no confirmación de que no exista). Solo cubre el Municipio de Querétaro (capital).

| Hallazgo | Fundamento | Certeza |
|---|---|---|
| El visto bueno de Protección Civil se exige antes de trámites de licencia de funcionamiento o construcción, cuando la Unidad lo considere necesario por el riesgo | Reglamento de Protección Civil Municipio de Querétaro, arts. 14-XVI y 26-XIV | **Confirmado** |
| **El texto no fija una vigencia en días o meses para el visto bueno en sí** | — | El Reglamento no establece un plazo de vigencia/caducidad del visto bueno; lo que sí fija el art. 14-XVI es un **plazo de 30 días naturales para acreditar cumplimiento mediante evidencia digital después de obtener el visto bueno vía Carta Compromiso** — es un concepto distinto de "vigencia del documento" |
| **Implicación directa para el sistema**: `vigencia_max_dias = 30` configurado hoy en `TiposDocumentosSeeder` para `visto_bueno_proteccion_civil` | COMPENDIO_MAESTRO (regla operativa interna) | **Sin fundamento verificado como vigencia del documento** — el "30 días" que sí existe en la norma es un plazo de acreditación posterior a la obtención del visto bueno (Carta Compromiso), no la vigencia del visto bueno ya emitido. Esto es estructuralmente el mismo problema ya resuelto para el Dictamen de Uso de Suelo (ver `docs/reports/2026-10-19-dictamen-uso-suelo.md`) y **debería revisarse con el mismo criterio** |
| Clasificación de riesgo: las escuelas **no tienen categoría propia** — se clasifican como cualquier otro inmueble por aforo (alto riesgo: más de 250 personas; mediano: 15-250; bajo: lo demás) y por superficie (más de 3,000 m² = alto riesgo) | Reglamento de Protección Civil Municipio de Querétaro, art. 23 | **Confirmado** — contradice una eventual asunción de que existen reglas de Protección Civil diferenciadas por nivel educativo; **no las hay** en este reglamento municipal |
| Requisitos según nivel de riesgo (alto: Programa Interno de Protección Civil, brigadas, dictámenes eléctrico/gas/estructural, simulacro, póliza de responsabilidad civil; mediano: plan de emergencia, constancias de capacitación, revisiones eléctrica/gas; bajo: botiquín, señalética, extintores, curso de primeros auxilios) | Reglamento de Protección Civil Municipio de Querétaro, art. 23 bis | **Confirmado** |

**Implicación de diseño**: la "vigencia" de 30 días bloqueante hoy configurada para Protección Civil está en la misma situación normativa que tenía el Dictamen de Uso de Suelo antes del 2026-10-19 (regla operativa sin fundamento verificado tratada como vigencia jurídica). No se modifica el sistema en esta tarea (fuera de alcance — solo investigación/documentación), pero el hallazgo debe registrarse como pendiente de decisión del dueño del proyecto, en paralelo al PENDIENTE de dictámenes por nivel.

---

## 5.1 Perfiles Profesionales Autorizados (Profesiogramas — Anexo A por nivel)

Fuente: `PROFESIOGRAMA_INICIAL.pdf`, `PROFESIOGRAMA_PREESCOLAR.pdf`, `PROFESIOGRAMA_PRIMARIA.pdf`, `PROFESIOGRAMA_SECUNDARIA.pdf` (ciclo escolar 2023-2024). Los cuatro niveles quedan cubiertos (ver detalle de Secundaria más abajo, con estructura distinta por asignatura).

Cada perfil define: función, área de conocimiento, perfil profesional (catálogo cerrado de carreras/títulos válidos) y documento que lo acredita (título+cédula, certificado, etc.). Esto sirve como catálogo de validación del campo "Estudios"/"Cargo" en el Anexo 1.

### Reglas de personal condicionadas por capacidad de alumnos (nueva regla cruzada)
- **Preescolar**: docente de Educación Física obligatorio **solo si la instalación tiene capacidad de 60 alumnos o más**.
- **Primaria**: docente de Educación Física obligatorio **por cada 60 alumnos o más en la escuela** (proporción, se puede requerir más de uno).

### Reglas de personal condicionadas por grado ofertado
- **Preescolar**: docente de Inglés obligatorio **a partir de 3º grado**.
- **Primaria**: docente de Inglés **obligatorio siempre** (todos los grados); docente de Computación obligatorio **a partir de 3º grado**.
- **Preescolar**: el "asistente de grupo/auxiliar" **no es obligatorio**, pero si la escuela decide tenerlo debe cumplir el perfil profesional exigido (certificado).

### Reglas de personal — Educación Inicial (detalle de perfiles, complementa sección 5)
- **Director Técnico/Académico** (1 por plantel): título y cédula profesional, nivel licenciatura/posgrado. Perfiles válidos: Lic. en Educación Preescolar, Puericultura y Educación Infantil, Intervención Educativa, Trabajo Social, Psicología, Médico, o profesionista afín (sujeto a revisión previa de la Dirección de Educación).
- **Responsable de sala** (Lactantes A/B/C y Maternal A/B): título y cédula, TSU o Lic. en Puericultura/Educación Preescolar/Enfermería o afín.
- **Asistente educativo de lactantes** (obligado 1 por cada 5 lactantes): certificado — puericultista, técnico en enfermería o asistente educativo.
- **Asistente educativo de maternal** (obligado 1 por cada 10 niños): mismo perfil que el anterior.
- **Responsable del filtro y área de fomento a la salud** (obligatorio, 1 por plantel): médico general/pediatra (título y cédula) o enfermera pediatra (nivel técnico/licenciatura).
- **Personal adicional según servicios ofrecidos**: nutriólogo (asesoría, no requiere planta), cocinera (si hay servicio de comedor), 1 auxiliar de cocina por cada 50 niños, encargada del banco de leche, 1 intendente (cobertura 0-40 niños).
- **Personal opcional**: director administrativo, secretaria, docente de educación física, docente de música, auxiliar de mantenimiento, auxiliar de lavandería.

### Reglas de personal — Preescolar
- **Director Técnico/Académico**: título y cédula, nivel lic/posgrado. Perfiles: Normalista, Lic. en Educación/Preescolar/Inicial/Pedagogía/Intervención Educativa, o afín con experiencia docente en educación básica.
- **Docente titular de grupo**: título y cédula, nivel lic/posgrado. Perfiles: Normalista, Lic. en Educación/Preescolar/Primaria/Especial, TSU en Puericultura, Pedagogía, Psicopedagogía, Psicología (Educativa), Intervención Educativa, Ciencias de la Educación, o afín.
- **Asistente de grupo/auxiliar** (no obligatorio, solo si se decide tener): certificado — asistente educativo o TSU en Puericultura y Educación Infantil (con título y cédula).
- **Docente de Educación Física** (obligatorio si capacidad ≥60 alumnos): título y cédula — Lic. en Educación Física/Ciencias del Deporte/Salud Física y Deporte/Entrenamiento Deportivo, o Entrenador Deportivo certificado por CONADE.
- **Docente de Inglés** (obligatorio a partir de 3º): certificado — Educación/Idiomas/Lenguas Modernas/Letras con especialidad en Inglés, o certificaciones reconocidas (TKT, CENNI, KET, PET, FCE, CAE, CPE, MCER).
- **Docente de asignaturas extracurriculares**: bachillerato, preparación técnica o profesional en la materia a impartir.

### Reglas de personal — Primaria
- **Director Técnico/Académico**: título y cédula, nivel lic/posgrado. Perfiles: Normalista, Lic. en Educación/Educación Básica/Primaria/Pedagogía/Intervención Educativa, o afín con experiencia docente en educación básica.
- **Docente titular de grupo**: título y cédula, nivel lic/posgrado. Perfiles: Normalista, Lic. en Educación/Primaria/Preescolar/Especial/Psicología Educativa/Pedagogía/Ciencias de la Educación/Intervención Educativa.
- **Docente de Educación Física** (obligado por cada 60 alumnos o más en la escuela): mismos perfiles que en Preescolar.
- **Docente de Inglés** (obligatorio, todos los grados): título y cédula o certificado — mismos perfiles/certificaciones que en Preescolar.
- **Docente de Computación** (obligatorio a partir de 3º grado): título y cédula — Informática, Sistemas Computacionales Administrativos, Tecnologías de la Información, Computación, o afín.
- **Docentes de asignaturas extracurriculares**: certificado o documento que acredite preparación en la materia (bachillerato o preparación técnica/profesional).

### Reglas de personal — Secundaria (estructura distinta: por asignatura, no por grupo)

Fuente: `PROFESIOGRAMA_SECUNDARIA.pdf` (13 páginas). A diferencia de Inicial/Preescolar/Primaria (docente titular de grupo genérico), en **Secundaria se exige un Docente Titular por cada asignatura**, cada una con su propio catálogo cerrado de carreras afines (título y cédula profesional, nivel licenciatura/posgrado, salvo excepciones señaladas).

**Asignaturas con perfil definido**: Biología, Español, Física, Formación Cívica y Ética, Geografía, Historia, Inglés, Matemáticas, Química, Artes Visuales, Danza, Música, Teatro, Informática, Educación Física, y Asignaturas Extracurriculares.

- La mayoría exige **título y cédula profesional** (nivel licenciatura/posgrado); las carreras aceptadas por asignatura son extensas (hasta ~30 opciones) y quedan sujetas a "revisión de mapa curricular" si es carrera afín no listada.
- **Inglés**: además de título/cédula, acepta certificaciones alternativas — TOEFL (580+), TKT, CENNI, KET, PET, FCE, CAE, CPE, MCER.
- **Educación Física**: acepta certificado CONADE o perfil de Entrenador Deportivo, además de las licenciaturas del área.
- No se encontraron en este documento reglas de proporción por capacidad de alumnos (a diferencia del umbral de 60 alumnos que sí aplica a Educación Física en Preescolar/Primaria).
- **Director Técnico**: mismo esquema que los otros niveles — título y cédula, nivel lic/posgrado. Perfiles: Educación Básica, Educación Media (cualquier especialidad), Intervención Educativa, Pedagogía, Psicología Educativa, Ciencias de la Educación, Maestría en Educación, o profesionista con experiencia probada en educación.

> **Nota de diseño**: dado el volumen de carreras aceptadas por asignatura (cientos de entradas), el catálogo completo no se reproduce aquí — vive en el PDF fuente. Si se requiere validación automática de "carrera vs. asignatura" en el Anexo 1, este catálogo deberá migrarse a una tabla/base de datos estructurada, no mantenerse como texto en este compendio.
> **Implicación en el modelo de datos**: el Anexo 1 (Plantilla de Personal) necesita, para Secundaria, un campo adicional de "asignatura que imparte" por cada docente — dato que no aplica de la misma forma en niveles con docente de grupo genérico.

---

### Reglas transversales a todos los niveles (Inicial, Preescolar, Primaria, Secundaria)

- El Director Técnico/Académico **no debe ejercer funciones docentes frente a grupo** — su rol es exclusivamente académico, de coordinación y administrativo.
- Se sugiere experiencia previa como docente para el puesto de Director (mínimo 3 años en Inicial; sin cifra explícita en Preescolar/Primaria, pero se menciona como deseable).
- Todos los títulos/certificados/diplomas deben provenir de instituciones con **reconocimiento de validez oficial de estudios**.
- Todo el personal debe acreditar **cursos de primeros auxilios** avalados por institución pública de salud (SESEQ, ISSSTE, IMSS, Cruz Roja) o Protección Civil.
- Se promueve (no obligatorio) la participación del personal en cursos de convivencia escolar e inclusión educativa.

---

## 5.2 Motor de Validación — Preescolar, Primaria y Secundaria (Acuerdos 357, 254 y 255)

> **Hueco cerrado**: se obtuvo el texto completo de los tres Acuerdos Secretariales directamente de fuentes oficiales de la SEP (Acuerdo 357 vía `portal.sepyc.gob.mx`, Acuerdo 254 vía `normatecainterna.sep.gob.mx`, Acuerdo 255 vía `evaluacion.septlaxcala.gob.mx`). Nota: estos acuerdos son de alcance federal (redactados originalmente para el entonces Distrito Federal); Querétaro/SEDEQ puede tener adecuaciones locales menores, pero constituyen el marco normativo base al que remiten los documentos de SEDEQ ya revisados.

### Preescolar (Acuerdo 357)

**Superficie**
- Superficie construida total: **1.00 m² por educando** (planta baja + máximo 2 niveles).
- Aulas: **1 m² por educando** + 2 m² adicionales para el espacio del maestro.
- Área de recreación: **1.25 m² por educando** (considerando la inscripción esperada de los tres grados); debe ubicarse en planta baja.
- Aula de usos múltiples: superficie mínima equivalente a 1.5 veces el aula mayor del plantel.

**Puertas**: acceso principal 1.20 m mínimo; aulas 0.90 m; aula de usos múltiples 1.60 m; altura mínima 2.10 m en todos los casos.

**Pasillos/corredores**: 1.20 m de ancho mínimo, 2.30 m de altura; +0.60 m por cada 100 usuarios adicionales sobre 160.

**Escaleras**: 1.20 m de ancho hasta 160 educandos, +0.60 m por cada 75 educandos adicionales (máx. 2.40 m); huella antiderrapante mínima 25 cm, peralte máximo 10-18 cm; barandales mínimo 90 cm de altura, elementos verticales con separación máxima 10 cm.

**Sanitarios** (separados por género, ratio retretes/lavabos):

| Rango de educandos | Retretes | Lavabos |
|---|---|---|
| Hasta 20 | 1 | 1 |
| 21 a 50 | 2 | 2 |
| 51 a 75 | 3 | 2 |
| 76 a 150 | 4 | 3 |
| Adicional c/incremento sobre 75 | +2 | +2 |

**Personal**: Educación Física **obligatoria si el número de educandos es mayor a 60**. Director Técnico: Profesor/Licenciado en Educación Preescolar egresado de escuela normal, o profesionista titulado en licenciatura afín a la educación.

### Primaria (Acuerdo 254)

**Superficie**
- Superficie total del predio: **2.50 m² por alumno**.
- Superficie de aulas: **0.90 m² por alumno**.
- Altura de aulas: siempre **2.70 m**.

**Dimensiones de aulas por capacidad (edificios construidos ex-profeso)**:

| Capacidad | Superficie |
|---|---|
| 1 a 15 alumnos | 24 m² |
| 16 a 30 alumnos | 48 m² |
| 31 a 40 alumnos | 64 m² |

(Para construcciones adaptadas: mínimo 12 m² por salón, equivalente a 0.90 m²/alumno.)

**Puertas**: aulas 0.90 m mínimo; salidas de emergencia y acceso a vía pública 1.20 m mínimo; auditorios/salones de reunión 2 puertas de 0.90 m (total 1.80 m); altura mínima 2.10 m.

**Escaleras**: 1.20 m de ancho hasta 360 alumnos, +0.60 m por cada 180 alumnos adicionales (máx. 2.40 m); huella 25 cm mínimo, peralte 18 cm máximo; barandales 0.90 m de altura mínima.

**Pasillos**: 1.20 m ancho, 2.30 m altura, +0.60 m por cada 100 usuarios adicionales.

**Iluminación**: natural en al menos 1/5 de la superficie del aula; mínimo 150 luxes en salones, 330 luxes en auditorios.

**Sanitarios**: hombres — 1 retrete + 1 mingitorio por cada 30 alumnos; mujeres — 1 retrete por cada 20 alumnas; 1 lavabo por cada 40 alumnos (ambos géneros); concentración máxima en planta baja.

**Bebederos**: 1 por cada 40 alumnos, con agua purificada.

**Patios**: medida igual a la mitad de la altura de los paramentos que los delimitan, mínimo 3 m en construcciones adaptadas.

**Biblioteca**: mínimo 50 títulos por grado escolar (300 en total para los 6 grados), actualizable cada ciclo escolar.

**Personal**: Educación Física **obligatoria si >60 alumnos**. Documentos adicionales del personal no capturados antes: **cartilla del Servicio Militar Nacional liberada** (varones mexicanos), y **constancia de "Capacitación Didáctica"** para docentes con perfil de Lic. en Educación Especial, Psicología Educativa o Pedagogía.

### Secundaria (Acuerdo 255)

**Superficie**
- Superficie total del predio: **2.50 m² por alumno** (igual que Primaria).
- Superficie de aulas: **0.90 m² por alumno** (igual que Primaria).
- Área de recreación: **1.25 m² por alumno**, considerando inscripción esperada de los 3 grados.
- Construcción: máximo **3 niveles** (Primaria no especifica límite explícito de niveles en este punto).

**Dimensiones de aulas por capacidad** (distintas a Primaria pese a compartir el ratio general):

| Capacidad | Superficie |
|---|---|
| 1 a 15 alumnos | 24 m² |
| 16 a 30 alumnos | 36 m² |
| 31 a 40 alumnos | 48 m² |

**Puertas**: acceso principal 1.20 m; aulas 0.90 m; salidas de emergencia 1.20 m; auditorios/salones de reunión 1.80 m; altura mínima 2.10 m.

**Escaleras**: mismo esquema que Primaria (1.20 m hasta 360 alumnos, +0.60 m por cada 75 alumnos adicionales, máx. 2.40 m; huella 25 cm, peralte 18 cm máx.; barandales 0.90 m).

**Pasillos**: 1.20 m, 2.30 m altura, +0.60 m por cada 100 usuarios adicionales (igual que los demás niveles).

**Iluminación**: natural en al menos 1/5 de la superficie del aula; **250 luxes en aulas** (más que Primaria), 330 luxes en talleres/laboratorios/auditorio.

**Ventilación**: área de aberturas no inferior al 5% del área del aula.

**Sanitarios** (tabla propia, distinta a Primaria y Preescolar):

| Rango de alumnos | Retretes | Lavabos |
|---|---|---|
| Cada 50 alumnos | 3 | 2 |
| Hasta 75 | 4 | 2 |
| 76 a 150 | 6 | 2 |
| Adicional c/75 alumnos o fracción | +2 | +2 |

Además: 1 mingitorio por cada 2 retretes en sanitarios de hombres.

**Agua potable**: 25 litros/alumno/turno.

**Patios**: mitad de la altura de los paramentos que los limitan, mínimo **2.50 m** (menor que el mínimo de 3 m de Primaria).

**Áreas recreativas/deportivas**: espacio mínimo de **200 m²**, sin colindar con bardas o escaleras.

**Estacionamiento**: 1 cajón por cada 40 m² construidos, cajones de 5.00 x 2.40 m.

**Biblioteca**: mínimo 300 títulos totales (no por grado, coincide con lo ya documentado).

**Laboratorio polifuncional**: especificación extremadamente detallada de instalaciones (regadera de emergencia, extintores, 6 núcleos de servicio, salidas de agua/gas/corriente), mobiliario (mesas de trabajo, bancos, estantes) y equipo/reactivos específicos por materia (biología, física, química) — el catálogo completo de reactivos e instrumental no se reproduce aquí por su volumen; queda como referencia en la fuente oficial para cuando se diseñe el formulario de inventario de laboratorio.

**Personal — novedad importante**: si hay más de 60 alumnos, es obligatorio contar no solo con **profesor de Educación Física**, sino también con **un trabajador social y un prefecto** (amplía lo que ya sabíamos, que solo mencionaba Educación Física).

**Requisitos de nacionalidad**: los docentes de **Historia de México, Formación Cívica y Ética, y Geografía de México deben ser de nacionalidad mexicana** — regla no capturada antes.

**Documentos adicionales del personal**: cartilla SMN liberada (varones mexicanos); para docentes de inglés y actividades tecnológicas, certificado de preparatoria o equivalente más documentos que acrediten preparación en la materia.

### Comparativo rápido entre los tres niveles

| Aspecto | Preescolar | Primaria | Secundaria |
|---|---|---|---|
| Superficie predio | (no especifica ratio directo) | 2.50 m²/alumno | 2.50 m²/alumno |
| Superficie aula | 1 m²/alumno + 2m² maestro | 0.90 m²/alumno | 0.90 m²/alumno |
| Aula 16-30 alumnos | — | 48 m² | 36 m² |
| Aula 31-40 alumnos | — | 64 m² | 48 m² |
| Educación Física obligatoria | >60 alumnos | >60 alumnos | >60 alumnos (+ trabajador social + prefecto) |
| Iluminación aulas (luxes) | — | 150 | 250 |
| Patio mínimo (adaptado) | — | 3 m | 2.50 m |
| Biblioteca | — | 50 títulos/grado | 300 títulos total |

> Con esto, el Motor de Validación de Capacidad Instalada queda **completo para los 4 niveles de Educación Básica** (Inicial, Preescolar, Primaria, Secundaria) tanto en superficie como en personal.

---

## 6. Etapas del proyecto

**Etapa 1 — Núcleo de expedientes y captura**
Modelo de datos (Plantel, Escuela, Nivel), preregistro, selección de tipo de responsable, carga de documentos según checklist condicional, y generación de PDFs prellenados para firma y resubida.

**Etapa 2 — Validación y revisión interna**
Motor de validación cruzada de área, capacidad y personal por nivel, verificación de consistencia entre documentos, y flujo de revisión para SEDEQ con estados de expediente y notificaciones.

**Etapa 3 — Reutilización y trámites subsecuentes**
Precarga de datos ya validados de un plantel para nuevas escuelas o niveles, gestión de modificaciones a expedientes existentes, y reportes o tableros de seguimiento para SEDEQ.

---

## 7. Pendientes / huecos abiertos

- [ ] Confirmar con SEDEQ si "Constancia de Seguridad Estructural" y "Carta responsiva del DRO" realmente se manejan como un solo documento (decisión de diseño adoptada, basada en evidencia documental) o como dos archivos separados en la práctica.
- [ ] Confirmar si Querétaro/SEDEQ tiene adecuaciones locales a los Acuerdos 357/254/255 (de alcance federal, redactados originalmente para el entonces Distrito Federal) — los ratios documentados son la base normativa nacional, pero podrían existir ajustes estatales.
- [ ] Aclarar con SEDEQ la contradicción del documento de Educación Inicial sobre el periodo de recepción de solicitudes (¿es "septiembre a diciembre" o "todos los días hábiles del año"?).
- [x] ~~Confirmar si existe un catálogo de mobiliario/equipo obligatorio equivalente al de Educación Inicial para Preescolar/Primaria/Secundaria/Media Superior/Superior/Posgrado.~~ **Reencuadrado (2026-09-11)**: no es una pregunta bloqueante — es una decisión de alcance del MVP (Educación Inicial únicamente, ver sección 5). Sigue pendiente para una expansión futura del MVP, no para el MVP mismo.
- [ ] Recabar documentación normativa de requisitos de infraestructura/personal/mobiliario para el nivel de **Posgrado** (nivel recién confirmado, sin documentos revisados aún).
- [ ] Confirmar tratamiento de docentes de idioma inglés (requieren certificación oficial adicional, mencionado en procedimiento de Básica).
- [ ] Definir estructura de "expediente por docente" (cédula + acta de nacimiento por cada uno, mencionado en procedimiento de Básica) dentro del Anexo 1.
- [ ] Definir Pasos 4 en adelante del flujo (aún no discutidos).
- [ ] Detallar los campos exactos de "Plan de estudios y modalidad" (más allá de escolarizada/no escolarizada/mixta) — la Matrícula ya quedó detallada.
- [ ] Confirmar a qué nivel exacto aplica "Cuatrimestre" en la captura de Matrícula (¿Superior, Posgrado, o ambos?), y detallar la estructura de captura de Matrícula para Superior/Posgrado.
- [ ] Localizar la fuente documental del "Formato de autoevaluación CEPPEMS" (Media Superior) — dato nuevo mencionado por el usuario, sin confirmar en los documentos revisados hasta ahora.
- [ ] Confirmar si el orden de 6 pasos por nivel (inmueble, infraestructura, mobiliario, plan de estudios, plantilla docente, matrícula) aplica igual a Media Superior y Superior, o tiene variantes.
- [ ] Revisar mockups compartidos (app "incorporacion-app") contra este compendio cuando se puedan visualizar (capturas de pantalla pendientes).
- [ ] **(2026-10-20)** Obtener y citar textualmente el documento interno de SEDEQ "Requisitos Autorización Educación Básica 2024" — es la fuente real de los requisitos documentales concretos (identidad, representación) y no se pudo abrir desde el portal público en esta investigación.
- [ ] **(2026-10-20)** Confirmar el artículo y las cuotas en UMA vigentes para 2026 del pago de derechos en la Ley de Hacienda del Estado de Querétaro (hubo reforma fiscal en diciembre de 2025); lo documentado en este Compendio corresponde a una versión consolidada anterior, no contrastada contra el Prontuario 2026.
- [ ] **(2026-10-20)** Verificar con SEDEQ si el documento que el sistema llama "Constancia de Seguridad Estructural y de Ocupación" corresponde al Comprobante de Terminación de Obra municipal (TR-170100-002) o a otro documento distinto; no se localizó un trámite municipal con ese nombre literal.
- [ ] **(2026-10-20)** Obtener el texto del artículo 295 del Reglamento de Construcción del Municipio de Querétaro (requisitos del Corresponsable en Seguridad Estructural) y revisar el reglamento completo (excede 140 páginas; solo se revisaron fragmentos) para la regla de "año de registro = año de emisión de la constancia" y la eventual renovación quinquenal.
- [ ] **(2026-10-20)** Verificar si existe reforma posterior a 2008 (P.O. No. 62, 21-nov-2008) del Reglamento de Protección Civil para el Municipio de Querétaro.
- [ ] **(2026-10-20)** Investigar los reglamentos de construcción y protección civil de los municipios de Querétaro distintos a la capital (Corregidora, El Marqués, San Juan del Río, Pedro Escobedo, Huimilpan, etc.) — esta investigación solo cubrió el Municipio de Querétaro.
- [ ] **(2026-10-20)** Completar la investigación normativa de las categorías F (Plano o croquis del inmueble), G (Certificado de número oficial — ya con hallazgo parcial, ver tabla de trámites municipales) e I (documentos específicos por nivel: Formato CEPPEMS de Media Superior, Formato COEPES/ENOPEES de Superior, requisitos de Posgrado) — no se investigaron a fondo en esta pasada por límite de alcance de la sesión; quedan pendientes para una siguiente ronda de investigación normativa.

---

## 8. Fuentes revisadas

- `CHECK_LIST_DOCUMENTOS_INCORPORACIÓN.xlsx`
- `FORMATO_DE_SOLICITUD_EDUCACIÓN_BÁSICA.docx`
- `REQUISITOS_DE_EDUCACIÓN_INICIAL.docx`
- `REQUISITOS_Y_PROCEDIMIENTO_PARA_LA_AUTORIZACIÓN_DEL_TIPO_BÁSICA.docx`
- `ANEXO_1_Plantilla.docx`
- `ANEXO_2_Instalaciones.docx`
- `PROFESIOGRAMA_INICIAL.pdf`
- `PROFESIOGRAMA_PREESCOLAR.pdf`
- `PROFESIOGRAMA_PRIMARIA.pdf`
- Acuerdo Secretarial 357 (Preescolar) — `portal.sepyc.gob.mx/consultas/marcolegal/acuerdos/acuerdo_357.pdf`
- Acuerdo Secretarial 254 (Primaria) — `normatecainterna.sep.gob.mx` (DOF 26/marzo/1999)
- Acuerdo Secretarial 255 (Secundaria) — `evaluacion.septlaxcala.gob.mx/docs/ACUERDO 255 AUTORIZACION SECUNDARIA.pdf` (DOF 13/abril/1999)
- `PROFESIOGRAMA_SECUNDARIA.pdf`
- Ley de Educación del Estado de Querétaro — `legislaturaqueretaro.gob.mx/app/uploads/2016/01/LEY026.pdf` (consultado 2026-10-20)
- Ley de Procedimientos Administrativos del Estado de Querétaro — Prontuario 2023, `queretaro.gob.mx` (consultado 2026-10-20)
- Ley de Hacienda del Estado de Querétaro — `queretaro.gob.mx` y Orden Jurídica Nacional (consultado 2026-10-20; versión 2026 no contrastada)
- Ley de Ingresos del Estado de Querétaro, Ejercicio Fiscal 2026 — `queretaro.gob.mx` (consultado 2026-10-20)
- Reglamento de Construcción para el Municipio de Querétaro — `webservices.municipiodequeretaro.gob.mx` (consultado 2026-10-20; texto con reformas hasta Gaceta Municipal No. 14, 29-mar-2022; arts. 120, 274, 277, 279, 289, 291-297, 333)
- Catálogo de trámites municipales de Desarrollo Urbano, Municipio de Querétaro — `tramitesqro.municipiodequeretaro.gob.mx` (consultado 2026-10-20)
- Reglamento de Protección Civil para el Municipio de Querétaro — `municipiodequeretaro.gob.mx` (consultado 2026-10-20)

---

## 9. Hallazgos normativos y decisiones pendientes de formalización (2026-10-20)

> Sección generada por la auditoría normativa del 2026-10-20 (ver spec de la tarea). Resume lo confirmado, lo corregido y lo que sigue abierto. No cierra ningún `PENDIENTE-*.md` ni crea ADR — esas decisiones siguen correspondiendo al dueño del proyecto y a SEDEQ.

### Normas confirmadas con texto leído (nuevas en esta pasada)

- **Ley de Educación del Estado de Querétaro (LEEQ)**, P.O. No. 55, 31-jul-2009, vigente — arts. 13-VII, 14-III, 48, 49, 55, 56. Fija la competencia de SEDEQ y los únicos tres requisitos legales de fondo para la autorización (personal, instalaciones, planes/programas). No regula documentos de identidad ni representación.
- **Ley de Procedimientos Administrativos del Estado de Querétaro (LPAEQ)**, P.O. No. 15, 27-feb-2009 — arts. 2, 13, 14, 15-VI, 20. Única fuente legal confirmada para los requisitos de representación (persona física: carta poder ratificada o instrumento público; persona moral: instrumento público) y para el límite a lo que la autoridad puede exigir.
- **Reglamento de Construcción para el Municipio de Querétaro**, texto con reformas hasta Gaceta Municipal No. 14 (29-mar-2022) — art. 120 (escuelas = Grupo A), art. 274-a (Visto Bueno de Seguridad y Operación obligatorio para Grupo A), art. 277 (Constancia de Seguridad Estructural, renovación quinquenal, firma el Corresponsable en Seguridad Estructural), art. 279 (cambio de uso), art. 289-III (el DRO firma el VoBo), arts. 291/293-295/297 (registro y refrendo de DRO y Corresponsables), art. 333 (sanciones). Detalle completo en `docs/decisions/PENDIENTE-constancia-seguridad-estructural.md`.
- **Reglamento de Protección Civil para el Municipio de Querétaro**, última reforma identificada P.O. No. 62, 21-nov-2008 — arts. 14-XVI, 23, 23 bis, 26-XIV, 28, 44, 52 (visto bueno, clasificación de riesgo por aforo/superficie, requisitos por nivel de riesgo).

### Normas abrogadas o con reformas detectadas

- Ninguna abrogación nueva detectada en esta pasada sobre lo ya documentado en §4 (Acuerdo 450, Acuerdo 17/11/17 siguen vigentes según lo ya verificado el 2026-10-19).
- El art. 17 original de la LEEQ (remisión a un reglamento del Gobernador) fue sustituido en la reforma de 2014 — se etiqueta como histórico/no vigente.

### Reglas existentes sin fundamento confirmado (corrección de grado de certeza)

| Regla | Estado anterior en el Compendio | Estado tras esta investigación |
|---|---|---|
| "El año de registro del perito/DRO debe coincidir con el año de emisión de la constancia" | Tratada como regla de validación bloqueante del sistema | **No encontrada en ningún artículo del Reglamento de Construcción** (120, 274, 277, 279, 289, 291, 293-297, 333 revisados) — Pendiente de SEDEQ. Ya se retiró como bloqueo en el código (ver `docs/reports/2026-10-20-constancia-seguridad.md`); el vencimiento del registro ahora solo avisa |
| DRO y Corresponsable en Seguridad Estructural tratados como una sola calidad de firmante | Un solo campo de "DRO" en el formulario | **Son calidades distintas**: el DRO firma el Visto Bueno de Seguridad y Operación (art. 289-III); el Corresponsable en Seguridad Estructural firma la Constancia de Seguridad Estructural (art. 277). El sistema ya captura la calidad del firmante (DRO / Corresponsable / otra) en paralelo a esta investigación |
| `vigencia_max_dias = 30` para `visto_bueno_proteccion_civil` | Configurado como vigencia bloqueante | El único "30 días" que existe en el Reglamento de Protección Civil es un **plazo de acreditación de cumplimiento posterior a la obtención del visto bueno (Carta Compromiso)**, no la vigencia del documento — misma situación que tuvo el Dictamen de Uso de Suelo antes del 2026-10-19 |
| Documentos de identidad (INE, acta de nacimiento, CURP, Constancia de Situación Fiscal) como requisito del trámite | Documentados sin fundamento legal explícito | Confirmado que **no provienen de la LEEQ** — son administrativos de SEDEQ; sin poder citar el documento fuente textualmente en esta pasada |

### Requisitos distintos por nivel educativo (nuevo, no documentado antes)

- La clasificación de riesgo de Protección Civil del Municipio de Querétaro **no distingue por nivel educativo** — solo por aforo y superficie del inmueble, igual para todos los niveles.
- La vigencia del Dictamen de Uso de Suelo (ya resuelto el 2026-10-19) tampoco distingue por nivel — es "la que indique el documento", sin relación con el nivel educativo que se tramite.

### Preguntas pendientes para SEDEQ (nuevas, se suman a `PENDIENTE-dictamenes-por-nivel.md`)

1. ¿El documento interno "Requisitos Autorización Educación Básica 2024" puede obtenerse en texto completo para citarlo en el Compendio? (Dirección de Educación, Blvd. Centro Sur 75, tel. 442 262 0330 ext. 301, según el hallazgo de la investigación).
2. ¿La "Constancia de Seguridad Estructural y de Ocupación" que pide SEDEQ corresponde a la Constancia de Seguridad Estructural del art. 277 (firma el Corresponsable, renovación quinquenal), al Visto Bueno de Seguridad y Operación del art. 274 (firma el DRO), a ambos en un solo documento, o a un formato propio de SEDEQ? Ver las 9 preguntas completas en `docs/decisions/PENDIENTE-constancia-seguridad-estructural.md`.
3. ¿De dónde proviene la regla de "año de registro del perito = año de emisión de la constancia"? ¿Es un criterio interno de revisión de SEDEQ, no normado en ningún reglamento?
4. ¿SEDEQ acepta la carta poder ratificada ante dos testigos (LPAEQ art. 20) como alternativa al poder notarial para persona física representada por gestor, o exige siempre instrumento público en la práctica?
5. ¿Qué cuota de derechos aplica para 2026 y bajo qué artículo exacto de la Ley de Hacienda del Estado de Querétaro reformada en diciembre de 2025?
6. Para inmuebles fuera del Municipio de Querétaro (Corregidora, El Marqués, San Juan del Río, etc.), ¿qué documento y autoridad equivalentes acepta SEDEQ? El Reglamento de Construcción estudiado solo aplica a la capital.

### Diferencias observadas entre normativa y formularios/sistema actuales

- El catálogo (`TiposDocumentosSeeder`) nombra `constancia_seguridad_estructural` como un solo documento, pero el Reglamento distingue dos figuras con firmantes distintos (Visto Bueno de Seguridad y Operación → DRO; Constancia de Seguridad Estructural → Corresponsable en Seguridad Estructural) — ver tabla de hallazgos arriba y `docs/decisions/PENDIENTE-constancia-seguridad-estructural.md`.
- El campo `vigencia_max_dias = 30` de `visto_bueno_proteccion_civil` no corresponde a ninguna vigencia normada del documento.
- El documento `poder_gestor` (persona física con gestor) podría estar exigiendo más de lo que permite la LPAEQ (que admite carta poder ratificada, no solo poder notarial) — confirmar antes de ajustar el catálogo.

### Fuentes oficiales consultadas en esta pasada (2026-10-20)

- Ley de Educación del Estado de Querétaro — `legislaturaqueretaro.gob.mx/app/uploads/2016/01/LEY026.pdf`
- Ley de Procedimientos Administrativos del Estado de Querétaro — `queretaro.gob.mx` (Prontuario 2023)
- Ley de Hacienda del Estado de Querétaro — `queretaro.gob.mx/documents/.../XVIII.+LEY+DE+HACIENDA...pdf`; versión consolidada de la Orden Jurídica Nacional
- Ley de Ingresos del Estado de Querétaro para el Ejercicio Fiscal 2026 — `queretaro.gob.mx/documents/.../20_LEY_DE_INGRESOS...2026.pdf`
- Reglamento de la Ley de Firma Electrónica Avanzada del Estado de Querétaro — `ceaqueretaro.gob.mx`
- Reglamento de Construcción para el Municipio de Querétaro — `webservices.municipiodequeretaro.gob.mx/mejora_regulatoria/bin/files/REGLAMENTO%20DE%20CONSTRUCCI%C3%93N.pdf`
- Catálogo de trámites municipales de Desarrollo Urbano — `tramitesqro.municipiodequeretaro.gob.mx`
- Cédula TR-170100-002 (Comprobante de Terminación de Obra) — `tramitesqro.municipiodequeretaro.gob.mx/cedula.php?id=TR-170100-002`
- Reglamento de Protección Civil para el Municipio de Querétaro — `municipiodequeretaro.gob.mx/municipio/repositorios/transparencia/a66/sayu/REGLAMENTODEPROTECCIONCIVILPARAELMUNICIPIODEQUERETARO.pdf`

### Confirmación de alcance

Solo se modificó `docs/COMPENDIO_MAESTRO_Sistema_Incorporacion.md` en esta tarea. No se tocó código, migraciones, seeders, reglas de validación, pruebas ni `docs/decisions/PENDIENTE-dictamenes-por-nivel.md`. No se hizo push ni merge.
