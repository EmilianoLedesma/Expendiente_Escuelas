# Guía de recorrido de la aplicación

**Estado descrito:** `master` en `06521c7` (2026-09-24), después de WS-0, WS-1 y WS-2 de la remediación de la auditoría. Nada se ha subido a `origin` todavía.

Esta guía explica qué puedes probar hoy en la aplicación, qué deberías ver en cada pantalla y qué sigue bloqueado hasta que se completen los siguientes bloques de trabajo (WS-3 a WS-9). No sustituye al PRD ni al COMPENDIO: describe lo que **existe en el código**, no lo que debería existir.

---

## 1. Cómo levantarla

Desde `app-laravel/`:

```bash
php artisan serve      # http://127.0.0.1:8000
npm run build          # o `npm run dev`; sin esto los estilos nuevos no aparecen
```

- **Base de datos:** desarrollo usa `sedeq_incorporacion`. WS-1 y WS-2 no agregaron migraciones ni seeders, así que no hay nada nuevo que correr.
- **Verificación de correo:** desde WS-1 es obligatoria para todo `/tramite/*`. Con el driver de correo `log`, el enlace de verificación aparece en `storage/logs/laravel.log`. Una cuenta de desarrollo sin verificar será enviada a verificar primero.
- **Tamaño de archivos:** si una carga de PDF falla con archivos de más de 2 MB, revisa `upload_max_filesize` / `post_max_size` en el `php.ini` de Herd (se subieron a 12M/13M el 2026-09-22; requiere reiniciar `php artisan serve`).

### Cuentas y roles
- **Solicitante:** cualquier cuenta registrada en `/register`. Al registrarse se crea su registro de solicitante automáticamente.
- **SEDEQ:** una cuenta con el rol `sedeq` (en desarrollo, `test@example.com` lo tiene). Esa cuenta entra a `/admin`.
- **`/dashboard`:** ya no es una página; redirige según el rol (SEDEQ → `/admin`, solicitante → `/tramite/preregistro`).

---

## 2. Recorrido del solicitante

La barra de progreso de la parte superior muestra: Preregistro → Responsable legal → Documentos → los sub-pasos de Paso 3. Un punto es enlace solo cuando ya puedes entrar a esa pantalla. Si escribes a mano la URL de un paso que aún no te corresponde, la aplicación te redirige al primer paso pendiente.

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
- Al guardar te lleva a **Documentos**.
- Una vez guardado, el responsable **no se puede editar**: al volver ves un resumen de solo lectura.

### Paso 2.2 — Documentos (`/tramite/paso2/{escuela}/documentos`)
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

### Paso 2.3 — Selección de niveles (misma URL de Paso 2)
**Qué capturas:** uno o más niveles de **Educación Básica** (Inicial, Preescolar, Primaria, Secundaria). Cada escuela + nivel es un expediente independiente.

**Qué esperar:** solo se puede seleccionar con Paso 2 completo (responsable + documentos vigentes); ni siquiera una llamada directa lo permite (WS-1.2). Al guardar entras a Paso 3 del primer nivel.

### Paso 3.1 — Datos del inmueble (`/tramite/paso3/{escuelaNivel}`)
**Qué ves:**
- El **domicilio del plantel** en solo lectura (WS-2.6).
- Los documentos del inmueble ya capturados en Paso 2.2, como contexto.

**Qué capturas:** metros totales (debe ser mayor a 0), coordenadas (latitud entre −90 y 90, longitud entre −180 y 180), servicios cercanos (salud/emergencia, distancia en m o km) y estudios que se imparten actualmente en el inmueble.

**Si el plantel ya se capturó** con otro nivel, este sub-paso se marca completo solo y te pasa al siguiente. Esos datos **no se pueden corregir** desde el asistente (queda para WS-7).

