# WS-2 — Integridad de captura y defectos menores

**Fecha:** 2026-09-24
**Rama:** `fix/integridad-captura` (worktree `.claude/worktrees/ws2`), creada desde `master` local en `573937f`.
**Origen:** brief de remediación de la auditoría del 2026-09-23, sección "WS-2 — Capture integrity & small defects" (tareas 2.1–2.8) y pruebas de regresión 7–8 del Apéndice A.
**Clasificación:** acotada (bounded). El propietario dio la autorización para continuar ("Continue where left on plan").

## Resultado

- `php artisan test`: **429/429** (977 aserciones). Base al iniciar la rama: 381.
- `vendor/bin/pint --test`: limpio.
- `vendor/bin/phpstan analyse --memory-limit=512M`: 0 errores (nivel 5 + regla PHPat de ADR-001).
- Sin migraciones y sin cambios de esquema. No se escribió nada en la base de datos de desarrollo.

## Método

Cada tarea pasó por un implementador (Sonnet; Haiku en 2.7/2.8) y una revisión independiente (Sonnet) o una verificación directa del controlador cuando el diff era pequeño (2.2, 2.5, 2.6, 2.7, 2.8). Después hubo una revisión de toda la rama (Opus): la primera vuelta dio "con correcciones", se aplicó una ronda de correcciones y la segunda vuelta confirmó todos los hallazgos de código como resueltos. Las dos observaciones menores restantes las corrigió el controlador (`f6b079d`).

Todas las pruebas nuevas se vieron fallar por la razón correcta antes de corregir el código, salvo las indicadas explícitamente como "cobertura de regresión" más abajo.

## Pruebas de regresión 7–8 (Apéndice A)

- **Prueba 7** (`test_documentos_antes_de_responsable_redirige_a_paso2`): ya pasaba al iniciar la rama, porque WS-1.2 agregó la redirección. Se incorporó sin cambios.
- **Prueba 8** (`test_espacio_con_superficie_sin_cantidad_se_guarda`): tras WS-1 fallaba por la razón equivocada (las compuertas de Paso 2/Paso 3 redirigían antes de llegar al código). **Solo se ajustó su preparación** (Paso 2 completo mediante `CompletaPaso2` y sub-paso `inmueble` completado); la aserción no cambió. Con eso falló por la razón correcta ("La superficie declarada se descartó en silencio") y pasa desde `04bf537`.

## Tareas

### 2.1 — Pérdida silenciosa de datos en infraestructura (`04bf537`, `857c73a`, `fa9d9be`)
- Un espacio se guarda si trae **cualquier** dato (cantidad, superficie, capacidad, destino, campo de fútbol, materiales de biblioteca); `cantidad` es nulo en el DDL.
- Los booleanos (ventilación/iluminación natural) cuentan como dato **solo si son `true`**. Una casilla sin marcar envía `false`; antes eso creaba una fila vacía y, por ADR-005, dejaba ese tipo como "capturado" para siempre en el plantel.
- Materiales de biblioteca sin fila de biblioteca: se crea la fila de biblioteca, porque `biblioteca_materiales.instalacion_espacio_id` es `NOT NULL` y es la única forma de conservar los materiales.
- **Decisión:** el caso de uso `RegistrarInfraestructuraNivel` es la **única** fuente de la regla "¿trae dato?" (`tieneDatosSignificativos`, y `tieneDatosSignificativosSanitario` para sanitarios). El componente dejó de filtrar por su cuenta y envía una entrada por cada tipo/categoría aplicable. Motivo: la revisión de rama encontró que las dos copias ya habían divergido (un campo de fútbol con solo "formato" se descartaba).
- Un sanitario sin datos ya no crea fila (solo alcanzable por un cliente de API).

### 2.2 — Indicador de progreso "Documentos" (`fb77a5d`)
- El punto "Documentos" solo es enlace cuando existe responsable legal.
- Su estado sale de `EstadoPaso2` (documentos completos **y** vigentes), no de la existencia de `escuela_niveles`.
- La redirección de `Paso2Documentos::mount()` sin responsable ya existía desde WS-1.2; se confirmó, no se rehízo.

