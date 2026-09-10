# Sesión: reutilizar escuela en Paso 1 + auditoría de validación de todos los formularios

**Fecha:** 2026-09-10
**Rama/worktree:** `worktree-paso1-reusar-escuela`, `.claude/worktrees/paso1-reusar-escuela`

## Parte 1 — Paso 1 crea una escuela nueva en cada visita

**Diagnóstico previo** (sesión anterior): `IniciarTramiteNuevo::ejecutar()` siempre hacía `Escuela::create(...)`, tanto para `bifurcacion=nuevo` como `bifurcacion=existente`. Elegir el mismo plantel dos veces creaba dos filas `escuelas` distintas para el mismo solicitante — por eso Paso 2 aparecía vacío al "volver a seleccionar plantel": era un `escuela_id` genuinamente distinto, no una segunda captura sobre el mismo registro.

**Fix**: `Escuela::create(...)` → `Escuela::firstOrCreate(['plantel_id' => ..., 'solicitante_id' => ...])`. Mismo patrón de idempotencia ya usado en `RegistrarResponsableLegal`/`RegistrarNivelesSeleccionados`. Un solicitante que vuelve a elegir el mismo plantel continúa el mismo trámite; dos solicitantes distintos en el mismo plantel siguen obteniendo escuelas independientes (verificado con test dedicado). `bifurcacion=nuevo` no se ve afectado en la práctica — siempre crea un plantel nuevo, así que `firstOrCreate` nunca encuentra coincidencia ahí.

2 tests nuevos en `IniciarTramiteNuevoTest`: reutilización para el mismo par (solicitante, plantel); aislamiento entre dos solicitantes distintos en el mismo plantel.

## Parte 2 — Auditoría de validación de todos los formularios

Revisados contra las columnas/CHECK reales de `docs/ddl_sistema_incorporacion_v3.sql`:

- `Paso1Preregistro::rules()` (planteles): todas las longitudes (`calle` 150, `colonia` 150, `municipio` 150, `codigoPostal` 10, `telefono` 20, `correoElectronico` 150) coinciden exactamente con el DDL. Sin cambios.
- `PersonaFisicaForm`, `PersonaMoralForm`, `GestorForm`: las 18 reglas de longitud coinciden exactamente con `personas_fisicas`/`personas_morales`/`gestores`. Sin cambios.
- `Paso2Responsable`'s `domicilioNotificaciones`/`personaAutorizadaRecoger`: coinciden con `responsables_legales` (250/200). Sin cambios.

**2 gaps reales encontrados y corregidos** (ambos en `Paso2Responsable`, ninguno en los Form objects):

1. `tipoPersona` no tenía regla `in:` — un valor manipulado (`wire:model` client-side) pasaba la validación de Livewire y llegaba sin capturar hasta `RegistrarResponsableLegal::ejecutar()`, cuyo guard `in_array` lanza `InvalidArgumentException` **sin capturar en `guardarResponsable()`** → error 500 en vez de un mensaje de validación. Fix: `'tipoPersona' => ['required', 'in:fisica,fisica_con_gestor,moral']`.
2. `nivelesSeleccionados.*` no tenía regla por elemento — un valor no numérico solo se atrapaba más abajo en el `array_diff` del whitelist de `RegistrarNivelesSeleccionados` (funcionalmente seguro, pero con un mensaje de error confuso: "Nivel educativo fuera de Educación Básica" para lo que en realidad es basura de tipo, no un nivel fuera de alcance). Fix: `'nivelesSeleccionados.*' => ['integer', 'exists:niveles_educativos,id']`.

2 tests nuevos en `Paso2ResponsableTest` cubriendo ambos casos.

## Verificación

Suite completa: 181/181 (177 previas + 4 nuevas). Pint limpio. PHPStan 0 errores (memoria por defecto de 128M insuficiente en este entorno para el proyecto completo — usar `--memory-limit=512M`, no es un hallazgo de código).

## Qué no se hizo

- No se tocó ningún archivo de migración — ambos fixes son de código de aplicación/validación, cero cambios de esquema.
- No se revisaron los formularios de autenticación de Breeze (login/register/forgot-password) — fuera del alcance pedido ("todos los formularios" se interpretó como los del wizard de trámite, que es donde vive la lógica de negocio propia del proyecto).

## Próximo paso

`superpowers:finishing-a-development-branch` — merge a `master`.
