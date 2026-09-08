# Add GitHub Actions CI — 2026-09-08

Branch: `worktree-chore+ci-github-actions` (worktree created via `EnterWorktree`;
requested branch name `chore/ci-github-actions` was sanitized by the tool —
same content, different literal name).

Adds `.github/workflows/ci.yml`: the 127-test suite has, until now, only run
on the developer's Windows/Herd machine, self-reported by whichever worktree
happened to run it. This adds independent verification, plus the first
mechanical check for ADR-001's architectural boundary.

## What was built

Three jobs, each required, all triggered on `push` (any branch) and
`pull_request`:

1. **`test`** — PHP 8.3, Postgres 18 service container (see "Postgres
   version" below), `composer install`, `npm ci` + `npm run build`,
   `php artisan migrate:fresh --seed --force` against the CI-only ephemeral
   database, `php artisan test` with a JUnit log parsed into the job summary
   (tests/assertions/failures/errors counts).
2. **`static-analysis`** — `vendor/bin/phpstan analyse -c phpstan.neon`
   (level 5, plus one PHPat architecture rule — see below).
3. **`style`** — `vendor/bin/pint --test` (check mode only, never modifies
   files in CI).

## Deviations from the task's literal assumptions (checked, not guessed)

- **Postgres version: 18 in the workflow, with an explicit fallback note to
  16.** `CLAUDE.md`/`docs/progress.md` document PostgreSQL 18 as the
  project's actual stack version, so the workflow specifies `postgres:18`.
  This sandbox has no network access, so the image's existence on Docker Hub
  could not be verified before committing to it, per the task's own
  instruction to check first. A comment in `ci.yml` states this plainly and
  names `postgres:16` (the PRD's original pinned version) as the fallback if
  the tag turns out not to exist. **If the first CI run fails at the
  Postgres service-container step specifically** (not at
  composer/npm/migrate/test), that is almost certainly this — change
  `image: postgres:18` to `image: postgres:16` in `.github/workflows/ci.yml`.
- **DB credentials: matched to what's already committed, not
  `postgres`/`postgres` defaults.** `phpunit.xml` and the already-committed,
  git-tracked `.env.testing` both hardcode `sedeq_app` / `sedeq_app_pw` /
  `sedeq_incorporacion_testing`. Using generic `postgres`/`postgres`
  defaults instead would have required either editing `phpunit.xml` (out of
  this task's scope — it's test configuration, not CI tooling) or a
  `.env.ci` that `phpunit.xml`'s own `<php>` env block would silently
  override anyway (`<env>` values in `phpunit.xml` take precedence over
  `.env` files for anything running through PHPUnit). Simplest and least
  risky: the Postgres service container's `POSTGRES_USER`/`_PASSWORD`/`_DB`
  and the workflow's job-level `env:` block both mirror the existing
  test-only credentials exactly, so `php artisan test` and
  `php artisan migrate:fresh --seed --force` both work unmodified. These are
  not real credentials — they were already committed, test-database-only
  defaults before this task started.
- **No separate `.env.ci` created.** `.env.testing` already exists,
  is git-tracked, and matches the credentials above — nothing to add.
  `APP_ENV` and the `DB_*`/`APP_KEY` values are instead set once at the
  workflow's job level so every step (migrate, test) is explicit and
  doesn't depend on `.env.testing`'s dotenv-loading precedence rules.
- **Test runner command**: confirmed via `composer.json`'s `scripts.test`
  (`php artisan config:clear && php artisan test`) that `php artisan test`
  is the project's actual convention, not raw `vendor/bin/phpunit`. Used
  `php artisan test --log-junit storage/logs/junit.xml` directly (verified
  locally this flag passes through to PHPUnit correctly and produces a real
  JUnit report) rather than running `composer test` in CI, so the JUnit
  log path could be controlled precisely for the job-summary step.
- **"Jobs, in this order" (the task's 5 numbered items) → 3 actual GitHub
  Actions jobs.** Items 1–3 (setup, migrate, test) are sequential *steps*
  within one job by necessity — they share the same Postgres service
  container and checkout, and steps within a job already run in order.
  Items 4 and 5 don't need the database, so they run as independent jobs in
  parallel with `test`, which is faster and still satisfies "all required to
  pass" (all three are separate required checks).

## Static analysis: the ADR-001 rule

`phpstan.neon` (level 5, not 9 — existing codebase, ratcheting up is a
separate future task) includes `larastan/larastan` and `phpat/phpat`, and
analyzes `app` plus a new `tests/Architecture` directory (analyzed by
PHPStan/PHPat, not run by PHPUnit — `phpunit.xml`'s test suites only include
`tests/Unit` and `tests/Feature`, so this doesn't collide).

`tests/Architecture/DomainBoundaryTest.php` — one rule, per the task's
explicit instruction not to invent more:

```php
PHPat::rule()
    ->classes(Selector::inNamespace('App\Domain'))
    ->shouldNotDependOn()
    ->classes(Selector::inNamespace('Illuminate'))
    ->because('ADR-001: app/Domain must be framework-agnostic — Illuminate/Laravel code belongs in app/Infrastructure or app/Application.');
```

Verified locally (`vendor/bin/phpstan analyse`): this rule **passes** —
`app/Domain` currently has zero `Illuminate` dependencies, consistent with
ADR-001. No layer rule beyond this one was added.

## First local run's result — pre-existing issues found (not introduced by this task)

Per the task's explicit instruction, these were **not fixed** — this task
adds tooling, it does not change application code (`app/Domain/`,
`app/Application/`, and all migrations are confirmed untouched: `git diff
--stat` shows zero changes to any of them).

### PHPStan (level 5) — 5 pre-existing errors, 3 files

| File | Error | Rough fix effort |
|---|---|---|
| `app/Application/Preregistro/ListarPlantelesDisponibles.php:17` | Return type mismatch: declared `Collection<..., array{id: int, etiqueta: string}>`, actually returns `non-falsy-string` for `etiqueta` | Small — narrow the return type annotation or cast |
| `app/Application/Preregistro/ListarPlantelesDisponibles.php:20` (×2) | Access to undefined properties `Plantel::$calle`, `Plantel::$municipio` — Larastan doesn't see these as real columns (likely missing `@property` PHPDoc on the model) | Small — add `@property` docblocks to `App\Models\Plantel` |
| `app/Http/Controllers/Auth/VerifyEmailController.php:22` | `Illuminate\Auth\Events\Verified` constructor expects `MustVerifyEmail`, given `User\|null` — Breeze-scaffolded code, `$request->user()` can be `null` per its return type even though it can't actually be null on an authenticated route | Small — non-null assertion or a `??` guard, standard Breeze/Larastan friction |
| `app/View/Components/Tramite/Progreso.php:32` | Property expects `Collection<..., object{...}>`, assigned `Collection<..., object{...}&stdClass>` — the `(object)` cast Larastan sees as `stdClass`-typed, not matching the declared anonymous object shape | Small — either loosen the property's declared type or restructure the cast |

None are architecture (PHPat) violations — all five are ordinary PHPStan
type-narrowing complaints, the kind expected on a first level-5 run of an
existing codebase. Total estimated fix effort: under an hour, all Minor-tier.

### Pint — 13 files need reformatting

```
app/Application/Preregistro/DTO/DatosPreregistro.php       (single_line_empty_body)
app/Application/Preregistro/DTO/ResultadoPreregistro.php   (single_line_empty_body)
app/Livewire/Tramite/Paso1Preregistro.php                  (class_attributes_separation)
bootstrap/providers.php                                     (fully_qualified_strict_types, single_line_after_imports)
tests/Feature/Application/Preregistro/IniciarTramiteNuevoTest.php   (new_with_parentheses)
tests/Feature/Database/AsignaturasSeederTest.php                    (new_with_parentheses)
tests/Feature/Database/CargosPuestosSeederTest.php                  (new_with_parentheses)
tests/Feature/Database/CatalogoMinimoSeederTest.php                 (new_with_parentheses)
tests/Feature/Database/PasosCapturaSeederTest.php                   (new_with_parentheses)
tests/Feature/Database/PerfilesProfesionalesSeederTest.php          (new_with_parentheses)
tests/Feature/Database/ReglasValidacionSeederTest.php               (new_with_parentheses)
tests/Feature/View/Components/Tramite/ProgresoTest.php              (new_with_parentheses)
tests/Unit/Domain/Personal/RegistroPersonalCompletoTest.php         (new_with_parentheses)
tests/Unit/Domain/Validaciones/Regla/CalculadoraRequerimientoTest.php (new_with_parentheses)
```

All purely mechanical (`new Foo` → `new Foo()`, blank-line/spacing rules) —
`vendor/bin/pint` with no flags would fix all 13 in one pass, but per the
task's explicit instruction this was **not run**, to avoid bloating this
PR's diff with unrelated reformatting. Rough effort: seconds of tool time,
zero design judgment; the only decision is whether to land it in a
follow-up commit here or a separate one.

**CI will be red on first landing for both jobs above.** This is expected
and is the useful information this task exists to surface — not a task
failure.

## Verification (local, before commit)

```
php artisan test                                    → 127/127 passing, 244 assertions (unchanged baseline)
vendor/bin/phpstan analyse -c phpstan.neon           → 5 pre-existing errors (see table above), PHPat rule passes
vendor/bin/pint --test                               → 13 files need reformatting (see list above), not fixed
```

Could not run the actual GitHub Actions workflow from this sandbox (no
network / no `act` tooling available) — the workflow YAML was written and
its individual commands (composer, npm, migrate, test, phpstan, pint) were
each verified to work locally with matching versions and flags, but the
GitHub-hosted-runner environment itself (Ubuntu image, `shivammathur/setup-php`
behavior, the `postgres:18` service container) is unverified until the
branch is actually pushed and CI runs for real. Flagging this explicitly
rather than claiming CI-green coverage this task cannot produce.

## Documentation

- `CLAUDE.md`: one line added under "Verify before claiming done" stating
  that CI exists and what it checks (tests, PHPStan level 5 + the ADR-001
  PHPat rule, Pint), and that it doesn't replace local verification.
- `.gitignore`: one line added (`app-laravel/storage/phpstan`) so
  PHPStan's local result-cache directory (created by this task's own
  `phpstan.neon` `tmpDir` setting) doesn't get committed — same pattern as
  the existing `storage/pail` / `storage/*.key` entries.
- `docs/progress.md` intentionally untouched — controller writes it at merge
  time from this report.

## What this task did not do

- Did not fix any of the 5 PHPStan errors or 13 Pint violations found —
  explicitly out of scope, reported above instead.
- Did not touch `app/Domain/`, `app/Application/` (beyond what PHPStan
  flagged as pre-existing, untouched), `app/Models/`, or any migration.
- Did not add layer rules beyond the single ADR-001 PHPat rule.
- Did not push, merge, or run the actual GitHub Actions workflow.
