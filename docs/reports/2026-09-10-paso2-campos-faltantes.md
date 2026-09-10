# Sesión: campos faltantes de Paso 2 (domicilio, persona autorizada, notariales)

**Fecha:** 2026-09-10
**Rama/worktree:** `worktree-paso2-campos-faltantes`, `.claude/worktrees/paso2-campos-faltantes`

## Contexto

La revisión final de rama del plan Paso 2a (`docs/reports/2026-09-10-paso2-responsable-niveles-final.md`) dejó dos hallazgos Minor aparcados por decisión humana pendiente: `domicilio_notificaciones`/`persona_autorizada_recoger` (sin campo de Form/Blade) y 11 campos de `PersonaMoralForm`/`PersonaFisicaForm`/`GestorForm` validados pero sin input. El usuario confirmó que **todos son alcance requerido**, y pidió verificar contra COMPENDIO antes de implementar.

## Verificación contra COMPENDIO

`docs/COMPENDIO_MAESTRO_Sistema_Incorporacion.md:92`: "El **Formato de Solicitud** se genera como PDF prellenado a partir de los datos capturados (nombre, **domicilio**, tipo de persona, **datos notariales si aplica**)". Confirma que domicilio y los datos notariales (constitutivos/poder) son datos capturados reales, no un dato inventado — consistente con la respuesta del usuario. No se encontró mención explícita de "persona autorizada para recoger" en COMPENDIO/PRD; se incluyó de todas formas por instrucción directa del usuario ("todos los campos validados son requeridos").

## Qué se hizo

Backend (DTO `DatosResponsableLegal`, modelo `ResponsableLegal`, caso de uso `RegistrarResponsableLegal`) ya cargaba `domicilio_notificaciones`/`persona_autorizada_recoger` de extremo a extremo desde el plan original — nunca se llamaban con datos reales porque no existía Form ni input de Blade. Cambio puramente de presentación (TDD, rojo→verde):

- `Paso2Responsable.php`: dos propiedades públicas nuevas (`domicilioNotificaciones`, `personaAutorizadaRecoger`) — compartidas entre las 3 variantes de `tipoPersona`, no atadas a ningún Form específico. Validación: `domicilioNotificaciones` `required|string|max:250` (confirmado por COMPENDIO L92 y por instrucción del usuario), `personaAutorizadaRecoger` `nullable|string|max:200` (sin confirmación normativa explícita, tratado igual que el resto de campos opcionales de la tabla).
- Blade view: agregados los dos campos compartidos, más `personaFisicaForm.fechaNacimiento`; `personaMoralForm.numeroEscrituraConstitutiva`/`fechaEscrituraConstitutiva`/`notarioNombre`/`notarioNumero`/`notarioCiudad`/`folioRegistroPublico`/`fechaInscripcionRpp`; `gestorForm.notarioNombre`/`notarioNumero`/`fechaPoder`. Ningún campo de validación se modificó salvo los dos nuevos — el resto conserva sus reglas `nullable` existentes.
- 5 tests nuevos en `Paso2ResponsableTest`: captura de domicilio/persona autorizada, domicilio requerido (rechaza envío vacío), round-trip completo de `personas_morales` (7 campos notariales), round-trip de `fecha_nacimiento` + `gestores` (3 campos notariales del gestor).

## Verificación

Suite completa: 176/176 (172 previas + 4 nuevas — 1 test extra vino del ajuste a `test_tipo_fisica_guarda_y_avanza_a_fase_niveles` para incluir `domicilioNotificaciones`, ya que ahora es requerido). Pint limpio, PHPStan 0 errores.

## Qué no se hizo / dejado abierto

- `persona_autorizada_recoger` se dejó `nullable` — no hay fuente normativa que confirme si debe ser obligatorio; si SEDEQ confirma que sí, es un cambio de una línea en `Paso2Responsable::guardarResponsable()`'s regla de validación.
- No se tocó ningún archivo de migración/DDL — ambas columnas ya existían como `VARCHAR` nullable desde el diseño original.

## Próximo paso

`superpowers:finishing-a-development-branch` — merge a `master`.
