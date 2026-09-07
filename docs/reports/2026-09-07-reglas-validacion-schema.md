# `reglas_validacion` schema redesign — 2026-09-07

Branch: `arch/reglas-validacion-schema` (not merged, left for review)

## 1. Row table (28 rows)

**Note (this amendment):** `clave` for the three rows reclassified to `tipo_regla =
infraestructura` (§8) changed to match the `{nivel}.{tipo_regla}.{slug}` convention:
`primaria.superficie.altura_aulas` → `primaria.infraestructura.altura_aulas`,
`primaria.superficie.acervo_bibliografico` → `primaria.infraestructura.acervo_bibliografico`,
`secundaria.superficie.acervo_bibliografico` → `secundaria.infraestructura.acervo_bibliografico`.
A `redondeo` column was also added (§9) — populated below.

| clave | nivel | tipo_regla | tipo_calculo | ambito | redondeo | condicion_min | condicion_max | valor_numerico | unidad |
|---|---|---|---|---|---|---|---|---|---|
| inicial.superficie.aula_lactantes | inicial | superficie | minimo_fijo | sala | na | — | 10 | 25.00 | m²/sala |
| inicial.superficie.aula_maternales | inicial | superficie | minimo_fijo | sala | na | — | 15 | 25.00 | m²/sala |
| inicial.superficie.area_recreativa | inicial | superficie | ratio_por_alumno | plantel | na | — | — | 1.00 | m²/alumno |
| inicial.superficie.sala_usos_multiples | inicial | superficie | ratio_por_alumno | plantel | na | — | — | 1.20 | m²/niño |
| inicial.superficie.sanitarios | inicial | superficie | ratio_por_alumno | plantel | na | — | — | 0.80 | m²/infante |
| inicial.personal.responsable_sala | inicial | personal | personal_por_espacio | sala | na | — | — | 1.00 | responsable/sala |
| inicial.personal.asistente_lactantes | inicial | personal | personal_proporcional | sala | **arriba** | — | — | 5.00 | alumnos/asistente |
| inicial.personal.asistente_maternales | inicial | personal | personal_proporcional | sala | **arriba** | — | — | 10.00 | alumnos/asistente |
| inicial.personal.director_tecnico | inicial | personal | personal_obligatorio | plantel | na | — | — | 1.00 | director/plantel |
| preescolar.superficie.construida_total | preescolar | superficie | ratio_por_alumno | plantel | na | — | — | 1.00 | m²/educando |
| preescolar.superficie.aula | preescolar | superficie | ratio_por_alumno | aula | na | — | — | 1.00 | m²/educando |
| preescolar.superficie.espacio_maestro | preescolar | superficie | adicional_fijo | aula | na | — | — | 2.00 | m² fijo |
| preescolar.superficie.area_recreacion | preescolar | superficie | ratio_por_alumno | plantel | na | — | — | 1.25 | m²/educando |
| preescolar.superficie.aula_usos_multiples | preescolar | superficie | factor | aula | na | — | — | 1.50 | factor |
| preescolar.personal.educacion_fisica | preescolar | personal | personal_umbral | escuela | na | 61 | — | 1.00 | docente |
| primaria.superficie.predio_total | primaria | superficie | ratio_por_alumno | predio | na | — | — | 2.50 | m²/alumno |
| primaria.superficie.aulas | primaria | superficie | ratio_por_alumno | aula | na | — | — | 0.90 | m²/alumno |
| **primaria.infraestructura.altura_aulas** | primaria | **infraestructura** | minimo_fijo | aula | na | — | — | 2.70 | m fijo |
| **primaria.infraestructura.acervo_bibliografico** | primaria | **infraestructura** | ratio_por_grado | escuela | na | — | — | 50.00 | títulos/grado |
| primaria.personal.educacion_fisica | primaria | personal | personal_proporcional | escuela | **abajo** | — | — | 60.00 | alumnos/docente |
| secundaria.superficie.predio_total | secundaria | superficie | ratio_por_alumno | predio | na | — | — | 2.50 | m²/alumno |
| secundaria.superficie.aulas | secundaria | superficie | ratio_por_alumno | aula | na | — | — | 0.90 | m²/alumno |
| secundaria.superficie.area_recreacion | secundaria | superficie | ratio_por_alumno | plantel | na | — | — | 1.25 | m²/alumno |
| secundaria.superficie.areas_recreativas_minimas | secundaria | superficie | minimo_fijo | plantel | na | — | — | 200.00 | m² fijo |
| **secundaria.infraestructura.acervo_bibliografico** | secundaria | **infraestructura** | minimo_fijo | escuela | na | — | — | 300.00 | títulos totales |
| secundaria.personal.educacion_fisica | secundaria | personal | personal_umbral | escuela | na | 61 | — | 1.00 | docente |
| secundaria.personal.trabajador_social | secundaria | personal | personal_umbral | escuela | na | 61 | — | 1.00 | trabajador social |
| secundaria.personal.prefecto | secundaria | personal | personal_umbral | escuela | na | 61 | — | 1.00 | prefecto |

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

