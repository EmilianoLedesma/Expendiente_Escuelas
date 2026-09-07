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
| primaria.personal.educacion_fisica | primaria | personal | personal_proporcional | escuela | 61 | — | 60.00 | alumnos/docente |
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

### 2.1 Educación Física threshold: 60 vs. 61 (suspected off-by-one — verified NOT a bug)

- **COMPENDIO §5.1** (line 452): *"docente de Educación Física obligatorio **solo si la instalación tiene capacidad de 60 alumnos o más**"* (Preescolar) — reads as ≥60.
- **COMPENDIO §5.2** (Acuerdos Secretariales text — the actual normative source, not COMPENDIO's own prose): line 540 (Preescolar/Acuerdo 357) *"Educación Física **obligatoria si el número de educandos es mayor a 60**"*; line 575 (Primaria/Acuerdo 254) *"Educación Física **obligatoria si >60 alumnos**"*; line 626 (Secundaria/Acuerdo 255) *"si hay **más de 60 alumnos**"*; and the §5.2 summary table (line 640) confirms **">60 alumnos"** uniformly across all three niveles.
- **PRD §5**: states "> 60" — agrees with §5.2, not with §5.1's "60 o más".
- **Old seeder**: used `condicion_min = 61`, already resolved this exact conflict on 2026-09-04 (see the prior Decisions Log entry and the seeder's own docblock), reasoning that §5.2 (the literal Acuerdos Secretariales text) is more authoritative than §5.1's earlier paraphrase, and that it agrees with the PRD.
- **What I seeded**: `condicion_min = 61` for all three niveles — unchanged from the prior decision.
- **Conclusion**: not an off-by-one bug. §5.1's "60 o más" is the internally-inconsistent outlier within the COMPENDIO itself; §5.2 (later, Acuerdos-sourced, and the section the summary table draws from) and the PRD both independently say strictly ">60". 61 is correct. Re-flagging here per the task's instruction to report rather than silently confirm, since this is exactly the kind of judgment call that should be visible to the architect even when the answer is "no change."

### 2.2 Preescolar threshold vs. Primaria proportional (confirmed real defect, now fixed)

- **COMPENDIO §5.1** (line 452): Preescolar — *"docente de Educación Física obligatorio **solo si** la instalación tiene capacidad de 60 alumnos o más"* — a single on/off condition, not scaled.
- **COMPENDIO §5.1** (line 453): Primaria — *"docente de Educación Física obligatorio **por cada 60 alumnos o más** en la escuela (**proporción, se puede requerir más de uno**)"* — explicitly proportional, re-confirmed at line 480 ("obligado por cada 60 alumnos o más en la escuela").
- **Old seeder**: both niveles seeded identically — `condicion_min => 61, valor_numerico => 1, unidad => 'docente'` — meaning a 300-student Primaria school would validate with a single PE teacher.
- **What I seeded**: Preescolar → `tipo_calculo = personal_umbral` (fixed 1 once `condicion_min` 61 is met). Primaria → `tipo_calculo = personal_proporcional`, `valor_numerico = 60` (the ratio), `condicion_min = 61` (the gate below which no PE teacher is required at all — COMPENDIO's own Acuerdo 254 text at line 575 says the requirement itself only kicks in ">60 alumnos", the "por cada 60" proportion applies above that line). Required headcount under the intended engine formula: `condicion_min` not met → 0; met → `ceil(enrollment / 60)`.
- **Conclusion**: confirmed real defect in the prior seed data, fixed.

### 2.3 Additional discrepancy noticed (not previously flagged)

- COMPENDIO §5 (Inicial section, not quoted above) gives Inicial's rules no explicit alumno-capacity threshold for any of its `personal` rules other than the fixed ratios (director 1/plantel, responsable 1/sala, asistentes 1/5 or 1/10) — no §5.1/§5.2-style ">60" language applies to Inicial at all. No conflict found here; noted only because the task's framing might suggest checking for one. No seed change needed.

## 3. Taxonomy fit report

**All 28 rows mapped onto one of the nine `tipo_calculo` values without forcing.** One row required a judgment call worth flagging before the engine is built:

- **`primaria.personal.educacion_fisica`** uses `tipo_calculo = personal_proporcional` **together with** a non-null `condicion_min`. The task's definition of `personal_proporcional` ("required headcount = ceil(enrollment ÷ valor_numerico)") doesn't itself mention a threshold gate — only `personal_umbral` was defined with one. COMPENDIO's Primaria rule is genuinely hybrid: no PE teacher at all below 61 students (a threshold, like Preescolar), but *above* that line the count scales per 60 (unlike Preescolar, which stays fixed at 1). I modeled this as `personal_proporcional` + a `condicion_min` gate, since `condicion_min`/`condicion_max` are defined generically ("disambiguated by `ambito`") rather than scoped to one `tipo_calculo`. This is the one place where `tipo_calculo` alone doesn't fully describe the rule — the engine's `personal_proporcional` handler will need to also check `condicion_min` when present, not just multiply. Flagging this now so the engine spec accounts for it rather than discovering it mid-implementation.

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
{"tool":"phpunit","result":"passed","tests":16,"passed":16,"assertions":27,"duration_ms":2291}

$ php artisan test
{"tool":"phpunit","result":"passed","tests":60,"passed":60,"assertions":140,"duration_ms":20828}
```

Tinker dump (28 rows, matches §1's table exactly — confirmed by direct `json_encode` output of every row). Note: the task's provided tinker script had an ambiguous-column bug (`select('clave', ...)` where both `reglas_validacion` and `niveles_educativos` have a `clave` column) — fixed to `reglas_validacion.clave` to get a real result. Row count returned: `28`.

## 5. Files changed

- `app-laravel/database/migrations/2026_01_01_000010_create_reglas_validacion_table.php` — added `clave` (UNIQUE), `tipo_calculo` (9-value CHECK), `ambito` (5-value CHECK), `cargo_puesto_id` (nullable FK); `concepto`/`fuente` widened to `TEXT`; `condicion_min`/`condicion_max` changed `INTEGER` → `NUMERIC(10,2)`; `valor_numerico`/`unidad` made `NOT NULL`.
- `docs/ddl_sistema_incorporacion_v3.sql` — mirrored the same change (gitignored, local-only file).
- `app-laravel/database/seeders/ReglasValidacionSeeder.php` — rewritten: 28 rows with `clave`/`tipo_calculo`/`ambito`; switched from delete-then-insert-per-nivel to `insertOrIgnore` (safe now that `clave` is UNIQUE); two normative fixes applied (§2.2).
- `app-laravel/tests/Feature/Database/ReglasValidacionSeederTest.php` — rewritten: 16 tests covering row count, `clave` uniqueness/non-null, `tipo_calculo`/`ambito` non-null, all-9-values-allowed, idempotency, one assertion per `tipo_calculo` actually used, and the two normative fixes by `clave`.
- `docs/superpowers/plans/2026-09-04-catalogos-motor-validacion.md` — Task 4 marked superseded, pointing here.
- `docs/progress.md` — Decisions Log entry appended.
- `docs/reports/2026-09-07-reglas-validacion-schema.md` — this report.

## 6. Follow-ups created

- **Populate `cargo_puesto_id`**: all 28 rows currently NULL. Blocked on `CargosPuestosSeeder` (plan Task 2) existing with data queryable by `(nivel clave, nombre)` — the same lookup pattern `PerfilesProfesionalesSeeder` already uses. Only the `personal` rows (13 of 28) would ever get a non-null value; `superficie` rows have no associated cargo.
- **Confirm the `ambito` → source-table mapping** (§3) with whoever designs the Motor de Validación's input model, before engine code is written against these rows.
- **Confirm the `personal_proporcional` + `condicion_min` combination** (§3) is an acceptable engine contract, or split into a 10th `tipo_calculo` value if the architect prefers a dedicated one (e.g. `personal_proporcional_con_umbral`) instead of overloading `personal_proporcional`.
