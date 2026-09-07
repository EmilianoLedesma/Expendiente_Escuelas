# Wizard progress tracking + personal draft rows — 2026-09-07

Branch: `feat/wizard-progreso` (off `master`, not merged). Authorization: two
new tables + one column-nullability change, approved 2026-09-07 after
architecture review (see task notice), scoped exactly as below.

## Part 1 — `pasos_captura` + `escuela_nivel_pasos`

Migrations: `2026_09_07_000000_create_pasos_captura_table.php`,
`2026_09_07_000001_create_escuela_nivel_pasos_table.php` (numbered after the
actual current highest migration, `2026_01_01_000043`+index/permission
migrations — the task's "after 000034" was based on a stale migration count).

```sql
CREATE TABLE pasos_captura (
    id      SMALLSERIAL PRIMARY KEY,
    clave   VARCHAR(40) NOT NULL UNIQUE,
    nombre  VARCHAR(100) NOT NULL,
    orden   SMALLINT NOT NULL
);

CREATE TABLE escuela_nivel_pasos (
    id                BIGSERIAL PRIMARY KEY,
    escuela_nivel_id  BIGINT NOT NULL REFERENCES escuela_niveles(id) ON DELETE CASCADE,
    paso_captura_id   SMALLINT NOT NULL REFERENCES pasos_captura(id),
    estado            VARCHAR(20) NOT NULL DEFAULT 'pendiente'
                          CHECK (estado IN ('pendiente','en_progreso','completado')),
    completado_at     TIMESTAMP,
    created_at        TIMESTAMP NOT NULL DEFAULT now(),
    updated_at        TIMESTAMP NOT NULL DEFAULT now(),
    UNIQUE (escuela_nivel_id, paso_captura_id)
);
```

`PasosCapturaSeeder` (idempotent, `insertOrIgnore`, pattern matches
`CatalogoMinimoSeeder`), wired into `DatabaseSeeder` right after
`CatalogoMinimoSeeder`. Seeded rows, PRD §5 Paso 3 order:

| orden | clave | nombre |
|---|---|---|
| 1 | inmueble | Datos del inmueble |
| 2 | infraestructura | Infraestructura del nivel |
| 3 | mobiliario | Mobiliario |
| 4 | plan_estudios | Plan de estudios y modalidad |
| 5 | plantilla_docente | Plantilla docente |
| 6 | matricula | Matrícula |

No logic creates or advances `escuela_nivel_pasos` rows — schema and catalog
seed only, as scoped. That belongs with the (not-yet-built) wizard.

**Follow-up, not solved here:** `mobiliario` only applies to Educación
Inicial in the MVP (the only nivel with a confirmed `mobiliario_conceptos`
catalog, PRD §8). `escuela_nivel_pasos` has no per-nivel step-applicability
model — a Preescolar/Primaria/Secundaria `escuela_nivel` will still get a
`mobiliario` row from whatever future code populates this table, unless that
code filters by nivel itself. Needs a decision before the wizard consumes
this table: either don't create the row for niveles where it doesn't apply,
or create it and let the wizard mark it `completado` trivially/skip it in
the UI.

## Part 2 — `personal` draft rows + completeness rule

`2026_01_01_000030_create_personal_table.php` edited in place — `NOT NULL`
dropped from six columns, `escuela_nivel_id` kept `NOT NULL`, `sexo` CHECK
unchanged (Postgres CHECK passes on NULL):

| column | before | after |
|---|---|---|
| id | NOT NULL | NOT NULL |
| escuela_nivel_id | NOT NULL | NOT NULL |
| cargo_puesto_id | NOT NULL | **nullable** |
| nombre | NOT NULL | **nullable** |
| nacionalidad | NOT NULL | **nullable** |
| sexo | NOT NULL | **nullable** |
| estudios | NOT NULL | **nullable** |
| cedula_o_documento | NOT NULL | **nullable** |
| perfil_validado | nullable | nullable (unchanged) |
| created_at | NOT NULL | NOT NULL |
| updated_at | NOT NULL | NOT NULL |

Verified against `information_schema.columns` on the dev DB after
`migrate:fresh --seed` (see Verification below) — matches this table exactly.

### Completeness rule

`app/Domain/Personal/RegistroPersonalCompleto.php` — pure class, no
Eloquent, no DB, same style as `CalculadoraRequerimiento`. One public method:

```php
public function esCompleto(
    ?int $cargoPuestoId,
    ?string $nombre,
    ?string $nacionalidad,
    ?string $sexo,
    ?string $estudios,
    ?string $cedulaODocumento,
): bool
```

COMPLETE iff all six are non-null **and**, for the five string fields, not
an empty string. **`''` does not count as present** (decision, as expected):
a text field that was touched and then cleared in the wizard should still
read as an incomplete draft, not a validly-empty value — nothing in the
domain (nombre, nacionalidad, estudios, cédula) has a meaningful "empty"
state. `cargo_puesto_id` is an int, so only the null check applies to it.

Unit-tested (`tests/Unit/Domain/Personal/RegistroPersonalCompletoTest.php`,
8 tests): all six present → true; each of the six missing individually
(data provider) → false; empty-string `nombre` → false.

**Not done, out of scope per the task:** no counting/query logic anywhere
calls this class yet. The Motor de Validación's personal-counting code must
call `RegistroPersonalCompleto::esCompleto()` (or query pre-filtered by the
same six-column non-null/non-empty condition) before counting a `personal`
row toward any staffing ratio — otherwise a draft row silently inflates
compliance. This is a landmine for whoever writes that counting logic next;
flagging it here and in `docs/progress.md`.

## Part 3 — ADR-001

`docs/decisions/ADR-001-frontera-de-capas.md` created, recording: D2's
HTTP-round-trip design rejected (cookie-jar/deadlock/transaction-boundary
problems), replaced by an in-process `app/Application/` layer; the HTTP API
still gets built as a thin adapter for Etapa 3, off the wizard's path;
Deptrac enforcement deferred past MVP; system is server-rendered, not a SPA.
`docs/superpowers/specs/2026-09-04-api-layer-design.md` D2/D3 annotated
as superseded in place (original reasoning kept, not deleted).

## Verification

```
php artisan migrate:fresh --seed --force   # against dev DB, user confirmed first (CLAUDE.md data-safety rule)
```
All 41 migrations + 5 seeders ran clean, no errors.

```
php artisan test
{"tool":"phpunit","result":"passed","tests":116,"passed":116,"assertions":209,"duration_ms":17761}
```
116 = 106 pre-existing + 2 (`PasosCapturaSeederTest`) + 8
(`RegistroPersonalCompletoTest`).

Tinker dump, `pasos_captura` ordered by `orden`:
```
1  inmueble           Datos del inmueble
2  infraestructura    Infraestructura del nivel
3  mobiliario         Mobiliario
4  plan_estudios      Plan de estudios y modalidad
5  plantilla_docente  Plantilla docente
6  matricula          Matrícula
```

Tinker dump, `personal` nullability (`information_schema.columns`):
```
id                  NO
escuela_nivel_id    NO
cargo_puesto_id     YES
nombre              YES
nacionalidad        YES
sexo                YES
estudios            YES
cedula_o_documento  YES
perfil_validado     YES
created_at          NO
updated_at          NO
```

## Environment note (not part of the schema work)

This worktree started with no `vendor/`, `node_modules/`, `.env`, or built
frontend assets (none of those are shared between git worktrees — they're
all gitignored/untracked). `.env`, `docs/ddl_sistema_incorporacion_v3.sql`,
and `docs/superpowers/specs/2026-09-04-api-layer-design.md` were copied in
from the main checkout (all three are local-only/gitignored); `composer
install`, `npm install`, and `npm run build` were run fresh. Baseline
(106/106) confirmed green before any change in this task.

## Out of scope, confirmed not touched

No Livewire component, no Filament resource, no `app/Application/` code, no
Deptrac config, no Motor de Validación counting logic, no work on
`docs/decisions/PENDIENTE-origen-de-magnitud.md`.
