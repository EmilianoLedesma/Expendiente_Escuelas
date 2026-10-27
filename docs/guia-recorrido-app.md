> **Aviso (2026-10-01):** esta guía describe el estado de la aplicación en el commit `8d1a6bb` (2026-09-25). Desde entonces se agregaron, entre otros, el Paso 2.4 (documentos por nivel), más documentos en Paso 2.2, los sub-pasos 4–6 de Paso 3 (Plan de estudios, Plantilla docente, Matrícula), el motor de validación documental, el motor de capacidad instalada y la página de Validación final. Consulta `docs/progress.md` y `docs/reports/2026-10-01-revision-global-proyecto.md` para el estado actual. Una actualización completa de esta guía está pendiente.

# Guía de recorrido de la aplicación

**Estado descrito:** `master` en `8d1a6bb` (2026-09-25), después de WS-0, WS-1, WS-2 y WS-3 de la remediación de la auditoría **y del rediseño de la interfaz** (dirección B, "recorrido guiado"; reporte `docs/reports/2026-09-25-rediseno-ui.md`).

Esta guía explica qué puedes probar hoy en la aplicación, qué deberías ver en cada pantalla y qué sigue bloqueado hasta que se completen los siguientes bloques de trabajo (WS-4 a WS-9). No sustituye al PRD ni al COMPENDIO: describe lo que **existe en el código**, no lo que debería existir.

---

## 1. Cómo levantarla

Desde `app-laravel/`:

```bash
php artisan serve      # http://127.0.0.1:8000
npm run build          # o `npm run dev`; sin esto los estilos nuevos no aparecen
```

- **Base de datos:** desarrollo usa `sedeq_incorporacion`. WS-1 y WS-2 no agregaron migraciones ni seeders. WS-3 solo cambió datos de catálogo; sus seeders (`TiposEspaciosSeeder`, `CargosPuestosSeeder`, `PerfilesProfesionalesSeeder`, `ReglasValidacionSeeder`) **ya se corrieron en desarrollo** el 2026-09-24. No hay nada nuevo que correr.
- **"could not find driver" (pgsql):** si aparece junto con el aviso `Unable to load dynamic library 'pdo_pgsql' … Una directiva de Control de aplicaciones bloqueó este archivo`, no es un error del código: el Control inteligente de aplicaciones de Windows bloqueó la DLL de Herd. Se resolvió el 2026-09-25 desactivándolo (Seguridad de Windows › Control de aplicaciones y del explorador). Comprueba con `php -m` que aparece `pdo_pgsql`.
- **Verificación de correo:** desde WS-1 es obligatoria para todo `/tramite/*`. Con el driver de correo `log`, el enlace de verificación aparece en `storage/logs/laravel.log`. Una cuenta de desarrollo sin verificar será enviada a verificar primero.
- **Tamaño de archivos:** si una carga de PDF falla con archivos de más de 2 MB, revisa `upload_max_filesize` / `post_max_size` en el `php.ini` de Herd (se subieron a 12M/13M el 2026-09-22; requiere reiniciar `php artisan serve`).

### Cuentas y roles
- **Solicitante:** cualquier cuenta registrada en `/register`. Al registrarse se crea su registro de solicitante automáticamente.
- **SEDEQ:** una cuenta con el rol `sedeq` entra a `/admin`. Desde la limpieza de la base de desarrollo (2026-09-25) **no existe ninguna**: registra una cuenta y asígnale el rol `sedeq` (o vuelve a correr la parte de `test@example.com` de `DatabaseSeeder`).
- **Supervisor y revisor (HUD de SEDEQ, rama `hud-sedeq-1`):** dos roles nuevos, distintos de `sedeq`. El primer supervisor se crea con `php artisan sedeq:crear-supervisor` (no por registro) y recibe un correo para fijar su contraseña; el supervisor invita después a los revisores por correo desde la página Revisores. Ver sección 3.
- **`/dashboard`:** ya no es una página; redirige según el rol (SEDEQ → `/admin`, solicitante → **Mis trámites** `/tramite`, supervisor → `/sedeq`, revisor → `/sedeq/cola`). Al iniciar sesión, registrarte o verificar el correo llegas a la misma pantalla de inicio de tu rol. Supervisor y revisor que abren `/tramite` son redirigidos a su HUD.

---

