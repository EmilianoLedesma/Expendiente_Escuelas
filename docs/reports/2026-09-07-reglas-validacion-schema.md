# `reglas_validacion` schema redesign — 2026-09-07

Branch: `arch/reglas-validacion-schema` (not merged, left for review)

## 1. Row table (28 rows)

| clave | nivel | tipo_regla | tipo_calculo | ambito | condicion_min | condicion_max | valor_numerico | unidad |
|---|---|---|---|---|---|---|---|---|
| inicial.superficie.aula_lactantes | inicial | superficie | minimo_fijo | sala | — | 10 | 25.00 | m²/sala |
| inicial.superficie.aula_maternales | inicial | superficie | minimo_fijo | sala | — | 15 | 25.00 | m²/sala |
| inicial.superficie.area_recreativa | inicial | superficie | ratio_por_alumno | plantel | — | — | 1.00 | m²/alumno |
| inicial.superficie.sala_usos_multiples | inicial | superficie | ratio_por_alumno | plantel | — | — | 1.20 | m²/niño |
| inicial.superficie.sanitarios | inicial | superficie | ratio_por_alumno | plantel | — | — | 0.80 | m²/infante |
| inicial.personal.responsable_sala | inicial | personal | personal_por_espacio | sala | — | — | 1.00 | responsable/sala |
| inicial.personal.asistente_lactantes | inicial | personal | personal_proporcional | sala | — | — | 5.00 | alumnos/asistente |
| inicial.personal.asistente_maternales | inicial | personal | personal_proporcional | sala | — | — | 10.00 | alumnos/asistente |
| inicial.personal.director_tecnico | inicial | personal | personal_obligatorio | plantel | — | — | 1.00 | director/plantel |
| preescolar.superficie.construida_total | preescolar | superficie | ratio_por_alumno | plantel | — | — | 1.00 | m²/educando |
| preescolar.superficie.aula | preescolar | superficie | ratio_por_alumno | aula | — | — | 1.00 | m²/educando |
| preescolar.superficie.espacio_maestro | preescolar | superficie | adicional_fijo | aula | — | — | 2.00 | m² fijo |
| preescolar.superficie.area_recreacion | preescolar | superficie | ratio_por_alumno | plantel | — | — | 1.25 | m²/educando |
| preescolar.superficie.aula_usos_multiples | preescolar | superficie | factor | aula | — | — | 1.50 | factor |
| preescolar.personal.educacion_fisica | preescolar | personal | personal_umbral | escuela | 61 | — | 1.00 | docente |
| primaria.superficie.predio_total | primaria | superficie | ratio_por_alumno | predio | — | — | 2.50 | m²/alumno |
| primaria.superficie.aulas | primaria | superficie | ratio_por_alumno | aula | — | — | 0.90 | m²/alumno |
| primaria.superficie.altura_aulas | primaria | superficie | minimo_fijo | aula | — | — | 2.70 | m fijo |
| primaria.superficie.acervo_bibliografico | primaria | superficie | ratio_por_grado | escuela | — | — | 50.00 | títulos/grado |
| primaria.personal.educacion_fisica | primaria | personal | personal_proporcional | escuela | — | — | 60.00 | alumnos/docente |
| secundaria.superficie.predio_total | secundaria | superficie | ratio_por_alumno | predio | — | — | 2.50 | m²/alumno |
| secundaria.superficie.aulas | secundaria | superficie | ratio_por_alumno | aula | — | — | 0.90 | m²/alumno |
| secundaria.superficie.area_recreacion | secundaria | superficie | ratio_por_alumno | plantel | — | — | 1.25 | m²/alumno |
| secundaria.superficie.areas_recreativas_minimas | secundaria | superficie | minimo_fijo | plantel | — | — | 200.00 | m² fijo |
| secundaria.superficie.acervo_bibliografico | secundaria | superficie | minimo_fijo | escuela | — | — | 300.00 | títulos totales |
| secundaria.personal.educacion_fisica | secundaria | personal | personal_umbral | escuela | 61 | — | 1.00 | docente |
| secundaria.personal.trabajador_social | secundaria | personal | personal_umbral | escuela | 61 | — | 1.00 | trabajador social |
| secundaria.personal.prefecto | secundaria | personal | personal_umbral | escuela | 61 | — | 1.00 | prefecto |

All 28 rows carry `cargo_puesto_id = NULL` (see §6, Follow-ups) and `fuente` per nivel: Inicial → `REQUISITOS_DE_EDUCACIÓN_INICIAL.docx`; Preescolar → `Acuerdo Secretarial 357`; Primaria → `Acuerdo Secretarial 254`; Secundaria → `Acuerdo Secretarial 255`.

## 2. Normative discrepancy log

### 2.1 Educación Física threshold: 60 vs. 61 — REOPENED, not resolved here