**Scope correction (this amendment):** that "no other rows affected" conclusion was
scoped narrowly to the gate-plus-division defect specifically — it did not check for
rounding-direction correctness, and it should have. A second, independent defect
existed in the same three `personal_proporcional` rows this whole time: two of them
(`inicial.personal.asistente_lactantes`, `inicial.personal.asistente_maternales`)
require rounding **up**, while Primaria's rule requires rounding **down**, and prior
to this amendment nothing in the schema distinguished them — see §9 for the full
finding and fix. Future readers: a check phrased as "re-audited all rows for X" does
not mean "verified correct in every dimension" — always check what X actually was.

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
{"tool":"phpunit","result":"passed","tests":39,"passed":39,"assertions":57,"duration_ms":5024}

$ php artisan test tests/Unit/Domain/Validaciones/Regla/CalculadoraRequerimientoTest.php
{"tool":"phpunit","result":"passed","tests":21,"passed":21,"assertions":21,"duration_ms":24}

$ php artisan test
{"tool":"phpunit","result":"passed","tests":104,"passed":104,"assertions":191,"duration_ms":16785}
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

- `app-laravel/database/migrations/2026_01_01_000010_create_reglas_validacion_table.php` — added `clave` (UNIQUE), `tipo_calculo` (9-value CHECK), `ambito` (5-value CHECK), `redondeo` (3-value CHECK — this amendment), `cargo_puesto_id` (nullable FK); `tipo_regla` CHECK extended with `'infraestructura'` (this amendment); `concepto`/`fuente` widened to `TEXT`; `condicion_min`/`condicion_max` changed `INTEGER` → `NUMERIC(10,2)`; `valor_numerico`/`unidad` made `NOT NULL`.
- `docs/ddl_sistema_incorporacion_v3.sql` — mirrored the same changes (gitignored, local-only file).
- `app-laravel/database/seeders/ReglasValidacionSeeder.php` — rewritten across three passes: `clave`/`tipo_calculo`/`ambito` (pass 1), Primaria gate-plus-division fix (pass 2), `redondeo` populated + 3 rows reclassified to `infraestructura` + docblock's contradictory "settled" note deleted, leaving only the provisional note (this amendment, pass 3).
- `app-laravel/tests/Feature/Database/ReglasValidacionSeederTest.php` — rewritten: 39 tests. Boundary tests now call `CalculadoraRequerimiento` instead of inline `intdiv`/manual arithmetic (§10); added redondeo/tipo_regla/infraestructura-classification assertions; added Inicial lactantes/maternales boundary tests (4/5/6/10/11 and 9/10/11).
- `app-laravel/app/Domain/Validaciones/Regla/CalculadoraRequerimiento.php` — **new**, pure calculation class (§10).
- `app-laravel/tests/Unit/Domain/Validaciones/Regla/CalculadoraRequerimientoTest.php` — **new**, pure unit test, 21 tests, no DB.
- `docs/superpowers/plans/2026-09-04-catalogos-motor-validacion.md` — Task 4 marked superseded, pointing here.
- `docs/progress.md` — Decisions Log entry appended (pass 1); not further amended in this pass — see follow-up note below.
- `docs/reports/2026-09-07-reglas-validacion-schema.md` — this report (amended across three passes: §2.1 reopened, §2.4/§7 added in pass 2; §8, §9, §10 added in pass 3).
- `docs/decisions/PENDIENTE-umbral-educacion-fisica.md` — decision memo (pass 2), unchanged in this pass.

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

## 8. `tipo_regla` re-audit — taxonomy leak fix

The report previously stated all 28 rows classified cleanly under `tipo_regla`. That
was checked against `tipo_calculo` fit, not against what each row's **unit** actually
measures — a narrower check than the claim implied. Three rows leaked:

| clave | old tipo_regla | unidad | what it actually measures | new tipo_regla |
|---|---|---|---|---|
| `primaria.infraestructura.altura_aulas` | superficie | `m fijo` | a linear height, not an area | **infraestructura** |
| `primaria.infraestructura.acervo_bibliografico` | superficie | `títulos/grado` | a book count, not an area | **infraestructura** |
| `secundaria.infraestructura.acervo_bibliografico` | superficie | `títulos totales` | a book count, not an area | **infraestructura** |