### 2.3 — Atomicidad al escribir documentos (`3240f90`, `6ff2927`, `80a9ace`, `f6b079d`)
- Cada carga se guarda en una ruta única `{ambito}/{ownerId}/{clave}-{ULID}.pdf`; nunca pisa el archivo anterior.
- La fila (base + extensión) se actualiza dentro de la transacción.
- El archivo anterior se borra con `DB::afterCommit`, envuelto en `rescue()`: un fallo al borrarlo se reporta pero no revierte ni rompe la carga ya confirmada.
- Si la transacción falla, se borra el archivo nuevo. Una bandera `$confirmado`, que solo se activa dentro del callback de commit, evita borrar el archivo nuevo cuando la fila ya lo referencia. Esa limpieza también va en `rescue()` para no ocultar la excepción original.
- Si una transacción **externa** que envuelve el caso de uso hace rollback, `DB::afterRollBack` elimina el archivo nuevo huérfano; el anterior se conserva.
- Las filas existentes con la ruta fija antigua siguen funcionando; no hubo migración de datos.
- **Corrección registrada:** el implementador afirmó al principio que `DB::afterCommit` no se ejecuta con `RefreshDatabase`. Es falso: `Illuminate\Foundation\Testing\DatabaseTransactionsManager` lo ajusta para que sí se ejecute. Se corrigió en `6ff2927`.

### 2.4 — Invariantes en la capa Application (preparación para la API)
**2.4a — datos de entrada (`bf17c21`):** nueva excepción única `App\Application\Excepciones\DatosInvalidos` (campo ⇒ mensaje). Las claves coinciden con las propiedades de Livewire, que muestra los errores en su campo (nunca un 500).
- `RegistrarDocumento`: clave aplicable al tipo de persona (reutiliza `DocumentosCompletos::clavesAplicables()`); el archivo debe ser PDF (se detecta el tipo en el servidor con `getMimeType()`, no el que declara el cliente).
- `RegistrarInfraestructuraNivel`: tipo de espacio dentro de `niveles_tipos_espacios` para el nivel; categoría de sanitario permitida (la lista se movió a `App\Application\Infraestructura\CategoriasSanitariosPorNivel`, usada por el componente y por el caso de uso); números no negativos.
- `RegistrarDatosInmueble`: `metrosTotales` > 0; latitud [-90, 90] y longitud [-180, 180]; `tipo` y `distancia_unidad` de servicios según los CHECK del DDL; cada estudio actual con `nivelEducativoId` **o** `otroNivelTexto`, no ambos.

**2.4b — orden del flujo (`3bc764c`, `bdbbbef`):** antes de cualquier escritura se lanza `PrecondicionIncumplida` si:
- `RegistrarDocumento`: no hay responsable legal. Con eso se eliminó la excepción temporal de 2.4a que omitía la validación de clave sin responsable.
- `RegistrarDatosInmueble`, `RegistrarInfraestructuraNivel`, `RegistrarMobiliarioNivel`: Paso 2 incompleto (`EstadoPaso2::etapaFaltante`, que incluye vigencia) o sub-paso inalcanzable (`EstadoPaso3::puedeAcceder`).
- `MarcarPasoCompletado`: las mismas dos condiciones.
- Los componentes Livewire capturan la excepción al guardar y redirigen al paso correcto.

**Autocompletado en GET:** sigue permitido solo cuando la página ya es alcanzable. `DatosInmueble` y `MobiliarioNivel` pasan primero por la compuerta `CompuertaPaso3`, y ahora además `MarcarPasoCompletado` verifica por sí mismo `EstadoPaso2` y `EstadoPaso3`, así que ningún cliente puede marcar un paso fuera de orden.

### 2.5 — `CalculadoraRequerimiento` (`469cd2a`)
`personal_proporcional` lanza `InvalidArgumentException` si `valorNumerico <= 0` (antes: división entre cero con 0, resultado sin sentido con negativos). El dominio sigue sin depender de `Illuminate`.

### 2.6 — Domicilio en el sub-paso 1 (`afd1d09`)
`DatosInmueble` muestra el domicilio del plantel (calle, número, colonia, municipio, C.P.) como texto de solo lectura, sin ningún campo editable.

### 2.7 — Código muerto (`1386d25`)
Eliminados, tras verificar con grep que no tenían referencias: `app/Infrastructure/Pdf/FormatoSolicitudPdfService.php` y las vistas huérfanas `livewire/tramite/paso3/{inmueble,infraestructura,mobiliario}.blade.php`. Se conservan los stubs `PlanEstudios`, `PlantillaDocente` y `Matricula`.

### 2.8 — Versión de PHP (`450db8c`)
`composer.json` exige `"php": "^8.4"`. `composer update --lock`: en `composer.lock` solo cambiaron `content-hash` y `platform.php`; ninguna versión de paquete.