### Paso 3.2 — Infraestructura del nivel (`/tramite/paso3/{escuelaNivel}/infraestructura`)
**Qué capturas:** número de aulas y superficie; espacios que aplican al nivel (dirección, biblioteca con sus materiales, áreas verdes, campo de fútbol, bodega, etc.); sanitarios por categoría.

**Qué esperar:**
- Un espacio se guarda si llenas **cualquier** dato (por ejemplo, solo la superficie de las áreas verdes). Antes se perdía en silencio (WS-2.1).
- Marcar y desmarcar una casilla sin escribir nada más **no** crea un espacio vacío.
- Números negativos muestran error.
- Lo que otro nivel del mismo plantel ya capturó aparece en "ya capturado" (solo lectura). Tú solo capturas lo que falta para tu nivel, por ejemplo el filtro de recepción de Inicial después de Primaria (ADR-005).

### Paso 3.3 — Mobiliario (`/tramite/paso3/{escuelaNivel}/mobiliario`)
- **Educación Inicial:** capturas el mobiliario por sala según el catálogo.
- **Otros niveles:** no aplica; el sub-paso se marca completo solo y te pasa a Próximos pasos.

### Próximos pasos (`/tramite/paso3/{escuelaNivel}/proximos-pasos`)
Pantalla "Captura inicial completa". Si la escuela tiene otros niveles con Paso 3 pendiente, aparece un enlace "Continuar con {nivel}" para cada uno.

**Aquí termina hoy el recorrido del solicitante.** No hay envío formal, folio, validación automática ni cambio de estado del expediente.

---

## 3. Panel SEDEQ (`/admin`)

Entra una cuenta con rol `sedeq` y ve el panel de Filament vacío: **no hay pantallas de expedientes, revisión ni cambio de estado**. Las cuentas sin rol reciben 403. El panel no exige verificar el correo.

---

## 4. Qué sigue bloqueado y qué lo desbloquea

| Bloque | Qué desbloquea | Qué necesita antes |
|---|---|---|
| **WS-3** Catálogos normativos | Inicial podrá declarar Sala de usos múltiples, cocina y comedor; perfiles faltantes (Enfermera, Profesor en Educación Preescolar); cargos de Trabajador Social y Prefecto en Secundaria; reglas de Director Técnico y Responsable de filtro; reglas de personal ligadas a su cargo. Solo datos de catálogo: **tú tendrás que correr los seeders en desarrollo**. | Tu autorización para empezar. |
| **WS-4** Frontera de lecturas (ADR-006) | Nada visible. Mueve las decisiones de flujo y permisos que aún leen la base desde la presentación a consultas de Application, y agrega reglas automáticas de arquitectura. | Plan escrito y tu aprobación. |
| **WS-5** Documentos completos y Formato | Documentos faltantes del checklist real: acta constitutiva y documentos del representante de persona moral, poder del gestor, Visto Bueno de Protección Civil, plano, certificado de número oficial. Nuevo **Paso 2.4 "Documentos por nivel"**: turno, tipo de alumnado, Formato de Solicitud por nivel (generar → firmar → subir), recibo de pago de derechos, acervo bibliográfico (Primaria/Secundaria), inventario de laboratorio (Secundaria). **Formato de Solicitud rehecho** con la estructura oficial. Paso 3 quedará detrás de Paso 2.4 para cada nivel. | Plan escrito y tu aprobación (decisiones D1–D3 ya tomadas). |
| **WS-6** Aulas por sala en Inicial | En Inicial, el sub-paso 3.2 pedirá una fila por sala (capacidad, superficie, altura, ventilación, iluminación) en lugar de solo totales. Primaria/Secundaria/Preescolar no cambian. Incluye una **tabla nueva** (migración). | Plan escrito y tu aprobación (D4). |
| **WS-7** Edición hasta el envío | Podrás regresar a **cualquier** paso anterior (Preregistro, Responsable legal, niveles, Datos del inmueble, Infraestructura, etc.) y editar lo capturado en formularios precargados mientras el expediente siga en captura. El domicilio del plantel sigue siendo de solo lectura; los datos del inmueble se editan solo mientras el plantel lo usen únicamente tus niveles en captura (D8). Una edición que invalide pasos posteriores los vuelve a marcar como pendientes. Ver `docs/decisions/PENDIENTE-edicion-hasta-envio.md`. | Plan con las reglas de detalle y tu autorización (alcance decidido el 2026-09-24). |
| **WS-8** Plan de estudios (Paso 3.4) | Nuevo sub-paso después de Mobiliario: modalidad (escolarizada, no escolarizada, mixta), referencia del plan de estudios y, si es mixta, tipo de plataforma. Aparece en la barra de progreso y en Próximos pasos. | Tu autorización (D9 ya decidida). |
| **WS-9** Cierre documental | Nada visible. PRD/COMPENDIO actualizados, reporte consolidado y lista única de preguntas para SEDEQ. | Tu autorización. |