**Correction to this report's original version**: this section previously concluded
"not a bug, 61 is correct." That conclusion was wrong to make — the task asked for
this discrepancy to be *reported* for the user to settle with SEDEQ, not adjudicated
by the agent. It also rested on a flawed premise: it treated the PRD and COMPENDIO
§5.2 as two independent sources that corroborate each other, when the PRD's own
preamble states COMPENDIO is authoritative for normative detail — the PRD is
downstream of COMPENDIO, not a second data point. It also mischaracterized §5.2's
">60" as appearing mainly in "a summary comparison table" — in fact ">60"/"mayor a
60" appears in the per-nivel normative prose of §5.2 itself (Preescolar line 540,
Primaria line 575, Secundaria line 626), not only in the comparison table (line 640).

The full quote-by-quote breakdown, source provenance (Profesiograma vs. Acuerdo
Secretarial text, per each section's own declared "Fuente:" line), the concrete
consequence for a 60-student school, and the exact question phrased for SEDEQ now
live in **`docs/decisions/PENDIENTE-umbral-educacion-fisica.md`** — that document is
the authority on this question going forward, not this report. Summary: `condicion_min
= 61` remains seeded, marked **provisional**, unchanged pending SEDEQ's answer.

### 2.2 Preescolar threshold vs. Primaria proportional (confirmed real defect, now fixed — see also §2.4 for a second defect found in the first fix)

- **COMPENDIO §5.1** (line 452): Preescolar — *"docente de Educación Física obligatorio **solo si** la instalación tiene capacidad de 60 alumnos o más"* — a single on/off condition, not scaled.
- **COMPENDIO §5.1** (line 453): Primaria — *"docente de Educación Física obligatorio **por cada 60 alumnos o más** en la escuela (**proporción, se puede requerir más de uno**)"* — explicitly proportional, re-confirmed at line 480 ("obligado por cada 60 alumnos o más en la escuela").
- **Old seeder**: both niveles seeded identically — `condicion_min => 61, valor_numerico => 1, unidad => 'docente'` — meaning a 300-student Primaria school would validate with a single PE teacher.
- **What I seeded (first pass)**: Preescolar → `tipo_calculo = personal_umbral` (fixed 1 once `condicion_min` 61 is met). Primaria → `tipo_calculo = personal_proporcional`, `valor_numerico = 60`, **plus** `condicion_min = 61` as a gate. This first pass itself introduced a new defect — see §2.4.
- **Conclusion**: the umbral-vs-proporcional split (Preescolar fixed / Primaria scaling) is confirmed correct and stands. The specific implementation of Primaria's gate did not; see §2.4 for the fix.

### 2.4 Primaria PE gate-plus-division defect (found during follow-up review, now fixed)

The first-pass fix in §2.2 combined `tipo_calculo = personal_proporcional` with a
`condicion_min = 61` gate on the Primaria row. That combination produces a
discontinuity: at 60 students the gate blocks entirely (0 teachers required); at 61
students the gate opens and the proportional formula immediately applies. Adding a
single student flips the requirement from 0 to a nonzero value, and depending on how
"gate then compute" is read, potentially straight to 2 (`ceil(61/60) = 2`). A school
should not lose or gain this much staffing obligation over one student.

**Fix**: removed `condicion_min` from the Primaria row entirely. `personal_proporcional`
now carries the threshold implicitly through integer (floor) division:
`required = floor(enrollment / valor_numerico)`. See §7 for the full before/after
boundary table and the floor-vs-ceil rationale.

**Checked for the same interaction elsewhere**: every other `personal_umbral` row
(`preescolar.personal.educacion_fisica`, `secundaria.personal.educacion_fisica`,
`secundaria.personal.trabajador_social`, `secundaria.personal.prefecto`) uses a flat
gate with **no division** — `personal_umbral` is defined as "required if enrollment ≥
condicion_min, else not required," a single binary flip with no multiplication/division
step, so it cannot exhibit this particular discontinuity by construction. The other two
`personal_proporcional` rows (`inicial.personal.asistente_lactantes`,
`inicial.personal.asistente_maternales`) carry no `condicion_min` at all — they were
never affected. **Primaria's PE rule was the only row with the gate-plus-division
combination; no other rows required changes.**

### 2.5 Additional discrepancy noticed (not previously flagged)

- COMPENDIO §5 (Inicial section, not quoted above) gives Inicial's rules no explicit alumno-capacity threshold for any of its `personal` rules other than the fixed ratios (director 1/plantel, responsable 1/sala, asistentes 1/5 or 1/10) — no §5.1/§5.2-style ">60" language applies to Inicial at all. No conflict found here; noted only because the task's framing might suggest checking for one. No seed change needed.

## 3. Taxonomy fit report

**All 28 rows mapped onto one of the nine `tipo_calculo` values without forcing.**

- **`primaria.personal.educacion_fisica`** originally used `tipo_calculo =
  personal_proporcional` **together with** a non-null `condicion_min` — flagged in
  this report's first version as a judgment call. Follow-up review correctly
  identified this as a defect, not a stylistic stretch: the gate-plus-division
  combination produces a discontinuity at the boundary (§2.4). Fixed by dropping
  `condicion_min` and relying on floor division to carry the threshold implicitly.
  `personal_proporcional` is now used cleanly, with no row combining it with a
  separate gate. See §7 for the corrected formula and boundary table.

**`ambito` convention used (interpretive, not literal COMPENDIO vocabulary in every case):**
- `sala` — Inicial's room-level rules only (Inicial's own DDL/COMPENDIO vocabulary distinguishes "sala" from "aula"; the domain has a dedicated `salas` table, confirmed in `database/migrations/2026_01_01_000004_create_salas_table.php`).
- `aula` — Preescolar/Primaria/Secundaria room-level rules (their own vocabulary).
- `predio` — used only where COMPENDIO's own text says "superficie total del **predio**" verbatim (Primaria/Secundaria) — the raw land parcel.
- `plantel` — site-wide developed/constructed-area rules not using the word "predio" (recreational areas, sala de usos múltiples, sanitarios, "superficie construida total"), and the two `personal` rules COMPENDIO itself scopes "por plantel" (Director Técnico, both niveles applicable).
- `escuela` — `personal` ratios COMPENDIO scopes to total student enrollment "en la escuela" (PE/trabajador social/prefecto thresholds, biblioteca ratios).

