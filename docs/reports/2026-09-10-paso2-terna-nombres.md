# Sesión: terna de nombres propuestos en Paso 2a

**Fecha:** 2026-09-10
**Rama/worktree:** `worktree-paso2-terna-nombres`, `.claude/worktrees/paso2-terna-nombres`

## Contexto

Tarea dirigida al agente: agregar la terna de nombres (Propuesta 1, 2, 3) al Formato de Solicitud de Paso 2, confirmada por COMPENDIO_MAESTRO como "el mismo dato" referenciado luego en Paso 3/Anexo 2 (`COMPENDIO_MAESTRO_Sistema_Incorporacion.md:121`). La tarea llegó con instrucciones de esquema (agregar una migración de 3 columnas o JSON a `escuelas`) que resultaron estar **equivocadas**.

## Hallazgo de plan-defecto — regla aplicada

Al leer `docs/ddl_sistema_incorporacion_v3.sql` antes de tocar migraciones (per CLAUDE.md), se encontró que la tabla `ternas_nombres` **ya existe** en el DDL fuente (líneas 316-325) y **ya está migrada** (`2026_01_01_000022_create_ternas_nombres_table.php`, parte del port original de las 44 tablas) — normalizada correctamente (una fila por propuesta 1-3, `UNIQUE(escuela_id, numero_propuesta)`), y con dos columnas booleanas (`valido_marca_comercial`, `valido_registro_sedeq`) que ya modelan exactamente las dos validaciones verificables por el propio sistema que describe COMPENDIO líneas 289-292 (marca comercial IMPI, registro interno SEDEQ).

**Ruling**: el DDL es la autoridad vinculante, la instrucción de la tarea (agregar columnas nuevas a `escuelas` o JSON) contradice el esquema ya existente y normalizado — se descartó por completo. No se creó ninguna migración nueva, no se tocó `docs/ddl_sistema_incorporacion_v3.sql` (nada que reflejar, la tabla ya estaba completa). Solo faltaban el modelo Eloquent y el código de aplicación.

## Qué se hizo

- `app/Models/TernaNombre.php` (nuevo): `$table = 'ternas_nombres'`, `const UPDATED_AT = null` (la tabla solo tiene `created_at`, mismo patrón que `Gestor`), PK estándar auto-incremental (no requiere configuración especial).
- `Escuela::ternasNombres(): HasMany` — mismo patrón ya usado para `escuelaNiveles()`/`responsableLegal()`.
- `DatosResponsableLegal` (DTO): 3 campos nullable nuevos (`nombrePropuesto1/2/3`).
- `RegistrarResponsableLegal::ejecutar()`: crea las filas `ternas_nombres` dentro de la misma transacción que `responsables_legales` — **salta cualquier propuesta que llegue null** en vez de fallar contra el `NOT NULL` de `nombre_propuesto`, mismo comportamiento defensivo que ya tienen todos los demás campos opcionales del DTO. Esto también mantuvo verdes los tests preexistentes del use case que no pasaban datos de terna (no es su alcance).
- `Paso2Responsable`: 3 propiedades públicas compartidas (no atadas a ningún `tipoPersona`, aplica a los 3), validadas `required|string|max:200` — todas las tres, no solo la primera. Requeridas en las tres por la propia palabra "terna" del documento fuente (conjunto fijo de tres) y por no encontrarse ningún texto que distinga requerido-parcial vs. total en COMPENDIO ni PRD.
- Blade view: 3 inputs nuevos, en la sección compartida (antes de las ramas por `tipoPersona`).
- 5 tests nuevos/editados en `Paso2ResponsableTest` (captura, requeridas las tres); 2 tests nuevos en `RegistrarResponsableLegalTest` (persistencia, no-duplicación en reenvío).

## Verificación

Suite completa: 187/187 (183 previas + 4 nuevas netas). Pint limpio. PHPStan 0 errores (`--memory-limit=512M`).

## Qué no se hizo (fuera de alcance, per la tarea)

- No se construyó ninguna validación real contra IMPI ni el registro interno de SEDEQ — `valido_marca_comercial`/`valido_registro_sedeq` quedan `NULL`, a llenarse en una fase de revisión de SEDEQ (Etapa 2), no en la captura del solicitante.
- No se tocó ningún consumo de este dato en Paso 3 — Paso 3 sigue siendo solo el placeholder.
- No se tocó `escuelas.nombre_aprobado` — concepto distinto (nombre final aprobado por SEDEQ, singular), confirmado no confundir.

## Próximo paso

`superpowers:finishing-a-development-branch` — merge a `master`.