## 2. Recorrido del solicitante

### Mis trámites (`/tramite`)
Tabla con cada escuela que has iniciado: nombre propuesto (o "Sin nombre propuesto"), `Nº` de trámite, domicilio y fecha de inicio, niveles, avance ("Sigue: …") y estado ("En captura" / "Captura inicial completa"), con **Continuar** o **Ver trámite**. "Iniciar nuevo trámite" lleva a Paso 1. Solo ves tus escuelas.

### Resumen del trámite (`/tramite/{escuela}`)
El título es el nombre de la escuela. A la izquierda (arriba, plegable como "Secciones del trámite", en pantallas chicas) está el **recorrido**: tarjeta con el avance y la lista de secciones agrupadas en "Datos generales" y una por nivel, con su estado. Ese mismo recorrido aparece en **cada página de paso**, marcando dónde estás. Las secciones completas de Responsable, Documentos, Niveles y Datos del inmueble se muestran como "Completado" **sin enlace** (su edición llega con WS-7); Infraestructura y Mobiliario completos ofrecen "Revisar". Plan de estudios, Plantilla docente y Matrícula aparecen como "No disponible aún".

Cada página de paso muestra arriba "{Grupo} · Paso X de N" y abajo una barra de acciones fija con "Guardar y continuar". Si escribes a mano la URL de un paso que aún no te corresponde, la aplicación te redirige al primer paso pendiente.

### Paso 1 — Preregistro (`/tramite/preregistro`)
**Qué capturas:** eliges entre **plantel nuevo** (calle, número exterior/interior, colonia, localidad, municipio, código postal, teléfono, correo) o **plantel existente**.

**Qué esperar:**
- En "plantel existente" solo aparecen **tus** planteles (donde ya tienes una escuela). Un plantel de otro solicitante no se puede elegir ni forzar (WS-1.1).
- Repetir Paso 1 con el mismo plantel antes de terminar Paso 2 reutiliza la escuela en curso; si esa escuela ya tiene responsable legal, se crea una escuela nueva (una misma persona puede tener dos escuelas en el mismo plantel).
- Al guardar pasas a Paso 2.

### Paso 2.1 — Responsable legal (`/tramite/paso2/{escuela}`)
**Qué capturas:**
- Tipo de persona: **física**, **física con gestor** o **moral**, cada una con sus campos (datos personales, RFC/CURP en mayúsculas automáticas, datos notariales y de Registro Público para moral y gestor).
- Domicilio para notificaciones (obligatorio) y persona autorizada para recoger (opcional).
- **Terna de nombres propuestos** para la escuela (las tres son obligatorias).

**Qué esperar:**
- Al guardar sigues con la **selección de niveles** (desde 2026-10-20 los niveles van antes que los documentos generales).
- Puedes volver a revisar y editar el responsable (tipo de persona, datos, terna, domicilio de notificaciones) hasta el envío: al volver ves el formulario prellenado y un resumen. Si cambias el tipo de persona, la página te avisa que revises tus documentos; si cambias algo que imprime el Formato de Solicitud, se descarta el Formato firmado y hay que descargarlo, firmarlo y subirlo de nuevo.

### Paso 2.3 — Documentos generales (`/tramite/paso2/{escuela}/documentos`)

> Desde 2026-10-20 esta página exige al menos un nivel elegido; sin niveles redirige a la selección de niveles.
**Qué ves:** una lista con todos los documentos que aplican y un contador "X de Y documentos completos". Puedes subirlos en cualquier orden.

**Documentos (6):** INE; acta de nacimiento (persona física) **o** escritura/poder de facultades (persona moral); escritura del inmueble; Dictamen de Uso de Suelo; Constancia de Seguridad Estructural + DRO; Formato de Solicitud.

- **Con datos adicionales:**
  - Escritura del inmueble: tipo (escritura pública, arrendamiento, comodato u otro), con campos distintos según el tipo.
  - Dictamen de Uso de Suelo: fecha de emisión, de la que se calcula su vigencia.
  - Constancia de Seguridad Estructural: datos del DRO.