This split rests on the DDL's real `planteles` vs. `escuelas` tables (a plantel can host multiple escuelas/niveles in this domain's data model), so it isn't an arbitrary invention — but COMPENDIO's prose doesn't consistently use "plantel"/"escuela"/"predio" as rigorously as the DDL's schema does. The architect should confirm this mapping against whichever table(s) will actually supply each `ambito`'s enrollment/space counts when the Motor de Validación's input model is designed (`escuelas`, `planteles`, `matricula_grados`, `matricula_salas`, etc.).

## 4. Verification output

```
$ php artisan migrate:fresh --seed
... 44 domain migrations + Laravel/Breeze/Spatie base tables — all DONE
Database\Seeders\CatalogoMinimoSeeder .. DONE
Database\Seeders\AsignaturasSeeder .. DONE
Database\Seeders\CargosPuestosSeeder .. DONE
Database\Seeders\PerfilesProfesionalesSeeder .. DONE
Database\Seeders\ReglasValidacionSeeder .. DONE

$ php artisan test tests/Feature/Database/ReglasValidacionSeederTest.php
{"tool":"phpunit","result":"passed","tests":25,"passed":25,"assertions":51,"duration_ms":2859}

$ php artisan test
{"tool":"phpunit","result":"passed","tests":69,"passed":69,"assertions":164,"duration_ms":16905}
```

**RED check for the Primaria fix**: before restoring the fix, the 9 new boundary
tests (and the updated normative-fix assertion) were run against the reverted
(buggy, gate-plus-`personal_proporcional`) seeder and confirmed to fail for the
right reason — 7 failures, e.g. `test_primaria_educacion_fisica_boundary with data
set "59 alumnos -> 0 docentes"` failing with `Failed asserting that '61.00' is
null` (the stale gate) and the discontinuity itself surfacing once the gate was
passed. Restoring the fix turned all 25 tests in the file GREEN (`25 passed`
above).

