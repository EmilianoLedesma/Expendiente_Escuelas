# Revisión global del proyecto — estado en `master` @ `70b0cdf`

**Fecha:** 2026-10-01
**Tipo:** revisión de solo lectura, basada en evidencia. No se editó código, pruebas, migraciones ni configuración; no se tocó la base de desarrollo `sedeq_incorporacion`. Este es el único archivo creado.
**Alcance:** todo `master` después de PR #1 (motor documental, ADR-007), PR #2 (motor de capacidad y sub-pasos 4–6) y PR #3 (validación de entradas).
**Fuentes leídas:** `CLAUDE.md`, `docs/progress.md` (copia de trabajo, ver §5), `docs/superpowers/auditoria/AGENT_BRIEF_remediacion-auditoria-2026-09-23.md`, COMPENDIO, PRD, DDL v3 (local, sin versionar), todos los archivos de `docs/decisions/`, `docs/guia-recorrido-app.md`, `README.md` y el código citado abajo.

Convenciones: **HECHO / PARCIAL / NO HECHO / SUPERADO**. Las referencias `archivo:línea` se resuelven contra `app-laravel/` salvo que se indique otra ruta. **PLAUSIBLE** marca lo que no pude verificar.

---

## 1. Estado actual

### 1.1 Números (ejecutados en esta revisión)

| Verificación | Resultado |
|---|---|
| `php -d memory_limit=1G vendor/bin/phpunit` | **997/997 pasan, 2692 aserciones** (≈232 s), contra `sedeq_incorporacion_testing` |
| `vendor/bin/phpstan analyse --memory-limit=1G` | **0 errores** (nivel 5 + 6 reglas PHPat: 1 de Dominio + 5 de presentación) |
| `vendor/bin/pint --test` | **limpio** |
| `php artisan route:list` | **42 rutas** en total; 23 de la app (`--except-vendor`) |
| Migraciones | **61** archivos (44 del DDL `2026_01_01_*`, 4 `2026_01_02_*`, 13 posteriores) |
| Tablas del DDL frente a migraciones | 53 tablas en el DDL, todas creadas por migraciones; las únicas de más en migraciones son las tablas base de Laravel |
| Modelos Eloquent | **39** |
| Policies | **2** (`EscuelaPolicy`, `EscuelaNivelPolicy`) |
| `app/Application` | 65 clases (49 sin DTO); 15 casos de uso de escritura/ejecución (`Registrar*`, `Ejecutar*`, `IniciarTramiteNuevo`, `MarcarPasoCompletado`) |
| `app/Domain` | 28 clases |
| Páginas Livewire del trámite | 11 (más `MobiliarioGrupo`, que es auxiliar) |
| Recursos de Filament | **0** (`app/Filament/Resources/` solo tiene `.gitkeep`) |
| Versiones (de `composer.lock`) | Laravel 13.30.1, Livewire **3.8.7**, Filament 4.12.8, PHPat 0.12.4, Larastan 3.11.0 |

### 1.2 Lo que hace la aplicación de punta a punta hoy

Todas las rutas `/tramite/*` llevan `auth` y `verified`. Las páginas que escriben usan `can:update`; las descargas y los PDF usan `can:view` (`routes/web.php:25-101`).

