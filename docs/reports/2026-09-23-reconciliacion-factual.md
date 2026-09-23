# 2026-09-23 — WS-0: reconciliación documental factual

**Rama:** `docs/reconciliacion-factual` (solo documentación, sin código).
**Origen:** auditoría externa del 2026-09-23, brief `docs/superpowers/auditoria/AGENT_BRIEF_remediacion-auditoria-2026-09-23.md`.

## Decisiones del dueño registradas al inicio (D1–D9)

| ID | Decisión |
|---|---|
| D1 | Formato de Solicitud después de 2.3, uno por `escuela_nivel` |
| D2 | Alinear documentos de persona moral/gestor con los Requisitos |
| D3 | Agregar todos los documentos faltantes; nuevo Paso 2.4 «Documentos por nivel» |
| D4 | Tabla nueva de detalle de aulas por sala (Inicial) |
| D5 | ADR-006: lecturas de flujo/permiso/completitud a Application |
| D6 | Habilitar verificación de correo |
| D7 | Des-versionar el DDL (`git rm --cached`, sin reescribir historial) |
| D8 | Datos de plantel editables mientras sea seguro |
| D9 | Construir Paso 3.4 Plan de estudios |

## Qué se corrigió

- `CLAUDE.md`: pruebas corren en Postgres `sedeq_incorporacion_testing` (no sqlite); los seeders de catálogo sí existen (10); `app/Domain` e `app/Infrastructure` no son stubs vacíos (solo `ValidacionCapacidadService` lo es); PHP 8.4; se agregó `app/Application/` al árbol y se reconcilió la regla «lógica nueva en Domain» con ADR-001; lista real de componentes del wizard.
- `README.md`: PHP 8.4; base de datos de pruebas; el DDL es referencia local no versionada.
- `docs/ddl_sistema_incorporacion_v3.sql`: encabezado que declara el prerrequisito (`users` de Laravel).
- ADR-001: «Nota de estado» fechada (Application ya existe, ruta `app/Livewire` por ADR-003, PHPat cubre solo Domain, remisión a ADR-006).
- ADR-002: «Nota de estado» — la consecuencia sobre Paso 2 sin dueño quedó obsoleta desde ADR-004. Hallazgo de una auditoría de solo lectura contra ADR-002…005; ADR-003, 004 y 005 no tenían afirmaciones falsas.

## Deliberadamente no hecho

- `docs/progress.md`: lo escribe el controlador al mergear.
- Des-versionar el DDL (D7): se ejecuta en `master` después del merge, porque un `git rm --cached` dentro de la rama borraría el archivo local del checkout principal al mergear.
- Redacción dependiente de decisiones (PRD/COMPENDIO): WS-9.