`'infraestructura'` added to the `tipo_regla` CHECK constraint (migration + local DDL)
for exactly this: physical/facility requirements that are neither an area (`superficie`)
nor a headcount (`personal`) nor furniture/equipment (`mobiliario`).

**Full re-audit of the remaining 25 rows, unit by unit:**

- **16 `superficie` rows** — every one carries an `m²`-denominated unit (`m²/sala`,
  `m²/alumno`, `m²/educando`, `m²/niño`, `m²/infante`, `m² fijo`) **except**
  `preescolar.superficie.aula_usos_multiples`, whose `unidad = 'factor'` (dimensionless).
  Checked and kept as `superficie`: the rule's semantics ("superficie mínima
  equivalente a 1.5 veces el aula mayor") resolve to an area once multiplied — the
  dimensionless unit is a multiplier *of* an area, not a different physical quantity
  the way "títulos" or "metros lineales" are. Not a leak.
- **9 `personal` rows** — every one is a headcount or a headcount ratio (`director/plantel`,
  `responsable/sala`, `alumnos/asistente`, `docente`, `alumnos/docente`, `trabajador
  social`, `prefecto`). No leak.

**Confirmed distribution after the fix: 16 superficie / 9 personal / 3 infraestructura
= 28.** This is now checked mechanically by
`test_tipo_regla_distribution_after_infraestructura_reclassification` in the test
file, not just asserted in this report.

## 9. Rounding audit — the `personal_proporcional` incompatibility (BLOCKER, fixed)

**The defect:** `tipo_calculo = personal_proporcional` was used identically for two
rules with opposite rounding requirements. COMPENDIO línea 422 (Educación Inicial):

> "1 asistente por cada 5 menores en salas de lactantes; 1 asistente por cada 10
> menores en salas de maternales (**redondeo hacia arriba**)"

Primaria's PE rule (§7) uses the same `tipo_calculo` but requires rounding **down**
(floor) — established in the earlier fix and unchanged here. Nothing in the schema
distinguished the two before this amendment: an engine built against `tipo_calculo`
alone could not know which direction to round. Concrete harm as reported: a Lactantes
room with 8 infants, rounded down (`floor(8/5) = 1`), would be staffed with 1
asistente when the norm requires 2 (`ceil(8/5) = 2`).

**Fix:** added `redondeo VARCHAR(10) NOT NULL CHECK (redondeo IN ('arriba', 'abajo',
'na'))` to the migration and local DDL (in place — schema still undeployed).

**Full rounding audit — every row, `tipo_calculo`, whether the source specifies
rounding, and what was set:**

| clave | tipo_calculo | source specifies rounding? | verbatim phrase | redondeo set |
|---|---|---|---|---|
| inicial.superficie.aula_lactantes | minimo_fijo | No — continuous area, not divided | — | na |
| inicial.superficie.aula_maternales | minimo_fijo | No | — | na |
| inicial.superficie.area_recreativa | ratio_por_alumno | No — continuous area | — | na |
| inicial.superficie.sala_usos_multiples | ratio_por_alumno | No | — | na |
| inicial.superficie.sanitarios | ratio_por_alumno | No | — | na |
| inicial.personal.responsable_sala | personal_por_espacio | No — multiplies by an existing discrete count, no division | — | na |
| **inicial.personal.asistente_lactantes** | personal_proporcional | **Yes** | "redondeo hacia arriba" (COMPENDIO L422) | **arriba** |
| **inicial.personal.asistente_maternales** | personal_proporcional | **Yes** | "redondeo hacia arriba" (COMPENDIO L422) | **arriba** |
| inicial.personal.director_tecnico | personal_obligatorio | No — fixed constant | — | na |
| preescolar.superficie.construida_total | ratio_por_alumno | No | — | na |
| preescolar.superficie.aula | ratio_por_alumno | No | — | na |
| preescolar.superficie.espacio_maestro | adicional_fijo | No — fixed addend | — | na |
| preescolar.superficie.area_recreacion | ratio_por_alumno | No | — | na |
| preescolar.superficie.aula_usos_multiples | factor | No — continuous multiplier | — | na |
| preescolar.personal.educacion_fisica | personal_umbral | No — flat gate, no division | — | na |
| primaria.superficie.aulas | ratio_por_alumno | No | — | na |
| primaria.superficie.predio_total | ratio_por_alumno | No | — | na |
| primaria.infraestructura.altura_aulas | minimo_fijo | No — fixed constant | — | na |
| primaria.infraestructura.acervo_bibliografico | ratio_por_grado | No — grados are always whole numbers, no fractional case exists | — | na |
| **primaria.personal.educacion_fisica** | personal_proporcional | **Silent** — COMPENDIO says "por cada 60... se puede requerir más de uno" but never says which way to round a partial group | (none — assumption, see §7) | **abajo (assumption, flagged for SEDEQ)** |
| secundaria.superficie.predio_total | ratio_por_alumno | No | — | na |
| secundaria.superficie.aulas | ratio_por_alumno | No | — | na |
| secundaria.superficie.area_recreacion | ratio_por_alumno | No | — | na |
| secundaria.superficie.areas_recreativas_minimas | minimo_fijo | No | — | na |
| secundaria.infraestructura.acervo_bibliografico | minimo_fijo | No — fixed constant | — | na |
| secundaria.personal.educacion_fisica | personal_umbral | No — flat gate | — | na |
| secundaria.personal.trabajador_social | personal_umbral | No | — | na |
| secundaria.personal.prefecto | personal_umbral | No | — | na |