Tinker dump (28 rows, matches §1's table exactly — confirmed by direct `json_encode` output of every row). Note: the task's provided tinker script had an ambiguous-column bug (`select('clave', ...)` where both `reglas_validacion` and `niveles_educativos` have a `clave` column) — fixed to `reglas_validacion.clave` to get a real result. Row count returned: `28`.

## 5. Files changed

- `app-laravel/database/migrations/2026_01_01_000010_create_reglas_validacion_table.php` — added `clave` (UNIQUE), `tipo_calculo` (9-value CHECK), `ambito` (5-value CHECK), `cargo_puesto_id` (nullable FK); `concepto`/`fuente` widened to `TEXT`; `condicion_min`/`condicion_max` changed `INTEGER` → `NUMERIC(10,2)`; `valor_numerico`/`unidad` made `NOT NULL`.
- `docs/ddl_sistema_incorporacion_v3.sql` — mirrored the same change (gitignored, local-only file).
- `app-laravel/database/seeders/ReglasValidacionSeeder.php` — rewritten: 28 rows with `clave`/`tipo_calculo`/`ambito`; switched from delete-then-insert-per-nivel to `insertOrIgnore` (safe now that `clave` is UNIQUE); umbral/proporcional split applied (§2.2); Primaria's gate-plus-division defect fixed by dropping `condicion_min` (§2.4/§7); `condicion_min = 61` on the four `*_umbral` rows marked provisional in the docblock, pending `docs/decisions/PENDIENTE-umbral-educacion-fisica.md`.
- `app-laravel/tests/Feature/Database/ReglasValidacionSeederTest.php` — rewritten: 25 tests total — the original 16 (row count, `clave` uniqueness/non-null, `tipo_calculo`/`ambito` non-null, all-9-values-allowed, idempotency, one assertion per `tipo_calculo`) plus 9 new boundary tests (`#[DataProvider]`, PHPUnit 12 attribute syntax) for Primaria (59/60/61/119/120/121) and Preescolar (59/60/61), watched RED against the pre-fix seeder before the fix made them GREEN.
- `docs/superpowers/plans/2026-09-04-catalogos-motor-validacion.md` — Task 4 marked superseded, pointing here.
- `docs/progress.md` — Decisions Log entry appended.
- `docs/reports/2026-09-07-reglas-validacion-schema.md` — this report (amended: §2.1 reopened, §2.4 and §7 added).
- `docs/decisions/PENDIENTE-umbral-educacion-fisica.md` — new decision memo, the 60-vs-61 threshold question, open pending SEDEQ.

## 6. Follow-ups created

- **Populate `cargo_puesto_id`**: all 28 rows currently NULL. Blocked on `CargosPuestosSeeder` (plan Task 2) existing with data queryable by `(nivel clave, nombre)` — the same lookup pattern `PerfilesProfesionalesSeeder` already uses. Only the `personal` rows (13 of 28) would ever get a non-null value; `superficie` rows have no associated cargo.
- **Confirm the `ambito` → source-table mapping** (§3) with whoever designs the Motor de Validación's input model, before engine code is written against these rows.
- **Resolve `docs/decisions/PENDIENTE-umbral-educacion-fisica.md` with SEDEQ**: the 60-vs-61 threshold question is open, not closed. The engine must not be built against `condicion_min = 61` as a settled fact — it's provisional.
- **Get SEDEQ's answer to also settle floor vs. ceil for Primaria's proportional rule** (§7) — currently implemented as floor, flagged as an assumption, not confirmed.

## 7. Primaria PE fix — boundary table (before / after)

**Formula, before (defect):** gate `condicion_min = 61` on top of
`personal_proporcional`. Required headcount:

| Enrollment | Gate (≥61?) | Old required headcount |
|---|---|---|
| 59 | No | 0 |
| 60 | No | 0 |
| 61 | Yes | 1 (naive `ceil(61/60)`) or 2 depending on how "gate then round up" is read — ambiguous and undocumented, which was itself part of the defect |
| 119 | Yes | 2 (`ceil(119/60)`) |
| 120 | Yes | 2 (`ceil(120/60)`) |
| 121 | Yes | 3 (`ceil(121/60)`) |

The discontinuity: **60 → 0 teachers, 61 → 1 or 2 teachers**, a jump triggered by a
single additional student. Confirmed defect.

**Formula, after (fixed):** no gate. `required = floor(enrollment / valor_numerico)`,
`valor_numerico = 60`.

| Enrollment | `floor(n / 60)` | Required headcount |
|---|---|---|
| 59 | 0 | 0 |
| 60 | 1 | 1 |
| 61 | 1 | 1 |
| 119 | 1 | 1 |
| 120 | 2 | 2 |
| 121 | 2 | 2 |

No discontinuity: each additional student past a multiple of 60 does not change the
requirement until the next full multiple of 60 is reached.

**Floor vs. ceil — decision and rationale:** implemented **floor**, not ceil. COMPENDIO's
wording is "**por cada** 60 alumnos o más ... se puede requerir más de uno" ("for
**each** 60 students or more"), which reads as one teacher per *complete* group of 60
— i.e. the requirement increases only once a full additional batch of 60 is reached.
Ceil would instead require a second teacher for a school with just 61 students (one
student past the first batch), which does not match "por cada 60" as naturally as
floor's "one per completed batch of 60" reading. This is an assumption, not a
confirmed reading — flagged for SEDEQ alongside the 60-vs-61 threshold question,
since floor also means the *effective* threshold for requiring even the first teacher
becomes exactly 60 (inclusive), not 61 — a third variant of the same underlying
ambiguity documented in `docs/decisions/PENDIENTE-umbral-educacion-fisica.md`.

Boundary tests for both the Primaria proportional rule (59/60/61/119/120/121) and the
Preescolar threshold rule (59/60/61) were added to
`ReglasValidacionSeederTest.php` and watched RED against the pre-fix seeder (7 failing
assertions, including the exact 60→0/61→discontinuity) before being made GREEN by the
fix — see §4 for the run output.