## Pruebas con HTTP real (`/livewire/update`, lección de ADR-003) — `2a24ed6`
- `Paso3InfraestructuraNivelHttpRoundTripTest`: un espacio con solo superficie se guarda; un espacio con solo `ventilacionNatural: false` no crea fila (con aserción adicional de que `guardar` sí se ejecutó). Pasaron de inmediato: son cobertura de regresión sobre comportamiento ya corregido en 2.1.
- `Paso2DocumentosGuardarHttpRoundTripTest`: llama `guardarDocumentoSimple('ine')` sin archivo por HTTP real y comprueba el error de validación `archivos.ine` en la respuesta, sin 500.
- **Excepción explícita a la regla del brief:** no hay prueba de ida y vuelta por HTTP de una **carga de archivo** en `Paso2Documentos`. Livewire sube los archivos por un endpoint firmado de carga temporal, distinto de `/livewire/update`, lo que hace impráctica la prueba con HTTP crudo. La ruta modificada (`intentarRegistrar()` y el manejo de `DatosInvalidos` / `PrecondicionIncumplida`) queda cubierta por pruebas `Livewire::test()` y por las pruebas del caso de uso. El riesgo que motiva la regla (resolución del componente por HTTP, el 419 de ADR-003) sí queda cubierto por la prueba anterior.

## Cambios de preparación en pruebas existentes (sin debilitar aserciones)
Las nuevas precondiciones obligaron a completar la preparación de pruebas que llamaban a los casos de uso fuera de orden. En todos los casos solo cambió el setup:
- `AuditoriaSeguridadTest` (WS-1): se registra un responsable antes de subir la escritura del plantel A; la intención (B no puede descargar documentos de A) no cambia.
- `DocumentoDownloadTest`: dos casos insertan la fila directamente ("dato heredado") porque el caso de uso ya rechaza esos datos; siguen probando el filtro de lectura de `ObtenerDocumentoCapturado`.
- `Paso2ResponsableTest`: helper `registrarDocumentoLegacy()` para tener documentos sin responsable. El implementador detectó que su primer intento hacía pasar dos pruebas sin ejercer la transición; se corrigió y el revisor confirmó que ahora la ejercen.
- `MarcarPasoCompletadoTest`, `EstadoPaso3Test`, `RegistrarDatosInmuebleTest`, `RegistrarInfraestructuraNivelTest`, `RegistrarMobiliarioNivelTest`, `DocumentosCompletosTest`, `ValidarVigenciaDocumentosTest`, `AlmacenDocumentosLocalTest`, `Paso2DocumentosTest`, `ProgresoTest`: Paso 2 completo, sub-pasos previos marcados, rutas únicas en lugar de la ruta fija, catálogo `tipos_documentos` sembrado. En `MarcarPasoCompletadoTest` los conteos subieron (1→2, 1→3) por las filas previas que ahora exige la precondición.

## Hallazgos menores estacionados (no corregidos)
- **Carrera teórica en Paso 3:** si `PrecondicionIncumplida` ocurre al guardar pero la compuerta vuelve a considerar la página alcanzable (solo posible con escrituras concurrentes), el formulario se vuelve a mostrar sin mensaje. `Paso2Documentos` redirige incondicionalmente y no tiene este problema.
- **TOCTOU en `RegistrarInfraestructuraNivel`:** `validar()` lee los tipos ya capturados antes de la transacción y la escritura vuelve a leerlos dentro. Solo importa con dos niveles del mismo plantel guardando a la vez.
- **Orden de validación inconsistente:** `RegistrarDatosInmueble` valida la entrada antes de la precondición de flujo; los otros casos de uso lo hacen al revés. Inofensivo.
- **Costo de `Progreso`:** unas 8–10 consultas pequeñas por carga completa de página (no en actualizaciones Livewire). Aceptable.

## Deliberadamente fuera de alcance
- Todo lo de WS-3 a WS-9 (catálogos, ADR-006, documentos faltantes y Paso 2.4, aulas por sala, corrección de datos del plantel, Paso 3.4).
- El plantel compartido entre dos solicitantes en la base de desarrollo (`PENDIENTE-plantel-solicitante-cardinalidad.md` sigue abierto; decisión del propietario).
- `FormatoSolicitudPdfController` sigue llamando a Infrastructure directamente (pendiente de aceptación del propietario desde WS-1; WS-5 reconstruye el Formato).

## Acciones del propietario
Ninguna nueva por esta rama: no hay migraciones ni seeders nuevos que correr contra desarrollo.