- **Formato de Solicitud:** la pantalla ofrece generar un PDF con los datos capturados, que luego se sube como documento.
- **Solo PDF.** Un archivo que no es PDF muestra un error en su campo (WS-2.4a).
- **Reemplazar un documento:** usa "reemplazar"; el archivo nuevo sustituye al anterior sin riesgo de perder el previo si algo falla (WS-2.3).
- **Documentos del plantel** (escritura, dictamen, constancia) son compartidos por todas tus escuelas en ese plantel.
- El nombre mostrado del archivo es genérico: el nombre original no se guarda.

**Para avanzar:** los 6 documentos completos **y vigentes**. Un Dictamen de Uso de Suelo vencido te detiene aquí.

### Paso 2.2 — Selección de niveles (misma URL de Paso 2, `?seccion=niveles`)
**Qué capturas:** uno o más niveles de **Educación Básica** (Inicial, Preescolar, Primaria, Secundaria). Cada escuela + nivel es un expediente independiente.

**Qué esperar:** desde 2026-10-20 basta el responsable legal (antes se exigían los documentos completos y vigentes); sin responsable ni una llamada directa lo permite (WS-1.2). Al guardar sigues con los documentos generales; los documentos de cada nivel (2.4) y Paso 3 siguen exigiendo los documentos generales completos y vigentes.

### Paso 3.1 — Datos del inmueble (`/tramite/paso3/{escuelaNivel}`)
**Qué ves:**
- El **domicilio del plantel** en solo lectura (WS-2.6).
- Los documentos del inmueble ya capturados en Paso 2.2, como contexto.

**Qué capturas:** metros totales (debe ser mayor a 0), coordenadas (latitud entre −90 y 90, longitud entre −180 y 180), servicios cercanos (salud/emergencia, distancia en m o km) y estudios que se imparten actualmente en el inmueble.

**Editable hasta el envío (WS-7b-2):** el sub-paso abre con lo ya capturado y puedes corregirlo mientras el trámite esté en captura. **Bloqueo:** si otro nivel del mismo plantel ya capturó datos propios de Paso 3 (infraestructura, mobiliario, plantilla, matrícula o plan de estudios), los datos del inmueble que ya tienen valor quedan de solo lectura, porque ese nivel se apoyó en ellos; solo puedes completar los campos que sigan vacíos. El bloqueo se levanta si esos datos del otro nivel desaparecen. Después del envío, cambiarlos requiere la devolución de SEDEQ.

### Paso 3.2 — Infraestructura del nivel (`/tramite/paso3/{escuelaNivel}/infraestructura`)
**Qué capturas:** número de aulas y superficie; espacios que aplican al nivel (dirección, biblioteca con sus materiales, áreas verdes, campo de fútbol, bodega, etc.); sanitarios por categoría.

**Qué esperar:**
- Un espacio se guarda si llenas **cualquier** dato (por ejemplo, solo la superficie de las áreas verdes). Antes se perdía en silencio (WS-2.1).
- Marcar y desmarcar una casilla sin escribir nada más **no** crea un espacio vacío.
- Números negativos muestran error.
- Lo que ya está capturado aparece en "Lo ya capturado", editable hasta el envío: puedes corregir o quitar filas. Si otro nivel del plantel ya capturó, esas filas quedan de solo lectura (solo se completan campos vacíos), pero siempre puedes agregar un espacio o una categoría de sanitarios que el plantel aún no tenga. Editar o quitar filas compartidas es del dueño del trámite; un responsable por nivel solo agrega las de su nivel. Tú solo capturas lo que falta para tu nivel, por ejemplo el filtro de recepción de Inicial después de Primaria (ADR-005).
- **Nuevo en WS-3:** para **Educación Inicial** aparecen tres espacios opcionales más: **Salón de usos múltiples**, **Cocina** y **Comedor**. No aparecen en Preescolar, Primaria ni Secundaria.
- **Descargar Anexo:** hay un enlace **Descargar Anexo** que abre el Anexo 2 Instalaciones en PDF con lo capturado del plantel y lo de tu nivel; lo que falta queda en blanco para llenarlo a mano. El enlace está en la página de Infraestructura mientras esa página es editable. La URL del PDF sigue respondiendo al dueño del trámite y al responsable de ese nivel también después del envío (el enlace ya no aparece en pantalla). SEDEQ no lo tiene.
- **Orden de los paneles:** ahora sigue el del Anexo 2: Espacios administrativos, Aulas del nivel, Cubículos, Sanitarios, Actividades físicas y recreativas, Instalaciones especiales ("Ya registrada" sigue primero cuando existe). En Inicial no hay Cubículos y el panel recreativo se llama "Áreas recreativas". En las áreas recreativas el campo "Destinado a" se llama **Equipamiento y características**. "Guardar y continuar" está en el último panel.
- **Inicial ya no ofrece Cubículo, Cancha de usos múltiples ni Campo de fútbol** en capturas nuevas. Lo que ya estuviera capturado no se borra: sigue en "Lo ya capturado" y sale en el Anexo como "Otras instalaciones capturadas". (La limpieza de la matriz en la base de desarrollo requiere una migración que aún no se ha corrido allí.)
- **Aulas de Inicial:** se sigue capturando solo el total (número y superficie), no una fila por sala; eso llega con WS-6 (pendiente, ver `docs/decisions/PENDIENTE-aulas-inicial-por-aula.md`).