| Paso | Ruta | ¿Real? | Notas |
|---|---|---|---|
| Mis trámites | `GET /tramite` | Real | `MisTramitesController` |
| Resumen / hub | `GET /tramite/{escuela}` | Real | `ResumenTramite` es la única fuente del mapa clave→ruta (`Application/Tramite/ResumenTramite.php:29-36`) |
| Paso 1 Preregistro | `/tramite/preregistro` | Real | El dueño se comprueba en la capa Application (`IniciarTramiteNuevo.php:36-39`) |
| 2.1 Responsable + 2.3 Niveles | `/tramite/paso2/{escuela}` | Real | `#[Locked] $fase` (`Paso2Responsable.php:42`); compuerta en `RegistrarNivelesSeleccionados.php:36-39` |
| 2.2 Documentos | `/tramite/paso2/{escuela}/documentos` | Real | Catálogo de 18 claves; física 8 / moral 10 / gestor 9 en 2.2 |
| 2.4 Documentos por nivel | `/tramite/paso2/nivel/{escuelaNivel}/documentos` | Real | Turno y tipo de alumnado, Formato, recibo, acervo, inventario |
| Formato de Solicitud (PDF) | `.../formato-solicitud.pdf` | **Provisional** | Plantilla mínima, sin estructura oficial (WS-5c, ver §2) |
| 3.1 Inmueble | `/tramite/paso3/{en}` | Real | Se completa solo al reutilizar el plantel; sin edición posterior |
| 3.2 Infraestructura | `.../infraestructura` | Real | Solo totales por aula (sin detalle por sala, D4) |
| 3.3 Mobiliario | `.../mobiliario` | Real (solo Inicial) | En los demás niveles se completa solo |
| 3.4 Plan de estudios | `.../plan-estudios` | Real (PR #2) | Desvíos respecto de WS-8 (ver §2) |
| 3.5 Plantilla docente | `.../plantilla` | Real (PR #2) | Sin validación contra el Profesiograma (ver §3) |
| 3.6 Matrícula | `.../matricula` | Real (PR #2) | Inicial por sala; Básica por grado y grupo |
| Validación final | `/tramite/{escuela}/validacion` | Real (PR #1/#2) | Motor documental (bloquea) + capacidad (solo observaciones); guarda el PDF y una fila |
| **Envío** | — | **No existe** | No hay transición de estado; `historial_estados_expediente` no se escribe en ningún punto de `app/` |
| Panel SEDEQ `/admin` | Filament | **Cascarón** | Solo el login, el Dashboard y los widgets por defecto; `canAccessPanel` exige el rol `sedeq` (`Models/User.php:42-45`) |
| `/dashboard` | closure | Redirección por rol | `routes/web.php:104` |

**Motores:**
- **Motor documental (ADR-007):** conectado y bloqueante. Sus reglas están en `Domain/Validaciones/Documental/CatalogoReglasDocumentales.php:29-66`.
- **Motor de capacidad:** `ValidacionCapacidadService` **ya no es un stub** (207 líneas). Está conectado vía `EjecutarValidacionCapacidad`, pero **no bloquea**: sus resultados son observaciones (`EjecutarValidacionFinal.php:50-52`).
- **`estado_id`:** todo expediente nace `en_captura` (`RegistrarNivelesSeleccionados.php:41-49`) y ahí se queda.

---

## 2. Cumplimiento del brief de auditoría (WS-0..WS-9, D1..D9, Apéndice A)

### 2.1 Workstreams

| WS | Estado | Evidencia / lo que falta |
|---|---|---|
| WS-0 Reconciliación factual | **HECHO** (con deriva posterior) | Se aplicó en su momento. Desde entonces `CLAUDE.md`, el README y la guía volvieron a quedar desactualizados (§5). D7 ejecutado: `git ls-files` no lista ningún `.sql`. |
| WS-1 Seguridad | **HECHO** | 1.1 `IniciarTramiteNuevo.php:36-39` + `PlantelesDelSolicitante`. 1.2 `EstadoPaso2`, `PrecondicionIncumplida`, `#[Locked] $fase`. 1.3 `EstadoPaso3` + `CompuertaPaso3`. 1.4 rutas `can:update`/`can:view` (`web.php`). 1.5 controladores en `Http/Controllers/Tramite/*`. 1.6 `User implements MustVerifyEmail` (`User.php:19`) + `verified`. 1.7 `/dashboard` redirige por rol. |
| WS-2 Integridad | **HECHO**, con un resto | 2.1/2.2: pruebas del Apéndice A en verde. 2.3: ruta única con ULID + `afterCommit` (`RegistrarDocumento.php:117-256`). 2.4: `DatosInvalidos` en los casos de uso. 2.5: `CalculadoraRequerimiento.php:80-82`. 2.6: domicilio de solo lectura (`datos-inmueble.blade.php:6-9`). 2.7: `FormatoSolicitudPdfService` y las vistas huérfanas se borraron, **pero sigue el stub vacío y sin referencias `Infrastructure/Documentos/ChecklistDocumentalService.php`**. 2.8: `composer.json` `"php": "^8.4"`. |
| WS-3 Catálogos | **HECHO** | Inicial con SUM/cocina/comedor (`TiposEspaciosSeeder.php:33-36`). Perfiles Enfermera y Profesor en Educación Preescolar (`PerfilesProfesionalesSeeder.php:46-49`). Cargos TS/Prefecto (`CargosPuestosSeeder.php:39-40`). Reglas de Director Técnico y del filtro; `upsert` sobre `clave` (`ReglasValidacionSeeder.php:171`). Existen los PENDIENTE de grado y de perfiles. |
| WS-4 Frontera de lecturas | **HECHO** | ADR-006 y `tests/Architecture/PresentationBoundaryTest.php` (5 reglas), registradas en `phpstan.neon:14-22`. Seguimiento abierto: `DocumentosCapturados` frente a `DocumentosCompletos::clavesPendientes()` (dos consultas). |
| WS-5a Catálogo y aplicabilidad | **HECHO** | Migración `2026_01_02_000002`; `clavesAplicables()` se deriva del catálogo. |
| WS-5b Paso 2.4 | **HECHO** | `Paso24DocumentosNivel` (`can:update`, `#[Locked]`), `EstadoPaso24`, `EstadoPaso3::puedeAcceder` exige 2.4 (`EstadoPaso3.php:46-48`). |
| **WS-5c Formato oficial** | **NO HECHO** | `resources/views/pdf/formato-solicitud.blade.php` (28 líneas) solo imprime nivel, turno, alumnado, `tipo_persona` en crudo, domicilio, nombre/razón social y la terna. Faltan: lugar y fecha, destinatario por config, persona autorizada (3), fundamento legal, RFC/CURP/fecha de nacimiento, bloque notarial de la persona moral, escritura del poder del representante, declaraciones "BAJO PROTESTA" 1–3, consentimiento de notificación electrónica, línea de firma y nomenclatura "Formato 1" en Inicial. El docblock lo reconoce (`Infrastructure/Pdf/FormatoSolicitudPdf.php:13`). |
| **WS-6 Aulas por sala (D4)** | **NO HECHO** | No hay tabla `aulas_nivel_detalle` en el código ni en el DDL. Consecuencia: el motor marca `inicial.superficie.aula_lactantes/aula_maternales` y `altura_aulas` como `no_evaluable` (`ValidacionCapacidadService.php:30-35`). |
| **WS-7 Edición hasta el envío** | **NO HECHO** | No hay paso de envío ni cambio de estado; Responsable, Documentos 2.2, Niveles e Inmueble redirigen hacia adelante (`ResumenTramite.php:44-50`). `PENDIENTE-edicion-hasta-envio.md` y `PENDIENTE-inmueble-relectura-datos.md` siguen abiertos. |
| WS-8 Plan de estudios (D9) | **PARCIAL / SUPERADO por PR #2** | Construido por PR #2 (`RegistrarPlanEstudios`, ruta, orden 4 en `PasosCapturaSeeder.php:19`). **Desvíos frente al brief:** (a) se ofrece `virtual` en Básica (`RegistrarPlanEstudios.php:23`, `plan-estudios.blade.php:10`), cuando el brief dice "do not offer it here"; (b) la plataforma se exige en toda modalidad no escolarizada, no solo en `mixta` (`RegistrarPlanEstudios.php:42-45`; el COMPENDIO §3 dice "Plataforma educativa para modalidad Mixta"); (c) **no hay prueba de ida y vuelta HTTP a `/livewire/update`** (regla 5 del brief) para PlanEstudios, ni para Plantilla, Matrícula, ValidacionFinal, DatosInmueble o Mobiliario. Además, PR #2 construyó 3.5 y 3.6, que el brief dejaba **fuera de alcance**. |
| **WS-9 Cierre documental** | **NO HECHO** | El PRD no se tocó (`PRD...md:42` dice "Laravel 11", `:127` dice "no se agrega Acta Constitutiva"). El COMPENDIO tampoco (encabezado de la línea 4, tabla de 2.2 con 7 documentos en `:74-84`, "calcula en tiempo real" en `:315`, "Pendiente: Preescolar…" en `:434`). No existe `docs/reports/*remediacion-auditoria*` ni la lista consolidada de preguntas para SEDEQ. |

### 2.2 Decisiones del dueño

| D | Respuesta | Estado | Evidencia |
|---|---|---|---|
| D1 Formato por escuela_nivel (b) | b | **HECHO** (ubicación) / **NO HECHO** (estructura, WS-5c) | `TiposDocumentosSeeder.php:45` `ambito=escuela_nivel` |
| D2 Moral/gestor (a) | a | **HECHO** | `acta_constitutiva`, `poder_gestor`, `acta_nacimiento` `ambas` (`TiposDocumentosSeeder.php:40-47`) |
| D3 Documentos faltantes (a) | a | **HECHO**, con dos huecos | Protección Civil, plano y número oficial (plantel); recibo, acervo e inventario (nivel); existe `PENDIENTE-dictamenes-por-nivel.md`. **Huecos:** `visto_bueno_proteccion_civil` tiene `vigencia_max_dias` nulo y no se captura su vigencia, aunque el brief pedía comprobarla "vigente a la fecha de la solicitud"; el número oficial es obligatorio para todos los niveles, aunque en Básica es condicional (abierto con SEDEQ). |
| D4 Aulas por sala (a) | a | **NO HECHO** | WS-6 |
| D5 ADR-006 (a) | a | **HECHO** | ADR-006 + PHPat |
| D6 Verificación de correo (a) | a | **HECHO** | `User.php:19`, `web.php:25` |
| D7 DDL fuera de git (a) | a | **HECHO** | `.gitignore` raíz `*.sql`; el README es coherente (`README.md:118-120`) |
| D8 Corrección de datos del plantel | a → | **SUPERADO** por WS-7 ("edición hasta el envío", `PENDIENTE-edicion-hasta-envio.md`) y **NO IMPLEMENTADO** | `PENDIENTE-inmueble-relectura-datos.md` sigue "abierto" sin registrar la respuesta D8 |
| D9 Plan de estudios | a | **HECHO por PR #2, con desvíos** | ver WS-8 |

### 2.3 Pruebas del Apéndice A (todas existen y pasan en la corrida de hoy)

| # | Prueba | Archivo | Estado |
|---|---|---|---|
| 1 | `test_paso1_rechaza_plantel_ajeno_en_livewire` | `tests/Feature/Auditoria/AuditoriaSeguridadTest.php` | verde |
| 2 | `test_iniciar_tramite_nuevo_rechaza_plantel_ajeno` | idem | verde |
| 3 | `test_solicitante_b_no_puede_descargar_documentos_de_plantel_de_a` | idem | verde |
| 4 | `test_guardar_niveles_sin_responsable_ni_documentos_no_crea_escuela_niveles` | idem | verde |
| 5 | `test_registrar_niveles_rechaza_si_paso2_incompleto` | idem | verde |
| 6 | `test_fase_de_paso2_no_es_escribible_por_el_cliente` | idem | verde |
| 7 | `test_documentos_antes_de_responsable_redirige_a_paso2` | `AuditoriaIntegridadTest.php` | verde |
| 8 | `test_espacio_con_superficie_sin_cantidad_se_guarda` | idem | verde |

El brief pedía un solo archivo `AuditoriaRegresionTest.php`; se dividió en dos, cosa que el brief permite ("Split the file per workstream if you prefer").

### 2.4 Hallazgos críticos originales: ¿siguen cerrados?

| Hallazgo | ¿Cerrado? | Comprobación de reapertura por PR #1/#2/#3 |
|---|---|---|
| Adjuntar un plantel de otro solicitante | **Sí** (`IniciarTramiteNuevo.php:36-39`, prueba 1–3) | PR #3 no tocó el predicado. Paso 1 sigue validando `exists:planteles,id` solo como apoyo de interfaz y el mensaje no revela si el plantel existe (`Paso1Preregistro.php:70-74`). |
| Saltarse el Paso 2 | **Sí** | Los casos de uso nuevos llevan compuerta: `RegistrarPlanEstudios`, `RegistrarPlantillaDocente` y `RegistrarMatricula` pasan por `CompuertaSubPaso3` (que lanza `PrecondicionIncumplida`); Plantilla y Matrícula la repiten bajo `lockForUpdate` (`RegistrarPlantillaDocente.php:34,76`). `EjecutarValidacionFinal.php:44-46` exige el trámite completo. |
| Deriva de capas en lecturas | **Sí** para las decisiones de flujo (ADR-006 + PHPat) | Las páginas nuevas leen Eloquent solo para mostrar (permitido por ADR-006 §2); ningún componente Livewire importa `App\Domain` ni `DB`. Ojo: `Application` depende de `Infrastructure` concreta (`EjecutarValidacionFinal.php:11` → `ReporteValidacionPdf`; `AlmacenDocumentos` es una interfaz que vive en Infrastructure). Ninguna regla PHPat lo prohíbe; ver §4 P3. |
| Huecos de cobertura normativa | **PARCIAL** | ver §3 |
| `#[Locked]` / `can:*` / `verified` en rutas nuevas | OK | Las 3 páginas de PR #2 tienen `#[Locked] $escuelaNivel`. `ValidacionFinal` (`can:update`) y `Paso2Documentos`/`Paso2Responsable`/`DatosInmueble`/`InfraestructuraNivel`/`MobiliarioNivel` tienen propiedades de modelo **sin** `#[Locked]`; Livewire 3 ya protege los IDs de modelo (lo comprobó la sonda de PR #2) y las rutas reaplican la autorización por middleware persistente (PLAUSIBLE: no lo probé por HTTP en esta revisión). Es consistencia, no un hueco. |
| Descargas/PDF nuevas | OK | `DescargarReporteValidacionController` + `ReporteValidacionGuardado.php:12` filtran por `escuela_id`, con prueba en `AutorizacionEscrituraTest.php:67`. El disco `documentos` es privado (`config/filesystems.php:63-69`, `serve=false`). |

---

## 3. Cumplimiento del COMPENDIO y del PRD

| Área | Estado | Evidencia y discrepancias |
|---|---|---|
| Modelo Plantel → Escuela → Escuela+Nivel | **Implementado** | DDL; `escuela_niveles` UNIQUE |
| Paso 1, bifurcación nuevo/existente | **Implementado** | La precarga se limita a planteles del mismo solicitante. El PRD §1 (`:34-35`) aún dice que la reutilización "no es parte del MVP", pero la reutilización existe: **contradice** (deriva del PRD). |
| 2.1 Tipo de responsable (3 tipos) | **Implementado** | `Paso2Responsable.php:144` |
| 2.2 Checklist por tipo de persona | **Implementado, por encima del COMPENDIO §3** | El COMPENDIO §3 (`:74-84`) dice "Ambos tipos de persona requieren 7 documentos" y marca el acta de nacimiento como solo física. El código sigue los Requisitos (D2): acta para `ambas`, acta constitutiva y poder del gestor. **El COMPENDIO quedó atrás (WS-9).** Las constancias de CURP y de Situación Fiscal (ADR-007) no están en el COMPENDIO. |
| Checklist real (17 ítems, Apéndice B) | **Parcial** | Faltan como documentos: **propuesta de emblema y sello** y **protocolos de conductas de riesgo** (Requisitos Inicial m, n; COMPENDIO `:295-296`); **Anexo 1 y Anexo 2 firmados** (el COMPENDIO `:163` dice "se genera PDF para firma autógrafa y resubida", y no existe ningún PDF de anexos: `grep anexo` en `app/` solo devuelve comentarios). Cédula y registro del perito se capturan como campos de la constancia (decisión documentada, COMPENDIO `:86`). |
| Por nivel (§4) | **Implementado para Básica** | Acervo Primaria/Secundaria, inventario Secundaria (`TiposDocumentosSeeder.php:63-66`) |
| Vigencias | **Parcial** | Dictamen de uso de suelo de 30 días = "1 mes" (`TiposDocumentosSeeder.php:43`, COMPENDIO `:82`) ✔. Año del registro DRO = año de emisión ✔ (`ValidarVigenciaDocumentos.php:41-49`). **Protección Civil sin vigencia.** Contrato de arrendamiento/comodato: la "vigencia mínima de un ciclo escolar" (COMPENDIO `:302`) y el "comodato ratificado ante Notario" en Básica (`:303`) se capturan (`ratificado_notario`) pero **ninguna regla los valida**. |
| Validación cruzada (nombre, domicilio) | **Parcial** | Nombre/CURP/RFC/domicilio vs certificado de número oficial ✔ (`CatalogoReglasDocumentales.php:34-40`). No se valida "escritura del inmueble a nombre del solicitante" (COMPENDIO `:81`). |
| Terna de nombres | **Parcial** | Se capturan 3 nombres distintos. **Falta** "no estar ya registrado ante la propia SEDEQ" (COMPENDIO `:291`, validable contra la base de datos) y las reglas de formato (`:293`). No hay consulta en `app/` (`grep TernaNombre`). |
| Formato de Solicitud | **Ubicación correcta (D1), estructura NO** | ver WS-5c |
| Turno / tipo de alumnado | **Implementado** | Paso 2.4, `RegistrarDatosNivel` |
| Infraestructura por nivel | **Implementado (agregado)** | Matriz `niveles_tipos_espacios` provisional (`PENDIENTE-matriz-espacios-por-nivel.md`). **Inicial por sala NO** (D4). |
| Mobiliario Inicial | **Implementado** | Valores coherentes con el COMPENDIO (`MobiliarioConceptosSeeder.php:50,58,83,94`: cuna 1/2, mesa 1/6). Los conceptos de la SUM no se evalúan (P5 provisional). |
| Plan de estudios / modalidad | **Implementado, con contradicción** | Se ofrece `Virtual` en Básica; el COMPENDIO `:122` lo liga a Media Superior/Superior. La plataforma se exige también en `no_escolarizada`. |
| Plantilla docente (Anexo 1) | **Parcial** | Seis campos, cargo por nivel, sala y asignatura ✔. **No se filtran ni validan "Estudios" contra `perfiles_profesionales`** (PRD §5.5; COMPENDIO `:144` "después filtra qué Estudios son válidos"): ninguna clase de `app/` usa esa tabla. Tampoco: nacionalidad mexicana para Historia/FCyE/Geografía (COMPENDIO `:628`), cartilla SMN ni capacitación didáctica (`:575`, `:630`), primeros auxilios (`:507`), "profesionista afín" sujeto a revisión (B.4). |
| Matrícula | **Implementado** | Por sala en Inicial y por grado/grupo en Básica (`RegistrarMatricula.php`). Solo `alta_nueva`; la variante "matrícula real" de reincorporación (COMPENDIO `:146`) no existe. No se limita a 11 grupos (A–K) por grado. |
| Superficies y ratios §5/§5.2 | **Parcial** | Coinciden con el COMPENDIO: Inicial 25 m²/sala (≤10 / ≤15), recreativa 1, SUM 1.2, sanitarios 0.80, asistentes 5/10 hacia arriba. Preescolar 1.00 / 1+2 / 1.25 / factor 1.5. Primaria 2.50 / 0.90 / 2.70 / 50 por grado. Secundaria 2.50 / 0.90 / 1.25 / 200 m² / 300 títulos (`ReglasValidacionSeeder.php:77-125`). **No sembrado** (granular, aplazado según `progress.md` Phase 3): tablas de sanitarios por rango (Preescolar, Primaria por sexo, Secundaria), bacinicas y retretes/lavabos de Maternal B, dimensiones de aula por capacidad (24/48/64 y 24/36/48), puertas, pasillos, escaleras, iluminación, bebederos, estacionamiento, agua potable, clasificación Tipo 1–4 de Inicial. |
| Umbral de Educación Física | **Inconsistente y provisional** | Preescolar y Secundaria usan `condicion_min = 61` (">60"). **Primaria usa `floor(alumnos/60)`, que exige 1 docente con 60 alumnos exactos (umbral efectivo ≥60).** El mismo concepto normativo tiene dos umbrales distintos en el código. Ya está documentado en `PENDIENTE-umbral-educacion-fisica.md:108-111`; lo resuelve SEDEQ. |
| Personal condicionado por grado | **No implementado** | `PENDIENTE-personal-condicionado-por-grado.md` (decide el dueño) |
| Alcance del motor de validación | **Implementado (MVP)** | Corre por lote al final, como dice el PRD. El COMPENDIO §5 `:315` aún dice "calcula en tiempo real… bloqueos": **contradice**; el PRD manda. |
| Estados del expediente / panel SEDEQ (PRD §6) | **No implementado** | Se siembran 6 estados (`CatalogoMinimoSeeder.php:25-32`). No hay envío, `historial_estados_expediente` ni recursos de Filament. Criterio de aceptación PRD §8 "El personal de SEDEQ puede ver expedientes… y cambiar su estado": **NO cumplido**. |
| Etapas 1/2/3 | Etapa 1 casi completa (le faltan el Formato oficial y los anexos firmados); la parte automática de la Etapa 2 está hecha; la revisión humana está fuera del MVP; la Etapa 3 queda limitada a reutilizar planteles del mismo dueño | — |
| Ventana de recepción, folio `IN-AAAA-NNN` | No implementado | El folio lo asigna SEDEQ (`ResumenTramiteDTO.php:26`); la ventana está abierta con SEDEQ (COMPENDIO `:245`) |
| Salas (catálogo) | OK aproximado | Lactantes A arranca en 1 mes, aunque la fuente dice "45 días" (`CatalogoMinimoSeeder.php:35`). Solo informativo. |

---

## 4. Correcciones pendientes, por prioridad

| # | P | Qué | Dónde | Por qué importa | Sugerencia / tamaño | ¿Decisión previa? | WS |
|---|---|---|---|---|---|---|---|
| 1 | **P0** | Formato de Solicitud sin la estructura oficial | `resources/views/pdf/formato-solicitud.blade.php`; `Infrastructure/Pdf/FormatoSolicitudPdf.php` | Es el documento legal que se firma y se sube: le faltan las declaraciones bajo protesta, el consentimiento de notificación, la persona autorizada, los datos notariales y la firma. Además imprime `tipo_persona` en crudo. | Reconstruir según el Apéndice B.1 + "Formato 1" en Inicial; destinatario por config; decidir dónde vive la escritura del poder del representante. **M–L** | Dónde se guarda la escritura del poder (dueño) | WS-5c |
| 2 | **P0** | Borrar la cuenta falla para todo solicitante real (por lectura de código, no ejecutado) | `database/migrations/2026_09_08_000000_create_solicitantes_table.php:13` (`REFERENCES users(id)` sin `ON DELETE`) + `resources/views/livewire/profile/delete-user-form.blade.php:20` | El registro crea un `solicitante` y la FK impide borrar el `user`: hay error de BD después de cerrar sesión (`tap(...)->delete()`). La prueba `tests/Feature/ProfileTest.php:67-83` usa un usuario de factory **sin** solicitante, así que no lo detecta. También toca la protección de datos (derecho de supresión). | Decidir la política: ocultar o deshabilitar el borrado mientras haya trámites, o borrado en cascada controlado. Añadir una prueba con solicitante. **S** + decisión | Sí (dueño: qué se borra) | Nuevo (ligado a "eliminar trámite") |
| 3 | **P0** | Umbral de Educación Física inconsistente entre niveles | `ReglasValidacionSeeder.php:96,104,122` | Primaria ≥60 frente a Preescolar/Secundaria >60: resultado normativo distinto para el mismo texto | Unificar cuando SEDEQ conteste. **XS** (datos) | Sí (SEDEQ) | PENDIENTE umbral |
| 4 | **P1** | No hay envío ni transición de estado; edición hasta el envío | `EjecutarValidacionFinal.php:24-26`; `ResumenTramite.php:44-50` | Sin envío no hay expediente cerrado, ni `historial`, ni bloqueo de edición; los datos editados después de validar dejan obsoleta la evaluación | Plan de WS-7: disparador de envío que **reejecuta** la validación, `en_captura→en_revision` + historial y bloqueo; reglas de edición (preguntas de `PENDIENTE-edicion-hasta-envio.md:30-37`). **L** | Sí (dueño, preguntas abiertas) | WS-7 |
| 5 | **P1** | Panel SEDEQ vacío (PRD §6, criterio §8) | `app/Filament/Resources/` | Criterio de aceptación del MVP no cumplido | Recurso de lista y detalle de `escuela_niveles` (documentos con descarga vía `can:view`, resultado del motor) + acción de cambio de estado con historial. **M–L** | Requiere WS-7 (estado) y abrir `view` a `sedeq` en las policies | Nuevo (PRD §6) |
| 6 | **P1** | Aulas por sala en Inicial (D4) | sin tabla; `ValidacionCapacidadService.php:30-35` | Las reglas de 25 m²/sala y de altura quedan `no_evaluable` | Migración nueva + DDL; filas de Formato 3; agregado derivado. **M** | No (D4 ya decidido) | WS-6 |
| 7 | **P1** | Plantilla no validada contra el Profesiograma | `Application/Personal/RegistrarPlantillaDocente.php:40-67`; `perfiles_profesionales` sin uso | PRD §5.5 / COMPENDIO `:144` exigen filtrar "Estudios" | Selector de estudios por cargo + escape "profesionista afín" (B.4) + regla del motor. **M** | Parcial (cómo se trata el "afín") | Nuevo / WS-8+ |
| 8 | **P1** | Desvíos de WS-8: `virtual` ofrecido en Básica; plataforma exigida fuera de `mixta` | `RegistrarPlanEstudios.php:23,42-45`; `plan-estudios.blade.php:10` | Contradice el brief y el COMPENDIO `:122,126` | Quitar `virtual`; exigir la plataforma solo en `mixta` (o confirmar P7). **XS** | Confirmar P7 (dueño) | WS-8 |
| 9 | **P1** | Faltan pruebas de ida y vuelta HTTP `/livewire/update` en las páginas de PR #2 y en DatosInmueble, Mobiliario y ValidacionFinal | `tests/Feature/Livewire/Tramite/` (solo existen 5 archivos con `/livewire/update`) | Regla 5 del brief y lección de ADR-003 (419) | Una prueba de ida y vuelta por página. **S** | No | WS-8 / calidad |
| 10 | **P1** | Visto Bueno de Protección Civil sin vigencia; número oficial obligatorio en Básica | `TiposDocumentosSeeder.php:48,50` | El requisito dice "vigente a la fecha de la solicitud" y el número oficial es condicional en Básica | Capturar la vigencia + regla en `ValidarVigenciaDocumentos`; la condición del número oficial espera a SEDEQ. **S** | Número oficial: SEDEQ | WS-5 resto |
| 11 | **P1** | Documentos ausentes: emblema y sello, protocolos (Inicial); Anexo 1 y 2 en PDF para firma | catálogo / `Infrastructure/Pdf` | Checklist real y COMPENDIO `:163,295-296` | Filas de catálogo nuevas por nivel + PDF de los anexos. **M** | Confirmar alcance (dueño) | Nuevo |
| 12 | **P2** | Ratios granulares no sembrados (sanitarios por rango, aula por capacidad, etc.) | `ReglasValidacionSeeder.php` | El motor no cubre todo §5/§5.2; el PRD §8 pide superficie/personal "completo" | Sembrar por fases + capturar los datos que faltan (altura, aula mayor). **M–L** | Origen de la magnitud (arquitecto) | PENDIENTE origen-de-magnitud |
| 13 | **P2** | Reglas documentales que faltan: escritura a nombre del solicitante, comodato ratificado (Básica), vigencia del contrato ≥ ciclo escolar, terna no registrada en SEDEQ | `CatalogoReglasDocumentales.php` | Normativo (COMPENDIO `:81,291,302-303`) | Reglas nuevas en Domain. **M** | No | Nuevo |
| 14 | **P2** | Pantalla de validación sin límite de frecuencia: cada "Validar de nuevo" genera un PDF y una fila | `Livewire/Tramite/ValidacionFinal.php:33-45`; `ReporteValidacionPdf.php:28-39` | Crecimiento de almacenamiento ilimitado por usuario | `RateLimiter` o deduplicación por hash de entradas. **S** | No | Nuevo |
| 15 | **P2** | Invariantes de API: `RegistrarDocumento` no limita el tamaño (solo Livewire, `max:10240`); los casos de uso de Paso 3 no reciben `solicitanteId` (dependen de la policy) | `RegistrarDocumento.php:88`; `Registrar*` | Un adaptador `/api/v1` heredaría huecos | Validar el tamaño en el caso de uso; documentar que la policy es obligatoria en todo adaptador. **S** | No | API (Etapa 3) |
| 16 | **P2** | Compuerta de Paso 3 duplicada | `RegistrarDatosInmueble.php:89-93`, `RegistrarInfraestructuraNivel.php:67-71`, `RegistrarMobiliarioNivel.php:83-87` frente a `CompuertaSubPaso3` | Riesgo de deriva (el propio docblock lo admite, `CompuertaSubPaso3.php:11-12`) | Usar `CompuertaSubPaso3` en los tres. **XS** | No | Calidad |
| 17 | **P2** | `DocumentosCapturados` frente a `clavesPendientes()`: dos consultas para la misma pregunta | `Application/Documentos/` | Seguimiento de WS-4 abierto | Una consulta base. **S** | No | WS-4 seguimiento |
| 18 | **P2** | Mensaje de folio reutilizado revela que el folio existe en otro trámite | regla `ReciboNoReutilizado` (conocido, `progress.md:164`) | Fuga menor de información entre solicitantes | Mensaje genérico; índice único si se decide. **XS** | Dueño | Nuevo |
| 19 | **P2** | PII en texto plano (CURP, RFC, INE, domicilio) | DDL `:355-356,599-611`; docblocks de los modelos ("not encrypted yet (owner decision)") | Protección de datos | Cast `encrypted` + índice ciego si se busca; política de retención. **M** | Sí (dueño) | Nuevo |
| 20 | **P2** | Deriva del DDL: CHECK de `aplica_persona` sin `fisica_con_gestor` | `docs/ddl_sistema_incorporacion_v3.sql:59-60` frente a la migración `2026_01_02_000002:17` | El DDL es la fuente de verdad declarada | Actualizar el DDL local. **XS** | No | WS-9 |
| 21 | **P3** | Código muerto: `ChecklistDocumentalService` vacío y sin referencias | `app/Infrastructure/Documentos/ChecklistDocumentalService.php` | Ruido; `CLAUDE.md` lo implica | Borrarlo. **XS** | No | WS-2.7 resto |
| 22 | **P3** | Application depende de Infrastructure concreta; ninguna regla PHPat lo cubre | `EjecutarValidacionFinal.php:11`, `RegistrarDocumento.php:12` | Frontera de capas | Interfaces en Application + regla PHPat; añadir "Livewire no depende de Domain" (regla de `CLAUDE.md` sin enforcement). **S** | No | WS-4 seguimiento |
| 23 | **P3** | Barrido de `#[Locked]` en los componentes antiguos | `Paso2Documentos`, `Paso2Responsable`, `DatosInmueble`, `InfraestructuraNivel`, `MobiliarioNivel`, `ValidacionFinal` | Consistencia (Livewire ya protege los IDs de modelo) | Añadir `#[Locked]`. **XS** | No | Calidad |
| 24 | **P3** | Panel Filament sin `emailVerification()`; hook de captura global | `AdminPanelProvider.php`; `Livewire/Hooks/LimpiarYValidarAlCapturar.php` | Endurecimiento; un futuro Textarea de Filament perdería saltos de línea | Limitar el hook a `App\Livewire\*`. **XS** | No | Calidad |
| 25 | **P3** | `.env.testing` versionado con APP_KEY y contraseña de BD de pruebas; el CI repite los mismos valores | `app-laravel/.env.testing`; `.github/workflows/ci.yml:16-24` | Aceptado como "solo pruebas", pero el repo es público. **No verifiqué** si la base de desarrollo usa la misma contraseña (comparar con `.env` fue denegado por permisos). | Confirmar que la de desarrollo es distinta; si es la misma, rotarla. **XS** | Dueño | Nuevo |

**Brechas de pruebas:** ninguna prueba de Filament más allá del acceso (`tests/Feature/Filament/PanelAccessTest.php`); ninguna del contenido del Formato PDF; borrado de cuenta con solicitante (#2); las rutas de ida y vuelta de #9; el motor no tiene prueba que fije el cambio de fecha imposible de CURP/RFC (`progress.md:184`).

---

## 5. Deriva documental

| Documento | Afirmación | Realidad |
|---|---|---|
| `CLAUDE.md:101` | "Eloquent persistence layer (25 models)" | 39 modelos |
| `CLAUDE.md:120` | "ValidacionCapacidadService as empty stub… remains a stub pending specification" | Implementado (207 líneas), conectado por `EjecutarValidacionCapacidad` |
| `CLAUDE.md` (árbol) | `Application/` lista 8 carpetas; `Domain/` solo Personal y Validaciones | También existen Captura, Excepciones, Matricula, Personal, PlanEstudios, Tramite y Validaciones (Application) y Domain/Captura, Validaciones/Documental y Resultado; no aparecen `ValidacionFinal` ni `ReporteValidacionPdf` |
| `CLAUDE.md:140` y `ci.yml` (nombre del paso) | "PHPStan… plus one PHPat rule" | 6 reglas PHPat (Dominio + 5 de presentación) |
| `README.md:80-98` | Árbol con `Http/Livewire/`, sin `Application/`; "la lógica de negocio vive en Domain/ e Infrastructure/" | Livewire vive en `app/Livewire` (ADR-003); los casos de uso están en `app/Application` (ADR-001) |
| `docs/guia-recorrido-app.md:3` | Describe `8d1a6bb` (2026-09-25): "Documentos (6)", Formato en 2.2, Plantilla y Matrícula como "esqueletos", motor "no se ejecuta" | Desactualizada por WS-4/5a/5b y PR #1/#2/#3 |
| `docs/progress.md:57` (Decisions Log) | "Livewire v4 instead of v3" | `composer.lock` tiene livewire **3.8.7**; el encabezado (`:5`) dice 3. La entrada es falsa (hay que corregirla con una nota anexa, no reescribirla). |
| `docs/progress.md:42-46` (Phase 4) | "Livewire wizard logic" y "Motor de Validación implementation" sin marcar | Existen |
| `docs/progress.md:37` (Phase 3) | "Still not started: tipos_documentos, niveles_tipos_espacios…" | Sembrados |
| `docs/progress.md` (copia de trabajo) | Entrada de PR #3 (`:179-185`) | **No está commiteada**: `git status` muestra `M docs/progress.md`; en `HEAD` el ledger termina en la entrada de PR #2. El controlador debe commitearla. |
| `docs/progress.md:91` + `PENDIENTE-plantel-solicitante-cardinalidad.md` ("Dato heredado") y `PENDIENTE-motor-capacidad-provisionales.md:74` | Siguen describiendo un plantel compartido en desarrollo | `progress.md:104` registra el TRUNCATE de todas las tablas de captura del 2026-09-25 y la guía (`:139`) dice que ya no existe. **PLAUSIBLE** que ya no exista (no consulté la base de desarrollo). |
| PRD `:42`, `:34-35`, `:115`, `:126-130`, §5 | Stack Laravel 11/Filament 3/PG 16; "reutilización no es parte del MVP"; "formato de pago" en 2.2; Acta Constitutiva diferida; sin 2.4 | Superados por D2/D3/D1 y por el código (WS-9 pendiente) |
| COMPENDIO `:4`, `:74-92`, `:315`, `:434-441`, `:430`, `:452/473`, `:595` | Encabezado viejo; 7 documentos; "tiempo real"; "Pendiente: Preescolar…" (superado por §5.2); EC0435 sin fuente; redacción del umbral; escaleras de Secundaria "mismo esquema que Primaria" con 75 frente a 180 | Ninguna nota "Corrección" posterior al 2026-09-11 (WS-9 pendiente) |
| DDL `:59-60` | CHECK de `aplica_persona` sin `fisica_con_gestor` | La migración `2026_01_02_000002` lo agrega |
| `PENDIENTE-inmueble-relectura-datos.md:3-5` | "Requiere: decisión del dueño" | D8 se respondió (a) y se absorbió en WS-7; el archivo no lo registra. El nombre es correcto (sigue sin implementarse). |
| `PENDIENTE-edicion-hasta-envio.md:11` | "Estado actual (`master` en `ce3a154`)" | Lista desactualizada (2.4 y los sub-pasos 4–6 ya existen y se pueden revisar) |
| Estados de los ADR | ADR-001..007 | Coherentes: los que dicen "Aceptado"/"resuelto" llevan prefijo ADR, y ningún PENDIENTE dice "resuelto". **Sin incumplimientos** de la regla de nombres. |

---

## 6. Preguntas abiertas que solo resuelve el dueño o SEDEQ (sin duplicados)

**SEDEQ (normativas):**
1. Educación Física (y Trabajador Social/Prefecto en Secundaria): ¿"60 o más" o "más de 60"? ¿Se mide por **capacidad** instalada o por **matrícula**? — `PENDIENTE-umbral-educacion-fisica.md`, P1 de `PENDIENTE-motor-capacidad-provisionales.md`.
2. ¿El Dictamen de Uso de Suelo y el Visto Bueno de Protección Civil deben emitirse **por nivel** o basta uno por plantel? — `PENDIENTE-dictamenes-por-nivel.md`.
3. ¿Cuándo es obligatorio el Certificado de Número Oficial en Básica (condicional por domicilios distintos) y cómo se determina antes de elegir niveles? — idem.
4. Perfiles profesionales de **Trabajador Social** y **Prefecto** en Secundaria. — `PENDIENTE-perfiles-trabajador-social-prefecto.md`.
5. ¿Puede un plantel tener escuelas de **solicitantes distintos**? — `PENDIENTE-plantel-solicitante-cardinalidad.md` (también la puede decidir el dueño).
6. Matriz de espacios por nivel: ¿a qué niveles aplica cada tipo de espacio y cuáles son obligatorios? — `PENDIENTE-matriz-espacios-por-nivel.md`.
7. Ventana de recepción: ¿septiembre–diciembre o todo el año? — COMPENDIO `:245,:666`.
8. Constancia de seguridad estructural + carta DRO: ¿uno o dos archivos? — COMPENDIO `:664`.
9. Escaleras de Secundaria: ¿+0.60 m por cada **75** o por cada **180** alumnos adicionales? — COMPENDIO `:595` frente a `:561` (brief, Apéndice C).
10. ¿Adecuaciones locales de Querétaro a los Acuerdos 357/254/255? — COMPENDIO `:665`.

**Dueño / arquitecto:**
11. WS-7: qué cuenta como "envío"; qué pasa con los documentos que dejan de aplicar al cambiar el tipo de persona; quitar un nivel que ya tiene datos; si la terna es editable. — `PENDIENTE-edicion-hasta-envio.md:30-37`.
12. Elecciones provisionales P2–P10 del motor de capacidad (predio compartido, áreas compartidas, mobiliario de la SUM, acervo solo libros, plataforma, reemplazo total, EF por nombre de asignatura). — `PENDIENTE-motor-capacidad-provisionales.md`.
13. Origen de la magnitud: ¿se confirma la opción C (resolver por `clave` en Domain)? — `PENDIENTE-origen-de-magnitud.md`.
14. Personal condicionado por grado (Inglés/Computación): opción de modelado y captura de grados ofertados. — `PENDIENTE-personal-condicionado-por-grado.md`.
15. Municipio/localidad como catálogo (FK, solo Querétaro o INEGI). — `PENDIENTE-catalogo-municipios.md`.
16. Política de borrado de cuenta y trámite (hallazgo P0 #2) y la tarea "eliminar trámite" en cola.
17. ¿Cifrado de PII (CURP/RFC/INE) y retención? (los modelos dicen "owner decision").
18. ¿Se mantiene `Virtual` en Básica y la plataforma obligatoria para `no_escolarizada`? (WS-8 frente a P7).

---

## 7. Lo que no pude verificar

- Si la contraseña o el APP_KEY de `.env` de desarrollo coinciden con los de `.env.testing` (la comparación fue denegada por permisos).
- Si en la base de desarrollo sigue existiendo un plantel compartido entre solicitantes (está prohibido consultar esa base).
- El fallo del borrado de cuenta (#2) sale de leer el código y el DDL; no se ejecutó.
- El rate limiting propio del login de Filament (PLAUSIBLE: Filament lo trae de serie) y la reaplicación de `can:*` en cada petición `/livewire/update` de las páginas sin prueba de ida y vuelta (PLAUSIBLE: middleware persistente de Livewire 3).
- No se hizo ninguna verificación en navegador ni de accesibilidad.
