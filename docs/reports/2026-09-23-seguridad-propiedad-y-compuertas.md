# 2026-09-23 — WS-1: seguridad, propiedad y compuertas de flujo

**Rama:** `fix/seguridad-propiedad-y-compuertas`. **Origen:** auditoría externa del 2026-09-23 (brief `docs/superpowers/auditoria/AGENT_BRIEF_remediacion-auditoria-2026-09-23.md`, WS-1).
**Método:** TDD con las 6 pruebas de regresión del Apéndice A (rojas en `776ffee`, primer commit de la rama), un implementador opus por tarea y un revisor opus por tarea; una ola de correcciones tras la revisión de 1.6/1.7.

## Qué se corrigió

| Tarea | Defecto | Corrección |
|---|---|---|
| 1.1 (crítico) | Un solicitante podía adjuntar una escuela al plantel de otro (y leer/sobrescribir sus documentos) | `IniciarTramiteNuevo` exige que el plantel `existente` ya tenga una escuela del mismo solicitante (`PlantelesDelSolicitante`, predicado único compartido con `ListarPlantelesDisponibles`); `Paso1Preregistro` lo muestra en `plantelId` con el mismo mensaje que un plantel inexistente (sin filtrar existencia) |
| 1.2 (crítico) | Bypass total de Paso 2: `guardarNiveles` sin precondición y `$fase` escribible desde el cliente | `EstadoPaso2` (Application) + `PrecondicionIncumplida` (`DomainException`) antes de escribir; `#[Locked]` en `$fase`; `mount()` reenvía escuelas incompletas; todas las páginas de Paso 3 redirigen a Paso 2 si está incompleto |
| 1.3 | Paso 3 alcanzable en cualquier orden; `mount()` marcaba pasos completados en un GET | `EstadoPaso3` (orden derivado de `pasos_captura`); compuerta única (`CompuertaPaso3`); el auto-completado por GET solo ocurre después de cumplir el orden |
| 1.4 | Páginas que escriben autorizadas con `can:view` | `update` en `EscuelaPolicy`/`EscuelaNivelPolicy` (mismas clases de decisión de propiedad); rutas de escritura con `can:update`; descarga, PDF y próximos-pasos conservan `can:view` |
| 1.5 | Cierres en `routes/web.php` con lógica de persistencia | `DescargarDocumentoController` y `FormatoSolicitudPdfController`; `ObtenerDocumentoCapturado` (Application) rechaza claves no aplicables al `tipo_persona` (404) |
| 1.6 (D6) | Verificación de correo desactivada | `User` implementa `MustVerifyEmail`; `verified` en todo `/tramite/*` **y** como middleware persistente de Livewire (la revisión de 1.6 encontró que `/livewire/update` no lo revalidaba: un usuario sin verificar reenviando un snapshot obtuvo 200) |
| 1.7 | `dashboard` de Breeze aún referenciado | Redirección por rol (sedeq → `/admin`, resto → preregistro) |

Seguimientos de revisión cerrados: 404 (no 500) si el archivo falta en disco; prueba de descarga de documento de plantel; prueba HTTP real de que `can:update` se reaplica en `/livewire/update`; la suite ya no reporta pruebas «riesgosas» (un proveedor de datos que arrancaba la aplicación durante la carga de PHPUnit instalaba manejadores de errores globales).

## Evidencia

- Suite: 318 → 381 pruebas, 0 riesgosas. Pint y PHPStan (nivel 5 + PHPat) limpios en la rama.
- Pruebas 1-6 del Apéndice A verdes sin modificar su comportamiento.
- Consultas **solo lectura** a la base de desarrollo (`sedeq_incorporacion`, autorizadas por el brief): 1 plantel con escuelas de más de un solicitante (dato heredado, sigue pasando la nueva compuerta); 0 de 9 `escuela_niveles` sin responsable legal. No se escribió nada.

## Decisiones y desviaciones

- `FormatoSolicitudPdfController` llama directamente a `Infrastructure\Pdf\FormatoSolicitudPdf` (no existe caso de uso; una clase que solo delegue no aporta). **Requiere aceptación explícita del dueño.**
- Una descarga sin responsable legal devuelve 404 (sin `tipo_persona` no hay contra qué validar la clave).
- Para usuarios sedeq, verify-email y confirm-password redirigen a preregistro (solo el login tenía decisión por rol); enrutarlos por `dashboard` los enviaría a `/admin`.
- Sin mensaje flash al fallar una precondición: ningún layout renderiza flash.
- `PENDIENTE-plantel-solicitante-cardinalidad.md` actualizado: la selección ahora exige propiedad; compartir plantel entre solicitantes exigirá un mecanismo explícito; la pregunta de cardinalidad sigue abierta. El plantel compartido existente en dev no se limpió (requiere decisión del dueño).

## Deliberadamente no hecho / trasladado

- Estado de «Documentos» en `Progreso` (se marca por existir `escuela_niveles`, no por completitud) → WS-2.2.
- Para WS-8: `Paso3ProximosPasos::PASOS_PASO3` duplica el orden de pasos (usar `Progreso::RUTAS_PASO3`/`EstadoPaso3`); una prueba de round-trip de próximos-pasos empezará a fallar al agregar la ruta de plan de estudios; `MobiliarioNivel` añade un salto extra a próximos-pasos.
- El listener de `Registered` que crea el `Solicitante` está registrado dos veces (descubrimiento + `Event::listen`); inofensivo (`firstOrCreate`), preexistente.
- Existencia de IDs de escuela distinguible (403 vs 404): aceptable para el MVP.

## Acciones para el dueño

- Ninguna contra la base de desarrollo en este WS. Las cuentas de desarrollo sin verificar deberán verificar su correo (driver `log`: el enlace queda en `storage/logs`).