### Paso 3.3 — Mobiliario (`/tramite/paso3/{escuelaNivel}/mobiliario`)
- **Educación Inicial:** capturas el mobiliario por sala según el catálogo; si vacías una cantidad, esa fila se borra.
- **Otros niveles:** no aplica; el sub-paso se marca completo solo y te devuelve al Resumen del trámite.

### Al terminar Paso 3 de un nivel
La antigua pantalla "Próximos pasos" ya no existe: su URL redirige al **Resumen del trámite**, donde ves qué niveles siguen pendientes y continúas desde el recorrido.

**Aquí termina hoy el recorrido del solicitante.** No hay envío formal, folio, validación automática ni cambio de estado del expediente.

---

## 3. Recorrido de SEDEQ: supervisor y revisor

Proyecto 1 del HUD (roles y asignación), proyecto 2 (revisión por pasos, subsección "Revisión de un nivel") y proyecto 3 (devolución al solicitante y reenvío, subsección "Devolución y reenvío de correcciones"; ADR-020). La resolución con QR llega en un proyecto posterior. No se ha visto en un navegador; lo descrito sale del código y de las pruebas automáticas.

### Crear al primer supervisor
Desde `app-laravel/`: `php artisan sedeq:crear-supervisor correo@sedeq.gob.mx "Nombre"` (el correo y el nombre son obligatorios; no pregunta nada) (crea la cuenta, le asigna el rol y le envía un correo para fijar su contraseña). Si el correo ya existe, el comando termina con error y no cambia nada. Si falla el envío del correo, la cuenta queda con una contraseña aleatoria y repetir el comando se rechaza.

### Encabezado y menú de los roles SEDEQ
- El encabezado de supervisor y revisor se llama **"Revisión de incorporación"** (sin etiqueta de rol); el solicitante conserva "Trámite de Incorporación de Escuelas Particulares". El enlace de inicio es "Tablero" (supervisor) o "Mis Revisiones" (revisor); ninguno ve "Mis trámites".
- **No hay barra lateral** en `/sedeq`, `/sedeq/cola` ni `/sedeq/tramite/{escuela}`: son páginas a todo el ancho. La barra lateral solo aparece en las páginas de cuenta: supervisor = **Mi perfil** y **Revisores**; revisor = **Mi perfil** y **Mis Revisiones**.

### Tablero del supervisor (`/sedeq`)
- Arriba, un resumen de carga por revisor ("Ana 1/5", en rojo con " · llena" al tope) para saber quién tiene lugar. Ya no hay barras, formulario de invitación ni "Revocar" aquí.
- Debajo, la tabla de trámites en revisión (casilla, N.º, plantel con su nivel, prioridad Alta / Media / Baja, días de espera, revisor o "Sin asignar"). Cambias la prioridad con el selector de cada fila. Marca filas, elige un revisor en "Asignar a…" y pulsa "Asignar seleccionados". Si no hay trámites: "No hay trámites en revisión."
- **Tope por revisor:** 5 (un valor de configuración). Si asignas uno más aparecen dos avisos: un **aviso emergente** ("<nombre> llegó a su límite de 5 trámites") y una **ventana modal** "Tope de trámites alcanzado" con "Cancelar" (foco inicial; Escape también cancela) y "Asignar de todos modos". Al confirmar, el sistema devuelve a "Sin asignar" uno de los trámites de ese revisor (el de menos avance, luego de menor prioridad, luego el asignado más recientemente), conserva su avance de revisión y lo registra. Una selección mayor que el tope no se puede confirmar (error en línea, sin ventana).
- Un clic en cualquier parte de una fila marca o desmarca su casilla (el selector de prioridad, el enlace del plantel y la propia casilla funcionan normal, sin alternar la fila); la fila resalta al pasar el cursor y, si está marcada, además lleva un fondo azul tenue.
- Un revisor, `sedeq` o un solicitante que abren `/sedeq` reciben 403.

