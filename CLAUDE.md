# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Repository layout

Monorepo, single app:

- `app-laravel/` — the Laravel application (backend, solicitante-facing wizard, and SEDEQ admin panel — all one app, no split repos/services by design).
- `docs/` — reference docs that travel with the code: `PRD_Sistema_Incorporacion_MVP.md` (requirements), `COMPENDIO_MAESTRO_Sistema_Incorporacion.md` (normative/business rules), `ddl_sistema_incorporacion_v3.sql` (source-of-truth DB schema), `progress.md` (append-only work ledger with a Decisions Log — read it for *why*, not just *what*), `INSTRUCCIONES_SETUP_ENTORNO.md` (environment setup).
- `ARQUITECTURA_PROYECTO_SEDEQ.md` (if present, e.g. in Downloads) — architecture direction doc; already applied to the code structure below.

All application commands below run from `app-laravel/`.

## Stack

Laravel 13 (PHP 8.3) · Livewire 3 · Filament 4 · PostgreSQL 18 · `spatie/laravel-permission` · `barryvdh/laravel-dompdf` · Laravel Breeze · Vite + Tailwind.

(The PRD specified Laravel 11 / Livewire 3 / Filament 3 / PostgreSQL 16 — bumped to current stable at setup time because the originals weren't installable together. See `docs/progress.md` Decisions Log for the full reasoning.)

## Commands

```bash
# install
composer install
npm install

# run
php artisan serve       # app at http://127.0.0.1:8000, admin panel at /admin
npm run dev              # Vite dev server (or `composer run dev` for both together)

# build frontend assets
npm run build

# tests (full suite)
composer test             # clears config cache, then `php artisan test`
php artisan test

# single test file / single test
php artisan test tests/Feature/Auth/AuthenticationTest.php
php artisan test --filter=test_method_name

# migrations
php artisan migrate
php artisan migrate:fresh   # drops all tables, re-runs everything — never run against a DB you haven't checked first (see below)

# autoload after moving/renaming classes
composer dump-autoload
```

Tests run against **sqlite in-memory** (`phpunit.xml` overrides `DB_CONNECTION`), independent of the app's normal PostgreSQL connection — no separate test DB to provision.

## Database — read before touching migrations

The schema (44 tables) already exists and is **not to be redesigned**: `database/migrations/2026_01_01_*` are a 1:1 port of `docs/ddl_sistema_incorporacion_v3.sql`, one migration per table, in FK-dependency order. Each migration runs the DDL's exact `CREATE TABLE` statement verbatim via `DB::unprepared()` (not Schema Builder) specifically to preserve every `CHECK` constraint and default with zero translation drift.

Before creating or editing a migration:
- Inspect the current DB state and existing migrations first — don't assume the database is empty.
- Don't generate duplicate migrations or recreate existing tables.
- If the schema needs a structural change, it should trace back to the DDL/PRD, not be invented ad hoc.

Catalog seed data (`niveles_educativos`, `estados_expediente`, `tipos_documentos`, `reglas_validacion`, etc. — PRD §4, DDL "SEEDS MÍNIMOS") is not yet ported to Seeder classes — check `docs/progress.md` Phase 3 before assuming seeds exist.

## Architecture

Domain-first / modular-monolith `app/` layout — **not** the Controllers/Services/Repositories/Models split, and business logic does not live in Controllers, Livewire components, Filament Resources, or Eloquent models:

```
app/
├── Domain/            # framework-agnostic business rules, per bounded concern
│   └── Validaciones/
│       └── Engine/     # Motor de Validación de Capacidad Instalada — the most
│                        # business-critical piece; new rules must plug in here,
│                        # not get scattered across Livewire/Filament/observers
├── Infrastructure/     # concrete implementations of things the domain needs
│   ├── Pdf/            # Formato de Solicitud PDF generation (dompdf)
│   └── Documentos/     # document/checklist handling
├── Http/
│   └── Controllers/
├── Livewire/           # Livewire components — presentation only, thin: a
│   ├── Actions/         # component method should call into Domain/Infrastructure,
│   ├── Forms/            # not contain the business logic itself
│   └── Tramite/          # the incorporation wizard (Paso1Preregistro,
│                          # Paso2Responsable, Paso3/{Inmueble,Infraestructura,
│                          # Mobiliario,PlanEstudios,PlantillaDocente,Matricula})
├── Filament/
│   └── Resources/      # SEDEQ admin panel (id: 'admin', path: /admin)
├── Models/
└── Providers/
```

Rules for this layout (from the architecture doc, already applied — keep following them for new code):

- New business logic goes in `app/Domain/<Concern>/`, not in a Livewire method body or a Filament Resource. Only create a new `Domain/<Concern>/` folder when its first real Action/Service is written — don't pre-scaffold empty domain folders for concerns that don't have code yet, even though the eventual domain list is large (Expedientes, Documentos, Validaciones, Planteles, Escuelas, Personal, Matricula, Infraestructura, Mobiliario, Seguridad).
- `app/Infrastructure/` holds concrete implementations the domain depends on (storage, PDF, notifications, external integrations) — the domain should not know infrastructure details.
- Eloquent models are persistence, not business logic — don't let them grow into God objects with validation/calculation/transactional flow baked in.
- Single Laravel app / single repo is the deliberate choice (confirmed via architecture review, see `docs/progress.md` Decisions Log) — no independent deploy needs, one DB as shared contract. Don't introduce microservices or split repos preemptively; only if real scaling/team-isolation needs appear later.
- As of this writing, `app/Services/*` (Domain root was `app/Domain/Validacion/`) is the **old** location — code has since moved to `app/Infrastructure/*`, `app/Domain/Validaciones/Engine/`. If you see references to the old paths anywhere (docs, comments), they're stale.
- Livewire components live under `app/Livewire/`, Livewire 3's own auto-discovered convention — **not** `app/Http/Livewire/`. They briefly lived under `app/Http/Livewire/` (2026-09-03 to 2026-09-08); that path broke every Livewire AJAX round-trip with a misleading "page expired" 419, because Livewire's `ComponentRegistry` looks a component up by a name it auto-derives from the class's namespace, and that lookup only resolves under Livewire's own convention. Do not move Livewire components back under `app/Http/` — see `docs/decisions/PENDIENTE-namespace-livewire.md` and `docs/reports/2026-09-08-namespace-livewire.md` before reconsidering this.

Everything under `app/Domain`, `app/Infrastructure` is currently empty stubs — structure exists ahead of the logic that will fill it in.

## Ways of working

### Think before coding
State assumptions explicitly; if genuinely blocked on a decision only the user can make, ask (one question at a time, multiple-choice where possible) instead of guessing. Never silently pick between multiple valid interpretations.

### Classify before building
Every feature request gets classified first: spike / bounded / architectural. A bounded task still gets a short in-chat design and an explicit yes before implementation starts; an architectural one gets a written plan first. "Too simple to need approval" is the most common way this gets skipped, and skipping it is where wasted work comes from.

### TDD as the actual iron law, not a suggestion
No production code without a failing test first, watched failing for the right reason. Tests written after the fact don't count — they only prove the code does what it does, not what it should do.

### Plan → dispatch → review, for anything with real shape
Architectural work becomes a written plan with literal code per task, then gets executed one task at a time: implementer → separate reviewer → fix round if needed → next task. Never dispatch implementers in parallel, even across files that don't overlap — sequencing catches cross-task drift a parallel run would miss.

### Isolate anything non-trivial in a worktree
Any change with real shape gets its own git worktree before implementation starts, merged (or discarded) once done. Reserve direct-on-main work for genuinely trivial, single-file changes.

### Verify before claiming done
GitHub Actions CI (`.github/workflows/ci.yml`) runs on every push/PR and independently re-verifies the test suite, PHPStan (level 5, plus one PHPat rule enforcing ADR-001: `app/Domain` must not depend on `Illuminate\*`), and Pint style — it does not replace running these locally before claiming done, but a red CI check on a pushed branch is real signal, not noise. It also runs in a clean environment, so it can surface drift your local machine can't see (e.g. it caught composer.lock requiring PHP 8.4 while CLAUDE.md and composer.json still said 8.3) — treat a CI-only failure as a real finding, not a fluke to retry past.

### Data safety is a hard line, not a judgment call
Never insert/mutate/upload data against a live database — dev included — without asking first, even for a known, pre-existing test fixture, even for an idempotent operation. Reading data (SELECTs, navigating pages) is free; anything that writes is not. Same bar for DDL/schema changes.

### Ask before using tools that reach outside the sandbox
A live database, a browser, anything with real-world side effects — ask first, every time, even when already connected from a prior turn in the same session.

### Commit discipline
Never commit or push without being explicitly asked, regardless of how "obviously correct" the change looks or whether a skill's own instructions describe a commit step. When asked to commit: review the actual diff for anything that looks like a secret before staging, exclude the user's unrelated in-progress files rather than sweeping them in with a broad `git add`, and never add a "Co-Authored-By" trailer unless asked to.

### Subagent boundaries
Never let implementer subagents touch `.gitignore` or run `git push` — that's a controller-only decision. Never dispatch plan implementers in parallel, always sequential.

### `docs/progress.md` has one writer, and it isn't you

`docs/progress.md` is the append-only ledger of what is **on `master`** — not of what any given branch did. Agents working in a worktree don't know whether their branch will be merged, reworked, or discarded, so they don't write to it. Record the session's work in a dated `docs/reports/YYYY-MM-DD-<topic>.md` instead, with enough detail (evidence, decisions, what was deliberately left undone) that `progress.md` can later be written from it without re-reading the diff. The controller updates `progress.md` at merge time, from the reports. This rule exists because it was violated three times: every branch appended its Session Log entry at the same anchor line at the end of the file, and every merge conflicted there — a conflict that carries no information and is pure cost. Any open decision the session couldn't resolve gets its own `docs/decisions/PENDIENTE-<topic>.md` rather than living as a bullet, so it survives independently of whether the branch lands. ECC session files under `~/.claude/session-data/` are transient scratch and are never a substitute for either; where they disagree with `progress.md`, `progress.md` wins. Controller changes applied straight to `master` with no branch involved still get their own Session Log entry, marked as such — "at merge time" describes the usual trigger, not the only one.

### Register/tone
Default to concise, direct answers — lead with the result, skip the preamble, keep exploratory questions to 2-3 sentences with a clear recommendation rather than an exhaustive options survey. Match whatever tone mode is active without letting it override the substance underneath — code, security notices, and irreversible-action confirmations always stay in full, clear language regardless of active tone mode.

### Read the decision record before writing code in an area it covers

`docs/decisions/` is not read automatically at session start — nothing enforces this today. Before starting work that touches a domain area with an ADR or PENDIENTE file (identity/ownership, layer boundaries, Livewire namespace, validation rule versioning), read the relevant file in full, not just its mention in `docs/progress.md`'s summary. Filenames are not reliable status indicators on their own — check the `Estado:` line inside the file, since a resolved decision may still carry a stale `PENDIENTE-` prefix if nobody renamed it. When in doubt, list `docs/decisions/` and skim each header before proceeding.

Closing a `PENDIENTE-<topic>.md` is not just adding an `Estado: resuelto` line — rename the file in the same commit (`ADR-00N-<topic>.md` if it's an architectural decision worth numbering alongside ADR-001, or keep the topic name if it's narrower) so the filename and the content never disagree. This is not optional cleanup: a stale `PENDIENTE-` prefix on a resolved file is what caused this exact confusion once already. `docs/reports/` files don't get this treatment — they're dated, immutable records of what happened in a session, not tracked open/closed state, so there's nothing to rename.