**Rows where the source is silent and a rule was still needed (flagged, not
invented-and-hidden):** only `primaria.personal.educacion_fisica`. This is the same
floor-vs-ceil assumption already disclosed in §7 and in
`docs/decisions/PENDIENTE-umbral-educacion-fisica.md` — restated here because this
audit's job is to surface every silent case, and this is the only one that both (a)
involves a real division and (b) has no explicit source instruction either way.
Every other row is either explicitly specified (the two Inicial asistente rows) or
`na` for a structural reason (continuous magnitude, fixed constant, or a flat gate
with no division) — none of those `na` rows required inventing a rounding rule,
because none of them divide anything.

## 10. `CalculadoraRequerimiento` — pure calculation class

Created `app/Domain/Validaciones/Regla/CalculadoraRequerimiento.php` — the first real
piece of the Motor de Validación. Pure PHP: no Eloquent, no framework, no DB access.
One public method:

```php
public function calcular(
    string $tipoCalculo,
    float $valorNumerico,
    ?float $condicionMin,
    string $redondeo,
    float $magnitud,
): int|float
```

Covers all nine `tipo_calculo` values via `match`; `personal_proporcional` delegates
to a private `proporcional()` helper that applies `ceil`/`floor` per `redondeo`.
Scope was kept to exactly this — no repositories, no Application layer, no changes to
`ValidacionCapacidadService` (still an empty stub) — the calculator only needs its
five scalar inputs, so no stop-and-report was triggered.

**Pure unit test** (no DB, no Laravel `TestCase`, plain PHPUnit):
`tests/Unit/Domain/Validaciones/Regla/CalculadoraRequerimientoTest.php` — 21 tests
covering every `tipo_calculo`, both rounding directions, and the exact boundary cases
named in this follow-up (Primaria 59-121, Inicial lactantes 4-11, Inicial maternales
9-11).

**Before/after: the boundary test body.**

Before (tested PHP's own `intdiv`, not this system):

```php
$rule = DB::table('reglas_validacion')->where('clave', 'primaria.personal.educacion_fisica')->first();
$this->assertSame('personal_proporcional', $rule->tipo_calculo);
$this->assertNull($rule->condicion_min);
$required = intdiv($enrollment, (int) $rule->valor_numerico);
$this->assertSame($expectedDocentes, $required, "enrollment={$enrollment}");
```

After (loads the real row, runs it through the same class the engine will use):

```php
$rule = DB::table('reglas_validacion')->where('clave', 'primaria.personal.educacion_fisica')->first();
$required = (new CalculadoraRequerimiento())->calcular(
    tipoCalculo: $rule->tipo_calculo,
    valorNumerico: (float) $rule->valor_numerico,
    condicionMin: $rule->condicion_min !== null ? (float) $rule->condicion_min : null,
    redondeo: $rule->redondeo,
    magnitud: (float) $enrollment,
);
$this->assertSame($expectedDocentes, $required, "enrollment={$enrollment}");
```

If the engine's rounding rule for this row ever changes without a matching test
update, this now fails — the earlier version could not have caught that, since it
carried its own copy of the arithmetic instead of calling the system under test.

New boundary cases added per this follow-up:
`test_inicial_asistente_lactantes_boundary` (4→1, 5→1, 6→2, 10→2, 11→3) and
`test_inicial_asistente_maternales_boundary` (9→1, 10→1, 11→2), both round-trip
through `CalculadoraRequerimiento` against the seeded row exactly like the Primaria
test above.