### Revisores (`/sedeq/revisores`, solo supervisor)
Una página con la barra de cada revisor ("N de 5", " · llena" al tope, y secciones revisadas), el formulario **"+ Invitar revisor"** (nombre y correo; el revisor recibe un correo para fijar su contraseña) y el botón **"Revocar"**. Cada fila de revisor se expande con **"Trámites asignados (N)"** (botón con flecha, operable con teclado) y muestra sus trámites por prioridad y antigüedad, cada uno como enlace al trámite en solo lectura con plantel, nivel, prioridad y días de espera; si no tiene, dice "Sin trámites asignados.". Revocar abre una ventana modal que nombra al revisor y avisa que sus trámites regresan a "Sin asignar"; se confirma con "Revocar acceso" o se cancela con "Cancelar" o Escape. Revocar quita el rol, devuelve sus trámites a "Sin asignar" y le quita el acceso al HUD (403). La cuenta no se borra y puede reinvitarse. Limitación: el revisor revocado todavía puede iniciar sesión; llega a `/tramite` y ve un "Mis trámites" vacío, y no puede eliminar su propia cuenta (una vista de "acceso revocado" queda como mejora posible).

### Mis Revisiones (`/sedeq/cola`)
Lista solo los trámites **asignados** a esa persona, ordenados por prioridad (Alta, Media, Baja) y luego por envío más antiguo, con la barra "N de 5 asignados". Al elegir uno, el panel derecho muestra su resumen y el enlace "Ver trámite (solo lectura)"; cada fila tiene el enlace "Revisar" (ver "Revisión de un nivel"). Un supervisor o `sedeq` que abren `/sedeq/cola` reciben 403.

### Trámite en solo lectura (`/sedeq/tramite/{escuela}`)
Muestra plantel, responsable legal, documentos y los niveles visibles con su estado y folio. No tiene formularios ni enlaces de edición. Las secciones sin datos lo dicen: "Sin datos del responsable legal", "Sin documentos del plantel", "Sin documentos del nivel". El supervisor ve los niveles que no estén en captura; el revisor, solo los niveles que se le asignaron. Las rutas de escritura del solicitante responden 403 para ambos roles. Desde 2026-10-20 la vista muestra además los metadatos de cada documento (con el estado de su vigencia), los datos de cada nivel (turno, alumnado, matrícula, personal, avance de Paso 3) y el **resultado de validación almacenado al envío**, acotado a los niveles visibles. El PDF de ese reporte cubre todos los niveles: lo descarga el supervisor o un revisor asignado a todos los niveles de la escuela.

**Vista previa de documentos:** cada documento (PDF) se abre en una ventana modal con la **primera página** como portada; al pasar el cursor o enfocar se abren en abanico hasta 4 páginas más, y un botón **"Abrir en pantalla completa"** abre el PDF completo en otra pestaña. pdf.js se descarga solo la primera vez que se abre una vista previa. Sin JavaScript, o si falla la carga ("No se pudo cargar el documento."), el documento sigue siendo un enlace de descarga normal (protegido por la misma política `view`).

