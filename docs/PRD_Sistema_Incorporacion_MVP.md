# PRD — Sistema de Incorporación de Escuelas (SEDEQ)
## MVP — Educación Básica (Inicial, Preescolar, Primaria, Secundaria)

> Este documento es la especificación técnica para construir el MVP. Deriva del
> `COMPENDIO_MAESTRO_Sistema_Incorporacion.md` (fuente de verdad de negocio/normativa)
> y del `ddl_sistema_incorporacion_v3.sql` (esquema de base de datos ya diseñado y
> normalizado en 3FN). Ante cualquier duda de detalle normativo no cubierta aquí,
> el compendio es la referencia autorizada.

---

## 1. Objetivo del MVP

Construir la primera versión funcional del sistema web que digitaliza el trámite de
incorporación de escuelas ante SEDEQ, cubriendo el flujo completo de captura y
prevalidación (Pasos 1-3 del proceso) para los 4 niveles de Educación Básica.

**Fuera de alcance de este MVP** (explícitamente, no ambigüedad):
- Media Superior, Superior y Posgrado (normativa aún no recabada por completo).
- El flujo de revisión interna de SEDEQ más allá de un estado básico de expediente
  (bandeja de trabajo completa, notificaciones automáticas, agenda de visita de
  verificación) — se documentará en un PRD de fase 2 (Etapa 2 del proyecto).
- Integración real con el portal de pago RecaudaNet (se captura el folio manualmente).
- Reutilización automática de datos de plantel entre trámites (Etapa 3) — el modelo
  de datos ya lo soporta, pero la lógica de "precarga inteligente" no es parte del MVP.
- Autenticación federada o SSO gubernamental — usar autenticación estándar de Laravel.

## 2. Stack técnico (decidido, no renegociable en este MVP)

| Capa | Tecnología |
|---|---|
| Backend + Frontend | Laravel 11 (PHP 8.3+) + Livewire 3 + Alpine.js |
| Base de datos | PostgreSQL 16 |
| Generación de PDF | `barryvdh/laravel-dompdf` |
| Autenticación | Laravel Breeze |
| Roles y permisos | `spatie/laravel-permission` |
| Panel administrativo (SEDEQ) | Filament PHP 3 |
| Entorno de desarrollo | Windows + Laravel Herd (local); producción futura en RHEL |

## 3. Roles de usuario

- **Solicitante**: representante legal (persona física/moral) de la escuela. Accede
  vía web, sin necesidad de credenciales previas de SEDEQ.
- **Personal SEDEQ (revisor)**: rol administrativo con acceso al panel Filament,
  puede ver expedientes y cambiar su estado. Para el MVP, un solo rol es suficiente
  (no se requiere aún separación por departamento).

## 4. Modelo de datos

**Usar el DDL ya definido en `ddl_sistema_incorporacion_v3.sql` como base de las
migraciones de Laravel.** No rediseñar el esquema — está normalizado en 3FN y fue
validado contra el compendio. Convertir cada `CREATE TABLE` en una migración de
Laravel (`php artisan make:migration`), respetando el orden de dependencias FK.

Puntos clave del modelo que el código debe respetar:
- **Plantel → Escuela → Escuela+Nivel** es la jerarquía central. Cada combinación
  escuela+nivel es un expediente independiente (tabla `escuela_niveles`).
- Los catálogos (`tipos_documentos`, `cargos_puestos`, `niveles_tipos_espacios`,
  `mobiliario_conceptos`, `perfiles_profesionales`, `reglas_validacion`) son datos
  de configuración que **impulsan la UI dinámicamente** — no hardcodear en Blade/PHP
  qué documentos, cargos, espacios o mobiliario mostrar; consultarlo de estas tablas.
- Los seeds mínimos ya están en el DDL; se deben ampliar con el catálogo completo
  de `tipos_documentos` (checklist por nivel/tipo de persona), `cargos_puestos`
  (Profesiogramas), `mobiliario_conceptos` y `reglas_validacion` (ratios de
  superficie/personal) usando el detalle del compendio, secciones 4 y 5.

## 5. Flujo funcional (Pasos 1-3)

### Paso 1 — Preregistro

**Historia de usuario**: Como solicitante, quiero iniciar un trámite indicando si
es para un plantel/escuela nuevo o uno ya existente, para que el sistema sepa si
debe precargar datos o partir de cero.

- Pantalla de bifurcación: "¿Es tu primer trámite?" (Sí → crear `plantel` y `escuela`
  nuevos) / "¿Ya tienes un plantel registrado?" (No implementado en MVP más allá de
  un selector simple; la precarga inteligente es Etapa 3).
- Captura de datos básicos de preregistro.
- **No** se muestra ningún checklist ni se selecciona nivel educativo en este paso
  (corrección de diseño ya documentada — eso ocurre al final del Paso 2).

### Paso 2 — Responsable legal, documentos y selección de niveles

**2.1 Selección de tipo de responsable**: física / física con gestor / moral
(campo `responsables_legales.tipo_persona`). Renderizar campos condicionales según
el tipo (subtipo `personas_fisicas` o `personas_morales` + `gestores` si aplica).

**2.2 Captura de documentos**: para cada documento requerido (consultar
`tipos_documentos` filtrando por `aplica_persona` y `ambito`), renderizar un
formulario con **dos partes**: (a) campos de datos estructurados propios del
documento, (b) carga del archivo PDF. Ver compendio para el detalle de campos por
documento (INE, acta de nacimiento, escritura, dictamen de uso de suelo, constancia
de seguridad estructural + datos del perito/DRO, formato de pago).

