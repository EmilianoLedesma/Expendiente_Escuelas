# Migrate Livewire components to `app/Livewire/` — 2026-09-08

Branch: `worktree-fix+namespace-livewire` (worktree created via `EnterWorktree`;
the requested branch name `fix/namespace-livewire` was sanitized by the tool
to `worktree-fix+namespace-livewire` — same content, different literal name).

Resolves `docs/decisions/PENDIENTE-namespace-livewire.md`: migrates all
Livewire components from `app/Http/Livewire/` to `app/Livewire/`, closing the
419 "Page expired" bug diagnosed in `docs/reports/2026-09-07-fix-sesion-419.md`.
That report found the root cause but deliberately applied no fix (out of
scope for that task). This task applies the fix the decision doc settled on.

## Decision (already made before this task started — not re-litigated)

Migrate the directory, don't register components manually. Manual
registration needs one `Livewire::component(...)` line per component,
including the 11 wizard steps not yet built; a single missed registration
reproduces the same 419, surfaced to the user as a misleading "page expired"
dialog — the same failure mode that already cost hours of diagnosis on this
project. Migration removes the bug class instead of requiring permanent
vigilance against it.

## TDD: proving the bug and the fix over a real HTTP round-trip

The existing suite passed the entire time this bug was live, because
`Livewire::test()` calls straight into the component class and never touches
the `/livewire/update` HTTP route where `ComponentRegistry` lookup happens —
so a green suite proved nothing about this bug. Per
`superpowers:test-driven-development`, wrote the test **before** touching any
component code:

`tests/Feature/Livewire/Tramite/Paso1PreregistroHttpRoundTripTest.php`
(created at `tests/Feature/Http/Livewire/Tramite/` first, since that mirrored
the still-old namespace at the time; moved along with everything else once
the migration ran):

1. `GET /tramite/preregistro`, assert 200.
2. Extract the CSRF token (`<meta name="csrf-token">`) and the rendered
   `wire:snapshot` attribute (HTML-entity-decoded) from the response body.
3. Extract the session cookie from the response's `Set-Cookie` headers
   (Laravel's test client does not carry cookies across calls automatically —
   each call gets a fresh session unless the cookie is passed forward
   explicitly, same as the manual `curl`/`Http`-facade reproduction in the
   419 report).
4. `POST /livewire/update` with that cookie, that CSRF token, and a real
   Livewire update payload (`components: [{snapshot, updates: {calle: ...},
   calls: []}]` — the exact shape `HandleRequests::handleUpdate()` expects),
   with `X-Livewire: true`.
5. Assert 200.

**Red, for the right reason**: before the move, this failed with
`Expected response status code [200] but received 419`. Temporarily dumped
the response body to confirm the exception was
`Livewire\Exceptions\LivewireReleaseTokenMismatchException` — the exact
class named in the 419 report's diagnosis, not a generic CSRF failure. Debug
dump removed before the move.

**Green, for the same reason**: after the move + `composer dump-autoload`,
this test passes with a real 200.

## Scope executed

Moved via `git mv` (untracked new test file moved with plain `mv`):

- `app/Http/Livewire/Actions/Logout.php` → `app/Livewire/Actions/Logout.php`
- `app/Http/Livewire/Forms/LoginForm.php` → `app/Livewire/Forms/LoginForm.php`
- `app/Http/Livewire/Tramite/Paso1Preregistro.php` → `app/Livewire/Tramite/Paso1Preregistro.php`
- `app/Http/Livewire/Tramite/Paso2Responsable.php` → `app/Livewire/Tramite/Paso2Responsable.php`
- `app/Http/Livewire/Tramite/Paso3/{Infraestructura,Inmueble,Matricula,Mobiliario,PlanEstudios,PlantillaDocente}.php` → `app/Livewire/Tramite/Paso3/...`

Namespace declarations updated in all 8 files
(`App\Http\Livewire\...` → `App\Livewire\...`). Empty `app/Http/Livewire/`
directory tree removed.

References updated:

- `routes/web.php` — `use` import for `Paso1Preregistro`.
- `resources/views/livewire/layout/navigation.blade.php`,
  `.../profile/delete-user-form.blade.php`,
  `.../pages/auth/verify-email.blade.php` — `use App\Livewire\Actions\Logout;`.
- `resources/views/livewire/pages/auth/login.blade.php` —
  `use App\Livewire\Forms\LoginForm;`.
- `tests/Feature/Http/Livewire/Tramite/Paso1PreregistroTest.php` — moved to
  `tests/Feature/Livewire/Tramite/Paso1PreregistroTest.php`, namespace and
  `use` import updated to match (PSR-4 `Tests\` → `tests/`, per
  `composer.json`'s `autoload-dev`, requires the directory to match the
  namespace).
- The new `Paso1PreregistroHttpRoundTripTest.php` moved the same way.

Confirmed via grep that no reference to `App\Http\Livewire` or
`Tests\Feature\Http\Livewire` remains anywhere under `app/`, `tests/`,
`routes/`, `resources/`.

**Checked, found nothing to change:**

- `routes/auth.php` — uses Livewire Volt (`Volt::route(...)`), a separate
  mechanism unaffected by this move; no `App\Http\Livewire` reference there.
- `app/Providers/AppServiceProvider.php` — registers an anonymous Blade
  component path for `resources/views/layouts`, unrelated to
  `App\Http\Livewire`; no change needed.
- `<livewire:layout.navigation />` in `resources/views/layouts/app.blade.php`
  — Livewire's dashed-tag alias syntax, not an explicit `App\Http\Livewire`
  class reference; resolved by the same auto-discovery this migration now
  aligns with, not something to edit by hand.

**Not touched**, per task scope: `app/Application/`, `app/Domain/`,
`app/Models/`, migrations. No component's behavior, method signature, or
Blade view content changed beyond the namespace-driven edits above.

## Verification

```
composer dump-autoload   →   9172 classes regenerated, no errors
php artisan test --filter=Paso1PreregistroHttpRoundTripTest
  → red before the move (419, LivewireReleaseTokenMismatchException)
  → green after the move
php artisan test (full suite)
  → 127/127 passing, 244 assertions
```

Delta from the 126/126 baseline (confirmed clean before this task started,
after a from-scratch `composer install` + `npm install` + `npm run build` in
this worktree — none of `vendor/`, `node_modules/`, `public/build/`, or
`.env` are shared across worktrees): +1 test, the new HTTP round-trip test.
No regressions, no other count changes.

Browser verification with `chrome-devtools-mcp` was not requested by the
user for this task and was not run — deferred, not claimed as covered.

## Documentation

- `docs/decisions/PENDIENTE-namespace-livewire.md` closed: header changed to
  `Estado: resuelto (2026-09-08)`, original analysis preserved unedited as
  historical record, resolution + rationale appended in a new `## Resolución`
  section. Neither of the other two `PENDIENTE-*` files in that directory had
  a "closed" state yet to copy a convention from — proposed one there
  (`Estado: resuelto (fecha)` header, verdict in a trailing `## Resolución`
  section, original analysis untouched) rather than inventing it silently.
- `CLAUDE.md` architecture section corrected: the `app/` tree diagram moved
  `Livewire/` out from under `Http/` to a top-level entry; the stale
  "old location" bullet (which had things backwards — it still said
  `app/Http/Livewire/*` was current) rewritten into two bullets — one for
  `app/Services/*` being the stale reference it always was, one new bullet
  stating `app/Livewire/` is now the convention, why it changed, and pointing
  at this report and the closed decision doc so the move isn't silently
  reverted. Also dropped `app/Http/Livewire/{Actions,Forms,Tramite}` from the
  "currently empty stubs" line — inaccurate regardless of path, since
  `Paso1Preregistro` and `Logout`/`LoginForm` carry real code, not stubs.
- Per the "one writer" rule, `docs/progress.md` is untouched — this report is
  what the controller will write it from at merge time.

## What this task did not do

- Did not resolve `PENDIENTE-umbral-educacion-fisica.md` or
  `PENDIENTE-origen-de-magnitud.md` — unrelated, still open.
- Did not run browser verification (not requested).
- Did not push or merge — branch left as-is for the user.