### Revisión de un nivel (`/sedeq/revision/{escuelaNivel}`)
- **Cómo se llega:** con el enlace **"Revisar"** de cada fila del tablero del supervisor o de Mis Revisiones. El revisor solo abre los niveles que se le asignaron; el supervisor, cualquiera en revisión; `sedeq` y los solicitantes reciben 403.
- **Pantalla:** a la izquierda, "Avance: X de N" y la lista de pasos con su estado (Pendiente / Aceptado / Rechazado); a la derecha, el paso abierto con lo que capturó el solicitante, sus documentos y, al final, **Aceptar paso** y **Rechazar paso**. Son 10 pasos (11 en Inicial, por Mobiliario): Datos del plantel, Responsable legal, Documentos del plantel, Niveles educativos, Documentos del nivel, Datos del inmueble, Infraestructura, Mobiliario, Plan de estudios, Plantilla docente y Matrícula. Los cuatro primeros se deciden por separado en cada nivel.
- **Decidir:** Aceptar no pide nada. Rechazar exige escribir el **motivo del rechazo** ("Escribe el motivo del rechazo." si lo dejas vacío). Se puede cambiar una decisión mientras el nivel esté en revisión (no aprobado ni con observaciones); el paso muestra la decisión vigente y su historial (quién, cuándo, motivo). Si dos personas deciden el mismo paso, vale la última y el historial conserva ambas. Si otro revisor ya decidió ese paso en otro nivel del mismo plantel, solo se muestra como referencia; no obliga.
- **Estado del nivel:** mientras falte algún paso por decidir sigue **en revisión**. Al aceptar el último paso pasa solo a **aprobado** y ya no se puede decidir. Si todos los pasos están decididos y alguno fue rechazado, queda en **con observaciones**: está con el solicitante y ya no se puede decidir hasta que reenvíe (ver "Devolución y reenvío de correcciones"). Un nivel aprobado o con observaciones sale del tablero y de la cola, y deja de contar contra el tope de 5 del revisor; al reenviarse vuelve a la cola del mismo revisor.
- **Pistas del sistema:** en los pasos de documentos y de capacidad instalada aparece un cuadro con lo que el motor de validación encontró (Cumple, Advertencia, No cumple, No evaluable). Son solo informativas: no bloquean ni deciden nada.
- **Pasos corregidos:** tras un reenvío, los pasos que el solicitante corrigió aparecen primero y resaltados, con el **motivo del rechazo anterior** junto a lo que hay capturado ahora. Los aceptados conservan su decisión.
- **"Resultado cambiado desde que se aceptó":** si una regla del motor que cumplía en la última evaluación guardada (la del envío o la del cierre de la ronda) ya no cumple, el paso aceptado que la cubre lleva este aviso, también en la vista del supervisor del trámite. Solo avisa: no reabre ni cambia ninguna decisión.

### Devolución y reenvío de correcciones
- **Cuándo llega al solicitante:** solo cuando **ningún nivel de la escuela sigue en revisión** y al menos uno quedó con observaciones (la "ronda" se cierra). Antes, el solicitante sigue viendo "En revisión" y no ve motivos.
- **Aviso:** al cerrarse la ronda se manda el correo "Tu trámite tiene observaciones" al dueño (todos los motivos) y a cada responsable por nivel con motivos de su nivel (solo esos). Mientras el `.env` de desarrollo tenga `MAIL_MAILER=log`, el correo solo queda en el log.
- **Qué ve el solicitante:** en "Mis trámites" el trámite dice **Con observaciones**; en el Resumen del trámite, un aviso y, en cada sección rechazada, el motivo y el enlace para corregirla. Lo aceptado sigue bloqueado (abrir su página da 403). Un responsable por nivel solo ve y corrige los pasos de su nivel; los pasos del plantel, responsable legal, niveles, documentos generales, inmueble e infraestructura son del dueño.
- **Al corregir:** cada guardado durante la devolución regresa al Resumen. El paso **Niveles** reabierto no permite agregar niveles; solo quitar uno con observaciones, y la escuela conserva al menos uno. **Datos del inmueble** e **Infraestructura** siguen con el bloqueo de ADR-019 en planteles con varios niveles (solo se completan campos vacíos). Si se corrige el **Responsable legal** y cambia algo que imprime el Formato de solicitud, el Formato firmado de los niveles con observaciones se descarta y hay que volver a cargarlo (por eso "Documentos del nivel" también se reabre en esos niveles). Una vigencia que venza durante la devolución no bloquea, salvo que "Documentos del plantel" esté entre lo rechazado.
- **Reenviar:** el botón **"Reenviar correcciones"** (solo el dueño) pide que cada paso con observaciones esté completo y que la validación no tenga nada bloqueante en los niveles devueltos. Los niveles con observaciones vuelven a **en revisión** con el mismo revisor; solo los pasos rechazados quedan pendientes. Se puede reenviar sin cambiar nada; el revisor lo volverá a rechazar.
- **Pendiente de ver en navegador:** el recorrido anterior solo se ha probado con pruebas automáticas.