- Regla de vigencia: si `tipos_documentos.vigencia_max_dias` no es NULL, validar que
  la fecha de emisión capturada no exceda ese máximo (ej. Dictamen de Uso de Suelo,
  30 días).
- Generar el **Formato de Solicitud** como PDF prellenado (dompdf) a partir de los
  datos capturados, para descarga, firma autógrafa y resubida.

**2.3 Selección de niveles educativos**: checklist de los 4 niveles de Básica. Por
cada nivel marcado, crear un registro en `escuela_niveles` (dispara el Paso 3 para
cada uno).

### Paso 3 — Captura de información por nivel (repetir por cada `escuela_niveles`)

Seis sub-pasos, en este orden, como un wizard:

1. **Datos del inmueble** (`planteles` + `servicios_cercanos` + `acreditaciones_ocupacion_legal`
   + `constancias_seguridad_estructural`) — si el plantel ya existe (Paso 1), precargar
   y permitir edición; si es nuevo, formulario completo.
2. **Infraestructura del nivel** (`instalaciones_espacios`, `sanitarios`,
   `inmueble_estudios_actuales`) — el formulario debe mostrar dinámicamente los
   `tipos_espacios` correspondientes según `niveles_tipos_espacios` para el nivel
   en curso.
3. **Mobiliario** (`mobiliario_nivel`) — mostrar dinámicamente los conceptos de
   `mobiliario_conceptos` aplicables (por sala, si el nivel es Inicial); capturar
   cantidad declarada.
4. **Plan de estudios y modalidad** (`escuela_niveles.modalidad`,
   `plan_estudios_referencia`) — alcance mínimo para MVP: capturar modalidad
   (escolarizada/no escolarizada/mixta) y una referencia de texto libre al plan de
   estudios oficial. *(Detalle de campos pendiente de más información — no bloquear
   el MVP por esto.)*
5. **Plantilla docente** (`personal` + extensiones `personal_asignaturas` /
   `personal_salas`) — el selector de "Cargo/Puesto" debe filtrar `cargos_puestos`
   por el nivel en curso; al elegir cargo, filtrar qué "Estudios" son válidos
   consultando `perfiles_profesionales`. Sin carga de PDF por persona.
6. **Matrícula** (`matricula_salas` / `matricula_grados` según el nivel) — Inicial
   usa `matricula_salas`; Preescolar/Primaria/Secundaria usan `matricula_grados`
   (grado + grupo). Media Superior/Superior quedan fuera de alcance del MVP.

### Motor de Validación de Capacidad Instalada

Al completar los sub-pasos 2, 3, 5 y 6 de un nivel, ejecutar validación cruzada
contra `reglas_validacion`:
- **Superficie**: comparar `aulas_nivel.superficie_m2` y m² de áreas recreativas
  contra los rangos de `reglas_validacion` (tipo `superficie`) según la matrícula
  declarada.
- **Personal**: verificar que existan los cargos obligatorios (ej. Director Técnico
  siempre; Educación Física si matrícula > 60 en Preescolar/Primaria/Secundaria,
  más Trabajador Social y Prefecto en Secundaria).
- **Mobiliario**: comparar `mobiliario_nivel.cantidad_declarada` contra el ratio
  esperado en `mobiliario_conceptos` según la matrícula de la sala correspondiente
  (solo Inicial en el MVP, es el único nivel con catálogo de mobiliario confirmado).

Mostrar advertencias claras al solicitante cuando una validación no se cumpla, sin
bloquear el guardado (el expediente puede quedar "con observaciones").

## 6. Panel SEDEQ (Filament) — alcance mínimo del MVP

- Listado de expedientes (`escuela_niveles`) con filtro por nivel, estado y fecha.
- Vista de detalle de expediente: datos del plantel/escuela/responsable, documentos
  cargados (con enlace de descarga), resultado del Motor de Validación.
- Acción para cambiar `estado_id` del expediente (usar `estados_expediente`),
  registrando el cambio en `historial_estados_expediente`.

## 7. No funcionales

- Todos los formularios deben usar el patrón de captura confirmado: **datos
  estructurados + archivo PDF** para documentos del Paso 2; **solo datos** para
  Anexo 1 (personal) e infraestructura/mobiliario.
- El sistema es 100% web — sin flujos que asuman presencia física, excepto el
  registro del resultado de la visita de verificación (campo de estado, no un
  flujo propio en el MVP).
- Idioma: español (México) en toda la interfaz.
- Diseño: seguir la skill `frontend-design` disponible en el entorno para la UI.

## 8. Criterios de aceptación del MVP

- [ ] Un solicitante puede completar los Pasos 1-3 para Educación Inicial de inicio
      a fin, incluyendo la generación del PDF del Formato de Solicitud.
- [ ] Lo mismo para Preescolar, Primaria y Secundaria.
- [ ] El Motor de Validación de superficie/personal/mobiliario funciona para
      Educación Inicial (cobertura completa) y de superficie/personal para los
      otros 3 niveles de Básica (mobiliario queda fuera si no hay catálogo).
- [ ] El personal de SEDEQ puede ver expedientes en el panel Filament y cambiar su
      estado.
- [ ] La base de datos está poblada con los catálogos completos (`tipos_documentos`,
      `cargos_puestos`, `niveles_tipos_espacios`, `mobiliario_conceptos`,
      `perfiles_profesionales`, `reglas_validacion`) según el compendio.

## 9. Documentos de referencia (deben estar disponibles en el repositorio)

- `COMPENDIO_MAESTRO_Sistema_Incorporacion.md`
- `ddl_sistema_incorporacion_v3.sql`