### No está en ningún bloque de esta remediación
- **Paso 3.5 Plantilla docente** y **Paso 3.6 Matrícula:** siguen siendo esqueletos; faltan definiciones de SEDEQ.
- **Motor de Validación** (revisión automática de capacidad instalada): solo existe la calculadora de reglas; no se ejecuta desde el asistente. Está pensado para correr una sola vez al terminar todo Paso 3, que hoy no está completo.
- **Envío del trámite, folio y revisión humana de SEDEQ:** fuera del MVP actual.
- **API externa (Etapa 3):** no existe; la preparación de WS-2 hace que los casos de uso ya se protejan solos para cuando exista.

---

## 5. Cosas que pueden sorprenderte al probar

- **Plantel compartido en desarrollo:** hay un plantel con escuelas de dos solicitantes distintos, creado antes de WS-1. Ambos dueños aún pueden ver los documentos de ese plantel. No se tocó; falta tu decisión (`PENDIENTE-plantel-solicitante-cardinalidad.md`).
- **Escuelas viejas en desarrollo** con niveles seleccionados pero sin documentos completos ahora son enviadas de vuelta a Documentos en lugar de a Paso 3. Es el comportamiento correcto, no una regresión.
- **El responsable legal no se puede editar** una vez guardado (y en general, los pasos anteriores se ven en solo lectura). Poder regresar y editar cualquier paso llega con WS-7.
- **El nombre original del archivo** subido no se conserva.
- **El Formato de Solicitud actual** es provisional: se genera antes de elegir niveles y no tiene la estructura oficial (WS-5 lo rehace).
- **Pasos que se completan solos:** Datos del inmueble (plantel ya capturado) y Mobiliario (niveles distintos de Inicial) se marcan completos al entrar; es intencional.

---

## 6. Lista rápida de prueba

1. Registra una cuenta, verifica el correo con el enlace del log y confirma que llegas a Preregistro.
2. Crea un plantel nuevo; en otra cuenta, comprueba que ese plantel **no** aparece como "existente".
3. Captura el responsable (prueba los tres tipos de persona en escuelas distintas) y la terna.
4. Sube los 6 documentos en desorden; intenta un archivo que no sea PDF; reemplaza uno. Con un dictamen de fecha antigua, confirma que no puedes avanzar.
5. Selecciona dos niveles (por ejemplo, Primaria e Inicial).
6. Paso 3 de Primaria: comprueba el domicilio en solo lectura; en Infraestructura declara áreas verdes solo con superficie y comprueba que se guardan; toca una casilla sin llenar nada y comprueba que ese espacio sigue disponible.
7. Desde Próximos pasos, continúa con Inicial: Datos del inmueble debe completarse solo; en Infraestructura debe aparecer lo de Primaria como "ya capturado" y quedar disponible lo propio de Inicial; captura Mobiliario.
8. Escribe a mano la URL de Mobiliario de un nivel nuevo: debe redirigirte al primer sub-paso pendiente.
9. Entra con la cuenta SEDEQ a `/dashboard`: debe llevarte a `/admin`.