### Cuenta
Ambos roles usan `/profile` (nombre y contraseña). **No pueden eliminar su cuenta** (tampoco `sedeq`). No pueden abrir `/admin`.

---

## 4. Qué sigue bloqueado y qué lo desbloquea

| Bloque | Qué desbloquea | Qué necesita antes |
|---|---|---|
| **WS-4** Frontera de lecturas (ADR-006) | Nada visible. Mueve las decisiones de flujo y permisos que aún leen la base desde la presentación a consultas de Application, y agrega reglas automáticas de arquitectura. | Plan escrito y tu aprobación. **En pausa** por tu decisión (2026-09-25). |
| **WS-5** Documentos completos y Formato | Documentos faltantes del checklist real: acta constitutiva y documentos del representante de persona moral, poder del gestor, Visto Bueno de Protección Civil, plano, certificado de número oficial. Nuevo **Paso 2.4 "Documentos por nivel"**: turno, tipo de alumnado, Formato de Solicitud por nivel (generar → firmar → subir), recibo de pago de derechos, acervo bibliográfico (Primaria/Secundaria), inventario de laboratorio (Secundaria). **Formato de Solicitud rehecho** con la estructura oficial. Paso 3 quedará detrás de Paso 2.4 para cada nivel. | Plan escrito y tu aprobación (decisiones D1–D3 ya tomadas). |
| **WS-6** Aulas por sala en Inicial | En Inicial, el sub-paso 3.2 pedirá una fila por sala (capacidad, superficie, altura, ventilación, iluminación) en lugar de solo totales. Primaria/Secundaria/Preescolar no cambian. Incluye una **tabla nueva** (migración). | Plan escrito y tu aprobación (D4). |
| **WS-7** Edición hasta el envío (**7b-1 y 7b-2 hechos**: Paso 1, Paso 2 y Paso 3 editables hasta el envío) | Podrás regresar a **cualquier** paso anterior (Preregistro, Responsable legal, niveles, Datos del inmueble, Infraestructura, etc.) y editar lo capturado en formularios precargados mientras el expediente siga en captura. El domicilio del plantel sigue siendo de solo lectura; Paso 3 (Datos del inmueble, Infraestructura, Mobiliario, Plan de estudios, Plantilla y Matrícula) se reabre precargado. Los datos compartidos del plantel (inmueble e infraestructura) se editan libremente mientras ningún otro nivel haya capturado datos propios de Paso 3; cuando otro nivel ya capturó, los campos con valor quedan de solo lectura y solo se pueden completar los vacíos. Editar o borrar las filas compartidas de infraestructura es solo del dueño. Una edición no reinicia las banderas de los pasos posteriores: al enviar, el sistema vuelve a evaluar todo y bloquea el envío si algo quedó incompleto. Ver `docs/decisions/ADR-019-edicion-hasta-envio.md`. | Terminado (2026-10-21). |
| **WS-8** Plan de estudios (Paso 3.4) | Nuevo sub-paso después de Mobiliario: modalidad (escolarizada, no escolarizada, mixta), referencia del plan de estudios y, si es mixta, tipo de plataforma. Aparece en el recorrido y en el Resumen del trámite. | Tu autorización (D9 ya decidida). |
| **WS-9** Cierre documental | Nada visible. PRD/COMPENDIO actualizados, reporte consolidado y lista única de preguntas para SEDEQ. | Tu autorización. |

### Ya terminado
- **WS-3 Catálogos normativos** (merge `08812e7`, seeders corridos en desarrollo). Solo datos de catálogo:
  - **Visible:** los tres espacios nuevos de Inicial en Paso 3.2 (ver arriba).
  - **Existe pero sin pantalla que lo muestre:** perfiles Enfermera y Profesor en Educación Preescolar (Inicial); cargos Trabajador Social y Prefecto (Secundaria); reglas de Director Técnico (Preescolar, Primaria, Secundaria) y Responsable de filtro (Inicial); 12 reglas de personal ligadas a su cargo. Quedan listos para Plantilla docente y el Motor de Validación, que aún no existen. Solo se comprueban con una consulta a la base (desarrollo: 32 reglas, 18 cargos, 89 perfiles).
  - Preguntas abiertas para SEDEQ que dejó: perfiles de Trabajador Social/Prefecto, personal condicionado por grado (Inglés/Computación) y el umbral de Educación Física (≥60 o >60).

