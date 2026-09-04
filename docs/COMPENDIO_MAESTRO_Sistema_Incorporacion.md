# Compendio Maestro — Sistema de Incorporación de Escuelas (SEDEQ)

> Documento vivo. Se actualiza conforme avanza la definición del proyecto y se comparten nuevos documentos normativos.
> Última actualización: **hueco mayor cerrado** — obtenidos y documentados los Acuerdos Secretariales 357 (Preescolar), 254 (Primaria) y 255 (Secundaria) directamente de fuentes oficiales SEP. El Motor de Validación de Capacidad Instalada queda completo para los 4 niveles de Educación Básica (superficie + personal).

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
| 4 | Dictamen de Uso de Suelo vigente | ✔️ | ✔️ | **Vigencia máxima: 1 mes de antigüedad** (regla operativa confirmada por el usuario, no aparece en los documentos normativos fuente) |
| 5 | Constancia de Seguridad Estructural y de Ocupación (incluye datos del perito/DRO) | ✔️ | ✔️ | Un solo documento a cargar; el formulario de captura incluye campos del perito (nombre, cédula profesional, número de registro como DRO, autoridad que expide, vigencia del registro) además de los datos de la propia constancia. Regla de validación: el año del registro del perito debe coincidir con el año de emisión de la constancia. |
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
   - **Instalaciones para actividades físicas y recreativas** (cada una con superficie en m²): cancha de usos múltiples, chapoteadero, arenero, zona de juegos, áreas verdes, área de recreo, auditorio, **campo de fútbol** (tipo de superficie, formato: 11/7/5/baby fut — dato nuevo, sin fuente documental, aportado por el usuario), otras.
     > **Discrepancia con la fuente**: en `ANEXO_2_Instalaciones.docx`, "Auditorio o Aula Magna" aparece clasificado en la sección de instalaciones especiales/adicionales, **no** en actividades físicas y recreativas. Pendiente confirmar si la reclasificación es una decisión operativa intencional de SEDEQ o solo agrupación informal.
   - **Instalaciones adicionales** (cada una con superficie en m²): centro de documentación/biblioteca (física o **virtual** — si es virtual, requiere captura del contrato de servicio como documento; dato nuevo sin fuente documental confirmada, aportado por el usuario; detalle de material si es física: libros, periódicos, revistas especializadas, diapositivas, videos, películas, discos compactos, software, otro), taller(es) (con asignatura asociada), **laboratorio polifuncional**, **salón de usos múltiples**, cocina, comedor, sala de artes (con asignatura asociada), otras.
3. **Mobiliario** — captura **separada** de la infraestructura física, no mezclada. El formulario inicia con un selector del **nivel educativo que se pretende incorporar** (confirmado: son 7 opciones — Inicial, Preescolar, Primaria, Secundaria, Media Superior, Superior, Posgrado), que determina qué catálogo de mobiliario se muestra. Para Inicial ya tenemos el catálogo completo de ratios, verificado palabra por palabra contra la fuente (ver Motor de Validación, sección 5). Pendiente confirmar si existe catálogo equivalente para Preescolar/Primaria/Secundaria/Media Superior/Superior/Posgrado (probablemente en los Acuerdos 357/254/255 para Básica; sin fuente aún para los demás).
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

**4. Dictamen de Uso de Suelo**: autoridad que expide, folio del dictamen, fecha de expedición, giro que autoriza, vigencia.

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
> Este catálogo de mobiliario/equipo es específico de Educación Inicial; queda pendiente confirmar si existe un equivalente para Preescolar/Primaria/Secundaria/Media Superior/Superior/Posgrado (posiblemente dentro de los Acuerdos 357/254/255 aún no disponibles).

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
- [ ] Confirmar si existe un catálogo de mobiliario/equipo obligatorio equivalente al de Educación Inicial para Preescolar/Primaria/Secundaria/Media Superior/Superior/Posgrado.
- [ ] Recabar documentación normativa de requisitos de infraestructura/personal/mobiliario para el nivel de **Posgrado** (nivel recién confirmado, sin documentos revisados aún).
- [ ] Confirmar tratamiento de docentes de idioma inglés (requieren certificación oficial adicional, mencionado en procedimiento de Básica).
- [ ] Definir estructura de "expediente por docente" (cédula + acta de nacimiento por cada uno, mencionado en procedimiento de Básica) dentro del Anexo 1.
- [ ] Definir Pasos 4 en adelante del flujo (aún no discutidos).
- [ ] Detallar los campos exactos de "Plan de estudios y modalidad" (más allá de escolarizada/no escolarizada/mixta) — la Matrícula ya quedó detallada.
- [ ] Confirmar a qué nivel exacto aplica "Cuatrimestre" en la captura de Matrícula (¿Superior, Posgrado, o ambos?), y detallar la estructura de captura de Matrícula para Superior/Posgrado.
- [ ] Localizar la fuente documental del "Formato de autoevaluación CEPPEMS" (Media Superior) — dato nuevo mencionado por el usuario, sin confirmar en los documentos revisados hasta ahora.
- [ ] Confirmar si la reclasificación de "Auditorio" en actividades físicas y recreativas (en vez de instalaciones especiales, como indica `ANEXO_2_Instalaciones.docx`) es una decisión operativa intencional de SEDEQ.
- [ ] Confirmar si el orden de 6 pasos por nivel (inmueble, infraestructura, mobiliario, plan de estudios, plantilla docente, matrícula) aplica igual a Media Superior y Superior, o tiene variantes.
- [ ] Revisar mockups compartidos (app "incorporacion-app") contra este compendio cuando se puedan visualizar (capturas de pantalla pendientes).

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
