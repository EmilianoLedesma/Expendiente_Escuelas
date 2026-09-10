# Sesión: corrección del scoping de deduplicación de Escuela

**Fecha:** 2026-09-10
**Rama/worktree:** `worktree-fix-escuela-dedup-scoping`, `.claude/worktrees/fix-escuela-dedup-scoping`

## Contexto

Corrección de `a7b35c8` (commit anterior, sesión del mismo día), confirmada con el dueño del dominio: el mismo solicitante (física o moral) **puede** legítimamente registrar múltiples Escuela distintas en el mismo Plantel — por ejemplo, dos escuelas con nombre distinto compartiendo un mismo campus/domicilio bajo un mismo dueño. `a7b35c8`'s `Escuela::firstOrCreate` (scoped solo por `plantel_id` + `solicitante_id`) hacía esto estructuralmente imposible: cualquier segunda visita a Paso 1 para el mismo plantel del mismo solicitante reutilizaba la primera Escuela para siempre, sin importar si esa primera Escuela ya era un registro completado e independiente.

## La distinción real que faltaba

"Retomar una visita de Paso 1 abandonada" y "empezar una segunda Escuela distinta" se ven idénticas al nivel `(plantel_id, solicitante_id)` — por eso la corrección anterior estaba mal. La señal real es si la Escuela existente ya avanzó más allá de preregistro: una Escuela sin fila `responsables_legales` todavía está "en progreso" y es segura de reutilizar; una Escuela que ya tiene una es un registro completado y no debe reutilizarse para una visita nueva y no relacionada.

## Fix

`IniciarTramiteNuevo::ejecutar()`: `Escuela::firstOrCreate(...)` reemplazado por `Escuela::where('plantel_id', ...)->where('solicitante_id', ...)->whereDoesntHave('responsableLegal')->first() ?? Escuela::create(...)`. Usa la relación `Escuela::responsableLegal()` (HasOne) ya existente — ninguna lógica de Eloquent nueva fuera de la capa de Aplicación (ADR-001).

## Tests

- `test_reutiliza_escuela_existente_del_mismo_solicitante_y_plantel` (de `a7b35c8`): sin cambios, sigue pasando — ninguna de las dos llamadas crea un `responsable_legal`, así que el escenario sigue siendo "en progreso ambas veces".
- `test_dos_solicitantes_distintos_obtienen_escuelas_distintas_en_el_mismo_plantel`: sin cambios, sigue pasando.
- **Nuevo**: `test_crea_una_segunda_escuela_distinta_si_la_primera_ya_tiene_responsable_legal` — completa Paso 2 (crea `responsables_legales`) para la primera escuela, luego reinvoca `ejecutar()` con el mismo plantel/solicitante; verifica que se crea una segunda escuela distinta, no se reutiliza la primera.

## Verificación

Suite completa: 188/188 (187 previas + 1 nueva). Pint limpio. PHPStan 0 errores (`--memory-limit=512M`).

## Qué no se hizo

- No se tocó el escenario de `bifurcacion=nuevo` — siempre crea un plantel nuevo, así que nunca hay coincidencia que reutilizar de todas formas; el fix no cambia su comportamiento observable.
- No se agregó ninguna UI para que el solicitante elija explícitamente entre "continuar trámite existente" vs. "registrar una escuela nueva en este plantel" — la lógica actual decide automáticamente según el estado real de la escuela existente, sin pedirle nada al usuario. Si SEDEQ quiere que el solicitante elija explícitamente en vez de que el sistema decida solo, eso es una decisión de UX separada, no cubierta aquí.

## Próximo paso

`superpowers:finishing-a-development-branch` — merge a `master`.