- **Rediseño de la interfaz** (fuera de la remediación, integrado el 2026-09-25 en `8d1a6bb`): Mis trámites, Resumen del trámite con recorrido lateral, formularios accesibles y pantallas de acceso renovadas. Sin cambios de reglas ni de datos. Pendiente: la revisión manual de accesibilidad (teclado, 375 px, Lighthouse) que dispensaste para la integración, y una función de **eliminar trámite** que pediste (tarea aparte).

### No está en ningún bloque de esta remediación
- **Paso 3.5 Plantilla docente** y **Paso 3.6 Matrícula:** siguen siendo esqueletos; faltan definiciones de SEDEQ. Los catálogos de cargos y perfiles que usarán ya están completos tras WS-3.
- **Motor de Validación** (revisión automática de capacidad instalada): solo existe la calculadora de reglas; no se ejecuta desde el asistente. Está pensado para correr una sola vez al terminar todo Paso 3, que hoy no está completo.
- **Envío del trámite, folio y revisión humana de SEDEQ:** fuera del MVP actual. (En la rama `hud-sedeq-1` ya existe el reparto y la lectura para SEDEQ, sección 3; la revisión por pasos y la devolución por sección ya existen; la resolución con QR sigue pendiente.)
- **API externa (Etapa 3):** no existe; la preparación de WS-2 hace que los casos de uso ya se protejan solos para cuando exista.

---

## 5. Cosas que pueden sorprenderte al probar

- **Base de desarrollo limpia (2026-09-25):** se vaciaron todas las tablas de captura (cuentas, planteles, escuelas, documentos, Paso 3) y los PDF subidos; solo quedan los catálogos. El plantel compartido entre dos solicitantes que existía antes de WS-1 ya no está, pero la pregunta de fondo sigue abierta (`PENDIENTE-plantel-solicitante-cardinalidad.md`).
- **Hasta el envío se pueden revisar** el responsable legal, el teléfono y correo del plantel ("Datos del plantel") y los niveles (agregar o quitar; quitar un nivel con datos pide confirmación). El **domicilio del plantel sigue de solo lectura**. Los pasos de Paso 3 también se pueden volver a abrir y corregir hasta el envío (WS-7b-2), con el bloqueo de datos compartidos descrito en Paso 3.1.
- **El nombre original del archivo** subido no se conserva.
- **El Formato de Solicitud actual** es provisional: se genera antes de elegir niveles y no tiene la estructura oficial (WS-5 lo rehace).
- **Pasos que se completan solos:** Datos del inmueble (plantel ya capturado) y Mobiliario (niveles distintos de Inicial) se marcan completos al entrar; es intencional.

---

## 6. Lista rápida de prueba

1. Registra una cuenta, verifica el correo con el enlace del log y confirma que llegas a **Mis trámites** (vacío); inicia un trámite nuevo.
2. Crea un plantel nuevo; en otra cuenta, comprueba que ese plantel **no** aparece como "existente".
3. Captura el responsable (prueba los tres tipos de persona en escuelas distintas) y la terna.
4. Sube los 6 documentos en desorden; intenta un archivo que no sea PDF; reemplaza uno. Con un dictamen de fecha antigua, confirma que no puedes avanzar.
5. Selecciona dos niveles (por ejemplo, Primaria e Inicial).
6. Paso 3 de Primaria: comprueba el domicilio en solo lectura; en Infraestructura declara áreas verdes solo con superficie y comprueba que se guardan; toca una casilla sin llenar nada y comprueba que ese espacio sigue disponible.
7. Desde el Resumen del trámite (o el recorrido lateral), continúa con Inicial: Datos del inmueble debe completarse solo; en Infraestructura debe aparecer lo de Primaria como "ya capturado" y quedar disponible lo propio de Inicial, incluidos Salón de usos múltiples, Cocina y Comedor (declara uno y comprueba que se guarda); captura Mobiliario.
   - En Infraestructura de Primaria (u otro nivel que no sea Inicial), confirma que esos tres espacios **no** aparecen.
8. Escribe a mano la URL de Mobiliario de un nivel nuevo: debe redirigirte al primer sub-paso pendiente.
9. Con una cuenta a la que asignaste el rol `sedeq`, entra a `/dashboard`: debe llevarte a `/admin`.
