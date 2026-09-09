# 2026-09-08 — Modelo de identidad del solicitante

Branch: `worktree-feat+identidad-solicitante`. Plan: `docs/superpowers/plans/2026-09-08-modelo-identidad-solicitante.md`. Spec: `docs/superpowers/specs/2026-09-08-modelo-identidad-solicitante-design.md`. Full per-task ledger: `.superpowers/sdd/2026-09-08-modelo-identidad-solicitante/progress.md`. Final review: `.superpowers/sdd/2026-09-08-modelo-identidad-solicitante/final-review-report.md`.

## What was built

Introduces `solicitantes` as the business identity of the person filing a trámite, separate from `users` (authentication) and `responsables_legales` (per-trámite legal paperwork). Eight sequential tasks, implementer → reviewer → controller-verified test run each:

1. **`solicitantes` table + model + factory**, `User::solicitante()` relation — commit `6119b1e`.
2. **`CrearSolicitanteAlRegistrarUsuario` listener** on `Registered`, auto-creates the `Solicitante` row on signup — commit `3c86ebb`.
3. **`solicitante_id` added to `escuelas`** (`NOT NULL`, `ON DELETE RESTRICT` explicit) — commit `d4f2edd`.
4. **`IniciarTramiteNuevo::ejecutar()`** now takes and writes the acting `solicitante_id` — commit `ee31f17`.
5. **`auth` middleware required on the trámite wizard routes** — commit `ade7464`.
6. **`Paso1Preregistro` resolves `solicitante_id`** from the authenticated session (never from client input) — commit `6bb464a`.
7. **`historial_estados_expediente.usuario_sedeq` converted to a real FK** on `users` — commit `a387807`.
8. **Filament admin panel gated behind a spatie `sedeq` role** (`canAccessPanel()` on `User`) — commit `53c8437`.

Security shape: three independent enforcement layers for trámite ownership, each with its own test — route middleware blocks anonymous access, the component resolves the owner from the session only, the DTO deliberately excludes `solicitante_id` so it can't be spoofed from client input, and the DB enforces `NOT NULL` + FK.

## Plan test-count corrections (Tasks 3, 4, 5)

The plan predicted more newly-failing tests at each step than actually occurred. All three were controller-verified directly (not trusted from implementer reports) and ruled on in the ledger rather than silently absorbed:

- **Task 3**: plan claimed 5 pre-existing `IniciarTramiteNuevoTest` failures from the new `NOT NULL` column; only 2 actually reached the write path. Also surfaced an unrelated `ProgresoTest` fixture broken by the same constraint, not owned by any later task — fixed in Task 3 as the same root cause, not scope creep (confirmed by Task 3's reviewer).
- **Task 4**: plan predicted 4 newly-failing tests from the `ejecutar()` signature change; only 1 actually reached the changed call path (a Mockery arg-count mismatch).
- **Task 5**: plan (inheriting Task 4's miscount) again predicted 4; actual was the same 1 pre-existing failure plus 1 new one from the `auth` middleware redirect (200→302), both left for Task 6 as planned.

None of these required plan or code changes — they were arithmetic errors in the plan's predictions, not defects in the implementation.

## Untracked-DDL git-history finding (parked, needs your decision)

`docs/ddl_sistema_incorporacion_v3.sql` was never git-tracked before Task 7's commit `a387807` (confirmed via `git ls-tree` on master and this branch's parent — absent, not gitignored). Task 7 is the first commit to `git add` it, so its diff bundles every prior task's on-disk-only DDL edits (Tasks 1, 3, and the wizard-progreso section) into one commit instead of showing a scoped one-line change. This is a git-blame/history-attribution defect only — no functional or runtime impact. Untangling it means git history surgery (splitting/rewriting a commit) on a pushed branch, which is destructive and wasn't done without asking. Left as-is per the final review's explicit agreement; your call whether it's worth fixing.

## This fix wave: final-review findings and disposition

The final whole-branch review (`.superpowers/sdd/2026-09-08-modelo-identidad-solicitante/final-review-report.md`) found 1 Critical, 3 Important, 4 Minor. This fix wave addressed:

- **Critical #1 — CI red.** `./vendor/bin/pint` auto-fixed 5 files (unused imports, `fully_qualified_strict_types`, import ordering). `./vendor/bin/phpstan analyse` (level 5) had 2 errors from untyped `->id` access on interface-typed values — fixed with the type-correct accessors: `CrearSolicitanteAlRegistrarUsuario.php:12` now uses `$event->user->getAuthIdentifier()` (the event's `$user` is `Authenticatable`, which has no `$id`), and `Paso1Preregistro.php:78` now uses `auth()->user()->solicitante->getKey()`. No suppression, no baseline entry, no cast. All three gates now pass: `php artisan test` 142/142, `pint --test` 0 files, `phpstan analyse` 0 errors.
- **Important #2 — seeder gap.** `DatabaseSeeder`'s `Test User` (created via `User::factory()->create()`, which never fires `Registered`) had no `Solicitante` row, so logging in as that user and submitting `/tramite/preregistro` would 500 on the null relation. Fixed at the root of this specific gap: `DatabaseSeeder.php` now also creates `Solicitante::factory()->create(['user_id' => $testUser->id])`. Verified by running `php artisan migrate:fresh --env=testing --force` followed by `php artisan db:seed --env=testing --force` against the **test** database only (`sedeq_incorporacion_testing`, per `.env.testing`/`phpunit.xml`) — seeded cleanly with no errors, then the test DB was reset back to a clean state via another `migrate:fresh` and the full suite re-verified (142/142). Dev database was never touched.
- **Important #4 — DDL doc not runnable top-to-bottom.** `CREATE TABLE escuelas` referenced `solicitantes(id)` via FK at line 298, but `CREATE TABLE solicitantes` wasn't defined until line 664. Moved the `solicitantes` block (comment + `CREATE TABLE`, byte-for-byte) to immediately before the `SECCIÓN 3: ESCUELA` header, so it now precedes `escuelas`. This is a repo-local, gitignored doc file, not a tracked migration — no migration file was touched.
- **Important #3 — this report.** Written now; you're reading it.

**Deliberately left undone in this fix wave:**

- **ADR-002.** `docs/ddl_sistema_incorporacion_v3.sql` comments (both the moved `solicitantes` block and the `escuelas.solicitante_id` comment) point at "docs/decisions/ADR-002, pendiente de escribirse". No `ADR-002` or `PENDIENTE-*` file exists yet for the identity/ownership decision. Writing the ADR is a separate architectural-decision task, not this fix wave's job — flagging it here so it doesn't get lost.
- **Minor #5 — Paso 2 not owner-scoped.** `/tramite/paso2/{escuela}` is `auth`-gated but not owner-gated; any authenticated user can pass any escuela id. Harmless today because `paso2-placeholder.blade.php` only echoes the integer and loads no record, but once Paso 2 loads the actual escuela this becomes an IDOR. The `solicitante_id` column this branch added is exactly what makes the future fix possible (policy, route-model-binding scope, or an Application-layer read) — worth its own `PENDIENTE-` note or ticket before Paso 2 gets real logic.
- **Minor #6 — `Paso1Preregistro`'s direct Eloquent relation read.** `auth()->user()->solicitante->getKey()` is a relation read for identity resolution, not business logic. Read as within ADR-001's spirit (`app/Domain` must not depend on `Illuminate\*`; the concern is business logic leaking into presentation, and none did here) — no action needed, noted for future readers who ask the same question.
- **Minor #7 — migration depends on an application model.** `2026_09_08_000000_create_solicitantes_table.php` calls `Solicitante::backfillDesdeUsers()`. Known Laravel tradeoff (a later model rename/delete would break this historical migration on `migrate:fresh`); the plan argued testability over isolation explicitly and the final review agreed the tradeoff is sound at this project stage. No action.
- **Minor #8 — 2 previously-parked Task 7 test-hygiene items.** `HistorialEstadosExpedienteUsuarioSedeqTest::test_usuario_sedeq_id_rechaza_un_id_de_usuario_inexistente` passes an invalid `escuela_nivel_id` alongside the invalid `usuario_sedeq_id`, so the resulting `QueryException` isn't proven to come specifically from the FK this task added; and that same test file has an unused `use App\Models\User;` import (a no-op left by Pint's `no_unused_imports` fixer, already cleaned up as part of Critical #1's `pint` run — the isolation issue itself is still open). Both inherited verbatim from the plan's literal test code, low value, not blocking.

## Deferred dev-migration gating steps — NOT done, dev DB untouched

The plan's own text names three steps still required before this branch's migrations can run against the dev database. None of them happened as part of this branch or this fix wave:

1. **Resolve 3 pre-existing `escuelas` rows in dev** — the new `solicitante_id NOT NULL` column has no default, so `ALTER TABLE` will fail against them. Needs a human decision: delete as manual-QA artifacts, or hand-assign each to a solicitante.
2. **Confirm with the user before running `php artisan migrate` against dev.**
3. **Run `RolesSeeder` against dev afterward** so a real SEDEQ account can hold the `sedeq` role — until this runs, no dev account holds that role and `/admin` is closed to everyone in dev (expected behavior from Task 8, not a bug, but will look like one to whoever opens the panel next).

Every migration and seed run in this branch and this fix wave targeted `sedeq_incorporacion_testing` only (`--env=testing`, matching `.env.testing`/`phpunit.xml`'s pinned test connection). The default `.env` / dev connection was never touched.

## Documentation drift noted in passing (unrelated to this branch)

CLAUDE.md's Stack section still says tests run against sqlite in-memory ("`phpunit.xml` overrides `DB_CONNECTION`"), but `phpunit.xml` actually pins `DB_CONNECTION=pgsql` against `sedeq_incorporacion_testing`. Several of this branch's own tests depend on Postgres-specific behavior (`information_schema` queries, `now()` in raw SQL), so the code is correct and the doc is stale. Worth a one-line correction whenever CLAUDE.md is next touched — not fixed here, out of this branch's scope.
